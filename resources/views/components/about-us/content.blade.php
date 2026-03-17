@props([
'section',
])

<div class="prose max-w-none">
    {!! site('about', $section.'_content', '') !!}
</div>