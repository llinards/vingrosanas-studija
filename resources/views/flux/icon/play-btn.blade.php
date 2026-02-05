@php $attributes = $unescapedForwardedAttributes ?? $attributes; @endphp

@props([
'variant' => 'outline',
])

@php
$classes = Flux::classes('shrink-0')
->add(match($variant) {
'outline' => '[:where(&)]:size-6',
'solid' => '[:where(&)]:size-6',
'mini' => '[:where(&)]:size-5',
'micro' => '[:where(&)]:size-4',
});
@endphp

{{-- Your SVG code here: --}}
<svg {{ $attributes->class($classes) }} data-flux-icon aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="38"
    height="37" viewBox="0 0 122 122" fill="none">
    <path
        d="M61 122C94.6894 122 122 94.6894 122 61C122 27.3106 94.6894 0 61 0C27.3106 0 0 27.3106 0 61C0 94.6894 27.3106 122 61 122Z"
        fill="currentColor" />
    <path d="M49 34L83 61L49 88V34Z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
</svg>