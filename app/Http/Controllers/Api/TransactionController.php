<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Database\Events\TransactionCommitted;
use Session;
use App\Models\UserChat;
use App\Models\AccountLog;
use App\Models\Transaction;
use App\Models\TransactionComplete;
use App\Models\TransactionIn;
use App\Models\TransactionOut;
use App\Models\TransactionLegal;
use App\Models\Users;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\UsersWallet;
use App\Models\UserCashInfo;
use App\Models\UserReal;

class TransactionController extends Controller
{

     //正在买入记录
    public function TransactionInList()
    {
        $user_id = Users::getUserId();
        if (empty($user_id)) return $this->error('参数错误');
        $limit = Input::get('limit', 10);
        $page = Input::get('page', 1);
        $transactionIn = TransactionIn::where('user_id', $user_id)->orderBy('id', 'desc')->paginate($limit, ['*'], 'page', $page);
        if (empty($transactionIn)) return $this->error('您还没有交易记录');
        return $this->success(array(
            "list" => $transactionIn->items(), 'count' => $transactionIn->total(),
            "page" => $page, "limit" => $limit
        ));
    }

    public function TransactionOutList()
    {
        $user_id = Users::getUserId();
        if (empty($user_id)) {
            return $this->error('参数错误');
        }
        $limit = Input::get('limit', 10);
        $page = Input::get('page', 1);
        $transactionOut = TransactionOut::where('user_id', $user_id)->orderBy('id', 'desc')->paginate($limit, ['*'], 'page', $page);
        if (empty($transactionOut)) {
            return $this->error('您还没有交易记录');
        }
        return $this->success(array(
            "list" => $transactionOut->items(), 'count' => $transactionOut->total(),
            "page" => $page, "limit" => $limit
        ));
    }

    public function TransactionCompleteList()
    {
        $user_id = Users::getUserId();
        $limit = Input::get('limit', 10);
        $page = Input::get('page', 1);
        if (empty($user_id)) {
            return $this->error('参数错误');
        }
        $TransactionComplete = TransactionComplete::where('user_id', $user_id)
            ->orwhere('from_user_id', $user_id)
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        if (empty($TransactionComplete)) {
            return $this->error('您还没有交易记录');
        }
        foreach ($TransactionComplete->items() as $key => &$value) {
            if ($value['type'] == 2) {
                //触发者是买方
                if ($value['user_id'] == $user_id) {
                    $value['type'] = 'in';
                } else {
                    $value['type'] = 'out';
                }
            } elseif ($value['type'] == 1) {
                //触发者是卖方
                if ($value['user_id'] == $user_id) {
                    $value['type'] = 'out';
                } else {
                    $value['type'] = 'in';
                }
            }
        }
        return $this->success(array(
            "list" => $TransactionComplete->items(), 'count' => $TransactionComplete->total(),
            "page" => $page, "limit" => $limit
        ));
    }


