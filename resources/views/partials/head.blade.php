<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>

{{-- Title --}}
<title>{{ isset($title) ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

{{-- Meta Description --}}
<meta name="description"
      content="{{ $metaDescription ?? site('seo', 'meta_description', 'Vingrošanas Studija Siguldā — grupu un individuālās nodarbības veselīgam dzīvesveidam. Pieteikties nodarbībām var tiešsaistē.') }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">

{{-- Robots --}}
<meta name="robots" content="{{ $robots ?? 'index, follow' }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ isset($title) ? $title . ' · ' . config('app.name') : config('app.name') }}">
<meta property="og:description"
      content="{{ $metaDescription ?? site('seo', 'meta_description', 'Vingrošanas Studija Siguldā — grupu un individuālās nodarbības veselīgam dzīvesveidam. Pieteikties nodarbībām var tiešsaistē.') }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:locale" content="lv_LV">
<meta property="og:image" content="{{ $ogImage ?? asset(site('seo', 'og_image', 'images/og-image.png')) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ config('app.name') }}">

{{-- Twitter / X Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ isset($title) ? $title . ' · ' . config('app.name') : config('app.name') }}">
<meta name="twitter:description"
      content="{{ $metaDescription ?? site('seo', 'meta_description', 'Vingrošanas Studija Siguldā — grupu un individuālās nodarbības veselīgam dzīvesveidam. Pieteikties nodarbībām var tiešsaistē.') }}">
<meta name="twitter:image" content="{{ $ogImage ?? asset(site('seo', 'og_image', 'images/og-image.png')) }}">

{{-- Favicons --}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

{{-- Structured Data: LocalBusiness --}}
<script type="application/ld+json">
    {
        "context": "https://schema.org",
        "type": "HealthClub",
        "name": "{{ config('app.name') }}",
    "description": "{{ site('seo', 'meta_description', 'Vingrošanas studija Siguldā — grupu un individuālās nodarbības veselīgam dzīvesveidam.') }}",
    "url": "{{ config('app.url') }}",
    @if (filled(site('contact', 'phone')))
    "telephone": "{{ site('contact', 'phone') }}",
    @endif
    "email": "{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}",
    "address": {
        "type": "PostalAddress",
        "streetAddress": "{{ site('contact', 'address', 'Strēlnieku iela 20 A, Sigulda') }}",
        "addressLocality": "Sigulda",
        "addressCountry": "LV"
    },
    "sameAs": [
        "{{ site('contact', 'instagram_url', 'https://www.instagram.com/vingrosanas.studija/') }}",
        "{{ site('contact', 'facebook_url', 'https://www.facebook.com/vs.sigulda') }}"
    ],
    "logo": "{{ asset('images/vingrosanas_studija_logo.svg') }}",
    "image": "{{ asset(site('seo', 'og_image', 'images/og-image.png')) }}"
}
</script>

{{-- Fonts --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>

{{-- Assets --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
