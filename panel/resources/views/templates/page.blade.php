{{-- Hydra: a public page (e.g. the imprint) for visitors who are not logged in. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1" name="viewport">
        <title>{{ $title }} – {{ config('app.name', 'Hydra') }}</title>
        @include('partials.hydra.head')
    </head>
    <body class="hydra-body">
        <main class="hydra-public-page">
            <a href="{{ route('auth.login') }}" class="hydra-public-page-logo">
                <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}">
            </a>
            <article class="hydra-box">
                <div class="hydra-box-body hydra-markdown">
                    @if(trim($html) === '')
                        <h1>{{ $title }}</h1>
                    @else
                        {!! $html !!}
                    @endif
                </div>
            </article>
            <p class="hydra-footer">
                @include('partials.hydra.credit')
                &middot; <a href="{{ route('auth.login') }}">{{ __('client/auth.login.title') }}</a>
            </p>
        </main>
        <script>
            document.addEventListener('click', function (event) {
                if (event.target.classList.contains('md-spoiler')) event.target.classList.add('is-revealed');
            });
        </script>
    </body>
</html>
