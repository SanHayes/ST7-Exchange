<?php

namespace App\Nova\Metrics;

use App\Models\UsersWalletOut;
use App\Models\ChargeReq;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;

class BankExtractCurrency extends Value
{

    public function name()
    {
        return __('BankExtractCurrency'); // TODO: Change the autogenerated stub
    }
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $recharge = $this->sum($request, ChargeReq::with([])->where('status', 2), 'amount');
        $withdraw = $this->sum($request, UsersWalletOut::with([])->where('status', 2), 'number');
        
        $netAmount = $recharge->value - $withdraw->value;
        
        return $this->result($netAmount)->format('currency');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            'TODAY' => Nova::__('Today'),
            'YESTERDAY' => Nova::__('Yesterday'),
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            365 => Nova::__('365 Days'),
            'MTD' => Nova::__('Month To Date'),
            'QTD' => Nova::__('Quarter To Date'),
            'YTD' => Nova::__('Year To Date'),
            'ALL' => Nova::__('All Time')
        ];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }
}
