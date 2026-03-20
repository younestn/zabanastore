



@extends('layouts.vendor.app')



@section('title', 'New Ad Request')



@section('content')

    <div class="container">

        <div class="row">

            <div class="col-12">

                <div class="card">

                    <div class="card-header">

                        <h4 class="card-title">New Ad Request</h4>

                        <p class="card-subtitle">Fill out the form below to create a new ad request</p>

                    </div>

                    <div class="card-body">

                        <form id="adRequestForm" action="{{ route('vendor.ad-request.store') }}" method="POST" enctype="multipart/form-data">

                            @csrf

                            <!-- Add these new required fields -->

                            <div class="row">

                                <div class="col-md-6 mb-4">

                                    <label for="adTitle" class="form-label">Ad Title</label>

                                    <div class="input-group-store">

                                        <i class="input-icon fas fa-heading"></i>

                                        <input type="text" class="form-control input-enhanced" id="adTitle" name="title" required>

                                    </div>

                                </div>

                                

                       

                            </div>



                            <!-- Description field -->

                            <div class="mb-4">

                                <label for="adDescription" class="form-label">Ad Description</label>

                                <div class="textarea-wrapper">

                                    <i class="textarea-icon fas fa-align-left"></i>

                                    <textarea class="form-control input-enhanced" id="adDescription" name="description" rows="4" required placeholder="Describe your ad campaign"></textarea>

                                </div>

                            </div>

                            

                            <div class="row">

                                <!-- Store Name (Auto-displayed) -->

                                <div class="col-md-6 mb-4">

                                    <label for="storeName" class="form-label">Store Name</label>

                                    <div class="input-group-store">

                                        <i class="input-icon fas fa-store"></i>

                                        <input type="text" 

                                               class="form-control input-enhanced"

                                               id="storeName"

                                               value="{{ auth('seller')->user()->shop->name ?? '' }}"

                                               readonly>

                                    </div>

                                    <div class="form-text">Store name is displayed automatically and cannot be changed</div>

                                </div>

                                

                                <!-- Product Selection -->

                                <div class="col-md-6 mb-4">

                                    <label for="productSelect" class="form-label">Product</label>

                                    <div class="custom-select-wrapper">

                                        <i class="select-icon fas fa-box"></i>

                                        <select class="form-select custom-select" id="productSelect" name="product_id" required>

                                            <option value="" selected disabled>Select product to advertise</option>

                                            @forelse($products as $product)

                                                <option value="{{ $product['id'] }}">

                                                    {{ $product['name'] }} 

                                                    @if($product['status'] == 0)

                                                        (Inactive)

                                                    @endif

                                                </option>

                                            @empty

                                                <option value="" disabled>No products found</option>

                                            @endforelse

                                        </select>

                                        <i class="select-arrow fas fa-chevron-down"></i>

                                    </div>

                                </div>

                            </div>

                            

                            <div class="row">

                                <!-- Ad Type -->

                                <div class="col-md-6 mb-4">

                                    <label for="adType" class="form-label">Ad Type</label>

                                    <div class="custom-select-wrapper">

                                        <i class="select-icon fas fa-ad"></i>

                                        <select class="form-select custom-select" id="adType" name="ad_type" required>

                                            <option value="" selected disabled>Select ad type</option>

                                            <option value="banner" data-icon="🖼️">Banner Ad (Homepage)</option>

                                            <option value="sidebar" data-icon="📋">Sidebar Ad</option>

                                            <option value="product" data-icon="📦">Product Page Ad</option>

                                            <option value="popup" data-icon="🔔">Popup Ad</option>

                                            <option value="email" data-icon="✉️">Email Ad</option>

                                        </select>

                                        <i class="select-arrow fas fa-chevron-down"></i>

                                    </div>

                                </div>

                                

                                <!-- Duration -->

                                <div class="col-md-6 mb-4">

                                    <label for="adDuration" class="form-label">Ad Duration</label>

                                    <div class="custom-select-wrapper">

                                        <i class="select-icon fas fa-calendar-alt"></i>

                                        <select class="form-select custom-select" id="adDuration" name="duration" required>

                                            <option value="" selected disabled>Select ad duration</option>

                                            <option value="7">1 Week</option>

                                            <option value="14">2 Weeks</option>

                                            <option value="30">1 Month</option>

                                            <option value="60">2 Months</option>

                                            <option value="90">3 Months</option>

                                        </select>

                                        <i class="select-arrow fas fa-chevron-down"></i>

                                    </div>

                                </div>

                            </div>

                            

                            <div class="row">

                                <!-- Price (Auto-calculated) -->

                              <!-- Price (Auto-calculated) -->
