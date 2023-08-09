@extends($activeTemplate . 'layouts.app')
@section('panel')
    <section class="maintenance-page flex-column justify-content-center">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-8 text-center">
                    <div class="row justify-content-center">
                        <div class="col-sm-6 col-8 col-lg-8">
                            <img src="{{ getImage('assets/images/maintenance.png') }}" alt="@lang('image')" class="img-fluid mx-auto mb-5">
                        </div>
                    </div>
                    <p class="mx-auto text-center">@php echo $maintenance->data_values->description @endphp</p>
                </div>
            </div>
        </div>
    </section>
@endsection
