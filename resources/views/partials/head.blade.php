<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="https://fav.farm/%E2%9C%A8" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@if(config('app.env') === 'production')
<script src="https://cdn.usefathom.com/script.js" data-site="{{ config('laracity.fathom_site_id') }}" defer></script>
@endif

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
