<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0B3550">
<title>{{ isset($judul) && $judul ? $judul.' · ' : '' }}Piket AMKT Putri</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
