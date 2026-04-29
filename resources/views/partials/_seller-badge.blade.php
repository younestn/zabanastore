@if(!empty($badge))
    @php($sellerBadgeIconClasses = [
        'sparkles' => 'fi fi-rr-sparkles',
        'seedling' => 'fi fi-rr-seedling',
        'badge-check' => 'fi fi-rr-badge-check',
        'shield-check' => 'fi fi-rr-shield-check',
        'crown' => 'fi fi-rr-crown',
    ])
    <span class="seller-badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded border fs-12 fw-semibold text-nowrap"
          style="border-color: {{ $badge['color'] }}33; color: {{ $badge['color'] }}; background-color: {{ $badge['color'] }}14;"
          title="{{ $badge['name'] }}">
        @if(isset($sellerBadgeIconClasses[$badge['icon']]))
            <i class="{{ $sellerBadgeIconClasses[$badge['icon']] }}"></i>
        @else
            <span>{{ $badge['icon'] }}</span>
        @endif
        <span>{{ $badge['name'] }}</span>
    </span>
@elseif(!empty($showEmpty))
    <span>-</span>
@endif
