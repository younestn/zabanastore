@extends('layouts.admin.app')

@section('title', 'Ad Requests Management')

@section('content')
<div class="container-fluid">
    <!-- Notification Alert -->
    <div class="alert alert-warning alert-dismissible fade show d-none" role="alert" id="countdownAlert">
        <strong><i class="fas fa-bell"></i> Ad Expiration Notice</strong>
        <span id="alertMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h3 class="m-0 font-weight-bold text-primary">All Ad Requests</h3>
            <button class="btn btn-outline-primary" id="viewExpiringBtn">
                <i class="fas fa-clock"></i> View Expiring Ads
            </button>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad Image</th>
                            <th>Vendor Name</th>
                            <th>Product</th>
                            <th>Ad Type</th>
                            <th>Duration (Days)</th>
                            <th>Remaining Time</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="adRequestsTableBody">
                        @forelse($adRequests as $request)
                        <tr id="row-{{ $request->id }}">
                            <td>{{ $request->id }}</td>
                            <td>
                                @if($request->image_path)
                                    <img src="{{ asset($request->image_path) }}" 
                                         alt="Ad Image" 
                                         style="max-width: 200px; height: auto; cursor: pointer;"
                                         class="lightbox-trigger"
                                         data-image="{{ asset($request->image_path) }}"
                                         onerror="console.log('Image failed to load:', this.src)">
                                @else
                                    <span class="text-muted">No image available</span>
                                @endif
                            </td>
                            <td>{{ $request->vendor->f_name ?? 'N/A' }} {{ $request->vendor->l_name ?? '' }}</td>
                            <td>{{ $request->product->name ?? 'N/A' }}</td>
                            <td>{{ ucfirst($request->ad_type) }}</td>
                            <td id="duration-{{ $request->id }}">{{ $request->duration_days }}</td>
                            <td>
                                @if($request->status == 'approved' && $request->duration_days > 0)
                                    <span class="countdown countdown-normal" data-expires="{{ $request->created_at->addDays($request->duration_days)->format('Y-m-d H:i:s') }}" data-id="{{ $request->id }}">
                                        {{ $request->duration_days }} days remaining
                                    </span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td id="price-{{ $request->id }}">${{ number_format($request->price, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ 
                                    $request->status == 'pending' ? 'warning' : 
                                    ($request->status == 'approved' ? 'success' : 'danger') 
                                }}">
                                    {{ ucfirst($request->status) }}
                                </span>
                            </td>
                            <td>{{ $request->created_at->format('M j, Y, g:i a') }}</td>
                            <td>
                                @if($request->status == 'pending')
                                <form action="{{ route('admin.ad-requests.update', $request->id) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-success btn-sm mt-1">Approve</button>
                                </form>
                                <form action="{{ route('admin.ad-requests.update', $request->id) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-sm mt-1">Reject</button>
                                </form>
                                @endif
                                
                                <!-- Edit Button -->
                                <button class="btn btn-info btn-sm mt-1 edit-btn" data-id="{{ $request->id }}" data-duration="{{ $request->duration_days }}" data-price="{{ $request->price }}">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <!-- Details Button -->
                                <button class="btn btn-secondary btn-sm mt-1 details-btn" 
                                        data-id="{{ $request->id }}" 
                                        data-notes="{{ $request->notes ?? 'No notes available' }}"
                                        data-vendor="{{ $request->vendor->f_name ?? 'N/A' }} {{ $request->vendor->l_name ?? '' }}"
                                        data-product="{{ $request->product->name ?? 'N/A' }}"
                                        data-type="{{ ucfirst($request->ad_type) }}"
                                        data-status="{{ ucfirst($request->status) }}"
                                        data-date="{{ $request->created_at->format('M j, Y, g:i a') }}">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center">No ad requests found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Simple Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
        <h3>Edit Ad Details</h3>
        <form id="editForm">
            <input type="hidden" id="editId">
            <div class="mb-3">
                <label for="editDuration" class="form-label">Duration (Days)</label>
                <input type="number" class="form-control" id="editDuration" min="1" required>
            </div>
            <div class="mb-3">
                <label for="editPrice" class="form-label">Price ($)</label>
                <input type="number" step="0.01" class="form-control" id="editPrice" min="0" required>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <button type="button" id="cancelEdit" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #2c3e50;">Ad Request Details</h3>
            <button type="button" id="closeDetails" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d;">&times;</button>
        </div>
        
        <div class="details-content">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Ad ID:</strong>
                    <p id="detailsId" class="mb-1"></p>
                </div>
                <div class="col-md-6">
                    <strong>Status:</strong>
                    <p id="detailsStatus" class="mb-1"></p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Vendor:</strong>
                    <p id="detailsVendor" class="mb-1"></p>
                </div>
                <div class="col-md-6">
                    <strong>Product:</strong>
                    <p id="detailsProduct" class="mb-1"></p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Ad Type:</strong>
                    <p id="detailsType" class="mb-1"></p>
                </div>
                <div class="col-md-6">
                    <strong>Submitted On:</strong>
                    <p id="detailsDate" class="mb-1"></p>
                </div>
            </div>
            
            <div class="mb-3">
                <strong>Notes:</strong>
                <div id="detailsNotes" style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin-top: 10px; white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.5;"></div>
            </div>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" id="closeDetailsBtn" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

{{-- Enhanced Lightbox Overlay --}}
<div id="lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; cursor: pointer;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90vw; max-height: 90vh;">
        <img id="lightbox-image" src="" alt="Enlarged view" style="max-width: 100%; max-height: 80vh; border: 3px solid white; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>
    <div style="position: absolute; top: 20px; right: 20px; display: flex; gap: 10px;">
        <button id="close-lightbox" style="background: #ff4757; color: white; border: none; padding: 12px 18px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: all 0.3s;">
            ✕ Close
        </button>
        <a id="download-lightbox" href="#" download style="background: #2ed573; color: white; border: none; padding: 12px 18px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; transition: all 0.3s;">
            ⬇ Download
        </a>
    </div>
    <div style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); color: white; text-align: center;">
        <div id="lightbox-filename" style="font-size: 14px; opacity: 0.8;"></div>
        <div style="font-size: 12px; opacity: 0.6; margin-top: 5px;">Click anywhere to close</div>
    </div>
