<!doctype html>
<html itemscope itemtype="http://schema.org/WebPage" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> {{ $general->siteName(__($pageTitle)) }}</title>
    @include('partials.seo')
    <link href="{{ asset('assets/global/css/bootstrap.min.css') }}" rel="stylesheet">

    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet">

    <link href="{{ asset('assets/admin/css/vendor/datepicker.min.css') }}" rel="stylesheet">

    <link href="{{ asset('assets/global/css/line-awesome.min.css') }}" rel="stylesheet" />
    <link href="{{ asset($activeTemplateTrue . 'css/slick.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/jquery-ui.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/magnific-popup.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/odometer-theme-default.css') }}" rel="stylesheet">
    <link href="{{ asset($activeTemplateTrue . 'css/main.css') }}" rel="stylesheet">

    <link href="{{ asset($activeTemplateTrue . 'css/custom.css') }}" rel="stylesheet">

    @stack('style-lib')

    @stack('style')

    <link href="{{ asset($activeTemplateTrue . 'css/color.php') }}?color={{ $general->base_color }}&secondColor={{ $general->secondary_color }}" rel="stylesheet">
</head>

<body>
    @yield('panel')
    <script src="{{ asset('assets/global/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/slick.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/jquery.magnific-popup.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/jquery.filterizr.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/jquery.ui.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/viewport.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/odometer.js') }}"></script>
    @stack('script-lib')
    <script src="{{ asset($activeTemplateTrue . 'js/app.js') }}"></script>
    @stack('script')
    @include('partials.plugins')
    @include('partials.notify')
    <script>
        (function($) {
            "use strict";
            $(".langSel").on("change", function() {
                window.location.href = "{{ route('home') }}/change/" + $(this).val();
            });

        })(jQuery);
    </script>
</body>

</html>
