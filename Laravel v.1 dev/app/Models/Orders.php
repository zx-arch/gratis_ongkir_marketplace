<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    protected $table = 'orders';
    protected $fillable = [
        'user_id',
        'order_date',
        'total_amount'
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id');
    }
}