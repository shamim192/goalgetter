<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'payment_method_id',
        'punishment_amount',
        'joined_at',
        'status'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

}
