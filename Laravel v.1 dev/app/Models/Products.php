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

    public function cart()
    {
        return $this->hasMany(Carts::class);
    }
}