<div class="col-md-6 mb-4">
    <label for="adPrice" class="form-label">Price</label>
    <div class="input-group-price">
        @php($currentLang = session()->has('local') ? session('local') : 'en')
        
        @if($currentLang == 'ar')
            <!-- RTL Layout: DZD on left, icon on right -->
            <span class="input-currency">DZD</span>
            <input type="text" class="form-control input-enhanced" id="adPrice" value="0" readonly>
            <i class="input-icon fas fa-dollar-sign"></i>
        @else
            <!-- LTR Layout: Icon on left, DZD on right -->
            <i class="input-icon fas fa-dollar-sign"></i>
            <input type="text" class="form-control input-enhanced" id="adPrice" value="0" readonly>
            <span class="input-currency">DZD</span>
        @endif
    </div>
    <div class="form-text">Price is calculated automatically based on ad type and duration</div>
</div>

                                

                                <!-- Image Upload -->

                                <div class="col-md-6 mb-4">

                                    <label for="adImage" class="form-label">Upload Ad Image</label>

                                     <div class="file-upload-wrapper">

                                        <div class="file-upload-wrapper" style="direction: rtl; text-align: right;">

    <input type="file" class="form-control file-upload" id="adImage" name="ad_image" accept="image/*" required>



</div>

                                        <label for="adImage" class="file-upload-label">

                                            <i class="fas fa-cloud-upload-alt"></i>

                                            <span>Choose image file</span>

                                        </label>

                                    </div>

                                    <div class="form-text">Max file size: 5MB. Allowed formats: JPG, PNG</div>

                                </div>

                            </div>

                            

                            <!-- Notes -->

                            <div class="mb-4">

                                <label for="adNotes" class="form-label">Additional Notes</label>

                                <div class="textarea-wrapper">

                                    <i class="textarea-icon fas fa-sticky-note"></i>

                                    <textarea class="form-control input-enhanced" id="adNotes" name="notes" rows="4" placeholder="Add any notes or special instructions for your ad here"></textarea>

                                </div>

                            </div>

                            

                            <!-- Form Actions -->

                            <div class="d-flex justify-content-end gap-3 mt-4">

                                <button type="reset" class="btn btn-secondary btn-custom">

                                    <i class="fas fa-times me-2"></i>Cancel

                                </button>

                                <button type="submit" class="btn btn-primary btn-custom">

                                    <i class="fas fa-paper-plane me-2"></i>Submit Ad Request

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <style>

        /* Custom toast styles as fallback */

        .custom-toast {

            position: fixed;

            top: 20px;

            right: 20px;

            padding: 15px 20px;

            border-radius: 8px;

            color: white;

            z-index: 9999;

            max-width: 300px;

            animation: slideIn 0.3s ease;

            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);

            font-weight: 500;

        }

        

        .custom-toast.success {

            background: #28a745;

            border-left: 4px solid #1e7e34;

        }

        

        .custom-toast.error {

            background: #dc3545;

            border-left: 4px solid #bd2130;

        }

        

        @keyframes slideIn {

            from { transform: translateX(100%); opacity: 0; }

            to { transform: translateX(0); opacity: 1; }

        }

        

        @keyframes slideOut {

            from { transform: translateX(0); opacity: 1; }

            to { transform: translateX(100%); opacity: 0; }

        }

        

        .card {

            border: none;

            border-radius: 12px;

            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);

            margin-top: 20px;

            overflow: hidden;

        }

        

        .card-header {

            background: linear-gradient(120deg, #4b6cb7 0%, #182848 100%);

            color: white;

            padding: 25px 30px;

        }

        

        .card-title {

            font-size: 17px;

            font-weight: 700;

            margin-bottom: 0.5rem;

        }

        

        .card-subtitle {

            opacity: 0.9;

            font-size: 16px;

        }

        

        .card-body {

            padding: 30px;

        }

        

        .form-label {

            font-weight: 600;

            margin-bottom: 0.75rem;

            color: #2d3748;

            font-size: 0.95rem;

        }

        

        /* Enhanced input styling */

        .input-enhanced {

            border: 2px solid #e2e8f0;

            border-radius: 8px;

            padding: 12px 15px 12px 45px;

            font-size: 1rem;

            transition: all 0.3s ease;

            height: auto;

        }

        

        .input-enhanced:focus {

            border-color: #4b6cb7;

            box-shadow: 0 0 0 3px rgba(75, 108, 183, 0.2);

        }

        

        .input-group-store, .input-group-price, .textarea-wrapper {

            position: relative;

        }

        

        .input-icon, .textarea-icon {

            position: absolute;

            left: 15px;

            top: 50%;

            transform: translateY(-50%);

            color: #a0aec0;

            z-index: 5;

        }

        

        .textarea-icon {

            top: 22px;

            transform: none;

        }

        

        .input-currency {

            position: absolute;

            right: 15px;

            top: 50%;

            transform: translateY(-50%);

            background: #f7fafc;

            padding: 0 8px;

            color: #4a5568;

            font-weight: 600;

            border-left: 1px solid #e2e8f0;

            height: 70%;

            display: flex;

            align-items: center;

        }

        

        /* Custom select styling */

        .custom-select-wrapper {

            position: relative;

        }

        

        .custom-select {

            border: 2px solid #e2e8f0;

            border-radius: 8px;

            padding: 12px 45px 12px 45px;

            font-size: 1rem;

            cursor: pointer;

            appearance: none;

            background-image: none;

            height: auto;

            transition: all 0.3s ease;

        }

        

        .custom-select:focus {

            border-color: #4b6cb7;

            box-shadow: 0 0 0 3px rgba(75, 108, 183, 0.2);

        }

        

        .select-icon {

            position: absolute;

            left: 15px;

            top: 50%;

            transform: translateY(-50%);

            color: #a0aec0;

            z-index: 5;

        }

        

        .select-arrow {

            position: absolute;

            right: 15px;

            top: 50%;

            transform: translateY(-50%);

            color: #a0aec0;

            pointer-events: none;

        }

        

        /* File upload styling */

        .file-upload-wrapper {

            position: relative;

        }

        

        .file-upload {

            position: absolute;

            left: -9999px;

        }

        

        .file-upload-label {

            display: block;

            border: 2px dashed #cbd5e0;

            border-radius: 8px;

            padding: 20px;

            text-align: center;

            cursor: pointer;

            transition: all 0.3s ease;

            background-color: #f8fafc;

        }

        

        .file-upload-label:hover {

            border-color: #4b6cb7;

            background-color: #f0f5ff;

        }

        

        .file-upload-label i {

            display: block;

            font-size: 2rem;

            color: #4b6cb7;

            margin-bottom: 10px;

        }

        

        .file-upload-label span {

            color: #4a5568;

            font-weight: 500;

        }

        

        /* Button styling */

        .btn-custom {

            border-radius: 8px;

            padding: 12px 25px;

            font-weight: 600;

            transition: all 0.3s ease;

        }

        

        .btn-primary {

            background: linear-gradient(120deg, #4b6cb7 0%, #182848 100%);

            border: none;

        }

        

        .btn-primary:hover {

            background: linear-gradient(120deg, #182848 0%, #4b6cb7 100%);

            transform: translateY(-2px);

            box-shadow: 0 5px 15px rgba(75, 108, 183, 0.3);

        }

        

        .btn-secondary {

            background: #f7fafc;

            border: 2px solid #e2e8f0;

            color: #4a5568;

        }
        .input-group-price {
    position: relative;
}

/* Default LTR styling (for English) */
.input-group-price .input-enhanced {
    padding: 12px 60px 12px 45px;
    text-align: left;
}

.input-group-price .input-currency {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: #f7fafc;
    padding: 0 8px;
    color: #4a5568;
    font-weight: 600;
    border-left: 1px solid #e2e8f0;
    height: 70%;
    display: flex;
    align-items: center;
}

.input-group-price .input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
    z-index: 5;
}

/* RTL styling override for Arabic */
.input-group-price[dir="rtl"] .input-enhanced {
    padding: 12px 45px 12px 60px !important;
    text-align: right !important;
}

.input-group-price[dir="rtl"] .input-currency {
    left: 15px !important;
    right: auto !important;
    border-left: none !important;
    border-right: 1px solid #e2e8f0 !important;
}

.input-group-price[dir="rtl"] .input-icon {
    left: auto !important;
    right: 15px !important;
}

        

        .btn-secondary:hover {

            background: #edf2f7;

            border-color: #cbd5e0;

            transform: translateY(-2px);

            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);

        }

        

        .form-text {

            font-size: 0.85rem;

            color: #718096;

            margin-top: 6px;

        }

        

    </style>



    <script>

        // Fallback toast functions if ToastMagic is not available

        if (typeof ToastMagic === 'undefined') {

            window.ToastMagic = {

                success: function(message) {

                    this.showToast(message, 'success');

                },

                error: function(message) {

                    this.showToast(message, 'error');

                },

                showToast: function(message, type) {

                    const toast = document.createElement('div');

                    toast.className = `custom-toast ${type}`;

                    toast.textContent = message;

                    document.body.appendChild(toast);

                    

                    setTimeout(() => {

                        toast.style.animation = 'slideOut 0.3s ease';

                        setTimeout(() => {

                            toast.remove();

                        }, 300);

                    }, 3000);

                }

            };

        }



        document.addEventListener('DOMContentLoaded', function() {

            const adTypeSelect = document.getElementById('adType');

            const durationSelect = document.getElementById('adDuration');

            const priceInput = document.getElementById('adPrice');

            

            // Price calculation based on ad type and duration

            function calculatePrice() {

                if (!adTypeSelect.value || !durationSelect.value) {

                    priceInput.value = '0';

                    return;

                }

                

                const duration = parseInt(durationSelect.value);

                const adType = adTypeSelect.value;

                

                // Base prices for each ad type per week

                const basePrices = {

                    'banner': 25,

                    'sidebar': 15,

                    'product': 20,

                    'popup': 30,

                    'email': 35

                };

                

                // Calculate total price

                const weeks = duration / 7;

                const basePrice = basePrices[adType] || 0;

                const totalPrice = Math.round(basePrice * weeks);

                

                // Apply discount for longer durations

                let finalPrice = totalPrice;

                if (duration >= 30) {

                    finalPrice = Math.round(totalPrice * 0.9); // 10% discount for 30+ days

                } else if (duration >= 14) {

                    finalPrice = Math.round(totalPrice * 0.95); // 5% discount for 14+ days

                }

                

                priceInput.value = finalPrice;

            }

            

            // Add event listeners for changes

            adTypeSelect.addEventListener('change', calculatePrice);

            durationSelect.addEventListener('change', calculatePrice);

            

            // File upload label text update

            const fileInput = document.getElementById('adImage');

            const fileLabel = document.querySelector('.file-upload-label span');

            

            fileInput.addEventListener('change', function() {

                if (this.files && this.files.length > 0) {

                    fileLabel.textContent = this.files[0].name;

                } else {

                    fileLabel.textContent = 'Choose image file';

                }

            });

            

            // Form submission with AJAX

            document.getElementById('adRequestForm').addEventListener('submit', async function(e) {

                e.preventDefault();

                

                const form = this;

                const formData = new FormData(form);

                const submitBtn = form.querySelector('button[type="submit"]');

                const originalBtnText = submitBtn.innerHTML;

                

                // Show loading state

                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

                submitBtn.disabled = true;

                

                try {

                    const response = await fetch(form.action, {

                        method: 'POST',

                        body: formData,

                        headers: {

                            'X-Requested-With': 'XMLHttpRequest',

                            'Accept': 'application/json'

                        }

                    });

                    

                    const data = await response.json();

                    

                    if (data.success) {

                        // Show success toast

                        ToastMagic.success(data.message);

                        

                        // Reset form

                        form.reset();

                        priceInput.value = '0';

                        fileLabel.textContent = 'Choose image file';

                        

                        // Redirect or show success message

                        setTimeout(() => {

                            window.location.reload();

                        }, 2000);

                    } else {

                        // Show error toast

                        ToastMagic.error(data.message);

                    }

                    

                } catch (error) {

                    console.error('Error:', error);

                    ToastMagic.error('An error occurred while submitting your request. Please try again.');

                } finally {

                    // Restore button state

                    submitBtn.innerHTML = originalBtnText;

                    submitBtn.disabled = false;

                }

            });

        });

    </script>

@endsection