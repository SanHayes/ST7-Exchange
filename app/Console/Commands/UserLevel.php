<?php

namespace App\Console\Commands;

use App\Models\AccountLog;
use App\Models\Currency;
use App\Models\Level;
use App\Models\Users;
use App\Models\UsersWallet;
use App\Models\Setting;
use App\Utils\RPC;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class UserLevel extends Command
{
	protected $signature = "update_user_level";
	protected $description = "更新级别";
	public function handle()
	{
		$this->comment("start");
		$level_one = new Level();
		$l = $level_one->orderBy('level', 'desc')->first();
		if (!empty($l)) {
			$user = Users::where('level', '<', $l->level)->get();
			if (count($user) == 0) {
				$this->comment('没有用户可以升级');
			}
			$level = Level::all();
			foreach ($user as $v) {
				foreach ($level as $l) {
					if ($l->level <= $v->level) {
						continue 1;
					} else {
						if ($l->fill_currency != 0) {
							if ($v->fund < $l->fill_currency) {
								continue 1;
							}
						}
						$count = Users::where('parent_id', $v->id)->where(function ($query) use($l) {
							if ($l->direct_drive_price != 0) {
								$query->where('fund', '>=', $l->direct_drive_price);
							}
						})->count('id');
						if ($l->direct_drive_count != 0) {
							if ($l->direct_drive_count > $count) {
								continue 1;
							}
						}
						$v->level = $l->level;
						$v->save();
					}
				}
			}
		}
		$this->comment("end");
	}
	public function fund($user_id)
	{
		$currency = Currency::where('is_micro', 1)->get();
		if (empty($currency)) {
			return 0;
		}
		$price = 0;
		foreach ($currency as $v) {
			$user_wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency->id)->first();
			if (!empty($user_wallet)) {
				$fund = $user_wallet->micro_balance * $v->price;
				$price += $fund;
			}
		}
		return $price;
	}
}