</div>

@endsection

@push('script')
<script>
// Image error handling function
function handleImageError(img) {
    console.log('🖼️ Image failed to load:', img.src);
    
    if (!img.dataset.tried1) {
        console.log('🔄 Trying fallback URL 1...');
        img.dataset.tried1 = 'true';
        img.src = img.dataset.fallback1;
    } else if (!img.dataset.tried2) {
        console.log('🔄 Trying fallback URL 2...');
        img.dataset.tried2 = 'true';
        img.src = img.dataset.fallback2;
    } else {
        console.log('❌ All image URLs failed');
        img.style.display = 'none';
        img.nextElementSibling.style.display = 'block';
    }
}

// Debug logs to confirm script is loading
console.log('🔄 Ad requests page script loaded successfully!');
console.log('📍 Current page:', window.location.href);
console.log('🛠️ jQuery available:', typeof $ !== 'undefined');

// Test button click detection
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('edit-btn')) {
        console.log('✅ Edit button clicked detected!', e.target);
    }
    if (e.target.classList.contains('details-btn')) {
        console.log('✅ Details button clicked detected!', e.target);
    }
});

// Simple edit functionality
function setupEditButtons() {
    console.log('🔧 Setting up edit buttons...');
    
    // Get all edit buttons
    const editButtons = document.querySelectorAll('.edit-btn');
    console.log('📋 Found', editButtons.length, 'edit buttons');
    
    // Add click event to each button
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('🖱️ Edit button clicked!');
            
            const id = this.getAttribute('data-id');
            const duration = this.getAttribute('data-duration');
            const price = this.getAttribute('data-price');
            
            console.log('📝 Editing ad:', {id, duration, price});
            
            // Show the modal
            document.getElementById('editId').value = id;
            document.getElementById('editDuration').value = duration;
            document.getElementById('editPrice').value = price;
            document.getElementById('editModal').style.display = 'flex';
        });
    });
    
    // Close modal event
    const cancelBtn = document.getElementById('cancelEdit');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            console.log('❌ Modal cancelled');
            document.getElementById('editModal').style.display = 'none';
        });
    }
    
    // Form submission
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const id = document.getElementById('editId').value;
            const duration = document.getElementById('editDuration').value;
            const price = document.getElementById('editPrice').value;
            
            console.log('💾 Submitting form:', {id, duration, price});
            
            // Create form data
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('_method', 'PUT');
            formData.append('duration_days', duration);
            formData.append('price', price);
            
            // Send request
            fetch('/admin/ad-requests/' + id, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📡 Response received:', response);
                if (response.redirected) {
                    window.location.href = response.url;
                } else if (response.ok) {
                    // Update the table
                    document.getElementById('duration-' + id).textContent = duration;
                    document.getElementById('price-' + id).textContent = '$' + parseFloat(price).toFixed(2);
                    
                    // Update button data attributes
                    const button = document.querySelector('.edit-btn[data-id="' + id + '"]');
                    button.setAttribute('data-duration', duration);
                    button.setAttribute('data-price', price);
                    
                    // Close modal
                    document.getElementById('editModal').style.display = 'none';
                    
                    alert('✅ Ad updated successfully!');
                } else {
                    throw new Error('Failed to update');
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                alert('Error updating ad. Please try again.');
            });
        });
    }
}

