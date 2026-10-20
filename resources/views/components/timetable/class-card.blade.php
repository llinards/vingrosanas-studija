@props([
    'entry',
    'tone' => 'normal',
    'lines' => [],
])

{{--
    One class in the timetable. On small screens it is a compact row (time | name and details | action);
    in the narrow desktop day columns it stacks. $tone is "normal", "picked" or "muted" (cannot be booked).
    Each of $lines (coach, price, free places) is shown on a line of its own; empty ones are skipped.
--}}
<article {{ $attributes->class([
    'flex items-center gap-3 rounded-xl border px-3 py-2 lg:flex-col lg:items-stretch lg:gap-1 lg:p-2.5',
    'border-zinc-200 bg-surface' => $tone === 'normal',
    'border-blue bg-blue/5 ring-1 ring-blue' => $tone === 'picked',
    'border-zinc-100 bg-zinc-50 text-zinc-400' => $tone === 'muted',
]) }}>
    <div class="w-14 shrink-0 font-heading text-lg font-medium lg:w-auto">{{ $entry['start_time'] }}</div>

    <div class="min-w-0 flex-1">
        <div class="font-medium leading-snug">{{ $entry['service_name'] }}</div>
        @foreach(array_filter($lines) as $line)
            <div @class(['text-sm', 'text-zinc-500' => $tone !== 'muted'])>{{ $line }}</div>
        @endforeach
    </div>

    @isset($action)
        <div class="shrink-0 lg:mt-1">{{ $action }}</div>
    @endisset
</article>
