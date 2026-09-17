{{--
    The blank questionnaire, rendered on demand: sections, questions, options and
    the images the author pinned to either. Nothing here reflects an answer - the
    PDF is the form, not a compilazione.

    Labels and headings carry markup, sanitised on write to <b> <i> <u> <br>
    (App\Support\RichText), which is why they are the only fields printed unescaped.
--}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #14181f; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
        .meta { color: #6b7280; margin-bottom: 4px; }
        .description { margin-bottom: 14px; }
        .question { margin: 0 0 12px; page-break-inside: avoid; }
        .label { margin-bottom: 4px; }
        .required { color: #b91c1c; }
        .hint { color: #6b7280; font-size: 10px; }
        .option { margin: 2px 0 2px 14px; }
        .box { display: inline-block; width: 9px; height: 9px; border: 1px solid #6b7280; margin-right: 6px; }
        .round { border-radius: 5px; }
        .rule { border-bottom: 1px solid #d1d5db; height: 16px; margin: 4px 0 0 14px; }
        img { max-height: 120px; margin: 4px 0 4px 14px; }
    </style>
</head>
<body>
<h1>{{ $checklist->title }}</h1>
<p class="meta">{{ $checklist->company->name ?? '' }}@if ($checklist->createdBy) — aggiunto da {{ $checklist->createdBy->name }}@endif</p>
@if ($checklist->description)
    <p class="description">{{ $checklist->description }}</p>
@endif

@forelse ($checklist->sections as $section)
    <h2>{!! $section->title !!}</h2>

    @foreach ($section->questions as $question)
        <div class="question">
            <div class="label">{!! $question->label !!}@if ($question->is_required)<span class="required"> *</span>@endif</div>

            @if ($question->help_text)
                <div class="hint">{{ $question->help_text }}</div>
            @endif

            @if ($question->image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::path('checklist-images/'.basename($question->image_path)) }}" alt="">
            @endif

            @switch ($question->type)
                @case (\App\Enums\QuestionType::SingleChoice)
                @case (\App\Enums\QuestionType::MultiChoice)
                    @foreach ($question->options as $option)
                        <div class="option">
                            <span class="box {{ $question->type === \App\Enums\QuestionType::SingleChoice ? 'round' : '' }}"></span>{{ $option->label }}
                        </div>
                        @if ($option->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::path('checklist-images/'.basename($option->image_path)) }}" alt="">
                        @endif
                    @endforeach
                    @break

                @case (\App\Enums\QuestionType::Date)
                    <div class="option hint">data: __ / __ / ____</div>
                    @break

                @case (\App\Enums\QuestionType::Time)
                    <div class="option hint">orario: __ : __</div>
                    @break

                @default
                    <div class="rule"></div>
                    <div class="rule"></div>
            @endswitch

            @if ($question->allows_note)
                <div class="option hint">note</div>
                <div class="rule"></div>
            @endif

            @if ($question->allows_attachment)
                <div class="option hint">allegato richiesto</div>
            @endif
        </div>
    @endforeach
@empty
    <p class="hint">Questa checklist non contiene ancora domande.</p>
@endforelse
</body>
</html>
