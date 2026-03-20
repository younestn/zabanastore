{{-- Modal for viewing a specific ad request --}}
@component('components.modal', [
    'id' => "viewAdRequestModal-{$request->id}",
    'title' => "Ad Request #{$request->id}",
    'size' => 'modal-lg' // Use a larger modal for the image
])
    @slot('body')
        <div class="row">
            <div class="col-md-6">
                <h6>Vendor Information</h6>
                <p><strong>Name:</strong> {{ $request->vendor->f_name }} {{ $request->vendor->l_name }}</p>
                <p><strong>Email:</strong> {{ $request->vendor->email }}</p>
                <p><strong>Phone:</strong> {{ $request->vendor->phone }}</p>
            </div>
            <div class="col-md-6">
                <h6>Request Details</h6>
                <p><strong>Product:</strong> {{ $request->product->name ?? 'N/A' }}</p>
                <p><strong>Ad Type:</strong> {{ ucfirst($request->ad_type) }}</p>
                <p><strong>Duration:</strong> {{ $request->duration_days }} days</p>
                <p><strong>Price:</strong> ${{ number_format($request->price, 2) }}</p>
                <p><strong>Status:</strong> 
                    <span class="badge badge-{{ 
                        $request->status == 'pending' ? 'warning' : 
                        ($request->status == 'approved' ? 'success' : 'danger') 
                    }}">
                        {{ ucfirst($request->status) }}
                    </span>
                </p>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-12">
                <h6>Ad Image</h6>
                @if($request->image_path)
                    <img src="{{ asset('storage/' . $request->image_path) }}" 
                         alt="Ad Image for {{ $request->product->name ?? 'Request #' . $request->id }}" 
                         class="img-fluid rounded shadow-sm" 
                         style="max-height: 300px;">
                @else
                    <p class="text-muted">No image uploaded.</p>
                @endif
            </div>
        </div>

        @if($request->notes)
        <div class="row mt-3">
            <div class="col-12">
                <h6>Vendor Notes</h6>
                <p class="bg-light p-3 rounded">{{ $request->notes }}</p>
            </div>
        </div>
        @endif
    @endslot

    @slot('footer')
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        
        {{-- Only show action buttons if the request is pending --}}
        @if($request->status == 'pending')
            <form action="{{ route('admin.ad-requests.update', $request->id) }}" method="POST" class="d-inline">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="btn btn-success">Approve</button>
            </form>
            <form action="{{ route('admin.ad-requests.update', $request->id) }}" method="POST" class="d-inline">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="rejected">
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
        @endif
    @endslot
@endcomponent