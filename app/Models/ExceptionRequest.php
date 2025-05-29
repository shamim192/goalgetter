<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExceptionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'challenge_id', 'date', 'photo', 'text', 'status'
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
