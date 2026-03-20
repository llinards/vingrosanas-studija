@props([
'section',
])

<div class="rich-text-content">
    {!! site('about', $section.'_content', '') !!}
</div>