<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    // protected $appends = ['is_favorite'];

    protected $fillable = [
        'name',
        'description',
        'type',
        'price',
        'status',
        'popular',
        'image_url', // This can be 'image_url' if you want to use that name instead
    ];

    protected $casts = [
        'fav' => 'boolean',
    ];

    // Define many-to-many relationship with themes
    public function themes()
    {
        return $this->belongsToMany(Theme::class, 'product_theme', 'product_id', 'theme_id');
    }

    public function styles()
    {
        return $this->belongsToMany(Style::class, 'product_style');
    }

    public function getIsFavoriteAttribute(){

    }
}
