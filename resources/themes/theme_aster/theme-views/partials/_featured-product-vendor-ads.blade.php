@foreach(($featuredProductAds ?? collect()) as $ad)
    @php
        $visitUrl = $ad['visit_url'] ?? route('web.ad-requests.visit', $ad->id);
    @endphp
    <div
        class="product border rounded text-center d-flex flex-column gap-10 ov-hidden cursor-pointer"
        onclick="window.location.href='{{ $visitUrl }}'"
        data-impression-url="{{ route('api.v1.ad-requests.impression', $ad->id) }}"
    >
        <div class="product__top width--100 aspect-1 position-relative">
            <span class="badge bg-success position-absolute m-2" style="top: 0; z-index: 1; {{ Session::get('direction') === 'rtl' ? 'right' : 'left' }}: 0;">
                {{ translate('sponsored') }}
            </span>
            <div class="product__thumbnail align-items-center d-flex h-100 justify-content-center">
                <img
                    class="dark-support rounded"
                    alt="{{ $ad->title }}"
                    src="{{ $ad->image_full_url['path'] ?? $ad->image_url }}"
                    style="width: 100%; height: 100%; object-fit: cover;"
                >
            </div>
        </div>
        <div class="product__summary d-flex flex-column align-items-center gap-1 pb-3">
            <h6 class="product__title text-truncate">
                <a href="{{ $visitUrl }}" class="text-reset text-decoration-none stretched-link position-static">
                    {{ \Illuminate\Support\Str::limit($ad->title, 25) }}
                </a>
            </h6>
            <div class="text-muted fs-12 px-2">
                {{ translate('featured_products_ad') }}
            </div>
        </div>
    </div>
@endforeach