// Details modal functionality
function setupDetailsButtons() {
    console.log('🔧 Setting up details buttons...');
    
    // Get all details buttons
    const detailsButtons = document.querySelectorAll('.details-btn');
    console.log('📋 Found', detailsButtons.length, 'details buttons');
    
    // Add click event to each button
    detailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('🖱️ Details button clicked!');
            
            const id = this.getAttribute('data-id');
            const notes = this.getAttribute('data-notes');
            const vendor = this.getAttribute('data-vendor');
            const product = this.getAttribute('data-product');
            const type = this.getAttribute('data-type');
            const status = this.getAttribute('data-status');
            const date = this.getAttribute('data-date');
            
            console.log('📝 Showing details for ad:', {id, notes, vendor, product, type, status, date});
            
            // Populate modal with data
            document.getElementById('detailsId').textContent = '#' + id;
            document.getElementById('detailsStatus').textContent = status;
            document.getElementById('detailsVendor').textContent = vendor;
            document.getElementById('detailsProduct').textContent = product;
            document.getElementById('detailsType').textContent = type;
            document.getElementById('detailsDate').textContent = date;
            document.getElementById('detailsNotes').textContent = notes || 'No notes available';
            
            // Show the modal
            document.getElementById('detailsModal').style.display = 'flex';
        });
    });
    
    // Close modal events
    const closeDetailsBtn = document.getElementById('closeDetails');
    const closeDetailsBtnBottom = document.getElementById('closeDetailsBtn');
    
    function closeDetailsModal() {
        console.log('❌ Details modal closed');
        document.getElementById('detailsModal').style.display = 'none';
    }
    
    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', closeDetailsModal);
    }
    
    if (closeDetailsBtnBottom) {
        closeDetailsBtnBottom.addEventListener('click', closeDetailsModal);
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
}

// Enhanced Lightbox functionality
function setupLightbox() {
    console.log('🖼️ Setting up lightbox...');
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const closeButton = document.getElementById('close-lightbox');
    const downloadLink = document.getElementById('download-lightbox');
    const filenameDisplay = document.getElementById('lightbox-filename');

    // Add click event to all ad images
    const images = document.querySelectorAll('td img[src*="ad_images"]');
    console.log('🖼️ Found', images.length, 'ad images for lightbox');
    
    images.forEach(image => {
        // Add lightbox trigger class and cursor pointer
        image.classList.add('lightbox-trigger');
        image.style.cursor = 'pointer';
        
        image.addEventListener('click', function() {
            const imageUrl = this.src;
            console.log('🖱️ Opening lightbox for:', imageUrl);
            
            lightboxImage.src = imageUrl;
            downloadLink.href = imageUrl;
            
            // Extract filename from URL for display
            const filename = imageUrl.split('/').pop();
            filenameDisplay.textContent = filename;
            
            // Set download filename
            downloadLink.download = 'ad-image-' + filename;
            
            lightbox.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Close lightbox events
    function closeLightbox() {
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    if (closeButton) {
        closeButton.addEventListener('click', closeLightbox);
    }
    
    // Close when clicking on background
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });
    
    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox.style.display === 'block') {
            closeLightbox();
        }
    });
    
    console.log('✅ Lightbox setup complete');
}

