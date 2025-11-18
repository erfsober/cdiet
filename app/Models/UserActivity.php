<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    const TYPES = [
        'exercise' => 'exercise',
        'food' => 'food',
        'drink-water' => 'drink-water',
        'step' => 'step',
    ];

    public function recommendedMeal(): BelongsTo
    {
        return $this->belongsTo(RecommendedMeal::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function scopeToday($query)
    {
        return $query->where('date', verta()->formatJalaliDate());
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
