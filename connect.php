<?php  
    // Database configuration
    $host = 'localhost';
    $dbname = 'bit216';
    $username = 'root';
    $password = '';

    // Define constants for backward compatibility
    define('DB_HOST', $host);
    define('DB_NAME', $dbname);
    define('DB_USER', $username);
    define('DB_PASS', $password);

    // Create database connection
    function getConnection() {
        global $host, $dbname, $username, $password;
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    // Test connection function
    function testConnection() {
        try {
            $pdo = getConnection();
            return "Database connection successful!";
        } catch(Exception $e) {
            return "Database connection failed: " . $e->getMessage();
        }
    }

    // Create goals table if it doesn't exist
    function createGoalsTable() {
        try {
            $pdo = getConnection();
            $sql = "CREATE TABLE IF NOT EXISTS user_goals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                goal_type VARCHAR(50) NOT NULL,
                target_value DECIMAL(10,2) NOT NULL,
                current_value DECIMAL(10,2) DEFAULT 0,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('active', 'completed', 'paused') DEFAULT 'active',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $pdo->exec($sql);
            return true;
        } catch(Exception $e) {
            error_log("Error creating goals table: " . $e->getMessage());
            return false;
        }
    }

    // Initialize goals table
    createGoalsTable();

    // Function to update goal progress when donations are made
    function updateGoalProgress($pdo, $userId, $goalType, $increment = 1) {
        try {
            // Get active goals of the specified type
            $stmt = $pdo->prepare("
                SELECT id, current_value, target_value 
                FROM user_goals 
                WHERE user_id = ? AND goal_type = ? AND status = 'active'
            ");
            $stmt->execute([$userId, $goalType]);
            $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($goals as $goal) {
                $newCurrentValue = $goal['current_value'] + $increment;
                
                // Update the goal's current value
                $updateStmt = $pdo->prepare("
                    UPDATE user_goals 
                    SET current_value = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newCurrentValue, $goal['id']]);
                
                // Check if goal is completed
                if ($newCurrentValue >= $goal['target_value']) {
                    $completeStmt = $pdo->prepare("
                        UPDATE user_goals 
                        SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $completeStmt->execute([$goal['id']]);
                }
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Error updating goal progress: " . $e->getMessage());
            return false;
        }
    }

    // Function to update goal progress based on actual database data
    function syncGoalProgress($pdo, $userId) {
        try {
            // Check for goal completions and auto-increase (for default goals)
            checkAndAutoIncreaseGoals($pdo, $userId);
            
            // Also update any existing user goals in database (for backward compatibility)
            $stmt = $pdo->prepare("
                SELECT id, goal_type 
                FROM user_goals 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($goals as $goal) {
                $currentProgress = getCurrentGoalProgress($pdo, $userId, $goal['goal_type']);
                
                // Update the goal's current value
                $updateStmt = $pdo->prepare("
                    UPDATE user_goals 
                    SET current_value = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updateStmt->execute([$currentProgress, $goal['id']]);
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Error syncing goal progress: " . $e->getMessage());
            return false;
        }
    }

    // Function to get current progress for a specific goal type (moved from dashboard)
    function getCurrentGoalProgress($pdo, $userId, $goalType) {
        try {
            switch ($goalType) {
                case 'donations':
                    // Count total donations made by user (all statuses)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as donation_count 
                        FROM donations 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['donation_count'] ?? 0;
                    
                case 'quantity':
                    // Calculate total quantity of food saved (from all donations)
                    $stmt = $pdo->prepare("
                        SELECT SUM(CAST(quantity AS DECIMAL(10,2))) as total_quantity 
                        FROM donations 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['total_quantity'] ?? 0;
                    
                case 'inventory':
                    // Count total items in current inventory
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as inventory_count 
                        FROM food_inventory 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['inventory_count'] ?? 0;
                    
                case 'waste_days':
                    // Count days with zero waste (simplified calculation)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT DATE(created_at)) as waste_free_days
                        FROM food_inventory 
                        WHERE user_id = ? 
                        AND DATE(expiry_date) >= DATE(created_at)
                    ");
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['waste_free_days'] ?? 0;
                    
                default:
                    return 0;
            }
        } catch(Exception $e) {
            error_log("Error calculating goal progress: " . $e->getMessage());
            return 0;
        }
    }
    
    // Check for goal completions and auto-increase targets
    function checkAndAutoIncreaseGoals($pdo, $userId) {
        try {
            $goalTypes = ['donations', 'quantity', 'inventory'];
            
            foreach ($goalTypes as $goalType) {
                $currentProgress = getCurrentGoalProgress($pdo, $userId, $goalType);
                
                // Calculate current target (auto-increase logic)
                $currentTarget = getCurrentTarget($goalType, $currentProgress);
                
                // Debug logging
                error_log("Goal check for user $userId: $goalType - Progress: $currentProgress, Target: $currentTarget");
                
                // Check if goal is completed and notification hasn't been sent yet
                if ($currentProgress >= $currentTarget) {
                    error_log("Goal $goalType completed for user $userId! Progress: $currentProgress >= Target: $currentTarget");
                    
                    // Check if we already sent a notification for this achievement (more lenient check)
                    $goalNames = [
                        'donations' => 'Donations',
                        'quantity' => 'Food Saved',
                        'inventory' => 'Inventory Items'
                    ];
                    $goalName = $goalNames[$goalType] ?? ucfirst($goalType);
                    
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM notifications 
                        WHERE user_id = ? 
                        AND type = 'success'
                        AND title = '🎉 Goal Achieved!'
                        AND message LIKE ? 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTES)
                    ");
                    $searchPattern = "%$goalName goal of $currentTarget%";
                    $stmt->execute([$userId, $searchPattern]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    error_log("Existing notifications count: " . $result['count']);
                    
                    // Only create notification if we haven't sent one very recently (5 minutes instead of 1 hour)
                    if ($result['count'] == 0) {
                        $success = createCongratulationNotification($pdo, $userId, $goalType, $currentProgress, $currentTarget);
                        error_log("Goal completed! User $userId achieved $currentProgress $goalType (target: $currentTarget). Notification sent: " . ($success ? "SUCCESS" : "FAILED"));
                    } else {
                        error_log("Notification already sent very recently for this achievement");
                    }
                } else {
                    error_log("Goal $goalType not completed yet. Progress: $currentProgress < Target: $currentTarget");
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error checking goal completions: " . $e->getMessage());
            return false;
        }
    }
    
    // Get current target based on progress
    function getCurrentTarget($goalType, $currentProgress) {
        switch ($goalType) {
            case 'donations':
            case 'quantity':
                // Progression: 5 → 20 → 50 → 100
                if ($currentProgress < 5) return 5;
                if ($currentProgress < 20) return 20;
                if ($currentProgress < 50) return 50;
                if ($currentProgress < 100) return 100;
                return ceil($currentProgress / 100) * 100; // 100, 200, 300...
            case 'inventory':
                // Progression: 10 → 20 → 50 → 100
                if ($currentProgress < 10) return 10;
                if ($currentProgress < 20) return 20;
                if ($currentProgress < 50) return 50;
                if ($currentProgress < 100) return 100;
                return ceil($currentProgress / 100) * 100; // 100, 200, 300...
            default:
                return 5;
        }
    }
    
    // Get next target after completion
    function getNextTarget($goalType, $currentTarget) {
        switch ($goalType) {
            case 'donations':
            case 'quantity':
                // Progression: 5 → 20 → 50 → 100
                if ($currentTarget == 5) return 20;
                if ($currentTarget == 20) return 50;
                if ($currentTarget == 50) return 100;
                return $currentTarget + 100; // 100→200→300...
            case 'inventory':
                // Progression: 10 → 20 → 50 → 100
                if ($currentTarget == 10) return 20;
                if ($currentTarget == 20) return 50;
                if ($currentTarget == 50) return 100;
                return $currentTarget + 100; // 100→200→300...
            default:
                return 20; // Default progression
        }
    }
    
    // Create congratulation notification
    function createCongratulationNotification($pdo, $userId, $goalType, $achieved, $target) {
        try {
            $goalNames = [
                'donations' => 'Donations',
                'quantity' => 'Food Saved',
                'inventory' => 'Inventory Items'
            ];
            
            $goalName = $goalNames[$goalType] ?? ucfirst($goalType);
            $nextTarget = getNextTarget($goalType, $target);
            
            $title = "🎉 Goal Achieved!";
            $message = "Congratulations! You've successfully achieved your $goalName goal of $target! You've made $achieved $goalType. Your new target is $nextTarget. Keep up the great work!";
            
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, 'success', ?, ?, ?, 0, NOW())");
            $stmt->execute([$userId, $title, $message, $goalName]);
            
            error_log("Congratulation notification created for user $userId: $goalName goal achieved");
            return true;
        } catch (Exception $e) {
            error_log("Error creating congratulation notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a welcome notification for new users
     */
    function createWelcomeNotification($userId) {
        try {
            $pdo = getConnection();
            
            // Check if user already has a welcome notification
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'welcome'");
            $checkStmt->execute([$userId]);
            $existingCount = $checkStmt->fetchColumn();
            
            if ($existingCount > 0) {
                return false; // Welcome notification already exists
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
            
            error_log("Welcome notification created for user $userId");
            return true;
            
        } catch (Exception $e) {
            error_log("Error creating welcome notification: " . $e->getMessage());
            return false;
        }
    }
?>