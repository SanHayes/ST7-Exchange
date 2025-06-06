<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Currency;

class BindBoxOrder extends Model
{
    protected $table = 'bind_box_order';
    public $timestamps = false;

    protected $appends = [
        'currency_name',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

     public function getCurrencyNameAttribute()
    {
        return $this->currency()->value('name');
    }

}
