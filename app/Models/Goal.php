<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_type', 'frequency', 'duration', 'challenge_id',
    ];

    // A goal belongs to a challenge
    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
    /**
     * Define the relationship between GoalProgress and Goal.
     */
    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
