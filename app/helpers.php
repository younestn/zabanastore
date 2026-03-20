<?php

use App\Models\Notification;
use App\Models\NotificationSeen;
use Carbon\Carbon;

if (!function_exists('getSellerNotifications')) {
    function getSellerNotifications($sellerId)
    {
        return Notification::where('sent_to', 'seller')
                    ->where('user_id', $sellerId)
                    ->with('notificationSeenBy')
                    ->latest()
                    ->get();
    }
}

if (!function_exists('getUnseenNotificationCount')) {
    function getUnseenNotificationCount($sellerId)
    {
        return Notification::where('sent_to', 'seller')
                    ->where('user_id', $sellerId)
                    ->whereDoesntHave('notificationSeenBy')
                    ->count();
    }
}