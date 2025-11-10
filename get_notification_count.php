<?php
require_once 'connection.php';
require_once 'auth.php';

// Check if user is authenticated
requireAuth();

// Sample notification data (in a real app, this would come from the database)
$notifications = [
    [
        'id' => 1,
        'type' => 'expiry',
        'title' => 'Expiry Alert',
        'message' => 'Your bananas will expire in 2 days',
        'timestamp' => '2023-06-15 10:30:45',
        'is_read' => false,
        'related_item' => 'Bananas'
    ],
    [
        'id' => 2,
        'type' => 'donation',
        'title' => 'Donation Claimed',
        'message' => 'Someone has claimed your canned beans donation',
        'timestamp' => '2023-06-14 16:45:22',
        'is_read' => true,
        'related_item' => 'Canned Beans'
    ],
    [
        'id' => 3,
        'type' => 'meal',
        'title' => 'Meal Suggestion',
        'message' => 'You have ingredients that would make a great stir fry',
        'timestamp' => '2023-06-14 09:15:33',
        'is_read' => false,
        'related_item' => 'Various'
    ],
    [
        'id' => 4,
        'type' => 'account',
        'title' => 'Security Alert',
        'message' => 'A new device logged into your account',
        'timestamp' => '2023-06-13 14:20:18',
        'is_read' => true,
        'related_item' => null
    ],
    [
        'id' => 5,
        'type' => 'expiry',
        'title' => 'Expiry Alert',
        'message' => 'Your milk will expire tomorrow',
        'timestamp' => '2023-06-13 11:05:57',
        'is_read' => false,
        'related_item' => 'Milk'
    ],
    [
        'id' => 6,
        'type' => 'donation',
        'title' => 'Donation Suggested',
        'message' => 'Your bread is expiring soon. Consider donating it',
        'timestamp' => '2023-06-12 18:30:44',
        'is_read' => true,
        'related_item' => 'Bread'
    ]
];

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) $unread_count++;
}
//
// Return JSON response
header('Content-Type: application/json');
echo json_encode(['count' => $unread_count]);
?>

