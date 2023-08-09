@extends($activeTemplate . 'layouts.app')
@section('panel')
    @stack('fbComment')
    <div class="preloader">
        <div class="preloader__loader">
            <i class="las la-heart"></i>
        </div>
    </div>
    <div class="back-to-top">
        <span class="back-top">
            <i class="las la-angle-double-up"></i>
        </span>
    </div>
    <div class="body-overlay" id="body-overlay"></div>
    <div class="search-popup" id="search-popup">
        <form class="search-form" action="#">
            <div class="form-group">
                <input class="form-control" type="text" placeholder="Search....." />
            </div>
            <button class="submit-btn xl-text" type="submit">
                <i class="las la-search"></i>
            </button>
        </form>
    </div>
    <div class="toggle-overlay"></div>
    @include($activeTemplate . 'partials.header_top')
    @include($activeTemplate . 'partials.user_header')

    <div class="section bg-light">
        <div class="container">
            <div class="row g-4">
                @include($activeTemplate . 'partials.sidenav')
                <div class="col-lg-8 col-xl-9">
                    <div class="d-flex justify-content-between align-items-center breadcrumb mb-3 flex-wrap gap-2">
                        <div class="page-title">
                            <h5 class="m-0">{{ __($pageTitle) }}</h5>
                        </div>
                        @stack('breadcrumb-plugins')
                    </div>
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    @include($activeTemplate . 'partials.footer')
@endsection
