<?php

/**
 * Created by PhpStorm.
 * User: YSX
 * Date: 2018/12/4
 * Time: 16:36
 */

namespace App\Http\Controllers\Agent;

use Illuminate\Http\Request;
use App\Models\{AccountLog, Agent, Currency, Users, UsersWalletOut};

class UserController extends Controller
{


    //用户列表
    public function lists(Request $request)
    {
        $limit = $request->get('limit', 10);
        $id = request()->input('id', 0);
        $parent_id    = $request->user()->id;//代理商id
        $account_number = request()->input('account_number', '');
        $start = request()->input('start', '');
        $end = request()->input('end', '');

        $users = new Users();

        $users = $users->leftjoin("user_real", "users.id", "=", "user_real.user_id");

        if ($id) {
            $users = $users->where('users.id', $id);
        }
//        if ($parent_id > 0) {
//            $users = $users->where('users.agent_note_id', $parent_id);
//        }
        if ($account_number) {
            $users = $users->where('users.account_number', $account_number);
        }
        if (!empty($start) && !empty($end)) {
            $users->whereBetween('users.time', [strtotime($start . ' 0:0:0'), strtotime($end . ' 23:59:59')]);
        }
        $users = $users->where('users.simulation',0);
        $users = $users->whereRaw("FIND_IN_SET($parent_id,users.agent_path)");

        $list = $users->select("users.*", "user_real.card_id")->paginate($limit);

        return $this->success('list',$list);
    }

    /**
     * 获取用户管理的统计
     * @param Request $r
     */
    public function get_user_num(Request $request)
    {

        $id             = request()->input('id', 0);
        $account_number = request()->input('account_number', '');
        $parent_id            = $request->user()->id;//代理商id
        $start = request()->input('start', '');
        $end = request()->input('end', '');
        $currency_id = request()->input('currency_id', '');
        $users = new Users();

        if ($id) {
            $users = $users->where('id', $id);
        }
        if ($parent_id > 0) {
            $users = $users->where('agent_note_id', $parent_id);
        }
        if ($account_number) {
            $users = $users->where('account_number', $account_number);
        }
        if (!empty($start) && !empty($end)) {
            $users->whereBetween('time', [strtotime($start . ' 0:0:0'), strtotime($end . ' 23:59:59')]);
        }
        $users = $users->whereRaw("FIND_IN_SET($parent_id,`agent_path`)");
        $users_id = $users->get()->pluck('id')->all();

        $_num = $users->count();

        $_daili = $users->where('agent_id', '>', 0)->count();


        $_ru = AccountLog::where('type', AccountLog::CHAIN_RECHARGE)
            ->whereIn('user_id', $users_id)
            ->when($currency_id > 0, function ($query) use ($currency_id) {
                $query->where('currency', $currency_id);
            })->sum('value');

        $_chu = UsersWalletOut::where('status', 2)
            ->whereIn('user_id', $users_id)
            ->when($currency_id > 0, function ($query) use ($currency_id) {
                $query->where('currency', $currency_id);
            })->sum('real_number');

        $data = [];
        $data['_num'] = $_num;
        $data['_daili'] = $_daili;
        $data['_ru'] = $_ru;
        $data['_chu'] = $_chu;

        return $this->success('info',$data);
    }

    //我的邀请二维码
    public function get_my_invite_code()
    {

        $_self = Agent::getAgent();

        if ($_self == null) {
            $this->outmsg('超时');
        }

        $use = Users::getById($_self->user_id);

        return $this->ajaxReturn(['invite_code' => $use->extension_code, 'is_admin' => $_self->is_admin]);
    }

    //代理商管理
    public function salesmenIndex()
    {
        return view("agent.salesmen.index");
    }

    //添加代理商页面
    public function salesmenAdd()
    {
        $data = request()->all();

        return view("agent.salesmen.add", ['d' => $data]);
    }

    public function salesmenEdit()
    {
        $data = request()->all();
        return view("agent.salesmen.add", ['d' => $data]);
    }
    //出入金管理
    public function transferIndex()
    {
        return view("agent.user.transfer");
    }

     //用户点控
     public function risk()
     {

         $user_id = request()->get('id', 0);
         $user=Users::find($user_id);

         return view("agent.user.risk", ['result' => $user]);
     }

     public function postRisk()
     {

        $user_id = request()->get('id', 0);
        $risk = request()->get('risk', 0);
        $user=Users::find($user_id);
        $agent_id = Agent::getAgentId();
        $parent_agent = explode(',', $user->agent_path);

        if (!in_array($agent_id, $parent_agent)) {
            return $this->error('不是您的伞下用户，不可操作');
        }
        try {
            //code...
            $user->risk=$risk;
            $user->save();
            return $this->success("操作成功");

        } catch (\Throwable $th) {
            //throw $th;
            return $this->error($th->getMessage());
        }


     }


}