// Countdown functionality
function setupCountdowns() {
    console.log('⏰ Setting up countdowns...');
    const countdownElements = document.querySelectorAll('.countdown');
    const countdownAlert = document.getElementById('countdownAlert');
    const alertMessage = document.getElementById('alertMessage');
    const expiringAds = [];
    
    console.log('⏰ Found', countdownElements.length, 'countdown elements');
    
    function updateCountdowns() {
        countdownElements.forEach(element => {
            const expires = new Date(element.getAttribute('data-expires'));
            const now = new Date();
            const diff = expires - now;
            
            if (diff <= 0) {
                element.innerHTML = 'Expired';
                element.className = 'countdown countdown-expiring';
                
                const adId = element.getAttribute('data-id');
                if (!expiringAds.includes(adId)) {
                    expiringAds.push(adId);
                    showNotification(adId);
                }
            } else {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                element.innerHTML = `${days}d ${hours}h ${minutes}m remaining`;
                
                if (days < 1) {
                    element.className = 'countdown countdown-expiring';
                    const adId = element.getAttribute('data-id');
                    if (!expiringAds.includes(adId)) {
                        expiringAds.push(adId);
                        showNotification(adId);
                    }
                }
            }
        });
    }
    
    function showNotification(adId) {
        if (alertMessage) {
            alertMessage.textContent = `Ad #${adId} is about to expire or has expired.`;
            countdownAlert.classList.remove('d-none');
            
            setTimeout(() => {
                countdownAlert.classList.add('d-none');
            }, 5000);
        }
    }
    
    updateCountdowns();
    setInterval(updateCountdowns, 60000);
    
    const viewExpiringBtn = document.getElementById('viewExpiringBtn');
    if (viewExpiringBtn) {
        viewExpiringBtn.addEventListener('click', function() {
            if (expiringAds.length > 0) {
                alert(`Expiring ads: ${expiringAds.join(', ')}`);
            } else {
                alert('No ads are currently expiring.');
            }
        });
    }
}

// Initialize everything when DOM is loaded
function initializeAll() {
    console.log('🚀 Initializing all functions...');
    setupEditButtons();
    setupDetailsButtons();
    setupLightbox();
    setupCountdowns();
    console.log('✅ All functions initialized!');
}

// Multiple initialization methods to ensure it runs
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 DOM fully loaded');
    initializeAll();
});

// Fallback initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAll);
} else {
    console.log('🔄 DOM already loaded, initializing immediately');
    initializeAll();
}

// Additional fallback
setTimeout(function() {
    console.log('🕐 Timeout fallback initialization');
    initializeAll();
}, 500);
</script>

<style>
.countdown {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 4px;
    display: inline-block;
}

.countdown-expiring {
    background-color: #ffcccc;
    color: #d63031;
    animation: pulse 1.5s infinite;
}

.countdown-normal {
    background-color: #e8f4fd;
    color: #0984e3;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.edit-btn, .details-btn {
    display: block;
    width: 100%;
    margin-top: 5px;
}

.details-content .row {
    margin-bottom: 10px;
}

.details-content p {
    color: #495057;
    font-size: 14px;
}

.details-content strong {
    color: #2c3e50;
    font-size: 13px;
    text-transform: uppercase;
    font-weight: 600;
}

#detailsNotes {
    min-height: 50px;
    max-height: 200px;
    overflow-y: auto;
}

/* Lightbox image hover effects */
td img.lightbox-trigger {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

td img.lightbox-trigger:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

/* Lightbox button hover effects */
#close-lightbox:hover, #download-lightbox:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
</style>
@endpush