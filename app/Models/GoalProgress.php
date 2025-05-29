<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id', 'user_id', 'date', 'progress_data',
    ];

    // Define the relationship with the goal
    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    // Define the relationship with the user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
