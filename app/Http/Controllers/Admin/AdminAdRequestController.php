<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdRequest;
use App\Models\Notification;
use App\Models\NotificationSeen;

class AdminAdRequestController extends Controller
{
    public function index()
    {
        $adRequests = AdRequest::with(['vendor', 'product'])
                                ->latest()
                                ->get();

        return view('admin-views.all-adsfetch.index', compact('adRequests'));
    }
    
    public function update(Request $request, AdRequest $adRequest)
    {
        // Check if updating status or price/duration
        if ($request->has('status')) {
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected'
            ]);

            $oldStatus = $adRequest->status;
            $adRequest->update(['status' => $validated['status']]);
            
            // Create notification only if status changed
            if ($oldStatus !== $validated['status']) {
                $notification = Notification::create([
                    'sent_by' => 'admin',
                    'sent_to' => 'seller',
                    'user_id' => $adRequest->vendor_id, // Link to specific seller
                    'ad_request_id' => $adRequest->id,
                    'title' => 'Ad Request ' . ucfirst($validated['status']),
                    'description' => 'Your ad request #' . $adRequest->id . ' has been ' . $validated['status'],
                    'type' => $validated['status'] == 'approved' ? 'success' : 'warning',
                    'notification_count' => 1,
                    'status' => 1
                ]);
            }

            return redirect()->back()->with('success', "Ad request #{$adRequest->id} has been {$validated['status']}.");
        }
        
        // Update price or duration
        if ($request->has('price') || $request->has('duration_days')) {
            $validated = $request->validate([
                'price' => 'nullable|numeric|min:0',
                'duration_days' => 'nullable|integer|min:1'
            ]);
            
            // Update only the fields that are provided
            $updateData = [];
            if ($request->has('price')) {
                $updateData['price'] = $validated['price'];
            }
            if ($request->has('duration_days')) {
                $updateData['duration_days'] = $validated['duration_days'];
            }
            
            $adRequest->update($updateData);
            
            return redirect()->back()->with('success', "Ad request #{$adRequest->id} has been updated.");
        }
        
        return redirect()->back()->with('error', 'No valid fields to update.');
    }
}