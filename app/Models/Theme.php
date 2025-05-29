<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_url',
        'status'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_theme', 'theme_id', 'product_id');
    }


}
