<?php
    header('Content-Type: application/json');
    require_once 'connect.php';

    try {
        $pdo = getConnection();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }

    // Create notifications table if it doesn't exist (safe-guard)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Continue without crashing; endpoints will still return gracefully
    }

    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($userId <= 0 && $action !== 'ping') {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }

    switch ($action) {
        case 'get_notifications':
            handleGetNotifications($pdo, $userId);
            break;
        case 'get_unread_count':
            handleGetUnreadCount($pdo, $userId);
            break;
        case 'mark_as_read':
            handleMarkAsRead($pdo, $userId, (int)($_POST['notification_id'] ?? 0));
            break;
        case 'delete_notification':
            handleDeleteNotification($pdo, $userId, (int)($_POST['notification_id'] ?? 0));
            break;
        case 'mark_all_as_read':
            handleMarkAllAsRead($pdo, $userId);
            break;
        case 'create_welcome_notification':
            handleCreateWelcomeNotification($pdo, $userId);
            break;
        case 'test_claim_notification':
            handleTestClaimNotification($pdo, $userId);
            break;
        case 'approve_claim':
            handleApproveClaim($pdo, $userId, (int)($_POST['notification_id'] ?? 0));
            break;
        case 'reject_claim':
            handleRejectClaim($pdo, $userId, (int)($_POST['notification_id'] ?? 0));
            break;
        case 'ping':
            echo json_encode(['success' => true, 'message' => 'ok']);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

    function handleGetNotifications(PDO $pdo, int $userId): void {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = min(100, max(1, (int)($_POST['limit'] ?? 50)));
        $includeRead = filter_var($_POST['include_read'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $offset = ($page - 1) * $limit;

        $where = 'user_id = :uid';
        if (!$includeRead) {
            $where .= ' AND is_read = 0';
        }

        try {
            $stmt = $pdo->prepare("SELECT id, user_id, type, title, message, is_read, created_at
                                   FROM notifications
                                   WHERE $where
                                   ORDER BY created_at DESC
                                   LIMIT :lim OFFSET :off");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode(['success' => true, 'notifications' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'notifications' => []]);
        }
    }

    function handleGetUnreadCount(PDO $pdo, int $userId): void {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            $cnt = (int)($stmt->fetchColumn() ?: 0);
            echo json_encode(['success' => true, 'unread_count' => $cnt]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'unread_count' => 0]);
        }
    }

    function handleMarkAsRead(PDO $pdo, int $userId, int $notificationId): void {
        if ($notificationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification']);
            return;
        }
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
    }

    function handleDeleteNotification(PDO $pdo, int $userId, int $notificationId): void {
        if ($notificationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification']);
            return;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
    }

    function handleMarkAllAsRead(PDO $pdo, int $userId): void {
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update all']);
        }
    }

    function handleApproveClaim(PDO $pdo, int $userId, int $notificationId): void {
        try {
            // Get notification details
            $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? AND type = ?');
            $stmt->execute([$notificationId, $userId, 'claim_request']);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$notification) {
                echo json_encode(['success' => false, 'message' => 'Notification not found']);
                return;
            }
            
            // Extract donation_id from related_item or message
            $itemName = $notification['related_item'] ?? '';
            
            // Find the donation by item name and user_id
            $stmt = $pdo->prepare('SELECT d.id, d.user_id FROM donations d WHERE d.item_name = ? AND d.user_id = ? AND d.status = ?');
            $stmt->execute([$itemName, $userId, 'pending']);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$donation) {
                echo json_encode(['success' => false, 'message' => 'Donation not found or not pending']);
                return;
            }
            
            // Update donation status to claimed
            $stmt = $pdo->prepare('UPDATE donations SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute(['claimed', $donation['id']]);
            
            // Get claimer's user_id before updating
            $stmt = $pdo->prepare('SELECT claimed_by_user_id FROM donation_claims WHERE donation_id = ? AND status = ?');
            $stmt->execute([$donation['id'], 'pending']);
            $claimData = $stmt->fetch(PDO::FETCH_ASSOC);
            $claimerUserId = $claimData['claimed_by_user_id'] ?? null;
            
            // Update donation_claims status to approved
            $stmt = $pdo->prepare('UPDATE donation_claims SET status = ?, updated_at = NOW() WHERE donation_id = ? AND status = ?');
            $stmt->execute(['approved', $donation['id'], 'pending']);
            
            // Mark notification as read
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->execute([$notificationId]);
            
            // Create success notification for donor
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
            $stmt->execute([
                $userId,
                'donation_claimed',
                'Donation Claimed',
                "Your donation '$itemName' has been claimed. Check pickup details in Donations.",
                $itemName
            ]);
            
            // Create approval notification for claimer
            if ($claimerUserId) {
                // Get donor's name
                $donorStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $donorStmt->execute([$userId]);
                $donorData = $donorStmt->fetch(PDO::FETCH_ASSOC);
                $donorName = $donorData ? $donorData['username'] : 'The donor';
                
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
                $stmt->execute([
                    $claimerUserId,
                    'claim_approved',
                    'Claim Approved! 🎉',
                    "$donorName has accepted your request for '$itemName'! You can now arrange pickup with them.",
                    $itemName
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Claim approved successfully']);
            
        } catch (Exception $e) {
            error_log('Error approving claim: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to approve claim']);
        }
    }

    function handleRejectClaim(PDO $pdo, int $userId, int $notificationId): void {
        try {
            // Get notification details
            $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? AND type = ?');
            $stmt->execute([$notificationId, $userId, 'claim_request']);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$notification) {
                echo json_encode(['success' => false, 'message' => 'Notification not found']);
                return;
            }
            
            // Extract donation_id from related_item or message
            $itemName = $notification['related_item'] ?? '';
            
            // Find the donation by item name and user_id
            $stmt = $pdo->prepare('SELECT d.id, d.user_id FROM donations d WHERE d.item_name = ? AND d.user_id = ? AND d.status = ?');
            $stmt->execute([$itemName, $userId, 'pending']);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$donation) {
                echo json_encode(['success' => false, 'message' => 'Donation not found or not pending']);
                return;
            }
            
            // Get claimer's user_id before updating
            $stmt = $pdo->prepare('SELECT claimed_by_user_id FROM donation_claims WHERE donation_id = ? AND status = ?');
            $stmt->execute([$donation['id'], 'pending']);
            $claimData = $stmt->fetch(PDO::FETCH_ASSOC);
            $claimerUserId = $claimData['claimed_by_user_id'] ?? null;
            
            // Update donation status back to available
            $stmt = $pdo->prepare('UPDATE donations SET status = ?, claimed_by = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->execute(['available', $donation['id']]);
            
            // Update donation_claims status to rejected
            $stmt = $pdo->prepare('UPDATE donation_claims SET status = ?, updated_at = NOW() WHERE donation_id = ? AND status = ?');
            $stmt->execute(['rejected', $donation['id'], 'pending']);
            
            // Mark notification as read
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->execute([$notificationId]);
            
            // Create rejection notification for claimer
            if ($claimerUserId) {
                // Get donor's name
                $donorStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                $donorStmt->execute([$userId]);
                $donorData = $donorStmt->fetch(PDO::FETCH_ASSOC);
                $donorName = $donorData ? $donorData['username'] : 'The donor';
                
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
                $stmt->execute([
                    $claimerUserId,
                    'claim_rejected',
                    'Claim Rejected',
                    "$donorName has rejected your request for '$itemName'. The item is available for others to claim.",
                    $itemName
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Claim rejected successfully']);
            
        } catch (Exception $e) {
            error_log('Error rejecting claim: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to reject claim']);
        }
    }

    function handleCreateWelcomeNotification($pdo, $userId) {
        try {
            // Check if user already has a welcome notification
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'welcome'");
            $checkStmt->execute([$userId]);
            $existingCount = $checkStmt->fetchColumn();
            
            if ($existingCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Welcome notification already exists']);
                return;
            }
            
            // Get user's name for personalized message
            $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch();
            $username = $userData ? $userData['username'] : 'User';
            
            // Create welcome notification
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, 'welcome', ?, ?, 0, NOW())");
            $stmt->execute([
                $userId,
                'Welcome to SavePlate! 🎉',
                "Hi " . $username . "! Welcome to SavePlate, your food waste reduction companion. Start by adding items to your inventory, explore available donations, and help reduce food waste in your community. Happy saving!"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Welcome notification created successfully']);
            
        } catch (Exception $e) {
            error_log('Error creating welcome notification: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create welcome notification']);
        }
    }

    function handleTestClaimNotification($pdo, $userId) {
        try {
            // Create a test claim approval notification
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, 'claim_approved', ?, ?, 0, NOW())");
            $stmt->execute([
                $userId,
                'Claim Approved! 🎉',
                'John Doe has accepted your request for \'Cheese\'! You can now arrange pickup with them.'
            ]);
            
            // Create a test claim rejection notification
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, 'claim_rejected', ?, ?, 0, NOW())");
            $stmt->execute([
                $userId,
                'Claim Rejected',
                'Jane Smith has rejected your request for \'Bread\'. The item is available for others to claim.'
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Test claim notifications created successfully']);
            
        } catch (Exception $e) {
            error_log('Error creating test claim notifications: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create test notifications']);
        }
    }
?>


