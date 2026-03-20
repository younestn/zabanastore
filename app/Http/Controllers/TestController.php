<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AdRequest;
use App\Models\Product;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Enums\WebConfigKey;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

class TestController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
    ) {}

    public function index(Request $request): View
    {
        $vendorId = auth('seller')->id();
        $searchValue = $request['searchValue'];

        // Show ALL approved products (regardless of active status)
        $filters = [
            'added_by' => 'seller',
            'seller_id' => $vendorId,
            'request_status' => 1 // Only require approved status
        ];

        $products = $this->productRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            filters: $filters,
            relations: ['translations'],
            dataLimit: getWebConfig(name: WebConfigKey::PAGINATION_LIMIT)
        );
        
        return view('vendor1.test', compact('products', 'searchValue'));
    }

    public function storeAdRequest(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'ad_type' => 'required|in:banner,sidebar,product,popup,email',
            'duration' => 'required|integer|min:1',
            'ad_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            // Get the authenticated vendor ID
            $vendorId = auth('seller')->id();
            
            if (!$vendorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor not authenticated. Please log in again.'
                ], 401);
            }

            // Handle image upload - UPDATED FOR YOUR SERVER CONFIG
            $imagePath = null;
            if ($request->hasFile('ad_image')) {
                $image = $request->file('ad_image');
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                
                // Create directory if it doesn't exist
                $directory = storage_path('app/public/ad_images');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                // Move image to storage/app/public/ad_images
                $image->move($directory, $filename);
                
                // Store the full path that matches your server's accessible URL
                $imagePath = 'storage/app/public/ad_images/' . $filename;
            }

            // Calculate price based on ad type and duration
            $price = $this->calculatePrice($validated['ad_type'], $validated['duration']);

            // Create the ad request
            $adRequest = AdRequest::create([
                'vendor_id' => $vendorId,
                'product_id' => $validated['product_id'],
                'ad_type' => $validated['ad_type'],
                'duration_days' => $validated['duration'],
                'price' => $price,
                'image_path' => $imagePath, // Full path: "storage/app/public/ad_images/filename.jpg"
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ad request submitted successfully! It will be reviewed by admin.',
                'request_id' => $adRequest->id,
                'image_url' => asset($imagePath) // Use asset() helper for correct URL
            ]);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Ad Request Submission Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error submitting ad request: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculatePrice($adType, $duration)
    {
        // Base prices per week for each ad type
        $basePrices = [
            'banner' => 25,
            'sidebar' => 15,
            'product' => 20,
            'popup' => 30,
            'email' => 35
        ];

        // Calculate total price
        $weeks = $duration / 7;
        $basePrice = $basePrices[$adType] ?? 0;
        $totalPrice = round($basePrice * $weeks);

        // Apply discounts for longer durations
        if ($duration >= 30) {
            $totalPrice = round($totalPrice * 0.9); // 10% discount
        } elseif ($duration >= 14) {
            $totalPrice = round($totalPrice * 0.95); // 5% discount
        }

        return $totalPrice;
    }

    public function getAdPrice(Request $request)
    {
        // API endpoint for real-time price calculation
        $request->validate([
            'ad_type' => 'required|in:banner,sidebar,product,popup,email',
            'duration' => 'required|integer|min:1'
        ]);

        $price = $this->calculatePrice($request->ad_type, $request->duration);

        return response()->json(['price' => $price]);
    }
}