    public function TransactionDel()
    {
        $user_id = Users::getUserId();
        $id = Input::get('id', '');
        $type = Input::get('type', '');
        if (empty($user_id) || empty($id) || empty($type)) return $this->error('参数错误');
        DB::beginTransaction();
        if ($type == 'in') {//取消法币锁定
            try {
                $transactionIn = TransactionIn::where('user_id', $user_id)->find($id); //限定只能操作自己发布的
                if (!$transactionIn) {
                    throw new \Exception('非法操作,不能撤回非自己发布的信息');
                }
                $user_wallet = UsersWallet::where('user_id', $user_id)
                    ->where('currency', $transactionIn->legal)
                    ->lockForUpdate()
                    ->first();
                $amount = bc_mul($transactionIn->price, $transactionIn->number, 5);
                if (bc_comp($user_wallet->lock_legal_balance, $amount) < 0) {
                    throw new \Exception('非法操作:(');
                }
                $data_wallet1 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_wallet->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_wallet->lock_legal_balance,
                    'change' => -$amount,
                    'after' => bc_sub($user_wallet->lock_legal_balance, $amount, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_wallet->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' => $user_wallet->legal_balance,
                    'change' => $transactionIn->number,
                    'after' => bc_add($user_wallet->legal_balance, $transactionIn->number, 5),
                ];
                $user_wallet->lock_legal_balance = bc_sub($user_wallet->lock_legal_balance, $amount, 5);
                $user_wallet->legal_balance = bc_add($user_wallet->legal_balance, $amount, 5);
                $user_wallet->save();//法币余额增加 法币锁定余额减少
                $del_result = TransactionIn::destroy($id);
                if ($del_result < 1) {
                    throw new \Exception('取销卖出交易失败');
                }
                AccountLog::insertLog([
                    'user_id' => $user_id,
                    'value' => -$transactionIn->number,
                    'info' => "取消买入交易,解除法币余额锁定",
                    'type' => AccountLog::TRANSACTIONIN_IN_DEL,
                    'currency' => $transactionIn->legal,
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user_id,
                    'value' => $transactionIn->number,
                    'info' => "取消买入交易,解除法币余额锁定",
                    'type' => AccountLog::TRANSACTIONIN_IN_DEL,
                    'currency' => $transactionIn->legal,
                ],$data_wallet2);
                DB::commit();
                return $this->success('取消成功');
            } catch (\Exception $ex) {
                DB::rollBack();
                return $this->error($ex->getMessage());
            }
        } else if ($type == 'out') {
            try {
                $transactionOut = TransactionOut::where('user_id', $user_id)->find($id); //限定只能操作自己发布的
                if (!$transactionOut) {
                    throw new \Exception('非法操作');
                }
                $user_wallet = UsersWallet::where('user_id', $user_id)
                    ->where('currency', $transactionOut->currency)
                    ->lockForUpdate()
                    ->first();
                if (bc_comp($user_wallet->lock_change_balance, $transactionOut->number) < 0) {
                    throw new \Exception('非法操作');
                }
                $data_wallet1 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_wallet->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_wallet->lock_legal_balance,
                    'change' => -$transactionOut->number,
                    'after' => bc_sub($user_wallet->lock_legal_balance, $transactionOut->number, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_wallet->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' => $user_wallet->legal_balance,
                    'change' => $transactionOut->number,
                    'after' => bc_add($user_wallet->legal_balance, $transactionOut->number, 5),
                ];
                $user_wallet->lock_change_balance = bc_sub($user_wallet->lock_change_balance, $transactionOut->number, 5);
                $user_wallet->change_balance = bc_add($user_wallet->change_balance, $transactionOut->number, 5);
                $user_wallet->save();//余额增加 法币锁定余额减少
                $del_result = TransactionOut::destroy($id);
                if ($del_result < 1) {
                    throw new \Exception('取销卖出交易失败');
                }
                AccountLog::insertLog([
                    'user_id' => $user_id,
                    'value' => -$transactionOut->number,
                    'info' => "取消卖出交易,解除交易余额锁定",
                    'type' => AccountLog::TRANSACTIONIN_OUT_DEL,
                    'currency' => $transactionOut->currency,
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user_id,
                    'value' => $transactionOut->number,
                    'info' => "取消卖出交易,解除交易余额锁定",
                    'type' => AccountLog::TRANSACTIONIN_OUT_DEL,
                    'currency' => $transactionOut->currency,
                ],$data_wallet2);
                DB::commit();
                return $this->success('取消成功');
            } catch (\Exception $ex) {
                DB::rollBack();
                return $this->error($ex->getMessage());
            }
        } else {
            return $this->error('类型错误');
        }
    }

