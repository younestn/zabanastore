<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ session('direction') ?? "ltr" }}">

<head>
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="nofollow, noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>@yield('title')</title>
    <link rel="shortcut icon"
          href="{{ getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo') }}">

    {{-- Load Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    @include("layouts.admin.partials._style-partials")

    {!! ToastMagic::styles() !!}

    @stack('css_or_js')
</head>

<body data-bs-theme="light">
<script type="text/javascript">
    localStorage.getItem('aside-mini') === 'true' ? document.body.classList.add('aside-mini') : document.body.classList.remove('aside-mini');
</script>

<div class="row">
    <div class="col-12 position-fixed loader-container mt-10rem">
        <div id="loading" class="d--none">
            <div id="loader"></div>
        </div>
    </div>
</div>

@include('layouts.admin.partials._header')
@include('layouts.admin.partials._side-bar')

<main id="content" role="main" class="main-content">
    @yield('content')
    @include('layouts.admin.partials._toggle-modal')
    @include('layouts.admin.components.image-modal')
    @include('layouts.admin.partials._sign-out-modal')
    @include('layouts.admin.partials._modals')
    @include('layouts.admin.partials._alert-message')
</main>

<audio id="myAudio">
    <source src="{{ dynamicAsset(path: 'public/assets/backend/sound/notification.mp3') }}" type="audio/mpeg">
</audio>

@include('layouts.admin.partials._translator-for-js')
@include("layouts.admin.partials._translated-message-container")
@include("layouts.admin.partials._routes-list-container")

{{-- Load jQuery first --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

{{-- Then load your admin scripts --}}
@include("layouts.admin.partials._script-partials")

{{-- Then load Bootstrap 5 JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

{{-- Your custom scripts --}}
@stack('script')

</body>

</html>