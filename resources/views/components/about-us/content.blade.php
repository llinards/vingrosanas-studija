@props([
'section',
])

<div class="about-us-content">
    {!! site('about', $section.'_content', '') !!}
</div>