<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'tos_id',
        'lesson_id',
        'objective_text',
        'bloom_level',
        'bloom_level_index',
        'confidence',
        'all_probabilities',
    ];

    protected $casts = [
        'all_probabilities' => 'array',
    ];

    public function tos(): BelongsTo
    {
        return $this->belongsTo(TableOfSpecification::class, 'tos_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }
}