<?php
    session_start();
    require_once 'connect.php'; // Include your database connection

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_register.php");
        exit();
    }

    // Initialize variables
    $household_size = '';
    $address = '';

    // Get user data from database
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            // User not found, redirect to login
            header("Location: login_register.php");
            exit();
        }
        
        // Safely assign household_size and address values (handle NULL values)
        $household_size = $userData['household_size'] ?? '';
        $address = $userData['address'] ?? '';
        
    } catch (Exception $e) {
        die("Error retrieving user data: " . $e->getMessage());
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $newName = $_POST['name'];
        $newEmail = $_POST['email'];
        $newHouseholdSize = $_POST['household_size'];
        $newAddress = $_POST['address'];

        // Validate household_size to ensure it's numeric
        if (!empty($newHouseholdSize) && !is_numeric($newHouseholdSize)) {
            echo "<script>alert('Household size must be a number');</script>";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
                $stmt->execute([$newName, $newEmail, $newHouseholdSize, $newAddress, $_SESSION['user_id']]);
                echo "<script>alert('Profile changed successfully');</script>";

                // Refresh user data
                $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                $household_size = $userData['household_size'] ?? '';
                $address = $userData['address'] ?? '';
            } catch (Exception $e) {
                echo "<script>alert('Error updating profile: " . $e->getMessage() . "');</script>";
            }
        }
    }

    // Handle food item deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
        $item_id = $_POST['item_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM food_inventory WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_id, $_SESSION['user_id']]);
            echo "<script>alert('Food item deleted successfully');</script>";
        } catch (Exception $e) {
            echo "<script>alert('Error deleting food item');</script>";
        }
    }

    // Handle food item update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
        $item_id = $_POST['item_id'];
        $item_name = $_POST['item_name'];
        $quantity = $_POST['quantity'];
        $expiry_date = $_POST['expiry_date'];
        $storage_location = $_POST['storage_location'];
        $notes = $_POST['notes'];
        
        try {
            $stmt = $pdo->prepare("UPDATE food_inventory SET item_name = ?, quantity = ?, expiry_date = ?, storage_location = ?, notes = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_name, $quantity, $expiry_date, $storage_location, $notes, $item_id, $_SESSION['user_id']]);
            echo "<script>alert('Food item updated successfully');</script>";
        } catch (Exception $e) {
            echo "<script>alert('Error updating food item');</script>";
        }
    }

    // Handle donation creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_donation'])) {
        $item_id = $_POST['item_id'];
        $donation_quantity = intval($_POST['donation_quantity']);
        $pickup_location = $_POST['pickup_location'];
        $availability = $_POST['availability'];
        $additional_notes = $_POST['additional_notes'] ?? '';
        $item_category = $_POST['item_category'] ?? 'Other'; // Get category from hidden input

        // Normalize category to match donations enum values
        $normalizeCategory = function($rawCategory) {
            $map = [
                'fruits & vegetables' => 'fruits_vegetables',
                'fruits_vegetables' => 'fruits_vegetables',
                'fruits and vegetables' => 'fruits_vegetables',
                'dairy & eggs' => 'dairy_eggs',
                'dairy_eggs' => 'dairy_eggs',
                'meat & fish' => 'meat_fish',
                'meat_fish' => 'meat_fish',
                'grains & cereals' => 'grains_cereals',
                'grains_cereals' => 'grains_cereals',
                'canned goods' => 'canned_goods',
                'canned_goods' => 'canned_goods',
                'beverages' => 'beverages',
                'snacks' => 'snacks',
                'condiments' => 'condiments',
                'frozen foods' => 'frozen_foods',
                'frozen_foods' => 'frozen_foods',
                'other' => 'other'
            ];
            $key = strtolower(trim((string)$rawCategory));
            $key = str_replace(['  '], ' ', $key);
            $key = str_replace([' / ', '/', ' & '], ' & ', $key);
            // direct map
            if (isset($map[$key])) return $map[$key];
            // generic normalization: lowercase, replace non-letters with underscores, collapse repeats
            $generic = preg_replace('/[^a-z0-9]+/i', '_', strtolower($rawCategory));
            $generic = trim($generic, '_');
            // if still not recognized, default to other
            return isset($map[$generic]) ? $map[$generic] : 'other';
        };
        
        // Debug: Log the received data
        error_log("=== DONATION ATTEMPT START ===");
        error_log("Item ID: $item_id");
        error_log("Donation Quantity: $donation_quantity");
        error_log("Item Category from POST: $item_category");
        error_log("User ID: " . $_SESSION['user_id']);
        error_log("POST data: " . print_r($_POST, true));
        
        // Check for duplicate submission within last 2 seconds (only for rapid double-clicks)
        $session_key = "last_donation_" . $item_id;
        if (isset($_SESSION[$session_key])) {
            $time_diff = time() - $_SESSION[$session_key];
            if ($time_diff < 2) {
                error_log("Rapid duplicate donation attempt detected within 2 seconds, ignoring");
                echo "<script>window.location.reload();</script>";
                exit;
            }
        }
        $_SESSION[$session_key] = time();
        
        try {
            // Check if donations table exists, create if not
            $stmt = $pdo->prepare("CREATE TABLE IF NOT EXISTS donations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                quantity VARCHAR(100) NOT NULL,
                expiry_date DATE NOT NULL,
                category VARCHAR(100) NOT NULL,
                pickup_location TEXT NOT NULL,
                availability TEXT NOT NULL,
                notes TEXT,
                status VARCHAR(20) DEFAULT 'available',
                claimed_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                donor_phone VARCHAR(20) NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            $stmt->execute();
            
            // Note: Removed database-based duplicate check as it was too restrictive
            
            // Get the item details
            $stmt = $pdo->prepare("SELECT item_name, category, quantity, expiry_date FROM food_inventory WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_id, $_SESSION['user_id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                error_log("Item found: " . json_encode($item));
                error_log("Item category from DB: " . ($item['category'] ?? 'NULL'));
                error_log("Database quantity field: '" . $item['quantity'] . "'");
                
                // Parse current quantity to get the number
                $quantity_string = $item['quantity'];
                
                // More robust quantity parsing
                if (preg_match('/(\d+)/', $quantity_string, $matches)) {
                    $current_quantity = intval($matches[1]);
                } else {
                    $current_quantity = intval($quantity_string);
                }
                
                // Debug logging
                error_log("=== QUANTITY DEBUGGING ===");
                error_log("Original quantity string: '$quantity_string'");
                error_log("Regex match result: " . (preg_match('/(\d+)/', $quantity_string, $matches) ? "FOUND: " . $matches[1] : "NOT FOUND"));
                error_log("Parsed current quantity: $current_quantity");
                error_log("Donation quantity requested: $donation_quantity");
                error_log("Math: $current_quantity - $donation_quantity = " . ($current_quantity - $donation_quantity));
                error_log("Comparison: donation_quantity ($donation_quantity) vs current_quantity ($current_quantity)");
                
                // Store debug info for display
                $debug_info = "DB Quantity: '$quantity_string' | Parsed: $current_quantity | Donate: $donation_quantity";
                
                // Validate donation quantity
                if ($donation_quantity <= 0 || $donation_quantity > $current_quantity) {
                    error_log("Invalid donation quantity: $donation_quantity (max: $current_quantity)");
                    echo "<script>alert('Invalid donation quantity. Please enter a valid amount.');</script>";
                    exit;
                }
                
                // Create the donation with the specified quantity
                // Extract unit from original quantity string
                $unit = '';
                if (preg_match('/\d+\s+(.+)/', $item['quantity'], $unit_matches)) {
                    $unit = $unit_matches[1];
                }
                $donation_quantity_text = $donation_quantity . ($unit ? " " . $unit : "");
                
                // Ensure category matches enum; fallback to 'other' if unrecognized
                $final_category = $normalizeCategory(!empty($item_category) ? $item_category : 'other');
                error_log("Using category for donation: '$final_category' (original: '$item_category')");
                
                $stmt = $pdo->prepare("INSERT INTO donations (user_id, item_name, quantity, expiry_date, category, pickup_location, availability, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')");
                $result = $stmt->execute([$_SESSION['user_id'], $item['item_name'], $donation_quantity_text, $item['expiry_date'], $final_category, $pickup_location, $availability, $additional_notes]);
                
                if ($result) {
                    error_log("Donation created successfully");
                    
                    // Update goal progress for donations (count all donations created)
                    require_once 'connect.php';
                    updateGoalProgress($pdo, $_SESSION['user_id'], 'donations', 1);
                    
                    // Update goal progress for quantity (if quantity goal exists)
                    // Extract numeric value from quantity string for goal tracking
                    $numeric_quantity = floatval($donation_quantity);
                    if ($numeric_quantity > 0) {
                        updateGoalProgress($pdo, $_SESSION['user_id'], 'quantity', $numeric_quantity);
                    }
                    
                    // Check for goal completions and send congratulation notifications
                    syncGoalProgress($pdo, $_SESSION['user_id']);
                    
                    // Create success notification for the user
                    try {
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, 'donation_created', 'Donation Created Successfully! 🎉', ?, ?, 0, NOW())");
                        $message = "Your donation of '" . $item['item_name'] . "' has been successfully created and is now available for others to claim!";
                        $stmt->execute([$_SESSION['user_id'], $message, $item['item_name']]);
                    } catch (Exception $e) {
                        error_log("Error creating donation success notification: " . $e->getMessage());
                    }
                    
                    // Check if donating all items or just some
                    error_log("Comparing donation_quantity ($donation_quantity) with current_quantity ($current_quantity)");
                    if ($donation_quantity == $current_quantity) {
                        error_log("Donating all items - removing entire row");
                        // Remove the entire item from inventory
                        $stmt = $pdo->prepare("DELETE FROM food_inventory WHERE id = ? AND user_id = ?");
                        $delete_result = $stmt->execute([$item_id, $_SESSION['user_id']]);
                        
                        if ($delete_result) {
                            error_log("Item completely removed from inventory");
                            echo "<script>window.location.reload();</script>";
                        } else {
                            error_log("Failed to remove item from inventory");
                            echo "<script>alert('Donation created but failed to remove from inventory. Please refresh the page.'); window.location.reload();</script>";
                        }
                    } else {
                        error_log("Donating partial items - updating quantity");
                        // Update the quantity in inventory
                        $remaining_quantity = $current_quantity - $donation_quantity;
                        $remaining_quantity_text = $remaining_quantity . ($unit ? " " . $unit : "");
                        
                        error_log("=== REMAINING QUANTITY CALCULATION ===");
                        error_log("Current quantity: $current_quantity");
                        error_log("Donation quantity: $donation_quantity");
                        error_log("Remaining quantity: $remaining_quantity");
                        error_log("Unit: '$unit'");
                        error_log("Remaining quantity text: '$remaining_quantity_text'");
                        
                        $stmt = $pdo->prepare("UPDATE food_inventory SET quantity = ? WHERE id = ? AND user_id = ?");
                        $update_result = $stmt->execute([$remaining_quantity_text, $item_id, $_SESSION['user_id']]);
                        
                        if ($update_result) {
                            error_log("Item quantity updated in inventory successfully");
                            error_log("=== DONATION COMPLETED SUCCESSFULLY ===");
                            // Show debug info in alert
                            echo "<script>alert('DEBUG INFO:\\n$debug_info\\nRemaining: $remaining_quantity\\n\\n$donation_quantity items successfully converted to donation! $remaining_quantity items remain in your inventory.'); window.location.reload();</script>";
                        } else {
                            error_log("Failed to update item quantity in inventory: " . $stmt->errorInfo()[2]);
                            echo "<script>alert('Donation created but failed to update inventory. Please refresh the page.'); window.location.reload();</script>";
                        }
                    }
                } else {
                    error_log("Failed to create donation: " . $stmt->errorInfo()[2]);
                    echo "<script>alert('Failed to create donation: " . $stmt->errorInfo()[2] . "');</script>";
                }
            } else {
                error_log("Item not found in database - Item ID: $item_id, User ID: " . $_SESSION['user_id']);
            }
        } catch (Exception $e) {
            error_log("Exception in donation creation: " . $e->getMessage());
            echo "<script>alert('Error creating donation: " . $e->getMessage() . "');</script>";
        }
    }

    // Get search and filter parameters
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $expiry_filter = $_GET['expiry'] ?? '';

    // Build the query to fetch food inventory
    $query = "SELECT *, COALESCE(reserved_quantity, 0) AS reserved_quantity FROM food_inventory WHERE user_id = ?";
    $params = [$_SESSION['user_id']];

    if (!empty($search)) {
        $query .= " AND (item_name LIKE ? OR notes LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($category_filter)) {
        $query .= " AND category = ?";
        $params[] = $category_filter;
    }

    if (!empty($expiry_filter)) {
        switch ($expiry_filter) {
            case 'expired':
                $query .= " AND expiry_date < CURDATE()";
                break;
            case 'expiring_soon':
                $query .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'expiring_month':
                $query .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    $query .= " ORDER BY expiry_date ASC, created_at DESC";

    // Execute the query
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $food_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $food_items = [];
        error_log("Database error in view_inventory.php: " . $e->getMessage());
        // Don't show the error to users, just set empty array
    }

    // Get unique categories for filter
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT category FROM food_inventory WHERE user_id = ? AND category IS NOT NULL ORDER BY category");
        $stmt->execute([$_SESSION['user_id']]);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $categories = [];
        error_log("Error fetching categories: " . $e->getMessage());
    }

    // Calculate statistics
    try {
        $total_items = count($food_items);
        $expired_items = 0;
        $expiring_soon = 0;
        $total_value = 0;

        foreach ($food_items as $item) {
            // Safely check expiry date
            $expiry_date = $item['expiry_date'] ?? null;
            if ($expiry_date && strtotime($expiry_date) !== false) {
                if (strtotime($expiry_date) < time()) {
                    $expired_items++;
                } elseif (strtotime($expiry_date) <= strtotime('+7 days')) {
                    $expiring_soon++;
                }
            }
            
            // Safely calculate total value with proper numeric validation
            $quantity = $item['quantity'] ?? 1;
            if (is_numeric($quantity)) {
                $total_value += floatval($quantity) * 5; // Convert to float and multiply
            } else {
                $total_value += 5; // Default value if quantity is not numeric
            }
        }

        // Calculate total money saved from claimed donations
        $total_money_saved = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as claimed_count FROM donations WHERE user_id = ? AND status = 'claimed'");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $claimed_donations = $result['claimed_count'] ?? 0;
            $total_money_saved = $claimed_donations * 5; // RM5 per claimed donation
        } catch (Exception $e) {
            error_log("Error calculating money saved: " . $e->getMessage());
            $total_money_saved = 0;
        }
    } catch (Exception $e) {
        // Set default values if calculation fails
        $total_items = 0;
        $expired_items = 0;
        $expiring_soon = 0;
        $total_value = 0;
        $total_money_saved = 0;
        error_log("Error calculating statistics: " . $e->getMessage());
    }

    $current_page = basename($_SERVER['PHP_SELF']);

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo "<script>alert('All password fields are required');</script>";
        } elseif ($new_password !== $confirm_password) {
            echo "<script>alert('New passwords do not match');</script>";
        } elseif (strlen($new_password) < 6) {
            echo "<script>alert('New password must be at least 6 characters long');</script>";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($passwordData && isset($passwordData['password']) && password_verify($current_password, $passwordData['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed, $_SESSION['user_id']]);
                    echo "<script>alert('Password changed successfully');</script>";
                } else {
                    echo "<script>alert('Current password is incorrect');</script>";
                }
            } catch (Exception $e) {
                echo "<script>alert('Error changing password. Please try again.');</script>";
            }
        }
    }
    
    // Handle logout
    if (isset($_POST['logout'])) {
        // Destroy session and redirect to mainpage_aftlogin.php
        session_destroy();
        header("Location: mainpage_aftlogin.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - View Inventory Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --secondary: #FF9800;
            --secondary-dark: #F57C00;
            --accent: #8BC34A;
            --danger: #F44336;
            --warning: #FFC107;
            --success: #4CAF50;
            --info: #2196F3;
            --light-bg: #F1F8E9;
            --dark-text: #1B5E20;
            --light-text: #FFFFFF;
            --card-bg: #FFFFFF;
            --shadow: 0 4px 12px rgba(76, 175, 80, 0.15);
            --shadow-hover: 0 8px 20px rgba(76, 175, 80, 0.25);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F5F5F5;
            color: #2C3E50;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: #2C3E50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: width 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            cursor: pointer;
        }

        .sidebar-header i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .brand-text { display: inline; }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }

        .nav-link:hover {
            background-color: #34495E;
            color: white;
            border-left-color: var(--primary);
        }

        .nav-link.active {
            background-color: var(--primary);
            color: white;
            border-left-color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-link .label { white-space: nowrap; }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2C3E50;
            font-size: 1rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafafa;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            transform: translateY(-1px);
        }

        .form-group input[readonly],
        .form-group textarea[readonly] {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            border-color: #dee2e6;
        }

        .item-details-card {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border: 2px solid #c3e6cb;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .item-details-card h4 {
            margin: 0 0 15px 0;
            color: var(--primary-dark);
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .item-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .item-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .item-detail:last-child {
            border-bottom: none;
        }

        .item-detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        .item-detail-value {
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Professional Edit Item Modal Styles */
        .edit-item-modal {
            width: 600px;
            max-width: 95%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .edit-item-modal .modal-content {
            display: flex;
            flex-direction: column;
        }

        .edit-item-modal .modal-header {
            width: 100%;
            margin: 0;
            flex-shrink: 0;
        }

        /* Professional Donation Modal Styles */
        .donation-modal {
            width: 600px;
            max-width: 95%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .donation-modal .modal-content {
            display: flex;
            flex-direction: column;
        }

        .donation-modal .modal-header {
            width: 100%;
            margin: 0;
            flex-shrink: 0;
        }

        /* Professional form styling for donation modal */
        #donationModal .form-group {
            margin-bottom: 28px;
        }

        #donationModal .form-group label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        #donationModal .form-group label i {
            color: #10b981;
            width: 18px;
            font-size: 16px;
        }

        #donationModal .form-group input,
        #donationModal .form-group textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            background: #ffffff;
            transition: all 0.2s ease;
            font-family: inherit;
            color: #111827;
        }

        #donationModal .form-group input:focus,
        #donationModal .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #f9fafb;
        }

        #donationModal .form-group input::placeholder,
        #donationModal .form-group textarea::placeholder {
            color: #9ca3af;
            font-style: italic;
        }

        #donationModal .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        /* Professional actions styling for donation modal */
        #donationModal .actions {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 16px;
            justify-content: flex-end;
        }

        #donationModal .actions .btn {
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            min-width: 150px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        #donationModal .actions .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        #donationModal .actions .btn:hover::before {
            left: 100%;
        }

        #donationModal .actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #donationModal .actions .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        #donationModal .actions .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        #donationModal .actions .btn-secondary {
            background: #ffffff;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        #donationModal .actions .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
        }

        .edit-item-info-card {
            background: linear-gradient(135deg, #f8fffe 0%, #f0fdf4 100%);
            border: 1px solid #d1fae5;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            overflow: hidden;
        }

        .edit-item-info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #059669 50%, #047857 100%);
        }

        .edit-item-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);
            position: relative;
        }

        .edit-item-icon::after {
            content: '';
            position: absolute;
            inset: 2px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border-radius: 14px;
            pointer-events: none;
        }

        .edit-item-icon i {
            font-size: 32px;
            color: white;
            z-index: 1;
        }

        .edit-item-info h3 {
            margin: 0 0 8px 0;
            color: #064e3b;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .edit-item-info p {
            margin: 0;
            color: #065f46;
            font-size: 1rem;
            opacity: 0.8;
            line-height: 1.5;
        }

        .form-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-section-title {
            color: #111827;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 24px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
            position: relative;
        }

        .form-section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .form-section-title i {
            color: #10b981;
            font-size: 1.1rem;
        }

        /* Professional form styling for edit modal */
        #editItemModal .form-group {
            margin-bottom: 28px;
        }

        #editItemModal .form-group label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        #editItemModal .form-group label i {
            color: #10b981;
            width: 18px;
            font-size: 16px;
        }

        #editItemModal .form-group input,
        #editItemModal .form-group textarea,
        #editItemModal .form-group select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            background: #ffffff;
            transition: all 0.2s ease;
            font-family: inherit;
            color: #111827;
        }

        #editItemModal .form-group input:focus,
        #editItemModal .form-group textarea:focus,
        #editItemModal .form-group select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #f9fafb;
        }

        #editItemModal .form-group input::placeholder,
        #editItemModal .form-group textarea::placeholder {
            color: #9ca3af;
            font-style: italic;
        }

        #editItemModal .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        /* Professional actions styling */
        #editItemModal .actions {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 16px;
            justify-content: flex-end;
        }

        #editItemModal .actions .btn {
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            min-width: 150px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        #editItemModal .actions .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        #editItemModal .actions .btn:hover::before {
            left: 100%;
        }

        #editItemModal .actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #editItemModal .actions .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        #editItemModal .actions .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        #editItemModal .actions .btn-secondary {
            background: #ffffff;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        #editItemModal .actions .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
        }

        /* Professional item details card */
        .professional-details-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .professional-details-card h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .professional-details-card h4::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .professional-details-card h4 i {
            color: #10b981;
            font-size: 1rem;
        }

        .professional-details-card .item-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .professional-details-card .item-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 16px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .professional-details-card .item-detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .professional-details-card .item-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #e0a800 100%);
            color: #000;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        /* Notification dot on nav bell */
        .nav-link .notif-dot {
            position: absolute;
            top: 6px;
            left: 36px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 10px;
            background: #F44336;
            color: #fff;
            font-size: 10px;
            line-height: 16px;
            text-align: center;
            font-weight: 700;
        }
        .sidebar.collapsed .nav-link .notif-dot { left: 38px; }

        /* Collapsed state */
        .sidebar.collapsed { width: 72px; }
        .sidebar.collapsed .brand-text { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; }
        .sidebar.collapsed .nav-link .label { display: none; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            position: relative; /* Added for proper z-index context */
            z-index: 1; /* Lower than modal */
        }
        .sidebar.collapsed + .main-content { margin-left: 72px; }
        body.sidebar-collapsed .main-content { margin-left: 72px; }

        .container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            margin-bottom: 30px;
            flex: 1;
            z-index: 1; /* Lower than modal */
        }



        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 30px 0 24px 0;
            color: var(--dark-text);
            padding: 0 20px;
        }

        .section-title i {
            color: var(--primary);
            font-size: 24px;
        }

        /* Page Header Styling */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--light-text);
            padding: 30px;
            text-align: left;
            position: relative;
            overflow: hidden;
            margin-bottom: 0;
            border-radius: 16px 16px 0 0;
        }

        .page-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>');
            background-size: 30px 30px;
            opacity: 0.3;
        }

        .page-title {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .page-title::before {
            content: "🍃";
            margin-right: 10px;
        }

        .page-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-left: 65px;
            position: relative;
            z-index: 1;
        }

        /* User Menu Styling */
        .user-menu {
            position: absolute;
            top: 30px;
            right: 30px;
            z-index: 1000;
            padding-top: 35px;
            padding-right: 40px;
        }

        .user-btn {
            background: var(--primary);
            color: white;
            border: 2px solid var(--primary-dark);
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .user-btn:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .user-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 10px;
            background: #fff;
            border-radius: 10px;
            box-shadow: var(--shadow-hover);
            width: 260px;
            overflow: hidden;
            z-index: 5000;
        }

        .user-dropdown.show {
            display: block;
        }


        .user-profile {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .user-profile .name {
            font-size: 1rem;
            font-weight: 600;
            color: #2C3E50;
        }

        .user-profile .email {
            font-size: 0.9rem;
            color: #7F8C8D;
            margin-top: 4px;
        }

        .user-dropdown .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #2C3E50;
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .user-dropdown .menu-item:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .logout-btn {
            color: var(--danger) !important;
            font-weight: 600;
        }

        /* Enhanced Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 0;
            border-radius: 20px;
            width: 550px;
            max-width: 95%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid #e9ecef;
            animation: slideIn 0.3s ease;
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 10000;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            position: relative;
            width: 100%;
            box-sizing: border-box;
            margin: 0;
            flex-shrink: 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h2 i {
            font-size: 1.3rem;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
        }

        /* Delete Modal Special Styling */
        .delete-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .delete-warning-card {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 2px solid #feb2b2;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.1);
        }

        .warning-icon {
            font-size: 3rem;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .warning-content h3 {
            margin: 0 0 15px 0;
            color: #dc3545;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 10px 0;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            display: inline-block;
        }

        .warning-message {
            color: #e53e3e;
            font-size: 0.95rem;
            margin: 15px 0 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
            appearance: none;
            cursor: pointer;
        }

        select.form-control:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234CAF50' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        }

        .form-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }

        /* Quantity Selection Styles */
        .quantity-selection {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .quantity-selection input[type="number"] {
            width: 100px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }

        .quantity-selection input[type="number"]:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }

        .quantity-info {
            color: #4CAF50;
            font-weight: 600;
            font-size: 14px;
        }

        /* Profile Modal Styles */
        .profile-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .profile-avatar i {
            font-size: 30px;
            color: white;
        }

        .profile-details h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .profile-details p {
            margin: 0;
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* Settings Modal Styles */
        .password-info-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .password-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .password-icon i {
            font-size: 30px;
            color: white;
        }

        .password-info h3 {
            margin: 0 0 5px 0;
            color: #856404;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .password-info p {
            margin: 0;
            color: #856404;
            font-size: 0.95rem;
            opacity: 0.8;
        }

        /* Enhanced form styling for profile and settings */
        #profileModal .form-group,
        #settingsModal .form-group {
            margin-bottom: 20px;
        }

        #profileModal .form-group label,
        #settingsModal .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        #profileModal .form-group label i,
        #settingsModal .form-group label i {
            color: #4CAF50;
            width: 16px;
        }

        #profileModal input[readonly],
        #settingsModal input[readonly] {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }

        #profileModal input[readonly]:focus,
        #settingsModal input[readonly]:focus {
            background-color: #ffffff;
            border-color: #4CAF50;
            color: #495057;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }

        /* Content area spacing */
        .tab-content {
            padding: 20px 0;
        }

        /* Inventory Statistics */
        .inventory-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card i {
            font-size: 2rem;
            color: var(--primary);
        }

        .stat-card.warning i {
            color: var(--warning);
        }

        .stat-card.danger i {
            color: var(--danger);
        }

        .stat-card.success i {
            color: var(--success);
        }

        .stat-card.info i {
            color: var(--info);
        }

        .stat-content h3 {
            font-size: 1.8rem;
            margin: 0;
            color: var(--dark-text);
        }

        .stat-content p {
            margin: 0;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Enhanced Search and Filter Section */
        .search-filter-section {
            background: #f8f9fa;
            margin: 0 20px 20px 20px;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #2196F3;
        }

        .search-filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .search-filter-top {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-container {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input-container input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        .search-input-container input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .search-input-container input.searching {
            background: #f8f9fa;
            border-color: #2196F3;
        }

        .search-loading {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #2196F3;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .search-loading.show {
            opacity: 1;
        }

        .filter-dropdowns {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-dropdowns select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-dropdowns select:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .btn-clear {
            padding: 12px 15px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-clear:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .search-filter-bottom {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-refresh {
            padding: 12px 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-refresh:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .results-count {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        /* Inventory Table Section */
        .inventory-table-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            margin: 0 20px 20px 20px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 25px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(76, 175, 80, 0.1);
            overflow: hidden;
            position: relative;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px 40px;
            background: linear-gradient(135deg, #f8fffe 0%, #ffffff 100%);
            border-bottom: 2px solid rgba(76, 175, 80, 0.1);
            flex-wrap: wrap;
            gap: 20px;
        }

        .table-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header h2 i {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            filter: drop-shadow(0 2px 4px rgba(76, 175, 80, 0.3));
        }

        .table-container {
            overflow-x: auto;
        }

        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .inventory-table th,
        .inventory-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid rgba(76, 175, 80, 0.1);
        }

        .inventory-table th {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            font-weight: 700;
            color: white;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .inventory-table tbody tr {
            transition: all 0.3s ease;
            position: relative;
        }

        .inventory-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fff8 0%, #ffffff 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.1);
        }

        .inventory-table tbody tr:last-child td {
            border-bottom: none;
        }

        .inventory-table tr.expired {
            background: linear-gradient(135deg, #ffebee 0%, #ffffff 100%);
        }

        .inventory-table tr.expiring-soon {
            background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%);
        }

        .item-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .item-notes {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .expiry-date {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .expiry-date.expired {
            color: #e74c3c;
        }

        .expiry-date.expiring-soon {
            color: #f39c12;
        }

        .expiry-date.good {
            color: #27ae60;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .status-badge.expired {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .status-badge.expiring-soon {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #ef6c00;
            border: 1px solid #ff9800;
        }

        .status-badge.good {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            border: 1px solid #4CAF50;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .add-item-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 14px 28px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .add-item-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .add-item-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(76, 175, 80, 0.4);
            border-color: #2e7d32;
        }

        .add-item-btn:hover::before {
            left: 100%;
        }

        .add-item-btn i {
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 25px;
            color: #cbd5e0;
            opacity: 0.8;
            display: block;
        }

        .empty-state h3 {
            margin-bottom: 15px;
            color: #2d3748;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .empty-state p {
            margin-bottom: 35px;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
            color: #718096;
            font-size: 1rem;
            line-height: 1.6;
        }

        .empty-state .btn {
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .empty-state .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        /* Warning Text */
        .warning-text {
            color: var(--danger);
            font-weight: 500;
            margin-bottom: 20px;
        }

        /* Footer Styles */
        .footer {
            background-color: #2C3E50;
            color: white;
            padding: 20px 30px;
            text-align: center;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1; /* Lower than modal */
        }

        .sidebar.collapsed ~ .footer {
            margin-left: 72px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-copyright {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social a {
            color: white;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .footer-social a:hover {
            color: var(--primary-light);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .footer {
                margin-left: 0;
                padding: 20px;
            }

            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .mobile-menu-toggle {
                display: block;
            }
            
            .category-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .category-card {
                padding: 20px;
            }
            
            .category-card i {
                font-size: 2rem;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .section-title {
                margin: 20px 0 20px 0;
                padding: 0 15px;
            }
            
            .category-selection {
                padding: 0 15px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .food-items-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
            }
            
            .food-item-card {
                padding: 15px;
            }
            
            .food-item-card i {
                font-size: 1.5rem;
            }

            /* Mobile responsive for inventory */
            .inventory-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-content h3 {
                font-size: 1.5rem;
            }

            .search-filter-section {
                margin: 0 15px 15px 15px;
                padding: 15px;
            }

            .search-filter-top {
                flex-direction: column;
                gap: 12px;
            }

            .search-input-container {
                min-width: auto;
            }

            .filter-dropdowns {
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }

            .filter-dropdowns select {
                min-width: auto;
                width: 100%;
            }

            .search-filter-bottom {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .btn-clear,
            .btn-refresh {
                width: 100%;
                justify-content: center;
            }

            .inventory-table-section {
                margin: 0 15px 15px 15px;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 10px 8px;
                font-size: 0.9rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* Expired Item Popup Styles */
        .expired-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .expired-popup-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 3px solid #dc3545;
        }

        .expired-popup-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .expired-popup-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .expired-popup-message {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 1rem;
        }

        .expired-popup-message span {
            font-weight: 600;
            color: #dc3545;
        }

        .expired-popup-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .expired-popup-actions .btn {
            padding: 12px 25px;
            font-size: 1rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .expired-popup-actions .btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 5% auto;
                border-radius: 15px;
            }

            .modal-header {
                padding: 20px 25px;
            }

            .modal-header h2 {
                font-size: 1.3rem;
            }

            .modal-body {
                padding: 25px 20px;
            }

            .item-details-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .actions {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 16px 24px;
            }

            .delete-warning-card {
                padding: 20px 15px;
            }

            .warning-icon {
                font-size: 2.5rem;
            }

            .warning-content h3 {
                font-size: 1.1rem;
            }

            .warning-message {
                font-size: 0.9rem;
                flex-direction: column;
                gap: 5px;
            }

            .expired-popup-content {
                padding: 30px 20px;
                margin: 20px;
            }

            .expired-popup-icon {
                font-size: 3rem;
            }

            .expired-popup-title {
                font-size: 1.3rem;
            }

            .expired-popup-message {
                font-size: 0.9rem;
            }
        }
    </style>   
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 id="brand">
                <i class="fas fa-leaf"></i>
                <span class="brand-text">SavePlate</span>
            </h2>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/bit216_assignment/mainpage_aftlogin.php" class="nav-link <?php echo $current_page == 'mainpage_aftlogin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="label">Home</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/add_item.php" class="nav-link <?php echo $current_page == 'add_item.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span class="label">Add Item</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/view_inventory.php" class="nav-link <?php echo $current_page == 'view_inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i>
                    <span class="label">View Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/mydonation.php" class="nav-link <?php echo $current_page == 'inventory_interface.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span class="label">My Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/claimed_donation.php" class="nav-link <?php echo $current_page == 'available_donation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="label">Claimed Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/meal_plan1.php" class="nav-link <?php echo $current_page == 'meal_plan1.php' ? 'active' : ''; ?>">
                    <i class="fas fa-utensils"></i>
                    <span class="label">Meal Plan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/food_analytics_dashboard.php" class="nav-link <?php echo $current_page == 'food_analytics_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-poll"></i>
                    <span class="label">Food Analysis</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/notification.php" class="nav-link <?php echo $current_page == 'notification.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span class="label">Notifications</span>
                    <span class="notif-dot" id="sidebar-notification-count" style="display: none;">0</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- User Menu -->
        <div class="user-menu">
            <button class="user-btn" id="userBtn">
                <?php echo htmlspecialchars(explode(' ', $userData['username'])[0]); ?> 
                <i class="fas fa-chevron-down"></i>
            </button>

            <div class="user-dropdown" id="userDropdown">
                <div class="user-profile">
                    <div class="name"><?php echo htmlspecialchars($userData['username']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($userData['email']); ?></div>
                </div>
                <hr>
                <a href="javascript:void(0)" class="menu-item" onclick="openProfileModal()">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="javascript:void(0)" class="menu-item" onclick="openSettingsModal()"><i class="fas fa-cog"></i> Settings</a>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="menu-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">View Your Food Inventory</h1>
                <p class="page-subtitle">Manage your food items and reduce waste</p>
            </div>

            <!-- Inventory Statistics -->
            <div class="inventory-stats">
                <div class="stat-card">
                    <i class="fas fa-boxes"></i>
                    <div class="stat-content">
                        <h3><?php echo $total_items; ?></h3>
                        <p>Total Items</p>
                    </div>
                </div>
                <div class="stat-card danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="stat-content">
                        <h3><?php echo $expired_items; ?></h3>
                        <p>Expired Items</p>
                    </div>
                </div>
                <div class="stat-card warning">
                    <i class="fas fa-clock"></i>
                    <div class="stat-content">
                        <h3><?php echo $expiring_soon; ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                </div>
                <div class="stat-card success">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-content">
                        <h3>RM<?php echo number_format($total_value, 2); ?></h3>
                        <p>Total Value</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filter-section">
                <form method="GET" class="search-filter-form">
                    <!-- Top Row: Search and Filters -->
                    <div class="search-filter-top">
                        <div class="search-input-container">
                            <input type="text" name="search" placeholder="Search by item name, category, or notes..." value="<?php echo htmlspecialchars($search); ?>">
                            <div class="search-loading">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="filter-dropdowns">
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): 
                                    // Format category name for display
                                    $categoryMap = [
                                        'fruits_vegetables' => 'Fruits & Vegetables',
                                        'dairy_eggs' => 'Dairy & Eggs',
                                        'meat_fish' => 'Meat & Fish',
                                        'grains_cereals' => 'Grains & Cereals',
                                        'canned_goods' => 'Canned Goods',
                                        'beverages' => 'Beverages',
                                        'snacks' => 'Snacks',
                                        'condiments' => 'Condiments',
                                        'frozen_foods' => 'Frozen Foods',
                                        'other' => 'Other'
                                    ];
                                    
                                    $displayCategory = $categoryMap[$category] ?? ucwords(str_replace('_', ' & ', $category));
                                ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($displayCategory); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="expiry">
                                <option value="">All Status</option>
                                <option value="expired" <?php echo $expiry_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="expiring_soon" <?php echo $expiry_filter === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                                <option value="expiring_month" <?php echo $expiry_filter === 'expiring_month' ? 'selected' : ''; ?>>Expiring This Month</option>
                            </select>
                        </div>
                        <button type="button" onclick="clearFilters()" class="btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    
                    <!-- Bottom Row: Refresh Button and Results Count -->
                    <div class="search-filter-bottom">
                        <button type="submit" class="btn-refresh">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <span class="results-count">Showing <?php echo count($food_items ?? []); ?> of <?php echo count($food_items ?? []); ?> items</span>
                    </div>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-table-section">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Food Inventory</h2>
                    <a href="add_item.php" class="btn btn-primary add-item-btn"><i class="fas fa-plus"></i> Add New Item</a>
                </div>
                
                <?php if (empty($food_items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No food items found</h3>
                        <p>Start building your food inventory by adding items from the Add Item page.</p>
                        <a href="add_item.php" class="btn btn-primary">Add Your First Item</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Reserved</th>
                                    <th>Storage Location</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($food_items as $item): ?>
                                    <?php
                                        $expiry_date = new DateTime($item['expiry_date']);
                                        $today = new DateTime();
                                        $days_until_expiry = $today->diff($expiry_date)->days;
                                        $is_expired = $expiry_date < $today;
                                        $is_expiring_soon = $days_until_expiry <= 7 && !$is_expired;
                                        
                                        if ($is_expired) {
                                            $status_class = 'expired';
                                            $status_text = 'Expired';
                                        } elseif ($is_expiring_soon) {
                                            $status_class = 'expiring-soon';
                                            $status_text = 'Expiring Soon';
                                        } else {
                                            $status_class = 'good';
                                            $status_text = 'Good';
                                        }
                                    ?>
                                    <tr class="<?php echo $status_class; ?>">
                                        <td>
                                            <div class="item-info">
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if (!empty($item['notes'])): ?>
                                                    <small class="item-notes"><?php echo htmlspecialchars($item['notes']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php 
                                            // Format category name for display
                                            $category = $item['category'] ?? 'Uncategorized';
                                            if ($category === 'Uncategorized') {
                                                echo htmlspecialchars($category);
                                            } else {
                                                // Convert database format to display format
                                                $categoryMap = [
                                                    'fruits_vegetables' => 'Fruits & Vegetables',
                                                    'dairy_eggs' => 'Dairy & Eggs',
                                                    'meat_fish' => 'Meat & Fish',
                                                    'grains_cereals' => 'Grains & Cereals',
                                                    'canned_goods' => 'Canned Goods',
                                                    'beverages' => 'Beverages',
                                                    'snacks' => 'Snacks',
                                                    'condiments' => 'Condiments',
                                                    'frozen_foods' => 'Frozen Foods',
                                                    'other' => 'Other'
                                                ];
                                                
                                                $displayCategory = $categoryMap[$category] ?? ucwords(str_replace('_', ' & ', $category));
                                                echo htmlspecialchars($displayCategory);
                                            }
                                        ?></td>
                                        <td><?php 
                                            $quantity_str = $item['quantity'];
                                            $reserved_quantity = (float)($item['reserved_quantity'] ?? 0);
                                            
                                            // Extract number and unit from quantity string
                                            if (preg_match('/(\d+(?:\.\d+)?)\s*(.*)/', $quantity_str, $matches)) {
                                                $total_number = (float)$matches[1];
                                                $unit = trim($matches[2]);
                                                
                                                // Calculate available quantity (total - reserved)
                                                $available_number = max(0, $total_number - $reserved_quantity);
                                                
                                                echo htmlspecialchars(number_format($available_number, 0, '.', '') . ($unit ? ' ' . $unit : ''));
                                            } else {
                                                $total_number = (float)$quantity_str;
                                                $available_number = max(0, $total_number - $reserved_quantity);
                                                echo htmlspecialchars(number_format($available_number, 0, '.', ''));
                                            }
                                        ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float)($item['reserved_quantity'] ?? 0), 0, '.', '')); ?></td>
                                        <td><?php echo htmlspecialchars($item['storage_location'] ?? 'Not specified'); ?></td>
                                        <td>
                                            <span class="expiry-date <?php echo $status_class; ?>">
                                                <?php echo $expiry_date->format('M d, Y'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php 
                                                    // Calculate available quantity to determine if donate button should show
                                                    $quantity_str = $item['quantity'];
                                                    $reserved_quantity = (float)($item['reserved_quantity'] ?? 0);
                                                    
                                                    if (preg_match('/(\d+(?:\.\d+)?)\s*(.*)/', $quantity_str, $matches)) {
                                                        $total_number = (float)$matches[1];
                                                        $unit = trim($matches[2]);
                                                        $available_number = max(0, $total_number - $reserved_quantity);
                                                        $available_quantity_text = number_format($available_number, 0, '.', '') . ($unit ? ' ' . $unit : '');
                                                    } else {
                                                        $total_number = (float)$quantity_str;
                                                        $available_number = max(0, $total_number - $reserved_quantity);
                                                        $available_quantity_text = number_format($available_number, 0, '.', '');
                                                    }
                                                    
                                                    // Only show donate button if available quantity is greater than 0
                                                    if ($available_number > 0) {
                                                ?>
                                                <button class="btn btn-sm btn-success" onclick="donateItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', '<?php echo htmlspecialchars($item['category']); ?>', '<?php echo htmlspecialchars($available_quantity_text); ?>', '<?php echo $item['expiry_date']; ?>')" title="Donate this item">
                                                    <i class="fas fa-heart"></i>
                                                </button>
                                                <?php } ?>
                                                <button class="btn btn-sm btn-outline" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', '<?php echo htmlspecialchars($item['category']); ?>', '<?php 
                                                    $quantity_str = $item['quantity'];
                                                    $reserved_quantity = (float)($item['reserved_quantity'] ?? 0);
                                                    
                                                    if (preg_match('/(\d+(?:\.\d+)?)\s*(.*)/', $quantity_str, $matches)) {
                                                        $total_number = (float)$matches[1];
                                                        $unit = trim($matches[2]);
                                                        $available_number = max(0, $total_number - $reserved_quantity);
                                                        echo htmlspecialchars(number_format($available_number, 0, '.', '') . ($unit ? ' ' . $unit : ''));
                                                    } else {
                                                        $total_number = (float)$quantity_str;
                                                        $available_number = max(0, $total_number - $reserved_quantity);
                                                        echo htmlspecialchars(number_format($available_number, 0, '.', ''));
                                                    }
                                                ?>', '<?php echo $item['expiry_date']; ?>', '<?php echo htmlspecialchars($item['storage_location'] ?? ''); ?>', '<?php echo htmlspecialchars($item['notes'] ?? ''); ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> User Profile</h2>
                <span class="close-btn" onclick="closeProfileModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="profileForm">
                    <div class="profile-info-card">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-details">
                            <h3><?php echo htmlspecialchars($userData['username']); ?></h3>
                            <p><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Household Size</label>
                        <input type="number" name="household_size" value="<?php echo htmlspecialchars($household_size); ?>" min="1" readonly class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" readonly class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    
                    <div class="actions">
                        <button type="button" id="editProfileBtn" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeProfileModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <span class="close-btn" onclick="closeSettingsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="password-info-card">
                        <div class="password-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="password-info">
                            <h3>Security Settings</h3>
                            <p>Update your password to keep your account secure</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" name="current_password" required class="form-control" placeholder="Enter your current password">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" required class="form-control" placeholder="Enter your new password">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="form-control" placeholder="Confirm your new password">
                    </div>
                    
                    <div class="actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <button type="button" onclick="closeSettingsModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="modal edit-item-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Item Details</h2>
                <span class="close-btn" onclick="closeEditItemModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editItemForm">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    
                    <!-- Professional Item Info Header -->
                    <div class="edit-item-info-card">
                        <div class="edit-item-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="edit-item-info">
                            <h3 id="edit_item_title">Food Item Details</h3>
                            <p>View and edit your food inventory item information</p>
                        </div>
                    </div>
                    
                    <!-- Read-only view section -->
                    <div id="readonly-view">
                        <div class="item-details-card professional-details-card">
                            <h4><i class="fas fa-info-circle"></i> Item Details</h4>
                            <div class="item-details-grid">
                                <div class="item-detail">
                                    <span class="item-detail-label">Item Name:</span>
                                    <span class="item-detail-value" id="view_item_name">-</span>
                                </div>
                                
                                <div class="item-detail">
                                    <span class="item-detail-label">Quantity:</span>
                                    <span class="item-detail-value" id="view_quantity">-</span>
                                </div>
                                
                                <div class="item-detail">
                                    <span class="item-detail-label">Expiry Date:</span>
                                    <span class="item-detail-value" id="view_expiry_date">-</span>
                                </div>
                                
                                <div class="item-detail">
                                    <span class="item-detail-label">Storage Location:</span>
                                    <span class="item-detail-value" id="view_storage_location">-</span>
                                </div>
                                
                                <div class="item-detail">
                                    <span class="item-detail-label">Notes:</span>
                                    <span class="item-detail-value" id="view_notes">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button type="button" onclick="enableEditMode()" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Item
                            </button>
                            <button type="button" onclick="closeEditItemModal()" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    </div>
                    
                    <!-- Editable form section (hidden initially) -->
                    <div id="editable-form" style="display: none;">
                        <div class="form-section">
                            <h4 class="form-section-title"><i class="fas fa-edit"></i> Edit Information</h4>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Item Name</label>
                                <input type="text" name="item_name" id="edit_item_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-weight"></i> Quantity</label>
                                <input type="text" name="quantity" id="edit_quantity" placeholder="e.g., 3 bottles, 2 cans, 1 kg" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Expiry Date</label>
                                <input type="date" name="expiry_date" id="edit_expiry_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-box"></i> Storage Location</label>
                                <select name="storage_location" id="edit_storage_location">
                                    <option value="">Select Storage Location</option>
                                    <option value="Refrigerator">Refrigerator</option>
                                    <option value="Freezer">Freezer</option>
                                    <option value="Pantry">Pantry</option>
                                    <option value="Kitchen Cabinet">Kitchen Cabinet</option>
                                    <option value="Spice Rack">Spice Rack</option>
                                    <option value="Counter Top">Counter Top</option>
                                    <option value="Basement">Basement</option>
                                    <option value="Garage">Garage</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Notes</label>
                                <textarea name="notes" id="edit_notes" rows="3" placeholder="Any additional notes about this item"></textarea>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button type="submit" name="update_item" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Item
                            </button>
                            <button type="button" onclick="cancelEdit()" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header delete-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
                <span class="close-btn" onclick="closeDeleteConfirmModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="delete-warning-card">
                    <div class="warning-icon">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="warning-content">
                        <h3>Are you sure you want to delete this item?</h3>
                        <p class="item-name">"<span id="delete_item_name"></span>"</p>
                    </div>
                </div>
                
                <form method="POST" id="deleteItemForm">
                    <input type="hidden" name="item_id" id="delete_item_id">
                    <div class="actions">
                        <button type="submit" name="delete_item" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Item
                        </button>
                        <button type="button" onclick="closeDeleteConfirmModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-copyright">
                © 2014-2025 SavePlate. All rights reserved.
            </div>
            <div class="footer-social">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </footer>

    <!-- Donation Modal -->
    <div id="donationModal" class="modal donation-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-heart"></i> Convert to Donation</h2>
                <span class="close-btn" onclick="closeDonationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="donationForm">
                    <input type="hidden" name="item_id" id="donation_item_id">
                    <input type="hidden" name="item_category" id="donation_item_category_hidden">
                    
                    <!-- Professional Item Info Header -->
                    <div class="edit-item-info-card">
                        <div class="edit-item-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="edit-item-info">
                            <h3 id="donation_item_title">Convert to Donation</h3>
                            <p>Share your food items with others in need</p>
                        </div>
                    </div>
                    
                    <div class="item-details-card professional-details-card">
                        <h4><i class="fas fa-info-circle"></i> Item Details</h4>
                        <div class="item-details-grid">
                            <div class="item-detail">
                                <span class="item-detail-label">Item:</span>
                                <span class="item-detail-value" id="donation_item_name"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Category:</span>
                                <span class="item-detail-value" id="donation_item_category"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Quantity:</span>
                                <span class="item-detail-value" id="donation_item_quantity"></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">Expiry:</span>
                                <span class="item-detail-value" id="donation_item_expiry"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title"><i class="fas fa-edit"></i> Donation Information</h4>
                        
                        <div class="form-group">
                            <label><i class="fas fa-boxes"></i> Quantity to Donate *</label>
                            <div class="quantity-selection">
                                <input type="number" name="donation_quantity" id="donation_quantity" min="1" required>
                                <span class="quantity-info" id="quantity_info"></span>
                            </div>
                            <small class="form-hint">Enter how many items you want to donate from your inventory</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Pickup Location *</label>
                            <input type="text" name="pickup_location" id="donation_pickup_location" placeholder="e.g., 123 Main St, City, State" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Availability *</label>
                            <input type="text" name="availability" id="donation_availability" placeholder="e.g., Weekdays 9-5, Weekends 10-2" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea name="additional_notes" id="donation_notes" rows="3" placeholder="Any special instructions or notes for pickup..."></textarea>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" name="create_donation" class="btn btn-success">
                            <i class="fas fa-heart"></i> Create Donation
                        </button>
                        <button type="button" onclick="closeDonationModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Expired Item Popup -->
    <div id="expiredItemPopup" class="expired-popup" style="display: none;">
        <div class="expired-popup-content">
            <div class="expired-popup-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="expired-popup-title">Cannot Donate Expired Item</div>
            <div class="expired-popup-message">
                The item "<span id="expired-item-name"></span>" has expired on <span id="expired-item-date"></span> and cannot be donated for safety reasons.
            </div>
            <div class="expired-popup-actions">
                <button id="expired-ok-btn" class="btn btn-primary">
                    <i class="fas fa-check"></i> I Understand
                </button>
            </div>
        </div>
    </div>

    <script>    

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
            console.log("Sidebar toggled");
        }

         // Desktop collapse/expand toggle via brand + persistence
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            const collapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            
            // Adjust footer margin when sidebar collapses/expands
            const footer = document.querySelector('.footer');
            if (collapsed) {
                footer.style.marginLeft = '72px';
            } else {
                footer.style.marginLeft = '280px';
            }
            
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
            console.log("Sidebar collapse toggled");
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
            }
        });

        // Restore collapsed state and wire brand click
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    document.getElementById('sidebar').classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    document.querySelector('.footer').style.marginLeft = '72px';
                }
            } catch(e) {}
            const brand = document.getElementById('brand');
            if (brand) brand.addEventListener('click', toggleSidebarCollapse);
            
            // Load unread count for notif dot (shared with notification page)
            function updateSidebarNotificationCount(){
                try{
                    var formData = new FormData();
                    formData.append('action','get_unread_count');
                    formData.append('user_id', <?php echo (int)($userData['id'] ?? 0); ?>);
                    fetch('notification_handler.php',{method:'POST',body:formData})
                        .then(function(r){return r.json();})
                        .then(function(data){
                            var badge = document.getElementById('sidebar-notification-count');
                            if(!badge) return;
                            var count = (data && data.success) ? (data.unread_count||0) : 0;
                            if(count>0){
                                badge.textContent = count;
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        })
                        .catch(function(){});
                }catch(e){}
            }
            updateSidebarNotificationCount();
            setInterval(updateSidebarNotificationCount,30000);
        });


        // Toggle user dropdown
        document.getElementById("userBtn").addEventListener("click", function () {
            const dropdown = document.getElementById("userDropdown");
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        });

        // Close dropdown if clicked outside
        window.addEventListener("click", function(e) {
            if (!document.getElementById("userBtn").contains(e.target) && 
                !document.getElementById("userDropdown").contains(e.target)) {
                document.getElementById("userDropdown").style.display = "none";
            }
        });

        function openProfileModal() {
        document.getElementById("profileModal").style.display = "flex";
        }

        function closeProfileModal() {
        document.getElementById("profileModal").style.display = "none";
        }

        // Close when clicking outside modal
        window.onclick = function(event) {
        let modal = document.getElementById("profileModal");
        if (event.target === modal) {
            closeProfileModal();
        }
        }

        document.getElementById("editProfileBtn").addEventListener("click", function () {
        let inputs = document.querySelectorAll("#profileForm input, #profileForm textarea");

        inputs.forEach(el => {
            el.removeAttribute("readonly");
            el.style.background = "#fff"; // white when editable
        });

        document.getElementById("editProfileBtn").style.display = "none";
        document.getElementById("saveProfileBtn").style.display = "inline-block";
        });


        function openSettingsModal() {
        document.getElementById("settingsModal").style.display = "flex";
        const dd = document.getElementById('userDropdown');
        if (dd) dd.style.display = 'none';
        }
        function closeSettingsModal() {
        document.getElementById("settingsModal").style.display = "none";
        }

        document.addEventListener("DOMContentLoaded", function () {
            const userBtn = document.getElementById("userBtn");
            const userDropdown = document.getElementById("userDropdown");

            if (userBtn && userDropdown) {
                // Toggle dropdown on button click
                userBtn.addEventListener("click", function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle("show");
                });

                // Close dropdown when clicking outside
                document.addEventListener("click", function (e) {
                    if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove("show");
                    }
                });
            }
        });

        // Edit Item Function
        function editItem(itemId, itemName, category, quantity, expiryDate, storageLocation, notes) {
            // Set the item ID
            document.getElementById('edit_item_id').value = itemId;
            
            // Update the item title in the header
            document.getElementById('edit_item_title').textContent = itemName || 'Food Item Details';
            
            // Populate the read-only view with current data
            document.getElementById('view_item_name').textContent = itemName || '-';
            document.getElementById('view_quantity').textContent = quantity || '-';
            document.getElementById('view_expiry_date').textContent = expiryDate || '-';
            document.getElementById('view_storage_location').textContent = storageLocation || '-';
            document.getElementById('view_notes').textContent = notes || '-';
            
            // Populate the editable form with current data
            document.getElementById('edit_item_name').value = itemName || '';
            document.getElementById('edit_quantity').value = quantity || '';
            document.getElementById('edit_expiry_date').value = expiryDate || '';
            
            // Set the selected option for storage location dropdown
            const storageSelect = document.getElementById('edit_storage_location');
            if (storageLocation) {
                storageSelect.value = storageLocation;
            } else {
                storageSelect.value = '';
            }
            
            document.getElementById('edit_notes').value = notes || '';
            
            // Show read-only view and hide editable form
            document.getElementById('readonly-view').style.display = 'block';
            document.getElementById('editable-form').style.display = 'none';
            
            // Update modal header
            document.querySelector('#editItemModal .modal-header h2').innerHTML = '<i class="fas fa-eye"></i> Item Details';
            
            // Show the modal
            document.getElementById('editItemModal').style.display = 'flex';
            
            // Close user dropdown if open
            const dd = document.getElementById('userDropdown');
            if (dd) dd.classList.remove('show');
        }

        // Delete Item Function
        function deleteItem(itemId, itemName) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('delete_item_name').textContent = itemName;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            
            // Close user dropdown if open
            const dd = document.getElementById('userDropdown');
            if (dd) dd.classList.remove('show');
        }

        // Enable Edit Mode
        function enableEditMode() {
            // Hide read-only view and show editable form
            document.getElementById('readonly-view').style.display = 'none';
            document.getElementById('editable-form').style.display = 'block';
            
            // Update modal header
            document.querySelector('#editItemModal .modal-header h2').innerHTML = '<i class="fas fa-edit"></i> Edit Food Item';
        }

        // Cancel Edit Mode
        function cancelEdit() {
            // Show read-only view and hide editable form
            document.getElementById('readonly-view').style.display = 'block';
            document.getElementById('editable-form').style.display = 'none';
            
            // Update modal header
            document.querySelector('#editItemModal .modal-header h2').innerHTML = '<i class="fas fa-eye"></i> View Food Item';
        }

        // Close Edit Item Modal
        function closeEditItemModal() {
            document.getElementById('editItemModal').style.display = 'none';
        }

        // Close Delete Confirmation Modal
        function closeDeleteConfirmModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }

        // Donate Item Function
        function donateItem(itemId, itemName, category, quantity, expiryDate) {
            // Check if item is expired
            const today = new Date();
            const expiry = new Date(expiryDate);
            const isExpired = expiry < today;
            
            if (isExpired) {
                // Show expired item popup
                showExpiredItemPopup(itemName, expiryDate);
                return;
            }
            
            // Proceed with normal donation flow for non-expired items
            document.getElementById('donation_item_id').value = itemId;
            
            // Update the donation item title in the header
            document.getElementById('donation_item_title').textContent = itemName || 'Convert to Donation';
            
            document.getElementById('donation_item_name').textContent = itemName;
            document.getElementById('donation_item_category').textContent = category;
            document.getElementById('donation_item_category_hidden').value = category;
            console.log('Setting category value:', category);
            document.getElementById('donation_item_quantity').textContent = quantity;
            document.getElementById('donation_item_expiry').textContent = new Date(expiryDate).toLocaleDateString();
            
            // Set up quantity selection
            const quantityInput = document.getElementById('donation_quantity');
            const quantityInfo = document.getElementById('quantity_info');
            
            // More robust quantity parsing to match PHP logic
            let maxQuantity;
            const quantityMatch = quantity.match(/(\d+)/);
            if (quantityMatch) {
                maxQuantity = parseInt(quantityMatch[1]);
            } else {
                maxQuantity = parseInt(quantity);
            }
            
            // Debug logging
            console.log('Original quantity string:', quantity);
            console.log('Parsed maxQuantity:', maxQuantity);
            
            quantityInput.max = maxQuantity;
            quantityInput.value = 1; // Default to donating 1 for testing
            quantityInfo.textContent = `of ${maxQuantity} available`;
            
            // Add event listener for quantity changes
            quantityInput.addEventListener('input', function() {
                const selectedQuantity = parseInt(this.value);
                if (selectedQuantity > maxQuantity) {
                    this.value = maxQuantity;
                }
                if (selectedQuantity < 1) {
                    this.value = 1;
                }
            });
            
            document.getElementById('donationModal').style.display = 'flex';
            
            // Close user dropdown if open
            const dd = document.getElementById('userDropdown');
            if (dd) dd.style.display = 'none';
        }

        // Add form submission logging and validation
        let isSubmitting = false;
        document.addEventListener('DOMContentLoaded', function() {
            const donationForm = document.getElementById('donationForm');
            if (donationForm) {
                donationForm.addEventListener('submit', function(e) {
                    // Prevent multiple submissions
                    if (isSubmitting) {
                        e.preventDefault();
                        console.log('Form already submitting, preventing duplicate submission');
                        return false;
                    }
                    
                    const donationQuantity = parseInt(document.getElementById('donation_quantity').value);
                    const maxQuantity = parseInt(document.getElementById('donation_quantity').max);
                    
                    console.log('=== FORM SUBMISSION START ===');
                    console.log('Form submitting with donation_quantity:', donationQuantity);
                    console.log('Max quantity allowed:', maxQuantity);
                    console.log('Form data:', new FormData(this));
                    
                    // Validate quantity before submission
                    if (donationQuantity <= 0 || donationQuantity > maxQuantity) {
                        e.preventDefault();
                        alert('Invalid donation quantity. Please enter a valid amount between 1 and ' + maxQuantity);
                        return false;
                    }
                    
                    // Confirm donation
                    if (!confirm(`Are you sure you want to donate ${donationQuantity} items?`)) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Set submitting flag
                    isSubmitting = true;
                    console.log('=== FORM SUBMISSION PROCEEDING ===');
                });
            }
        });

        // Close Donation Modal
        function closeDonationModal() {
            document.getElementById('donationModal').style.display = 'none';
            // Reset form
            document.getElementById('donationForm').reset();
            // Reset submitting flag
            isSubmitting = false;
        }

        // Show Expired Item Popup
        function showExpiredItemPopup(itemName, expiryDate) {
            document.getElementById('expired-item-name').textContent = itemName;
            document.getElementById('expired-item-date').textContent = new Date(expiryDate).toLocaleDateString();
            document.getElementById('expiredItemPopup').style.display = 'flex';
            
            // Close user dropdown if open
            const dd = document.getElementById('userDropdown');
            if (dd) dd.style.display = 'none';
        }

        // Close Expired Item Popup
        function closeExpiredItemPopup() {
            document.getElementById('expiredItemPopup').style.display = 'none';
        }

        // Clear Filters Function
        function clearFilters() {
            // Clear all form inputs
            document.querySelector('input[name="search"]').value = '';
            document.querySelector('select[name="category"]').value = '';
            document.querySelector('select[name="expiry"]').value = '';
            
            // Submit the form to refresh with cleared filters
            document.querySelector('.search-filter-form').submit();
        }

        // Real-time Search Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            const categorySelect = document.querySelector('select[name="category"]');
            const expirySelect = document.querySelector('select[name="expiry"]');
            const form = document.querySelector('.search-filter-form');
            const loadingIndicator = document.querySelector('.search-loading');
            
            // Auto-submit form when search input changes (with debounce)
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    
                    // Show loading indicator
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('show');
                        searchInput.classList.add('searching');
                    }
                    
                    searchTimeout = setTimeout(function() {
                        form.submit();
                    }, 500); // Wait 500ms after user stops typing
                });
                
                // Also submit on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(searchTimeout);
                        if (loadingIndicator) {
                            loadingIndicator.classList.add('show');
                            searchInput.classList.add('searching');
                        }
                        form.submit();
                    }
                });
            }
            
            // Auto-submit form when filter dropdowns change
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('show');
                    }
                    form.submit();
                });
            }
            
            if (expirySelect) {
                expirySelect.addEventListener('change', function() {
                    if (loadingIndicator) {
                        loadingIndicator.classList.add('show');
                    }
                    form.submit();
                });
            }
        });

        // Event listener for expired popup button
        document.getElementById('expired-ok-btn').addEventListener('click', function() {
            closeExpiredItemPopup();
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const profileModal = document.getElementById("profileModal");
            const settingsModal = document.getElementById("settingsModal");
            const editItemModal = document.getElementById("editItemModal");
            const deleteConfirmModal = document.getElementById("deleteConfirmModal");
            const donationModal = document.getElementById("donationModal");
            const expiredItemPopup = document.getElementById("expiredItemPopup");
            
            if (event.target === profileModal) {
                closeProfileModal();
            }
            if (event.target === settingsModal) {
                closeSettingsModal();
            }
            if (event.target === editItemModal) {
                closeEditItemModal();
            }
            if (event.target === deleteConfirmModal) {
                closeDeleteConfirmModal();
            }
            if (event.target === donationModal) {
                closeDonationModal();
            }
            if (event.target === expiredItemPopup) {
                closeExpiredItemPopup();
            }
        }
    </script>
</body>
</html>
