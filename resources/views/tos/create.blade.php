@extends('layouts.app')

@section('content')

<p class="cvsu-intro">
    Add one entry per lesson (5-10 PDFs is fine). Give each lesson a weight
    reflecting how much class time you spent on it &mdash; exam items are
    distributed across lessons proportional to that weight, then balanced
    across Bloom's Taxonomy levels within each lesson.
</p>

<div class="bloom-legend">
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-remembering)"></span>Remembering</span>
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-understanding)"></span>Understanding</span>
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-applying)"></span>Applying</span>
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-analyzing)"></span>Analyzing</span>
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-evaluating)"></span>Evaluating</span>
    <span class="bloom-chip"><span class="dot" style="background: var(--bloom-creating)"></span>Creating</span>
</div>

@if ($errors->any())
    <div class="alert alert-error">
        <ul style="margin:0; padding-left: 1.1rem;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="spec-sheet">
    <form action="{{ route('tos.store') }}" method="POST" enctype="multipart/form-data" id="tos-form">
        @csrf

        <div class="field-group">
            <label for="course">Course</label>
            <input type="text" id="course" name="course" required value="{{ old('course') }}"
                   class="field-blank" placeholder="e.g. Data Structures and Algorithms">
        </div>

        <div class="field-group" style="margin-bottom: 2rem;">
            <label for="total_items">Total Exam Items</label>
            <input type="number" id="total_items" name="total_items" min="5" max="200" value="{{ old('total_items', 50) }}"
                   class="field-blank field-narrow">
        </div>

        <h2 class="section-title">Lessons</h2>

        <div id="lesson-panels"></div>

        <button type="button" class="btn-add-lesson" id="add-lesson-btn">+ Add Lesson</button>

        <div>
            <button type="submit" class="btn-seal">
                Classify Outcomes &amp; Build TOS
            </button>
        </div>
    </form>
</div>

{{-- Template for one lesson panel, cloned by JS below --}}
<template id="lesson-panel-template">
    <div class="lesson-panel" data-lesson-panel>
        <div class="lesson-panel-header">
            <span class="lesson-panel-number" data-lesson-number>Lesson 1</span>
            <button type="button" class="btn-remove-lesson" data-remove-lesson>Remove</button>
        </div>

        <div class="lesson-weight-row">
            <div class="field-group">
                <label>Lesson Title</label>
                <input type="text" data-field="title" class="field-blank" placeholder="e.g. Binary Search Trees" required>
            </div>
            <div class="field-group" style="max-width: 160px;">
                <label>Weight (hours)</label>
                <input type="number" data-field="weight" class="field-blank" placeholder="e.g. 3" min="0.1" step="0.1" required>
            </div>
        </div>

        <div class="field-group">
            <label>Course Learning Outcomes <span style="font-weight:400; color: var(--ink-soft); font-family: var(--font-body); font-size:0.85rem;">(one per line)</span></label>
            <textarea data-field="objectives_text" rows="5" required class="field-ruled"
                      placeholder="Students will be able to differentiate between a binary search tree and a balanced tree.&#10;Students will design an algorithm to balance an unbalanced tree."></textarea>
        </div>

        <div class="field-group" style="margin-bottom: 0;">
            <label>Lecture Material (PDF)</label>
            <input type="file" data-field="pdf" class="field-blank" accept="application/pdf" required>
        </div>
    </div>
</template>

<script>
(function () {
    const panelsContainer = document.getElementById('lesson-panels');
    const template = document.getElementById('lesson-panel-template');
    const addBtn = document.getElementById('add-lesson-btn');
    const form = document.getElementById('tos-form');
    let lessonCount = 0;

    function renumberPanels() {
        const panels = panelsContainer.querySelectorAll('[data-lesson-panel]');
        panels.forEach((panel, idx) => {
            panel.querySelector('[data-lesson-number]').textContent = 'Lesson ' + (idx + 1);
        });
    }

    function addLessonPanel() {
        const clone = template.content.cloneNode(true);
        const panel = clone.querySelector('[data-lesson-panel]');
        const index = lessonCount++;

        panel.querySelectorAll('[data-field]').forEach((field) => {
            const fieldName = field.getAttribute('data-field');
            field.setAttribute('name', 'lessons[' + index + '][' + fieldName + ']');
        });

        panel.querySelector('[data-remove-lesson]').addEventListener('click', function () {
            panel.remove();
            renumberPanels();
        });

        panelsContainer.appendChild(clone);
        renumberPanels();
    }

    addBtn.addEventListener('click', addLessonPanel);

    // Start with 2 lesson panels so the form isn't empty on load
    addLessonPanel();
    addLessonPanel();

    form.addEventListener('submit', function (e) {
        const panels = panelsContainer.querySelectorAll('[data-lesson-panel]');
        if (panels.length === 0) {
            e.preventDefault();
            alert('Please add at least one lesson.');
        }
    });
})();
</script>

@endsection