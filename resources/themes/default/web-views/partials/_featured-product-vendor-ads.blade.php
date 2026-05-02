@foreach(($featuredProductAds ?? collect()) as $ad)
    @php
        $visitUrl = $ad['visit_url'] ?? route('web.ad-requests.visit', $ad->id);
    @endphp
    <div>
        <div class="product-single-hover shadow-none rtl h-100">
            <div class="overflow-hidden position-relative h-100">
                <div class="inline_product clickable">
                    <span class="for-discount-value-null"></span>
                    <a href="{{ $visitUrl }}">
                        <img
                            loading="lazy"
                            src="{{ $ad->image_full_url['path'] ?? $ad->image_url }}"
                            alt="{{ $ad->title }}"
                            class="w-100"
                            style="aspect-ratio: 1 / 1; object-fit: cover;"
                        >
                    </a>

                    <span class="badge badge-success position-absolute m-2" style="top: 0; {{ Session::get('direction') === 'rtl' ? 'right' : 'left' }}: 0;">
                        {{ translate('ad') }}
                    </span>
                </div>

                <div class="single-product-details">
                    <h3 class="mb-1 letter-spacing-0">
                        <a href="{{ $visitUrl }}" class="text-capitalize fw-semibold line--limit-2 d-block">
                            {{ $ad->title }}
                        </a>
                    </h3>
                    <div class="small text-muted">
                        {{ translate('featured_products_ad') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
