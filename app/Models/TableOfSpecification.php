<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableOfSpecification extends Model
{
    use HasFactory;

    protected $table = 'table_of_specifications';

    protected $fillable = [
        'user_id',
        'course',
        'total_items',
        'distribution',
    ];

    protected $casts = [
        'distribution' => 'array',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'tos_id')->orderBy('sort_order');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'tos_id');
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'tos_id');
    }
}