<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #14181f; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th { width: 34%; text-align: left; vertical-align: top; padding: 6px 8px 6px 0; color: #6b7280; font-weight: normal; }
        td { padding: 6px 0; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<h1>{{ $incident->kind === \App\Enums\IncidentKind::Injury ? 'Infortunio' : 'Near miss' }} #{{ $incident->id }}</h1>
<p class="meta">{{ $incident->company->name ?? '' }} — segnalazione del
    {{ $incident->reported_at?->format('d/m/Y H:i') ?? '-' }}</p>

<table>
    <tr><th>tipo di evento</th><td>{{ $incident->kind->value }}</td></tr>
    <tr><th>stato</th><td>{{ $incident->status->value }}</td></tr>
    <tr><th>cosa è successo</th><td>{{ $incident->description ?: '-' }}</td></tr>
    <tr><th>dove è successo (reparto/area)</th><td>{{ $incident->location ?: ($incident->department ?: '-') }}</td></tr>
    <tr><th>data</th><td>{{ ($incident->occurred_at ?? $incident->reported_at)?->format('d/m/Y') ?? '-' }}</td></tr>
    <tr><th>orario</th><td>{{ ($incident->occurred_at ?? $incident->reported_at)?->format('H:i') ?? '-' }}</td></tr>
    @if ($incident->kind === \App\Enums\IncidentKind::Injury)
        <tr><th>infortunato</th><td>{{ $incident->injured_person_name ?: '-' }}</td></tr>
        <tr><th>durata della degenza</th><td>{{ $incident->absence_days !== null ? $incident->absence_days.' giorni' : '-' }}</td></tr>
        <tr><th>riferimento INAIL</th><td>{{ $incident->inail_ref ?: '-' }}</td></tr>
    @endif
    <tr><th>segnalato da</th><td>{{ $incident->is_anonymous ? 'anonimo' : ($incident->reportedBy?->name ?? '-') }}</td></tr>
    <tr><th>cause</th><td>{{ $incident->causes ?: '-' }}</td></tr>
    <tr><th>note e osservazioni</th><td>{{ $incident->actions_taken ?: '-' }}</td></tr>
    <tr><th>allegati</th><td>
        @forelse ($incident->attachments as $attachment)
            {{ $attachment->name ?: basename($attachment->storage_path) }}@if (! $loop->last)<br>@endif
        @empty
            nessun allegato
        @endforelse
    </td></tr>
</table>
</body>
</html>