    public static function delTemp($id)
    {
        DB::beginTransaction();
        try {
            $transactionOut = TransactionOut::find($id); //限定只能操作自己发布的
            if (!$transactionOut) {
                return '交易不存在';
            }
            $user_id = $transactionOut->user_id;
            $user_wallet = UsersWallet::where('user_id', $user_id)->where('currency', $transactionOut->currency)->first();
            if (bc_comp($user_wallet->lock_change_balance, $transactionOut->number) < 0) {
                return '资金不足';
            }

            $data_wallet1 = [
                'balance_type' =>  1,
                'wallet_id' => $user_wallet->id,
                'lock_type' => 1,
                'create_time' => time(),
                'before' => $user_wallet->lock_legal_balance,
                'change' => -$transactionOut->number,
                'after' => bc_sub($user_wallet->lock_legal_balance, $transactionOut->number, 5),
            ];
            $data_wallet2 = [
                'balance_type' =>  1,
                'wallet_id' => $user_wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $user_wallet->legal_balance,
                'change' => $transactionOut->number,
                'after' => bc_add($user_wallet->legal_balance, $transactionOut->number, 5),
            ];
            $user_wallet->lock_change_balance = $user_wallet->lock_change_balance - $transactionOut->number;
            $user_wallet->change_balance = $user_wallet->change_balance + $transactionOut->number;
            $user_wallet->save();//余额增加 法币锁定余额减少
            TransactionOut::destroy($id);
            AccountLog::insertLog([
                'user_id' => $user_id,
                'value' => -$transactionOut->number,
                'info' => "取消卖出交易,解除交易余额锁定",
                'type' => AccountLog::TRANSACTIONIN_OUT_DEL,
                'currency' => $transactionOut->currency
            ],$data_wallet1);
            AccountLog::insertLog([
                'user_id' => $user_id,
                'value' => $transactionOut->number,
                'info' => "取消卖出交易,解除交易余额锁定",
                'type' => AccountLog::TRANSACTIONIN_OUT_DEL,
                'currency' => $transactionOut->currency
            ],$data_wallet2);
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    }

    public function out()
    {

        $user_id = Users::getUserId();

        $price = Input::get("price");
        $num = Input::get("num");

        $legal_id = Input::get("legal_id");
        $currency_id = Input::get("currency_id");

        $has_num = 0;
        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("参数错误");
        }


        $user = Users::find($user_id);
        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();
        if (empty($user) || empty($legal) || empty($currency)) {

            return $this->error("数据未找到");
        }
        try {
            DB::beginTransaction();
            $user_currency = UsersWallet::where("user_id", $user_id)
                ->where("currency", $currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($user_currency)) {
                throw new \Exception("请先添加钱包");
            }
            if (bc_comp($price, 0) <= 0 || bc_comp($num, 0) <= 0) {
                throw new \Exception("价格和数量必须大于0");
            }
            if (bc_comp($user_currency->change_balance, $num) < 0) {
                throw new \Exception("您的币不足");
            }
            if (bc_comp($user_currency->lock_change_balance, 0) < 0) {
                throw new \Exception("您的锁定资金异常，禁止挂卖");
            }
            $today = date('Y-m-d');
            //查询该用户是不是黑名单用户
            $is_blacklist = $user->is_blacklist;
            //查询当天已经交易的IMC币数
            $my_outs = TransactionOut::where("user_id", $user_id)
                ->where("currency", "9")
                ->where('create_time', '>=', $today)
                ->sum('number');
            $my_complete_outs = TransactionComplete::where(function ($query) use ($today, $user_id) {
                    $query->orWhere(function ($query) use ($user_id) {
                        $query->where('way', 1)->where('user_id', $user_id);
                    })->orWhere(function ($query) use ($user_id) {
                        $query->where('way', 2)->where('from_user_id', $user_id);
                    });
                })->where('create_time', '>=', $today)
                ->where('currency', 9)
                ->sum('number');
            $my_total_outs = bc_add($my_outs, $my_complete_outs);
            $should_num = bc_add($my_total_outs, $num);
            if ($is_blacklist == 1 && $currency_id == 9) {
                $can_out_today = bc_mul($user_currency->change_balance, 0.1);
                if (bc_comp($can_out_today, $should_num) < 0) {
                    throw new \Exception("你今天的交易额度已达到上限!");
                }
            }
            $in = TransactionIn::where("price", ">=", $price)
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->where("number", ">", "0")
                ->orderBy('price', 'desc')
                ->orderBy('id', 'asc')
                ->get();

            if (!empty($in)) {
                foreach ($in as $i) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num);
                        $this_num = 0;
                        if (bc_comp($i->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $i->number;
                        }
                        $has_num = bc_add($has_num, $this_num, 5);
                        if (bc_comp($this_num, 0) > 0) {
                            TransactionOut::transaction($i, $this_num, $user, $user_currency, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }

            $num = bc_sub($num, $has_num, 5);

            if (bc_comp($num, 0) > 0) {
                $out = new TransactionOut();
                $out->user_id = $user_id;
                $out->price = $price;
                $out->number = $num;
                $out->currency = $currency_id;
                $out->legal = $legal_id;
                $out->create_time = time();
                $out->save();

                $data_wallet1 = [
                    'balance_type' =>  2,
                    'wallet_id' => $user_currency->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' => $user_currency->change_balance,
                    'change' => -$num,
                    'after' => bc_sub($user_currency->change_balance, $num, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  2,
                    'wallet_id' => $user_currency->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_currency->lock_change_balance,
                    'change' => $num,
                    'after' => bc_add($user_currency->lock_change_balance, $num, 5),
                ];
                $user_currency->change_balance = bc_sub($user_currency->change_balance, $num, 5);
                $user_currency->lock_change_balance = bc_add($user_currency->lock_change_balance, $num, 5);
                $user_currency->save();

                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => bc_mul($num, -1),
                    'info' => "提交卖出记录扣除",
                    'type' => AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE,
                    'currency' => $currency_id
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => $num,
                    'info' => "提交卖出记录(增加锁定)",
                    'type' => AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE,
                    'currency' => $currency_id
                ],$data_wallet2);
            }
            Transaction::pushNews($currency_id, $legal_id);
            DB::commit();
            return $this->success("操作成功");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    public function in()
    {
        $user_id = Users::getUserId();

        $price = Input::get("price");
        $num = Input::get("num");
        $legal_id = Input::get("legal_id");
        $currency_id = Input::get("currency_id");

        $has_num = 0;
        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("参数错误");
        }

        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();

        $user = Users::find($user_id);
        if (empty($user) || empty($legal) || empty($currency)) {
            return $this->error("数据未找到");
        }
        if (bc_comp($price, 0) <= 0 || bc_comp($num, 0) <= 0) {
            return $this->error("价格和数量必须大于0");
        }

        try {
            DB::beginTransaction();
            //买方法币钱包
            $user_legal = UsersWallet::where("user_id", $user_id)
                ->where("currency", $legal_id)
                ->lockForUpdate()
                ->first();
            $all_balance = bc_mul($price, $num, 5);
            if (bc_comp($user_legal->legal_balance, $all_balance) < 0) {
                throw new \Exception('余额不足');
            }
            //查找所有价格小于等于当前价格的卖出委托
            $out = TransactionOut::where("price", "<=", $price)
                ->where("number", ">", "0")
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->orderBy('price', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if (!empty($out)) {
                foreach ($out as $o) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num, 5);
                        $this_num = 0;
                        if (bc_comp($o->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $o->number;
                        }
                        $has_num = bc_add($has_num, $this_num, 5);
                        if (bc_comp($this_num, 0) > 0) {
                            TransactionIn::transaction($o, $this_num, $user, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }

            $remain_num = bcsub($num, $has_num); //匹配后的剩余数量

            if (bc_comp($remain_num, 0) > 0) {
                $in = new TransactionIn();
                $in->user_id = $user_id;
                $in->price = $price;
                $in->number = $remain_num;
                $in->currency = $currency_id;
                $in->legal = $legal_id;
                $in->create_time = time();

                $in->save();

                $all_balance = bc_mul($price, $remain_num, 5);
                $data_wallet1 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_legal->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' =>  $user_legal->legal_balance,
                    'change' => -$all_balance,
                    'after' => bc_sub($user_legal->legal_balance, $all_balance, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_legal->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_legal->lock_legal_balance,
                    'change' => $all_balance,
                    'after' => bc_add($user_legal->lock_legal_balance, $all_balance, 5),
                ];

                $user_legal->legal_balance = bc_sub($user_legal->legal_balance, $all_balance, 5);
                $user_legal->lock_legal_balance = bc_add($user_legal->lock_legal_balance, $all_balance, 5);
                $user_legal->save();

                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => bc_mul($all_balance, -1, 5),
                    'info' => "提交卖入记录扣除",
                    'type' => AccountLog::TRANSACTIONIN_SUBMIT_REDUCE,
                    'currency' => $currency_id,
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => $all_balance,
                    'info' => "提交卖入记录扣除",
                    'type' => AccountLog::TRANSACTIONIN_SUBMIT_REDUCE,
                    'currency' => $currency_id,
                ],$data_wallet2);
            } else {
                //匹配完成s
            }
            Transaction::pushNews($currency_id, $legal_id);
            DB::commit();
            return $this->success("操作成功");
        } catch (\Exception $ex) {
            DB::rollback();
            return $this->error($ex->getMessage());
        }
    }

    public function deal()
    {
        $user_id = Users::getUserId();

        $legal_id = Input::get("legal_id");
        $currency_id = Input::get("currency_id");

        if (empty($legal_id) || empty($currency_id)) {
            return $this->error("参数错误");
        }
        $in = TransactionIn::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'desc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit(10)
            ->get()
            ->toArray();
        $out = TransactionOut::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'asc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit(10)
            ->get()
            ->toArray();

        krsort($out);
        $out_data = array();
        foreach ($out as $o) {
            array_push($out_data, $o);
        }

        $complete = TransactionComplete::orderBy('id', 'desc')->where("currency", $currency_id)->where("legal", $legal_id)->take(15)->get();

        $last_price = 0;
        $last = TransactionComplete::orderBy('id', 'desc')->where("currency", $currency_id)->where("legal", $legal_id)->first();
        if (!empty($last)) {
            $last_price = $last->price;
        }

        $user_legal = 0;
        $user_currency = 0;
        if (!empty($user_id)) {
            $legal = UsersWallet::where("user_id", $user_id)->where("currency", $legal_id)->first();
            if ($legal) {
                $user_legal = $legal->legal_balance;
            }
            $currency = UsersWallet::where("user_id", $user_id)->where("currency", $currency_id)->first();
            if ($currency) {
                $user_currency = $currency->change_balance;
            }
        }

        $ustd_price = 0;
        $last = TransactionComplete::orderBy('id', 'desc')
            ->where("currency", $legal_id)
            ->where("legal", 1)->first();//4是usdt
        if (!empty($last)) {
            $ustd_price = $last->price;
        }
        if ($legal_id == 1) {
            $ustd_price = 1;
        }
        $cny_price = Currency::getCnyPrice($legal_id);
        return $this->success([
            "in" => $in,
            "out" => $out_data,
           "cny_price"=> $cny_price,
            "last_price" => $last_price,
            "user_legal" => $user_legal,
            "user_currency" => $user_currency,
            "complete" => $complete
        ]);
    }

    public function walletIn()
    {
        $user_id = Users::getUserId();

        $price = Input::get("price");
        $num = Input::get("num");
        $legal_id = Input::get("legal_id");
        $currency_id = Input::get("currency_id");

        $has_num = 0;
        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("参数错误");
        }

        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            // ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();

        $user = Users::find($user_id);
        if (empty($user) || empty($legal) || empty($currency)) {
            return $this->error("数据未找到");
        }
        if (bc_comp($price, 0) <= 0 || bc_comp($num, 0) <= 0) {
            return $this->error("价格和数量必须大于0");
        }

        try {
            DB::beginTransaction();
            //买方交易币币钱包
            $user_change = UsersWallet::where("user_id", $user_id)
                ->where("currency", $legal_id)
                ->lockForUpdate()
                ->first();
            $all_balance = bc_mul($price, $num, 5);
            if (bc_comp($user_change->change_balance, $all_balance) < 0) {
                throw new \Exception('余额不足');
            }
            //查找所有价格小于等于当前价格的卖出委托
            $out = TransactionOut::where("price", "<=", $price)
                ->where("number", ">", "0")
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->orderBy('price', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if (!empty($out)) {
                foreach ($out as $o) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num, 5);
                        $this_num = 0;
                        if (bc_comp($o->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $o->number;
                        }
                        $has_num = bc_add($has_num, $this_num, 5);
                        if (bc_comp($this_num, 0) > 0) {
                            TransactionIn::walletTransaction($o, $this_num, $user, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }

            $remain_num = bcsub($num, $has_num); //匹配后的剩余数量

            if (bc_comp($remain_num, 0) > 0) {
                $in = new TransactionIn();
                $in->user_id = $user_id;
                $in->price = $price;
                $in->number = $remain_num;
                $in->currency = $currency_id;
                $in->legal = $legal_id;
                $in->create_time = time();

                $in->save();

                $all_balance = bc_mul($price, $remain_num, 5);
                $data_wallet1 = [
                    'balance_type' =>  2,
                    'wallet_id' => $user_change->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' =>  $user_change->change_balance,
                    'change' => -$all_balance,
                    'after' => bc_sub($user_change->change_balance, $all_balance, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  1,
                    'wallet_id' => $user_change->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_change->lock_change_balance,
                    'change' => $all_balance,
                    'after' => bc_add($user_change->lock_change_balance, $all_balance, 5),
                ];

                $user_change->change_balance = bc_sub($user_change->change_balance, $all_balance, 5);
                $user_change->lock_change_balance = bc_add($user_change->lock_change_balance, $all_balance, 5);
                $user_change->save();

                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => bc_mul($all_balance, -1, 5),
                    'info' => "提交卖入记录扣除",
                    'type' => AccountLog::TRANSACTIONIN_SUBMIT_REDUCE,
                    'currency' => $currency_id,
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => $all_balance,
                    'info' => "提交卖入记录扣除，锁定余额增加",
                    'type' => AccountLog::TRANSACTIONIN_SUBMIT_REDUCE,
                    'currency' => $currency_id,
                ],$data_wallet2);
            } else {
                //匹配完成s
            }
            Transaction::pushNews($currency_id, $legal_id);
            DB::commit();
            return $this->success("操作成功");
        } catch (\Exception $ex) {
            DB::rollback();
            return $this->error($ex->getMessage());
        }
    }
    //钱包卖出代码
    public function walletOut()
    {

        $user_id = Users::getUserId();

        $price = Input::get("price");
        $num = Input::get("num");

        $legal_id = Input::get("legal_id");
        $currency_id = Input::get("currency_id");

        $has_num = 0;
        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("参数错误");
        }


        $user = Users::find($user_id);
        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            // ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();
        if (empty($user) || empty($legal) || empty($currency)) {

            return $this->error("数据未找到");
        }
        try {
            DB::beginTransaction();
            $user_currency = UsersWallet::where("user_id", $user_id)
                ->where("currency", $currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($user_currency)) {
                throw new \Exception("请先添加钱包");
            }
            if (bc_comp($price, 0) <= 0 || bc_comp($num, 0) <= 0) {
                throw new \Exception("价格和数量必须大于0");
            }
            if (bc_comp($user_currency->change_balance, $num) < 0) {
                throw new \Exception("您的币不足");
            }

            //查找价格高于等于当前卖出价格的所有买入委托
            $in = TransactionIn::where("price", ">=", $price)
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->where("number", ">", "0")
                ->orderBy('price', 'desc')
                ->orderBy('id', 'asc')
                ->get();

            if (!empty($in)) {
                foreach ($in as $i) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num);
                        $this_num = 0;
                        if (bc_comp($i->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $i->number;
                        }
                        $has_num = bc_add($has_num, $this_num, 5);
                        if (bc_comp($this_num, 0) > 0) {
                            TransactionOut::walletTransaction($i, $this_num, $user, $user_currency, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }

            $num = bc_sub($num, $has_num, 5);

            if (bc_comp($num, 0) > 0) {
                $out = new TransactionOut();
                $out->user_id = $user_id;
                $out->price = $price;
                $out->number = $num;
                $out->currency = $currency_id;
                $out->legal = $legal_id;
                $out->create_time = time();
                $out->save();

                $data_wallet1 = [
                    'balance_type' =>  2,
                    'wallet_id' => $user_currency->id,
                    'lock_type' => 0,
                    'create_time' => time(),
                    'before' => $user_currency->change_balance,
                    'change' => -$num,
                    'after' => bc_sub($user_currency->change_balance, $num, 5),
                ];
                $data_wallet2 = [
                    'balance_type' =>  2,
                    'wallet_id' => $user_currency->id,
                    'lock_type' => 1,
                    'create_time' => time(),
                    'before' => $user_currency->lock_change_balance,
                    'change' => $num,
                    'after' => bc_add($user_currency->lock_change_balance, $num, 5),
                ];
                $user_currency->change_balance = bc_sub($user_currency->change_balance, $num, 5);
                $user_currency->lock_change_balance = bc_add($user_currency->lock_change_balance, $num, 5);
                $user_currency->save();

                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => bc_mul($num, -1),
                    'info' => "提交卖出记录扣除",
                    'type' => AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE,
                    'currency' => $currency_id
                ],$data_wallet1);
                AccountLog::insertLog([
                    'user_id' => $user->id,
                    'value' => $num,
                    'info' => "提交卖出记录(增加锁定)",
                    'type' => AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE,
                    'currency' => $currency_id
                ],$data_wallet2);
            }
            Transaction::pushNews($currency_id, $legal_id);
            DB::commit();
            return $this->success("操作成功");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }
}
