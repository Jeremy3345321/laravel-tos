<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'tos_id',
        'title',
        'weight',
        'pdf_path',
        'item_quota',
        'level_distribution',
        'sort_order',
    ];

    protected $casts = [
        'level_distribution' => 'array',
    ];

    public function tos(): BelongsTo
    {
        return $this->belongsTo(TableOfSpecification::class, 'tos_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'lesson_id');
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'lesson_id');
    }
}
