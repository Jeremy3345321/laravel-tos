@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">

    @if (session('status'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded p-3 mb-4">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 rounded p-4 mb-4">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <h1 class="text-2xl font-bold mb-1">{{ $tos->course }}</h1>
    <p class="text-gray-500 mb-4">{{ $tos->lessons->count() }} lesson(s) &middot; {{ $tos->total_items }} total items</p>

    {{-- ===== Table of Specification — Overall ===== --}}
    <h2 class="text-lg font-semibold mb-2">Table of Specification &mdash; Overall</h2>
    <div class="border rounded-lg overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="text-left px-4 py-2">Bloom's Level</th>
                    <th class="text-right px-4 py-2">% Weight</th>
                    <th class="text-right px-4 py-2">Item Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tos->distribution as $level => $d)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $level }}</td>
                        <td class="px-4 py-2 text-right">{{ isset($d['weight']) ? ($d['weight'] * 100) . '%' : '—' }}</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ $d['item_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t-2">
                <tr>
                    <td class="px-4 py-2 font-bold">Total</td>
                    <td></td>
                    <td class="px-4 py-2 text-right font-bold">{{ $tos->total_items }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ===== Per-Lesson Breakdown ===== --}}
    @foreach ($tos->lessons as $lesson)
        <div class="border rounded-lg p-5 mb-6">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-lg font-semibold">{{ $loop->iteration }}. {{ $lesson->title }}</h3>
                <span class="text-xs text-gray-500 whitespace-nowrap">weight {{ $lesson->weight }} &middot; {{ $lesson->item_quota }} item(s)</span>
            </div>

            {{-- Bloom level pills for this lesson --}}
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($lesson->level_distribution as $level => $d)
                    @if (($d['item_count'] ?? 0) > 0)
                        <span class="text-xs font-semibold px-2 py-1 rounded bg-gray-100">{{ $level }}: {{ $d['item_count'] }}</span>
                    @endif
                @endforeach
            </div>

            {{-- Classified objectives for this lesson --}}
            @if ($lesson->objectives->isNotEmpty())
                <div class="space-y-2 mb-4">
                    @foreach ($lesson->objectives as $obj)
                        <div class="border rounded p-3 flex justify-between items-start">
                            <div>
                                <p class="text-sm">{{ $obj->objective_text }}</p>
                                <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                    {{ $obj->bloom_level }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 whitespace-nowrap ml-4">{{ $obj->confidence }}% confident</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Generated exam questions for this lesson --}}
            @if ($lesson->examQuestions->isNotEmpty())
                <h4 class="text-sm font-semibold mb-2 text-emerald-700">Generated Questions ({{ $lesson->examQuestions->count() }})</h4>
                <div class="space-y-4">
                    @foreach ($lesson->examQuestions as $i => $q)
                        <div class="border rounded p-4">
                            <div class="flex justify-between items-start mb-2">
                                <p class="font-medium">{{ $i + 1 }}. {{ $q->question }}</p>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-purple-100 text-purple-800 whitespace-nowrap ml-3">
                                    {{ $q->bloom_level }}
                                </span>
                            </div>
                            <ul class="text-sm space-y-1 mb-2">
                                @foreach ($q->options as $letter => $option)
                                    <li class="{{ $letter === $q->correct_answer ? 'font-semibold text-emerald-700' : '' }}">
                                        {{ $letter }}. {{ $option }}
                                        @if ($letter === $q->correct_answer) ✓ @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($q->rationale)
                                <p class="text-xs text-gray-500 italic">{{ $q->rationale }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- ===== Generate Exam (only if no questions exist anywhere yet) ===== --}}
    @if ($tos->examQuestions->isEmpty())
        <form action="{{ route('tos.generate-exam', $tos) }}" method="POST">
            @csrf
            <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded font-medium hover:bg-emerald-700">
                Generate Exam from All Lesson PDFs
            </button>
        </form>
    @endif
</div>
@endsection