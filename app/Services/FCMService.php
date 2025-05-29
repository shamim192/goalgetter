<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class FCMService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(config('services.firebase.credentials_file'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification($token, $title, $body, $data = [])
    {
        // Get Profile Image from Data or Use Default Image
        $profileImage = $data['profile_image'] ?? 'https://your-default-image-url.com/avatar.png';

        // Create Notification Payload with Image
        $notification = Notification::create($title, $body)
            ->withImageUrl($profileImage); // Add profile image

        // Set High-Priority for Instant Delivery
        $androidConfig = AndroidConfig::fromArray([
            'priority' => 'high',
            'ttl' => '3600s', // Message expires in 1 hour
            'notification' => [
                'sound' => 'default',
                'image' => $profileImage, // Add image to Android notification
            ],
        ]);

        $apnsConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10', // High priority for iOS
            ],
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'mutable-content' => 1, // Allows iOS to show rich notifications
                    'alert' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'attachment-url' => $profileImage, // Add image for iOS
                ],
            ],
        ]);

        // Create Message Object
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData(array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig);

        // Send Notification
        return $this->messaging->send($message);
    }
}
