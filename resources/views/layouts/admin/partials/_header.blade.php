@php
    $last5Orders = \App\Models\Order::where('order_status', 'pending')
        ->with('details.productAllStatus')
        ->orderBy('id', 'desc')
        ->take(5)
        ->get();
@endphp

<header class="header fixed-top navbar-fixed shadow-sm bg-white">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="">
            <button type="button" class="d-none d-lg-block btn-icon border-0">
                <i class="fi fi-rr-menu-burger" data-bs-toggle="tooltip" data-bs-title="Expand"></i>
            </button>
            <button type="button" class="d-lg-none p-0 bg-transparent border-0" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasAside">
                <i class="fi fi-rr-menu-burger"></i>
            </button>
        </div>

        <div class="navbar-nav-wrap-content-right">
            <ul class="navbar-nav align-items-center flex-row gap-3">

                <li class="nav-item">
                    <button type="button" id="search-btn"
                        class="btn border bg-section2 d-flex gap-1 max-w-200 px-2 px-3 px-md-3 py-1 rounded w-100"
                        data-bs-toggle="modal">
                        <span class="align-items-center d-flex flex-grow-1 gap-3 justify-content-between">
                            {{-- <span id="search-input" class="align-items-center d-none d-xxl-flex gap-2 text-muted">
                                <span class="search-shortcut-key bg-soft-secondary border d-md-block d-none font-bold fs-12 fw-bold p-1 rounded text-muted"></span>
                            </span> --}}
                            <span id="search-input"
                                class="align-items-center d-none d-xxl-flex gap-2 text-muted fs-12 fw-normal">
                                <span class="search-placeholder">{{ __('Search or') }}</span>
                                <span class="search-shortcut-key-wrapper">
                                    <span class="search-shortcut-key"></span>
                                </span>
                            </span>
                            <span class="lh-1 me-lg-n1">
                                <i class="fi fi-rr-search"></i>
                            </span>
                        </span>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="btn-icon" href="{{ route('home') }}" target="_blank" data-bs-toggle="tooltip"
                        data-bs-title="{{ translate('Website') }}">
                        <i class="fi fi-rr-globe fs-18"></i>
                    </a>
                </li>
<!-- Language Dropdown - Fixed Version -->
<li class="nav-item">
    @php($local = session()->has('local') ? session('local') : 'en')
    @php($lang = \App\Models\BusinessSetting::where('type', 'language')->first())
    <div class="dropdown">
        <button class="btn-icon topbar-link border-0 bg-transparent dropdown-toggle" 
                type="button" 
                id="languageDropdown"
                data-bs-toggle="dropdown" 
                data-bs-auto-close="true"
                aria-expanded="false">
            @foreach (json_decode($lang['value'], true) as $data)
                @if ($data['code'] == $local)
                    <img width="20"
                        src="{{ dynamicAsset(path: 'public/assets/front-end/img/flags/' . $data['code'] . '.png') }}"
                        alt="{{ $data['name'] }}">
                @endif
            @endforeach
        </button>
        
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
            @foreach (json_decode($lang['value'], true) as $key => $data)
                @if ($data['status'] == 1)
                    <li class="change-language" 
                        data-action="{{ route('change-language') }}"
                        data-language-code="{{ $data['code'] }}">
                        <a class="dropdown-item d-flex gap-2 align-items-center justify-content-between {{ $data['code'] == $local ? 'active' : '' }}"
                           href="javascript:void(0)">
                            <div class="d-flex gap-2 align-items-center">
                                <img width="20"
                                    src="{{ dynamicAsset(path: 'public/assets/front-end/img/flags/' . $data['code'] . '.png') }}"
                                    alt="{{ $data['name'] }}" />
                                <span class="text-capitalize">{{ $data['name'] }}</span>
                            </div>
                            {!! $data['code'] == $local ? '<i class="fi fi-rr-check"></i>' : '' !!}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</li>
            @if (\App\Utils\Helpers::module_permission_check('order_management'))
    <li class="nav-item">
        <a class="btn-icon" href="{{ route('admin.orders.list', ['status' => 'pending']) }}">
            @php($pendingOrderCount = \App\Models\Order::where('order_status', 'pending')->count())
            <div class="position-relative">
                <i class="fi fi-sr-shopping-cart fs-18"></i>
                @if ($pendingOrderCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $pendingOrderCount > 99 ? '99+' : $pendingOrderCount }}
                        <span class="visually-hidden">{{ translate('pending_Orders') }}</span>
                    </span>
                @endif
            </div>
        </a>
    </li>
