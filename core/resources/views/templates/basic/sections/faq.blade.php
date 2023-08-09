@php
    $content = getContent('faq.content', true);
    $faqs = getContent('faq.element', false, null, true);
@endphp
<div class="section section--bg">
    <div class="section__head">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-xl-6">
                    <h2 class="mt-0 text-center">{{ __(@$content->data_values->heading) }}</h2>
                    <p class="section__para mx-auto mb-0 text-center">
                        {{ __(@$content->data_values->subheading) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="row faq-bg gy-3">
            <div class="col-lg-6">
                <div class="accordion custom--accordion" id="faq-one">
                    @foreach ($faqs as $faq)
                        @if ($loop->odd)
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" data-bs-target="#faq-{{ $faq->id }}" data-bs-toggle="collapse" type="button">
                                        {{ __($faq->data_values->question) }}
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="faq-{{ $faq->id }}" data-bs-parent="#faq-one">
                                    <div class="accordion-body">
                                        {{ __($faq->data_values->answer) }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">
                <div class="accordion custom--accordion" id="faq-two">
                    @foreach ($faqs as $faq)
                        @if ($loop->even)
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" data-bs-target="#faq-{{ $faq->id }}" data-bs-toggle="collapse" type="button">
                                        {{ __($faq->data_values->question) }}
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="faq-{{ $faq->id }}" data-bs-parent="#faq-two">
                                    <div class="accordion-body">
                                        {{ __($faq->data_values->answer) }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
