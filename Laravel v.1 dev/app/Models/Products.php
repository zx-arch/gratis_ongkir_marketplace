<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    protected $table = 'products';
    protected $fillable = [
        'name',
        'price',
        'stock',
    ];

    public function carts()
    {
        return $this->belongsTo(Carts::class, 'id', 'product_id');
    }
}