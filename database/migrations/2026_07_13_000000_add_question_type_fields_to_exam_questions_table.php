<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTE: I inferred the table name as `exam_questions` from the
 * $lesson->examQuestions() / $tos->examQuestions() relations used in
 * _results.blade.php. Rename below if your actual table is different
 * (e.g. `exam_items`).
 *
 * ->change() on the existing `options` / `correct_answer` columns requires
 * doctrine/dbal:
 *   composer require doctrine/dbal
 * If you'd rather not add that dependency, drop the two ->change() lines
 * below — they're only there to make those columns nullable (Modified
 * True-or-False and Enumeration questions don't use them), and the app
 * will still work as long as your DB doesn't already enforce NOT NULL
 * on those columns at a level Laravel can't skip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->string('question_type')->default('multiple_choice')->after('bloom_level');
            $table->boolean('is_true')->nullable()->after('correct_answer');
            $table->text('correction')->nullable()->after('is_true');
            $table->json('accepted_answers')->nullable()->after('correction');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->json('options')->nullable()->change();
            $table->string('correct_answer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropColumn(['question_type', 'is_true', 'correction', 'accepted_answers']);
        });
    }
};