@endif

                @if (\App\Utils\Helpers::module_permission_check('support_section'))
                    <li class="nav-item">
                        <a class="btn-icon position-relative" href="{{ route('admin.contact.list') }}"
                            data-bs-title="{{ translate('message') }}" data-bs-toggle="tooltip">
                            <i class="fi fi-sr-comment-alt-dots fs-18"></i>

                            @php($message = \App\Models\Contact::where('seen', 0)->count())
                            @if ($message > 0)
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ $message > 99 ? '99+' : $message }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endif

             <li class="nav-item">
    <div class="dropdown">
        <!-- Fixed: Use button for better dropdown compatibility -->
        <button class="btn p-0 border-0 bg-transparent" 
                type="button" 
                data-bs-toggle="dropdown" 
                aria-expanded="false">
            <img class="rounded-circle border border-2 min-w-36 aspect-1" width="36"
                 src="{{ getStorageImages(path: auth('admin')->user()->image_full_url, type: 'backend-profile') }}"
                 alt="{{ translate('image_description') }}">
        </button>
        
        <div class="dropdown-menu dropdown-menu-end shadow-lg">
            <div class="px-3 py-2">
                <div class="d-flex gap-2 align-items-center">
                    <img class="rounded-circle border border-2 aspect-1" width="40"
                         src="{{ getStorageImages(path: auth('admin')->user()->image_full_url, type: 'backend-profile') }}"
                         alt="{{ translate('image_description') }}">

                    <div class="overflow-hidden w-100">
                        <h4 class="fw-bold mb-1 text-truncate" style="max-width: 200px;">
                            {{ auth('admin')->user()->name }}
                        </h4>
                        <p class="fs-12 text-body-light fw-medium text-truncate" style="max-width: 200px;">
                            {{ ucwords(auth('admin')->user()?->role?->name) ?? '' }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <a class="dropdown-item d-flex gap-2 align-items-center"
               href="{{ route('admin.profile.update', ['id' => auth('admin')->user()->id]) }}">
                <i class="fi fi-rr-settings text-body-light"></i>
                <span class="text-truncate" style="max-width: 200px;">
                    {{ translate('settings') }}
                </span>
            </a>
            
            <a class="dropdown-item d-flex gap-2 align-items-center" href="javascript:"
               data-bs-toggle="modal" data-bs-target="#sign-out-modal">
                <i class="fi fi-sr-sign-out-alt text-body-light"></i>
                <span class="text-truncate" style="max-width: 200px;">
                    {{ translate('logout') }}
                </span>
            </a>
        </div>
    </div>
</li>
            </ul>
        </div>
    </div>
</header>


@push('script')
    <script>
        let currentIndex = -1;

        function getItems() {
            return $('.search-list .search-item-wrapper');
        }

        function updateHighlight(index) {
            const items = getItems();
            items.removeClass('active-item');

            if (index >= 0 && index < items.length) {
                const currentItem = $(items[index]);
                currentItem.addClass('active-item');

                const link = currentItem.find('.search-list-item')[0];

                if (link) {
                    const container = document.querySelector('#searchResults');
                    const itemRect = link.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();

                    if (itemRect.top < containerRect.top || itemRect.bottom > containerRect.bottom) {
                        link.scrollIntoView({
                            behavior: 'auto',
                            block: 'nearest'
                        });
                    }
                }
            }
        }

        $(document).ready(function() {

            $('#search-btn').on('click', function() {
                $('#advanceSearchModal').modal('show');
                $('#advanceSearchModal').on('shown.bs.modal', function() {
                    $('#advance-search-input-global').focus();
                });
            });

            const platform = navigator.platform;
            let shortcutText = '';
            let isMac = false;

            if (platform.toLowerCase().includes('mac')) {
                shortcutText = 'Cmd+K';
                isMac = true;
            } else if (platform.toLowerCase().includes('linux') || platform.toLowerCase().includes('win')) {
                shortcutText = 'Ctrl+K';
                isMac = false;
            } else {
                shortcutText = 'Ctrl+K';
                isMac = false;
            }

            const currentPlaceholder = "{{ translate('Search_or') }}";
            $('#search-input').html(
                `${currentPlaceholder} <span class="search-shortcut-key-wrapper">${shortcutText}</span>`);

            $(document).keydown(function(event) {
                if ((event.ctrlKey && !isMac) || (event.metaKey && isMac)) {
                    if (event.key === 'k' || event.key === 'K') {
                        event.preventDefault();

                        $('#advanceSearchModal').modal('show');
                        $('#advanceSearchModal').on('shown.bs.modal', function() {
                            $('#advance-search-input-global').focus();
                        });
                    }
                }
                if (event.key === 'Escape') {
                    if ($('#advanceSearchModal').hasClass('show')) {
                        $('#advanceSearchModal').modal('hide');
                    }
                }
            });

            $('#advanceSearchModal').on('hidden.bs.modal', function() {
                $('#advanceSearchModal').off('shown.bs.modal');
            });


            let currentRequest = null;
            let debounceTimer = null;

            $('#advance-search-input-global').on('input', function() {
                const searchKeyword = $(this).val().trim();

                clearTimeout(debounceTimer);


                if (searchKeyword === '') {
                    if (currentRequest && currentRequest.readyState !== 4) {
                        currentRequest.abort();
                    }
                    toggleSearchLoader('show');
                    $.ajax({
                        type: 'GET',
                        url: '{{ route('admin.advanced-search') }}',
                        success: function(response) {
                            $('#searchResults').empty().html(response.htmlView);
                            toggleSearchLoader('hide');
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                        }
                    });

                    return;
                }

                debounceTimer = setTimeout(function() {
                    if (currentRequest && currentRequest.readyState !== 4) {
                        currentRequest.abort();
                    }

                    toggleSearchLoader('show');

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    currentRequest = $.ajax({
                        type: 'GET',
                        url: '{{ route('admin.advanced-search') }}',
                        data: {
                            keyword: searchKeyword,

                        },
                        success: function(response) {
                            $('#searchResults').empty().html(response.htmlView);

                            if (currentIndex === -1) {
                                currentIndex = 0;
                            }
                            updateHighlight(currentIndex);
                            toggleSearchLoader('hide');
                        },
                        error: function(xhr, status, error) {
                            if (status !== 'abort') {
                                console.error('Search error:', error);
                            }
                            toggleSearchLoader('hide');
                        }
                    });

                }, 300);
            });
            $('#advance-search-input-global').on('focus', function() {
                const searchKeyword = $(this).val().trim();

                if (searchKeyword.length > 0) {
                    if (currentRequest && currentRequest.readyState !== 4) {
                        currentRequest.abort();
                    }
                    toggleSearchLoader('show');
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    currentRequest = $.ajax({
                        type: 'GET',
                        url: '{{ route('admin.advanced-search') }}',
                        data: {
                            keyword: searchKeyword
                        },
                        success: function(response) {
                            $('#searchResults').empty().html(response.htmlView);

                            if (currentIndex === -1) {
                                currentIndex = 0;
                            }
                            toggleSearchLoader('hide');
                            updateHighlight(currentIndex);
                        },
                        error: function(xhr, status, error) {
                            if (status !== 'abort') {
                                console.error('Search error:', error);
                            }
                        }
                    });
                } else {
                    $.ajax({
                        type: 'GET',
                        url: '{{ route('admin.advanced-search') }}',
                        success: function(response) {
                            $('#searchResults').empty().html(response.htmlView);
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                             toggleSearchLoader('hide');
                        }
                    });
                }
            });

        });

        function toggleSearchLoader(type) {
            const loader = $('#searchLoaderOverlay');
            if (type === 'show') {
                loader.removeClass('d-none');
            } else if (type === 'hide') {
                loader.addClass('d-none');
            }
        }


        $(document).on('keydown', function(e) {
            if (!$('#advanceSearchModal').hasClass('show')) return;

            const items = getItems();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (currentIndex < items.length - 1) {
                    currentIndex++;
                    updateHighlight(currentIndex);
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (currentIndex > 0) {
                    currentIndex--;
                    updateHighlight(currentIndex);
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentIndex >= 0 && currentIndex < items.length) {
                    const target = $(items[currentIndex]).find('.search-list-item')[0];
                    if (target) target.click();
                }
            }
        });

        $('#advanceSearchModal').on('shown.bs.modal', function() {
            currentIndex = -1;
            const input = $('#advance-search-input-global');
            const keyword = input.val().trim();
            input.focus();
            if (keyword.length > 0) {
                input.trigger('focus');
            }
        });

        $('#advanceSearchModal').on('hidden.bs.modal', function() {
            currentIndex = -1;
        });

        $('#advance-search-input-global').on('input focus', function() {
            currentIndex = -1;
        });
        // Language change function - Enhanced with better error handling
function changeLanguage(languageCode) {
    // Show loading state
    const button = document.getElementById('languageDropdown');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fi fi-rr-spinner-third fa-spin"></i>';
    button.disabled = true;
    
    $.ajax({
        type: 'POST',
        url: '{{ route("change-language") }}',
        data: {
            'local': languageCode,
            '_token': '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                // Restore button state on failure
                button.innerHTML = originalContent;
                button.disabled = false;
                console.error('Language change failed:', response.message || 'Unknown error');
            }
        },
        error: function(xhr, status, error) {
            // Restore button state on error
            button.innerHTML = originalContent;
            button.disabled = false;
            console.error('Language change failed:', error);
            
            // Fallback: try to reload anyway
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    });
}

$(document).ready(function() {
    // Enhanced Bootstrap dropdown initialization
    const dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    const dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl, {
            autoClose: true,
            boundary: 'viewport'
        });
    });
    
    // Debug: Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JavaScript is not loaded!');
    }
    
    // Debug: Log dropdown initialization
    console.log('Dropdowns initialized:', dropdownList.length);
    
    // Ensure dropdowns work on mobile
    $('.dropdown-toggle').on('click', function(e) {
        const dropdown = bootstrap.Dropdown.getInstance(this) || new bootstrap.Dropdown(this);
        if (!$(this).next('.dropdown-menu').hasClass('show')) {
            dropdown.show();
        }
    });
    
    // Prevent dropdown menu from closing when clicking inside (except on links)
    $('.dropdown-menu').on('click', function(e) {
        if (!$(e.target).closest('a').length) {
            e.stopPropagation();
        }
    });
});
    </script>
@endpush

<style>
@media (max-width: 768px) {
    .dropdown-menu {
        position: absolute !important;
        transform: none !important;
        left: auto !important;
        right: 0 !important;
    }
}

.dropdown-toggle::after {
    display: none; /* Hide default Bootstrap arrow if you don't want it */
}

.dropdown-item.active {
    background-color: var(--bs-primary);
    color: white;
}
</style>
