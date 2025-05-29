<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'start_date',
        'end_date',
        'description',
        'punishment'
    ];
    // A challenge has many goals
    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'bookings', 'challenge_id', 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'challenge_id'); // Assuming the foreign key in bookings is 'challenge_id'
    }
}
