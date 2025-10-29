<?php
    session_start();
require_once 'connect.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_register.php");
        exit();
    }

    // Get user data from database
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            header("Location: login_register.php");
            exit();
        }
        
        $household_size = $userData['household_size'] ?? '';
        $address = $userData['address'] ?? '';
        
    } catch (Exception $e) {
        die("Error retrieving user data: " . $e->getMessage());
    }

    // Process due meal reminders -> notifications (send as in-app notification)
    try {
        $dueStmt = $pdo->prepare("SELECT id, meal_plan_id, meal_type, message, CONCAT(reminder_date, ' ', reminder_time) AS remind_at FROM meal_reminders WHERE user_id = ? AND status = 'pending' AND CONCAT(reminder_date, ' ', reminder_time) <= NOW()");
        $dueStmt->execute([$_SESSION['user_id']]);
        $dueReminders = $dueStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($dueReminders)) {
            // Detect optional related_item column on notifications
            $hasRelated = false;
            try {
                $colChk = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'related_item'");
                $hasRelated = $colChk && $colChk->rowCount() > 0;
            } catch (Exception $ignore) {}

            foreach ($dueReminders as $rem) {
                $title = 'Meal Reminder';
                $msg = $rem['message'] ?: ('It\'s time for your ' . ($rem['meal_type'] ?? 'meal') . '!');
                if ($hasRelated) {
                    $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_item, is_read, created_at) VALUES (?, 'meal_reminder', ?, ?, ?, 0, NOW())");
                    $ins->execute([$_SESSION['user_id'], $title, $msg, $rem['meal_type']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, 'meal_reminder', ?, ?, 0, NOW())");
                    $ins->execute([$_SESSION['user_id'], $title, $msg]);
                }
                $upd = $pdo->prepare("UPDATE meal_reminders SET status = 'sent' WHERE id = ?");
                $upd->execute([$rem['id']]);
            }
        }
    } catch (Exception $e) {
        // Non-fatal
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $newName = $_POST['name'];
        $newEmail = $_POST['email'];
        $newHouseholdSize = $_POST['household_size'];
        $newAddress = $_POST['address'];

        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, household_size = ?, address = ? WHERE id = ?");
        $stmt->execute([$newName, $newEmail, $newHouseholdSize, $newAddress, $_SESSION['user_id']]);
        // Profile updated successfully

        // Refresh user data
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, household_size, address FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        $household_size = $userData['household_size'] ?? '';
        $address = $userData['address'] ?? '';
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            // All password fields are required
        } elseif ($new_password !== $confirm_password) {
            // New passwords do not match
        } elseif (strlen($new_password) < 6) {
            // New password must be at least 6 characters long
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $passwordData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($passwordData && isset($passwordData['password']) && password_verify($current_password, $passwordData['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed, $_SESSION['user_id']]);
                    // Password changed successfully
                } else {
                    // Current password is incorrect
                }
            } catch (Exception $e) {
                // Error changing password
            }
        }
    }
    
    // Handle logout
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: mainpage_aftlogin.php");
        exit();
    }




    $current_page = basename($_SERVER['PHP_SELF']);

    // Get user's current inventory
    try {
        // First, check if the food_inventory table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'food_inventory'");
        if ($table_check->rowCount() == 0) {
            // Create the food_inventory table if it doesn't exist
            $create_table = $pdo->exec("
                CREATE TABLE IF NOT EXISTS food_inventory (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    item_name VARCHAR(255) NOT NULL,
                    quantity VARCHAR(100) NOT NULL,
                    reserved_quantity INT DEFAULT 0,
                    expiry_date DATE NOT NULL,
                    category VARCHAR(100),
                    storage_location VARCHAR(100),
                    notes TEXT,
                    status VARCHAR(50) DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_expiry_date (expiry_date)
                )
            ");
        }

        // Ensure reserved_quantity column exists (for older schemas)
        try {
            $colCheck = $pdo->prepare("SHOW COLUMNS FROM food_inventory LIKE 'reserved_quantity'");
            $colCheck->execute();
            if ($colCheck->rowCount() === 0) {
                $pdo->exec("ALTER TABLE food_inventory ADD COLUMN reserved_quantity INT DEFAULT 0");
            }
        } catch (Exception $e2) {
            // Ignore; will surface in SELECT if truly missing
        }
        
        $inventory_stmt = $pdo->prepare("
            SELECT id, item_name, quantity, COALESCE(reserved_quantity,0) AS reserved_quantity, expiry_date, category, storage_location 
            FROM food_inventory 
            WHERE user_id = ? AND quantity > 0 AND (expiry_date IS NULL OR expiry_date > CURDATE())
            ORDER BY expiry_date ASC, item_name ASC
        ");
        $inventory_stmt->execute([$_SESSION['user_id']]);
        $inventory_items = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $inventory_items = [];
        error_log("Error with inventory: " . $e->getMessage());
    }

    // Get current week's dates with navigation support
    $current_date = new DateTime();
    $week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0; // number of weeks to shift
    $week_start = clone $current_date;
    $week_start->modify('monday this week');
    if ($week_offset !== 0) {
        $week_start->modify(($week_offset > 0 ? '+' : '') . $week_offset . ' week');
    }
    $week_dates = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $week_start;
        $date->modify("+$i days");
        $week_dates[] = $date;
    }
    // Week range for scoping meal plans
    $week_start_date = $week_dates[0]->format('Y-m-d');
    $week_end_date = $week_dates[6]->format('Y-m-d');

    // Handle meal planning form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal_plan'])) {
        try {
            $day = $_POST['day'] ?? '';
            $meal_type = $_POST['meal_type'] ?? '';
            $meal_name = $_POST['meal_name'] ?? '';
            $ingredients = $_POST['ingredients'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $selected_ingredients = $_POST['selected_ingredients'] ?? [];
            $ingredient_quantities = $_POST['ingredient_quantities'] ?? [];
            // Build a readable ingredients string from selected inventory items
            $ingredients_text = $ingredients;
            try {
                if (!empty($selected_ingredients)) {
                    $placeholders = implode(',', array_fill(0, count($selected_ingredients), '?'));
                    $nameStmt = $pdo->prepare("SELECT id, item_name FROM food_inventory WHERE id IN ($placeholders)");
                    $nameStmt->execute($selected_ingredients);
                    $rows = $nameStmt->fetchAll(PDO::FETCH_ASSOC);
                    $idToName = [];
                    foreach ($rows as $r) { $idToName[$r['id']] = $r['item_name']; }
                    $parts = [];
                    foreach ($selected_ingredients as $idx => $invId) {
                        $qty = isset($ingredient_quantities[$idx]) && is_numeric($ingredient_quantities[$idx]) ? (float)$ingredient_quantities[$idx] : 1;
                        // Preserve integers (e.g., 20 -> "20") and trim only insignificant decimal zeros
                        if (is_finite($qty) && floor($qty) == $qty) {
                            $qtyStr = (string)(int)$qty;
                        } else {
                            $formatted = number_format($qty, 2, '.', ''); // e.g., 2.50
                            $formatted = rtrim($formatted, '0'); // -> 2.
                            $qtyStr = rtrim($formatted, '.');   // -> 2
                        }
                        $itemName = $idToName[$invId] ?? ("Item #" . $invId);
                        $parts[] = $itemName . ' x ' . ($qtyStr === '' ? '1' : $qtyStr);
                    }
                    if (!empty($parts)) { $ingredients_text = implode(', ', $parts); }
                }
            } catch (Exception $e) { /* ignore, fallback to raw $ingredients */ }
            
            if ($day && $meal_type && $meal_name) {
                // Prevent adding meal plans for days before today
                $selectedDate = date('Y-m-d', strtotime($week_start_date . ' ' . $day));
                $todayOnly = date('Y-m-d');
                if ($selectedDate < $todayOnly) {
                    $error_message = "You cannot add a meal plan for a past day.";
                } else {
                // Prevent overwrite if a plan already exists for this slot
                $existsStmt = $pdo->prepare("SELECT id FROM meal_plans WHERE user_id = ? AND day_of_week = ? AND meal_type = ? AND DATE(created_at) BETWEEN ? AND ? LIMIT 1");
                $existsStmt->execute([$_SESSION['user_id'], $day, $meal_type, $week_start_date, $week_end_date]);
                $existingId = $existsStmt->fetchColumn();
                if ($existingId) {
                    $error_message = "A meal plan for this slot already exists. Reset all plans to change it.";
                } else {
                $pdo->beginTransaction();
                
                // Insert or update meal plan (immediately confirmed)
                $stmt = $pdo->prepare("
                    INSERT INTO meal_plans (user_id, day_of_week, meal_type, meal_name, ingredients, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $day, $meal_type, $meal_name, $ingredients_text, $notes]);
                
                $meal_plan_id = $pdo->lastInsertId();
                
                // Link selected inventory items to this meal plan
                $stmt = $pdo->prepare("DELETE FROM meal_plan_ingredients WHERE meal_plan_id = ?");
                $stmt->execute([$meal_plan_id]);

                if (!empty($selected_ingredients)) {
                    $link_stmt = $pdo->prepare("INSERT INTO meal_plan_ingredients (meal_plan_id, inventory_item_id, quantity_required) VALUES (?, ?, ?)");
                    foreach ($selected_ingredients as $index => $inventory_id) {
                        $quantity = isset($ingredient_quantities[$index]) && is_numeric($ingredient_quantities[$index]) ? (float)$ingredient_quantities[$index] : 1;
                        if ($quantity > 0) {
                            $link_stmt->execute([$meal_plan_id, $inventory_id, $quantity]);
                            // Reserve quantity instead of deducting from main inventory
                            $upd = $pdo->prepare("UPDATE food_inventory SET reserved_quantity = COALESCE(reserved_quantity,0) + ? WHERE id = ? AND user_id = ?");
                            $upd->execute([$quantity, $inventory_id, $_SESSION['user_id']]);
                        }
                    }
                }
                
                // Create a meal reminder immediately for this slot (same behavior as weekly confirm)
                try {
                    $reminder_times = [ 'breakfast' => '08:00:00', 'lunch' => '13:00:00', 'dinner' => '19:00:00' ];
                    $reminder_time = $reminder_times[$meal_type] ?? '12:00:00';
                    // Compute next occurrence of selected day within/after the displayed week start
                    $targetDate = date('Y-m-d', strtotime($week_start_date . ' ' . $day));
                    if ($targetDate < $week_start_date) { $targetDate = date('Y-m-d', strtotime('next ' . $day, strtotime($week_start_date))); }

                    $rcheck = $pdo->prepare("SELECT id FROM meal_reminders WHERE meal_plan_id = ? LIMIT 1");
                    $rcheck->execute([$meal_plan_id]);
                    if (!$rcheck->fetchColumn()) {
                        $rstmt = $pdo->prepare("INSERT INTO meal_reminders (user_id, meal_plan_id, reminder_date, reminder_time, meal_type, message, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                        $message = "Don't forget: {$meal_name} for {$meal_type}!";
                        $rstmt->execute([$_SESSION['user_id'], $meal_plan_id, $targetDate, $reminder_time, $meal_type, $message]);
                    }
                } catch (Exception $ignore) {}

                $pdo->commit();
                $success_message = "Meal plan saved and ingredients reserved!";
                header("Location: meal_plan1.php" . (isset($week_offset) ? ('?week_offset=' . urlencode((string)$week_offset)) : ''));
                exit();
                }
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error saving meal plan: " . $e->getMessage();
        }
    }

    // Handle weekly confirmation (reserve ingredients and schedule reminders)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_weekly_plan'])) {
        try {
            $pdo->beginTransaction();

            // Fetch all confirmed meal plans for this user within the displayed week
            $stmt = $pdo->prepare("SELECT id, day_of_week, meal_type, meal_name FROM meal_plans WHERE user_id = ? AND status = 'confirmed' AND DATE(created_at) BETWEEN ? AND ?");
            $stmt->execute([$_SESSION['user_id'], $week_start_date, $week_end_date]);
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($plans as $plan) {
                // Get ingredients for this meal plan
                $istmt = $pdo->prepare("\n                SELECT mpi.inventory_item_id, mpi.quantity_required\n                FROM meal_plan_ingredients mpi\n                WHERE mpi.meal_plan_id = ?\n            ");
                $istmt->execute([$plan['id']]);
                $ingredients = $istmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($ingredients as $ing) {
                    // Create reservation if not already reserved for this plan
                    $check = $pdo->prepare("SELECT id FROM inventory_reservations WHERE inventory_item_id = ? AND meal_plan_id = ? AND status = 'active' LIMIT 1");
                    $check->execute([$ing['inventory_item_id'], $plan['id']]);
                    if (!$check->fetchColumn()) {
                        $ins = $pdo->prepare("INSERT INTO inventory_reservations (inventory_item_id, meal_plan_id, reserved_quantity, reservation_date, status) VALUES (?, ?, ?, CURDATE(), 'active')");
                        $ins->execute([$ing['inventory_item_id'], $plan['id'], $ing['quantity_required']]);
                        // Update reserved quantity on inventory
                        $upd = $pdo->prepare("UPDATE food_inventory SET reserved_quantity = COALESCE(reserved_quantity,0) + ? WHERE id = ?");
                        $upd->execute([$ing['quantity_required'], $ing['inventory_item_id']]);
                    }
                }

                // Schedule reminder
                $reminder_times = [ 'breakfast' => '08:00:00', 'lunch' => '13:00:00', 'dinner' => '19:00:00' ];
                $reminder_time = $reminder_times[$plan['meal_type']] ?? '12:00:00';
                $reminder_date = date('Y-m-d', strtotime('next ' . $plan['day_of_week']));

                $rcheck = $pdo->prepare("SELECT id FROM meal_reminders WHERE meal_plan_id = ? LIMIT 1");
                $rcheck->execute([$plan['id']]);
                if (!$rcheck->fetchColumn()) {
                    $rstmt = $pdo->prepare("INSERT INTO meal_reminders (user_id, meal_plan_id, reminder_date, reminder_time, meal_type, message, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $message = "Don't forget: {$plan['meal_name']} for {$plan['meal_type']} tomorrow!";
                    $rstmt->execute([$_SESSION['user_id'], $plan['id'], $reminder_date, $reminder_time, $plan['meal_type'], $message]);
                }
            }

            $pdo->commit();
            $success_message = 'Weekly meal plan confirmed! Reservations made and reminders scheduled.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Error confirming meal plan: ' . $e->getMessage();
        }
    }

    // Handle reset meal plans
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_meal_plans'])) {
        try {
            $pdo->beginTransaction();
            
            // Get all meal plans for this user
            $stmt = $pdo->prepare("SELECT id FROM meal_plans WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $meal_plan_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($meal_plan_ids)) {
                // Release reserved quantities and delete reservations
                $placeholders = str_repeat('?,', count($meal_plan_ids) - 1) . '?';
                
                // Delete ingredient links
                $stmt = $pdo->prepare("DELETE FROM meal_plan_ingredients WHERE meal_plan_id IN ($placeholders)");
                $stmt->execute($meal_plan_ids);

                // Delete meal reminders
                $stmt = $pdo->prepare("DELETE FROM meal_reminders WHERE meal_plan_id IN ($placeholders)");
                $stmt->execute($meal_plan_ids);

                // Delete reservations tied to these plans
                $stmt = $pdo->prepare("DELETE FROM inventory_reservations WHERE meal_plan_id IN ($placeholders)");
                $stmt->execute($meal_plan_ids);

                // Delete meal plans
                $stmt = $pdo->prepare("DELETE FROM meal_plans WHERE id IN ($placeholders)");
                $stmt->execute($meal_plan_ids);
            }

            // Hard reset: clear any remaining reservations for this user's inventory and restore quantities
            $stmt = $pdo->prepare("DELETE FROM inventory_reservations WHERE inventory_item_id IN (SELECT id FROM food_inventory WHERE user_id = ?)");
            $stmt->execute([$_SESSION['user_id']]);

            // Clear all reserved quantities (main quantity already represents total available)
            $stmt = $pdo->prepare("UPDATE food_inventory SET reserved_quantity = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            $pdo->commit();
            $success_message = "All plans reset and reserved ingredients returned to inventory!";
            header("Location: meal_plan1.php" . (isset($week_offset) ? ('?week_offset=' . urlencode((string)$week_offset)) : ''));
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error resetting meal plans: " . $e->getMessage();
        }
    }

    // Get existing meal plans for the week
    try {
        $meal_plans_stmt = $pdo->prepare(
            "
            SELECT id, day_of_week, meal_type, meal_name, ingredients, notes, status 
            FROM meal_plans 
            WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ? 
            ORDER BY day_of_week, meal_type
        "
        );
        $meal_plans_stmt->execute([$_SESSION['user_id'], $week_start_date, $week_end_date]);
        $meal_plans = [];
        while ($row = $meal_plans_stmt->fetch(PDO::FETCH_ASSOC)) {
            $meal_plans[$row['day_of_week']][$row['meal_type']] = $row;
        }
        
        // Draft counting removed
        $draft_count = 0;
    } catch (Exception $e) {
        $meal_plans = [];
        $draft_count = 0;
    }

    // Define $generic_recipes so it is always available for modal and anywhere else
    if (!isset($generic_recipes)) {
        $generic_recipes = [
            [ 'name' => 'Simple Omelette', 'ingredients' => ['Eggs', 'Salt', 'Pepper', 'Oil/Butter'] ],
            [ 'name' => 'Garlic Butter Pasta', 'ingredients' => ['Pasta', 'Garlic', 'Butter/Oil', 'Salt'] ],
            [ 'name' => 'Fried Rice', 'ingredients' => ['Rice', 'Egg', 'Soy Sauce', 'Oil'] ],
            [ 'name' => 'Tomato Toast', 'ingredients' => ['Bread', 'Tomato', 'Salt', 'Olive Oil'] ],
            [ 'name' => 'Veggie Stir-fry', 'ingredients' => ['Any Vegetables', 'Garlic', 'Soy Sauce', 'Oil'] ],
        ];
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Planning - SavePlate</title>
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
            --sidebar-bg: #2C3E50;
            --sidebar-hover: #34495E;
            --sidebar-active: #4CAF50;
            --main-bg: #F5F5F5;
            --card-bg: #FFFFFF;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--main-bg);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
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
            background-color: var(--sidebar-hover);
            color: white;
            border-left-color: var(--primary);
        }

        .nav-link.active {
            background-color: var(--sidebar-active);
            color: white;
            border-left-color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-link .label { white-space: nowrap; }

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
        }
        .sidebar.collapsed + .main-content { margin-left: 72px; }
        body.sidebar-collapsed .main-content { margin-left: 72px; }

        /* Disable page scroll when inventory sidebar open */
        body.inventory-open { overflow: hidden; }

        /* Inventory Sidebar (right panel) */
        .inventory-sidebar {
            position: fixed;
            top: 0;
            right: -380px;
            width: 360px;
            height: 100vh;
            background: #ffffff;
            border-left: 1px solid #E5E7EB;
            box-shadow: -10px 0 30px rgba(0,0,0,0.06);
            z-index: 1100;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            transition: right 0.3s ease;
        }
        .inventory-sidebar.open { right: 0; }
        .inventory-sidebar .inv-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid #f0f0f0;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        }
        .inventory-sidebar .inv-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .inventory-sidebar .close-inv {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6B7280;
        }
        .inventory-sidebar .inv-search {
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin: 12px 16px;
        }
        .inventory-sidebar .inv-list {
            overflow-y: auto;
            padding: 8px 16px 20px 16px;
            flex: 1;
        }
        .inv-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .inv-item .meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .inv-item .name { font-weight: 600; color: #111827; }
        .inv-item .sub { color: #6B7280; font-size: 12px; }
        .inv-item .add-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        .inv-item .add-btn:disabled { background: #9CA3AF; cursor: not-allowed; }

        .inventory-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .inventory-toggle:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        .page-header {
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #E5E7EB;
            position: relative;
            overflow: visible;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .header-left {
            flex: 1;
            padding-left: 20px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            background: linear-gradient(135deg, #2c3e50 0%, #4CAF50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 1.2rem;
            font-weight: 500;
            opacity: 0.8;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .kpi-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .kpi-icon.orange {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.3);
        }

        .kpi-icon.blue {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .kpi-icon.green {
            background: linear-gradient(135deg, #FFC107, #FF8F00);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .kpi-icon.red {
            background: linear-gradient(135deg, #F44336, #D32F2F);
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
        }

        .kpi-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            background: linear-gradient(135deg, #2c3e50 0%, #4CAF50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kpi-label {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .kpi-subtitle {
            font-size: 1rem;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        .chart-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
            background: linear-gradient(135deg, #2c3e50 0%, #4CAF50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .chart-dropdown {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            color: var(--text-dark);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .chart-dropdown:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .chart-placeholder {
            height: 300px;
            background: linear-gradient(135deg, #F8F9FA, #E9ECEF);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1.1rem;
            border: 2px dashed #DEE2E6;
        }

        .chart-legend {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 12px;
            background: #F8F9FA;
            border-radius: 6px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            background: var(--primary);
        }

        .legend-text {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* User Menu Styling */
        .user-menu {
            position: relative;
        }

        .user-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            font-size: 0.95rem;
        }

        .user-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #388E3C 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .user-dropdown {
            display: none;
            position: fixed;
            right: 30px;
            top: 120px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            width: 280px;
            overflow: visible;
            z-index: 9999;
            border: 1px solid #f0f0f0;
            min-height: 200px;
        }

        .user-dropdown * {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .user-dropdown .menu-item {
            display: flex !important;
        }

        .user-profile {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: block !important;
            min-height: 60px;
            box-sizing: border-box;
        }

        .user-profile .name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .user-profile .email {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        .user-dropdown .menu-item {
            display: flex !important;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            visibility: visible;
            opacity: 1;
        }

        .user-dropdown .menu-item:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .logout-btn {
            color: var(--danger) !important;
            font-weight: 600;
        }

        .user-dropdown hr {
            margin: 0;
            border: none;
            border-top: 1px solid #eee;
            display: block;
        }

        .user-dropdown form {
            margin: 0;
            padding: 0;
            display: block;
        }

        /* Profile Modal Styling */
        .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        justify-content: center; align-items: center;
        }

        .modal.show {
        display: flex;
        }

        .modal-content {
        background: #fff;
        padding: 0;
        border-radius: 12px;
        width: 500px;
        max-width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-content h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        }

        /* Meal Planning Modal Specific Styling */
        .modal-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0;
        }

        .modal-title {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title::before {
            content: "🍽️";
            font-size: 1.2rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: white;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
            position: relative;
            z-index: 1; /* ensure above decorative header overlay */
        }

        .close-modal:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #218838, #1ea085);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .close-btn {
        position: absolute;
        top: 12px; right: 15px;
        font-size: 24px;
        cursor: pointer;
        }

        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label { 
            font-weight: 600; 
            margin-bottom: 8px; 
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2C3E50;
            font-size: 0.95rem;
        }
        
        .form-group label i {
            color: #2196F3;
            width: 16px;
            text-align: center;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; 
            padding: 12px 16px;
            border: 2px solid #e9ecef; 
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; 
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        
        .btn-disabled {
            background: linear-gradient(135deg, #cccccc 0%, #999999 100%);
            color: #666666;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-reward {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }
        
        .btn-reward:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn {
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        }

        .btn-primary { background: #4CAF50; color: white; }
        .btn-secondary { background: #ccc; }

        input[readonly], textarea[readonly] {
            background: #e9ecef; /* light grey */
            cursor: not-allowed;
        }

        input:read-write, textarea:read-write {
            background: #fff; /* white when editable */
        }

        /* Footer Styles */
        .footer {
            background-color: #2C3E50;
            color: white;
            padding: 20px 30px;
            text-align: center;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
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

            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .page-title {
                font-size: 2rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-right {
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }
            
            .export-actions {
                justify-content: center;
            }
            
            .user-menu {
                align-self: center;
            }
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

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }

        /* Meal Suggestions Modal Styles */
        .meal-suggestions-modal {
            z-index: 10000;
        }

        .meal-modal-content {
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
        }

        .meal-modal-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .meal-modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .header-text {
            flex: 1;
        }

        .modal-subtitle {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .meal-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .meal-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .meal-modal-body {
            padding: 20px 25px;
            flex: 1;
            overflow-y: auto;
        }

        .meal-intro {
            text-align: center;
            margin-bottom: 25px;
        }

        .meal-intro p {
            font-size: 1.1rem;
            color: #666;
            margin: 0;
        }

        .meal-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .meal-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            animation: slideInUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .meal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .meal-emoji {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .meal-info {
            flex: 1;
        }

        .meal-name {
            margin: 0 0 8px 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .meal-meta {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .difficulty {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty.easy {
            background: #e8f5e8;
            color: #4CAF50;
        }

        .difficulty.medium {
            background: #fff3e0;
            color: #ff9800;
        }

        .difficulty.hard {
            background: #ffebee;
            color: #f44336;
        }

        .time {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .tip-section {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: linear-gradient(135deg, #fff9c4 0%, #fff59d 100%);
            border-radius: 15px;
            border-left: 4px solid #ffc107;
        }

        .tip-icon {
            font-size: 1.5rem;
            background: #ffc107;
            padding: 10px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .tip-content h4 {
            margin: 0 0 8px 0;
            color: #f57c00;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .tip-content p {
            margin: 0;
            color: #e65100;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .meal-modal-footer {
            padding: 15px 25px 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .meal-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .meal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .meal-modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .meal-modal-header {
                padding: 20px;
            }
            
            .meal-modal-body {
                padding: 20px;
            }
            
            .meal-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .meal-meta {
                justify-content: center;
            }
        }

        /* Category Items Modal Styles */
        .category-items-modal {
            z-index: 10000;
        }

        .category-modal-content {
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
        }

        .category-modal-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .category-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .category-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .category-modal-body {
            padding: 20px 25px;
            flex: 1;
            overflow-y: auto;
        }

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .item-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .item-details {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .item-quantity,
        .item-date {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .category-modal-footer {
            padding: 15px 25px 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .category-btn {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border: none;
            padding: 12px 25px;
            border-radius: 20px;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .category-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }

        .loading {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

        /* Category Breakdown Styles */
        .category-breakdown {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .breakdown-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 12px;
            text-align: center;
        }

        .breakdown-items {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .breakdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }

        .breakdown-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .breakdown-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #495057;
        }

        .breakdown-percentage {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
        }

        /* Modal Styling */
        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 0;
            border-radius: 20px;
            width: 480px;
            max-width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 85vh;
            overflow: hidden;
            z-index: 10000;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            z-index: 0;
            pointer-events: none; /* don't block clicks on header controls */
        }
        
        .modal-header h2 {
            color: white;
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        
        .modal-body {
            padding: 25px;
            overflow-y: auto;
            max-height: calc(85vh - 80px);
            background: #ffffff;
        }
        
        .modal-footer {
            padding: 15px 0 0 0;
            background: #ffffff;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 10px;
        }
        
        .modal-intro {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #bbdefb;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-intro-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .modal-intro-content h3 {
            margin: 0 0 5px 0;
            color: #1565C0;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .modal-intro-content p {
            margin: 0;
            color: #424242;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
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

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        /* Alert Section */
        .alert-section {
            margin-bottom: 30px;
        }

        .alert-container {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .alert-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #856404;
        }

        .alert-title i {
            color: #ffc107;
            font-size: 1.2rem;
        }

        .alert-content p {
            color: #856404;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .alert-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-outline:hover {
            background: #ffc107;
            color: white;
        }

        /* Export Actions */
        .export-actions {
            display: flex;
            gap: 10px;
        }

        .export-actions .btn {
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 2px solid #4CAF50;
            color: #4CAF50;
            background: transparent;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .export-actions .btn:hover {
            background: #4CAF50;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .export-actions .btn i.fa-file-pdf {
            color: #e74c3c;
        }

        .export-actions .btn:hover i.fa-file-pdf {
            color: white;
        }

        /* Goals Section */
        .goals-section {
            margin-bottom: 30px;
        }

        .goals-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .goals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .goals-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
            font-size: 1.3rem;
        }

        .goals-header h3 i {
            color: #ffc107;
        }

        .goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .goal-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .goal-card:hover {
            transform: translateY(-2px);
        }

        .goal-card {
            position: relative;
        }
        
        .goal-card.completed {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border: 2px solid #4CAF50;
        }
        
        .goal-completed {
            color: #4CAF50;
            font-weight: bold;
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Rewards Modal Styles */
        .rewards-section {
            margin: 20px 0;
        }
        
        .rewards-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .voucher-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .voucher-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .voucher-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #4CAF50;
        }
        
        .voucher-logo {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .voucher-content h5 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .voucher-amount {
            color: #4CAF50;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .voucher-desc {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .rewards-info {
            margin-top: 20px;
        }
        
        .info-card {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .info-card i {
            color: #2196f3;
            font-size: 1.2rem;
            margin-top: 3px;
        }
        
        .info-card h5 {
            color: #1976d2;
            margin-bottom: 8px;
        }
        
        .info-card p {
            color: #424242;
            margin: 0;
            line-height: 1.5;
        }

        .goal-description {
            font-size: 0.9rem;
            color: #666;
            margin: 5px 0;
            font-style: italic;
        }

        .goal-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .btn-delete-goal {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .btn-delete-goal:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .no-goals {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-goals-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .no-goals h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .goal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .goal-content {
            flex: 1;
        }

        .goal-content h4 {
            margin: 0 0 10px 0;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .goal-progress {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 500;
        }

        /* Enhanced header actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Chart canvas styling */
        canvas {
            width: 100% !important;
            height: 300px !important;
        }

        /* Notification-specific styles */
        .notifications-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 24px;
            margin-bottom: 24px;
            min-height: 500px;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-left: 18px;
            padding-left: 0px;
        }

        .section-title i {
            font-size: 1.5rem;
            color: var(--primary);
            background: var(--primary-light);
            padding: 8px;
            border-radius: 8px;
        }

        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .mark-all-read {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .mark-all-read:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .filters-bar {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            border: 1px solid var(--gray-200);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: white;
            border: 1px solid var(--primary);
            color: var(--primary-dark);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn:hover {
            border-color: var(--primary-dark);
            color: var(--primary-dark);
            background: var(--primary-light);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .notifications-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 8px;
            padding: 0;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-datetime {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.9);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .notification-card.read {
            background: linear-gradient(145deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            opacity: 0.85;
        }

        .notification-card.read .notification-title {
            color: #6c757d;
            font-weight: 600;
        }

        .notification-card.read .notification-message {
            color: #868e96;
        }

        .notification-card.read .notification-icon {
            opacity: 0.7;
            filter: grayscale(20%);
        }

        .notification-card.read .notification-item {
            background: #e9ecef;
            color: #6c757d;
        }

        .notification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            border-radius: 4px 0 0 4px;
        }

        .notification-card.unread {
            background: linear-gradient(145deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 15px 35px -5px rgba(76, 175, 80, 0.3), 0 10px 20px -5px rgba(76, 175, 80, 0.2);
            transform: scale(1.02);
            position: relative;
        }

        .notification-card.unread::before {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            width: 6px;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }

        .notification-card.unread::after {
            content: '';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .notification-card.unread .notification-title {
            font-weight: 800;
            color: #2E7D32;
        }

        .notification-card.unread .notification-icon {
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
            transform: scale(1.1);
            animation: iconGlow 3s ease-in-out infinite;
        }

        @keyframes iconGlow {
            0%, 100% {
                box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
            }
            50% {
                box-shadow: 0 8px 25px rgba(76, 175, 80, 0.6), 0 0 20px rgba(76, 175, 80, 0.3);
            }
        }

        .notification-card.unread .notification-meta {
            font-weight: 700;
            color: #2E7D32;
        }

        .notification-card.unread .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .notification-card[data-type="welcome"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="welcome"]::before {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            width: 6px;
        }

        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-color: #A5D6A7;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .notification-icon.expiry_warning {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.donation_update {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.pickup_arrangement {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.meal_reminder {
            background: var(--info-light);
            color: var(--info-dark);
        }

        .notification-icon.inventory_alert {
            background: var(--accent-light);
            color: var(--accent-dark);
        }

        .notification-icon.goal_achievement {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.welcome {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.pickup_request_sent {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .notification-card[data-type="welcome"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-message {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .notification-card[data-type="welcome"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-meta {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .notification-item {
            background: var(--primary-light);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: var(--primary-dark);
        }

        .notification-card[data-type="welcome"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--gray-300);
            position: relative;
            justify-content: space-between;
            align-items: center;
        }

        .action-buttons-right {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .notification-actions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gray-100);
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .action-btn.view {
            background: var(--primary);
            color: white;
        }

        .action-btn.view:hover {
            background: var(--primary-dark);
        }

        .action-btn.approve {
            background: var(--success);
            color: white;
        }

        .action-btn.approve:hover {
            background: var(--success-dark);
        }

        .action-btn.reject {
            background: var(--danger);
            color: white;
        }

        .action-btn.reject:hover {
            background: var(--danger-dark);
        }

        .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
        }

        .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .notification-card[data-type="welcome"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="welcome"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Pickup Request Sent Notification Styling */
        .notification-card[data-type="pickup_request_sent"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="pickup_request_sent"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="pickup_request_sent"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="pickup_request_sent"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="pickup_request_sent"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="pickup_request_sent"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="pickup_request_sent"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Claim Approved Notification Styling */
        .notification-card[data-type="claim_approved"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="claim_approved"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="claim_approved"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="claim_approved"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="claim_approved"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="claim_approved"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="claim_approved"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Donation Created Notification Styling */
        .notification-card[data-type="donation_created"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="donation_created"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="donation_created"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="donation_created"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="donation_created"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="donation_created"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="donation_created"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Expiry Warning Notification Styling */
        .notification-card[data-type="expiry_warning"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="expiry_warning"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="expiry_warning"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="expiry_warning"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="expiry_warning"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="expiry_warning"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="expiry_warning"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Goal Achievement Notification Styling */
        .notification-card[data-type="success"] {
            background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%);
            border: 2px solid #FFD700;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.2);
            position: relative;
        }

        .notification-card[data-type="success"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="success"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="success"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="success"] .notification-item {
            background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="success"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="success"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        .loading {
            display: flex;
                flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: var(--gray-500);
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--gray-400);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-600);
        }

        .empty-state p {
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Responsive design for notifications */
        @media (max-width: 768px) {
            .notifications-box {
                padding: 16px;
                margin: 0 16px 24px 16px;
                border-radius: 12px;
            }
            
            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .filter-buttons {
                gap: 6px;
            }
            
            .filter-btn {
                padding: 6px 12px;
                font-size: 0.75rem;
            }
            
            .notification-card {
                flex-direction: column;
                gap: 16px;
            }
            
            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .notifications-container {
                padding: 0;
                gap: 12px;
            }

            .section-title {
                margin-left: 0;
                padding-left: 0;
            }
        }

        /* Meal Planning Styles */
        .calendar-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 25px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(76, 175, 80, 0.1);
            position: relative;
            overflow: hidden;
        }


        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .calendar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .calendar-title {
            font-size: 2rem;
            font-weight: 800;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .calendar-title i {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            filter: drop-shadow(0 2px 4px rgba(76, 175, 80, 0.3));
        }

        .week-navigation {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .nav-btn:hover {
            background: var(--primary-dark);
        }

        .week-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
        }

        .day-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            min-height: 120px;
        }

        .day-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .day-card.today {
            background: #e8f5e8;
            border-color: var(--primary);
        }

        .day-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .day-date {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .meal-slots {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .meal-slot {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meal-slot:hover {
            background: var(--primary);
            color: white;
        }

        .meal-slot.breakfast { border-left: 4px solid #FFC107; }
        .meal-slot.lunch { border-left: 4px solid #FF9800; }
        .meal-slot.dinner { border-left: 4px solid #F44336; }

        .meal-name {
            flex: 1;
        }

        .status-icon {
            font-size: 0.8rem;
            margin-left: 5px;
        }

        .status-icon.confirmed {
            color: #28a745;
        }

        .meal-slot.confirmed {
            background: #d4edda;
            border-color: #28a745;
        }

        .inventory-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            border-radius: 24px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 25px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(76, 175, 80, 0.1);
            overflow: hidden;
            position: relative;
        }


        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px 40px;
            background: linear-gradient(135deg, #f8fffe 0%, #ffffff 100%);
            border-bottom: 2px solid rgba(76, 175, 80, 0.1);
            flex-wrap: wrap;
            gap: 20px;
        }

        .inventory-title {
            font-size: 2rem;
            font-weight: 800;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .inventory-title i {
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
            padding: 6px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-sm.btn-success {
            background: var(--success);
            color: white;
        }

        .btn-sm.btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-sm.btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .saved-plans-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 25px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(76, 175, 80, 0.1);
            position: relative;
            overflow: hidden;
        }


        .saved-plans-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .saved-plans-title {
            color: #2c3e50;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .saved-plans-title i {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            filter: drop-shadow(0 2px 4px rgba(76, 175, 80, 0.3));
        }

        .plans-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box i {
            position: absolute;
            left: 18px;
            color: #4CAF50;
            z-index: 1;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-box:focus-within i {
            color: #2e7d32;
            transform: scale(1.1);
        }

        .search-box input {
            padding: 14px 20px 14px 50px;
            border: 2px solid rgba(76, 175, 80, 0.2);
            border-radius: 30px;
            font-size: 15px;
            width: 280px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: #4CAF50;
            background: white;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.15), 0 0 0 4px rgba(76, 175, 80, 0.1);
            transform: translateY(-2px);
        }

        .search-box input::placeholder {
            color: #95a5a6;
            font-weight: 400;
        }

        .filter-dropdown select {
            padding: 14px 20px;
            border: 2px solid rgba(76, 175, 80, 0.2);
            border-radius: 30px;
            font-size: 15px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            font-weight: 500;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234CAF50' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 45px;
        }

        .filter-dropdown select:focus {
            outline: none;
            border-color: #4CAF50;
            background: white;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.15), 0 0 0 4px rgba(76, 175, 80, 0.1);
            transform: translateY(-2px);
        }

        .search-highlight {
            background: #fff3cd;
            color: #856404;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

        .no-results {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            width: 100%;
            padding: 40px 20px;
        }

        .no-results-content {
            text-align: center;
            padding: 50px 30px;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .no-results-icon {
            margin-bottom: 25px;
        }

        .no-results-icon i {
            font-size: 4rem;
            color: #dee2e6;
            opacity: 0.7;
        }

        .no-results-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #495057;
            margin: 0 0 15px 0;
        }

        .no-results-subtitle {
            font-size: 1rem;
            margin: 0 0 20px 0;
            color: #6c757d;
        }

        .no-results-suggestions {
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            padding: 0;
            list-style: none;
            width: 100%;
            max-width: 400px;
        }

        .no-results-suggestions li {
            padding: 8px 0;
            color: #6c757d;
            position: relative;
            padding-left: 20px;
        }

        .no-results-suggestions li:before {
            content: "•";
            color: #4CAF50;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .plans-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        .tab-btn:hover {
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .plans-list {
            display: grid;
            gap: 25px;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
        }

        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 0;
            border: 2px solid rgba(76, 175, 80, 0.1);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06), 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1), 0 4px 15px rgba(76, 175, 80, 0.12);
            border-color: #4CAF50;
        }

        .plan-card.confirmed {
            border-color: #4CAF50;
            background: linear-gradient(135deg, #f8fff8 0%, #ffffff 100%);
        }

        .plan-card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 50%, #2e7d32 100%);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
        }

        .plan-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .plan-title-section {
            flex: 1;
        }

        .plan-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 12px 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .plan-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .plan-day, .plan-type {
            background: rgba(255, 255, 255, 0.25);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .plan-day:hover, .plan-type:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .plan-status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.25);
            padding: 10px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .plan-status-badge:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }

        .plan-card-body {
            padding: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fffe 100%);
        }

        .ingredients-section, .notes-section {
            margin-bottom: 20px;
        }

        .ingredients-section h5, .notes-section h5 {
            color: #2c3e50;
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ingredients-section h5 i, .notes-section h5 i {
            color: #4CAF50;
            font-size: 1.1rem;
        }

        .ingredients-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ingredient-tag {
            background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%);
            color: #2e7d32;
            padding: 10px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #c8e6c9;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .ingredient-tag::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .ingredient-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.2);
            border-color: #4CAF50;
        }

        .ingredient-tag:hover::before {
            left: 100%;
        }

        .ingredient-tag i {
            font-size: 0.7rem;
            color: #4CAF50;
        }

        .ingredients-text, .notes-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }


        .plan-header h4 {
            margin: 0;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .plan-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .plan-status.confirmed {
            background: #28a745;
            color: white;
        }

        .plan-details {
            color: var(--gray);
            line-height: 1.6;
        }

        .plan-details p {
            margin: 5px 0;
        }

        .no-plans {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .no-plans i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-plans p {
            font-size: 1.1rem;
            margin: 0;
        }

        .saved-plans-scroll {
            max-height: 520px;
            overflow-y: auto;
            padding-right: 8px;
        }

        /* Responsive design for meal plans */
        @media (max-width: 768px) {
            .saved-plans-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .plans-controls {
                flex-direction: column;
                gap: 10px;
            }

            .search-box input {
                width: 100%;
            }

            .plans-list {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .plan-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .plan-meta {
                flex-wrap: wrap;
            }

            .ingredients-list {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        #available-ingredients {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
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

        <!-- Inventory Sidebar (Right) -->
        <div id="inventorySidebar" class="inventory-sidebar">
            <div class="inv-header">
                <div class="inv-title"><i class="fas fa-warehouse"></i> Inventory</div>
                <button class="close-inv" type="button" onclick="toggleInventorySidebar()">&times;</button>
            </div>
            <input id="invSearch" class="inv-search" type="text" placeholder="Search items..." oninput="filterInventoryItems(this.value)">
            <div class="inv-list" id="invList">
                <?php if (empty($inventory_items)): ?>
                    <div style="color:#6B7280; font-size: 14px;">No items in inventory.</div>
                <?php else: ?>
                    <?php foreach ($inventory_items as $item): ?>
                        <?php 
                            $reserved = (float)($item['reserved_quantity'] ?? 0);
                            $available = max(0, (float)$item['quantity'] - $reserved);
                        ?>
                        <div class="inv-item" data-name="<?php echo htmlspecialchars(strtolower($item['item_name'])); ?>">
                            <div class="meta">
                                <div class="name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="sub">Available: <?php echo $available; ?><?php echo $reserved > 0 ? ' • Reserved: ' . $reserved : ''; ?><?php echo !empty($item['expiry_date']) ? ' • Exp: ' . htmlspecialchars($item['expiry_date']) : ''; ?></div>
                            </div>
                            <button class="add-btn" type="button" onclick="addFromSidebar(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', <?php echo (float)$available; ?>)" <?php echo $available <= 0 ? 'disabled' : ''; ?>>Add</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Meal Plan</h1>
                    <p class="page-subtitle">Plan your weekly meals using your current inventory to reduce waste and save money</p>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <button class="user-btn" id="userBtn">
                            <?php echo htmlspecialchars(explode(' ', $userData['username'] ?? 'User')[0]); ?> 
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-profile">
                                <div class="name"><?php echo htmlspecialchars($userData['username'] ?? 'User'); ?></div>
                                <div class="email"><?php echo htmlspecialchars($userData['email'] ?? 'No email available'); ?></div>
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
                </div>
                </div>
            </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Weekly Calendar -->
        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title">
                    <i class="fas fa-calendar-week"></i>
                    Weekly Meal Plan
                </h2>
                <div class="calendar-actions">
                     
                    <?php if (!empty($meal_plans)): ?>
                    <form method="POST" style="display: inline-block; margin-right: 15px;">
                        <button type="submit" name="reset_meal_plans" class="btn btn-danger" 
                                onclick="return confirm('Reset plans? This will delete all plans, release reserved ingredients, and remove reminders. This action cannot be undone.')">
                            <i class="fas fa-trash-alt"></i> Reset Plans
                        </button>
                    </form>
                    <?php endif; ?>
                    <div class="week-navigation">
                        <button class="nav-btn" onclick="changeWeek(-1)">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span id="week-range"><?php echo $week_dates[0]->format('M j') . ' - ' . $week_dates[6]->format('M j, Y'); ?></span>
                        <button class="nav-btn" onclick="changeWeek(1)">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="week-grid">
                <?php 
                $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $meal_types = ['breakfast', 'lunch', 'dinner'];
                $today = date('Y-m-d');
                
                foreach ($week_dates as $index => $date): 
                    $is_today = $date->format('Y-m-d') === $today;
                    $is_past = $date->format('Y-m-d') < $today;
                    $day_name = $day_names[$index];
                    $day_key = strtolower($day_name);
                ?>
                <div class="day-card <?php echo $is_today ? 'today' : ''; ?>" data-day="<?php echo $day_key; ?>">
                    <div class="day-name"><?php echo $day_name; ?></div>
                    <div class="day-date"><?php echo $date->format('M j'); ?></div>
                    <div class="meal-slots">
                        <?php foreach ($meal_types as $meal_type): ?>
                            <?php 
                            $meal_data = $meal_plans[$day_key][$meal_type] ?? null;
                            $meal_name = $meal_data['meal_name'] ?? 'Add ' . ucfirst($meal_type);
                            $status_class = $meal_data ? 'confirmed locked' : 'empty';
                            ?>
                            <div class="meal-slot <?php echo $meal_type . ' ' . $status_class; ?>" 
                                 <?php if (!$meal_data && !$is_past): ?>
                                 onclick="openMealModal('<?php echo $day_key; ?>', '<?php echo $meal_type; ?>', '<?php echo htmlspecialchars($meal_name); ?>', '<?php echo htmlspecialchars($meal_data['ingredients'] ?? ''); ?>', '<?php echo htmlspecialchars($meal_data['notes'] ?? ''); ?>')"
                                 <?php else: ?>
                                 title="<?php echo $is_past ? 'You cannot add a meal for a past day.' : 'This slot is already planned. Reset plans to change it.'; ?>"
                                 style="cursor: not-allowed; opacity: 0.6;"
                                 <?php endif; ?>
                            >
                                <span class="meal-name"><?php echo htmlspecialchars($meal_name); ?></span>
                                <?php if ($meal_data): ?>
                                    <i class="fas fa-lock status-icon confirmed" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Meal Planning Modal -->
    <div class="modal" id="mealModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Plan Your Meal</h3>
                <button class="close-modal" onclick="closeMealModal()">&times;</button>
            </div>
            <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="day" id="modal-day">
                <input type="hidden" name="meal_type" id="modal-meal-type">
                <input type="hidden" name="save_meal_plan" value="1">
                
                <div class="form-group">
                    <label class="form-label" for="meal-type-display">Meal Type</label>
                    <input type="text" class="form-input" id="meal-type-display" readonly style="background-color: #f8f9fa; color: #6c757d; font-weight: bold;">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="meal-name">Meal Name</label>
                    <input type="text" class="form-input" id="meal-name" name="meal_name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="ingredients">Ingredients (select from your inventory)</label>
                    <div id="ingredient-selection">
                        <div class="ingredient-dropdown-container">
                            <select class="form-input" id="ingredient-dropdown" onchange="addIngredientToList()">
                                <option value="">Select an ingredient...</option>
                                <?php if (empty($inventory_items)): ?>
                                    <option value="" disabled>No items in inventory. Add items first!</option>
                                <?php else: ?>
                                    <?php foreach ($inventory_items as $item): ?>
                                    <?php $reserved = (float)($item['reserved_quantity'] ?? 0); $available = max(0, (float)$item["quantity"] - $reserved); ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                            data-quantity="<?php echo $item['quantity']; ?>" 
                                            data-available="<?php echo $available; ?>"
                                            <?php echo $available <= 0 ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (Available: <?php echo $available; ?><?php echo $reserved > 0 ? ', Reserved: ' . $reserved : ''; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        
                        <div class="selected-ingredients-list" id="selected-ingredients-list" style="margin-top: 15px;">
                            <h4 style="margin-bottom: 10px; color: #333;">Selected Ingredients:</h4>
                            <div id="ingredients-container">
                                <!-- Selected ingredients will be added here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Move and wrap the generic recipes table in a toggle-based div, hidden by default -->
                <div id="recipes-toggle-section" style="display:none; margin-bottom: 20px;">
                    <div class="table-container" style="margin-top: 10px;">
                        <h3 style="margin: 10px 0 16px 4px; color: #2C3E50;"><i class="fas fa-utensils"></i> Suggested Generic Recipes</h3>
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Meal Name</th>
                                    <th>Ingredients</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($generic_recipes as $rec): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($rec['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(implode(', ', $rec['ingredients'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-textarea" id="notes" name="notes" placeholder="Any additional notes or cooking instructions..."></textarea>
                </div>
                
                <div class="form-actions" style="display: flex; align-items: center; gap: 12px;">
                    <!-- Utensils icon to toggle generic recipes (now left of Cancel) -->
                    <button type="button" class="btn" style="padding: 6px 10px; background: none; color: #4a5568; font-size: 1.3em;" onclick="toggleRecipes()" title="Show suggested recipes">
                        <i class="fas fa-utensils"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeMealModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm and Plan</button>
                </div>
            </form>
            </div>
        </div>
    </div>

        <!-- Saved Meal Plans -->
        <div class="saved-plans-container">
            <div class="saved-plans-header">
                <h3 class="saved-plans-title">
                    <i class="fas fa-calendar-check"></i>
                    Your Saved Meal Plans
                </h3>
                <div class="plans-controls">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="mealPlanSearch" placeholder="Search meal plans..." onkeyup="filterMealPlans()">
                    </div>
                    <div class="filter-dropdown">
                        <select id="mealTypeFilter" onchange="filterMealPlans()">
                            <option value="">All Meal Types</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="plans-tabs">
                <button class="tab-btn active" onclick="showTab('confirmed')">Meal Plans</button>
            </div>
            
            <!-- Confirmed Plans Tab -->
            <div id="confirmed-tab" class="tab-content active saved-plans-scroll">
                <?php
                $confirmed_plans = [];
                foreach ($meal_plans as $day_plans) {
                    foreach ($day_plans as $meal) {
                        if ($meal['status'] === 'confirmed') {
                            $confirmed_plans[] = $meal;
                        }
                    }
                }
                ?>
                <?php if (empty($confirmed_plans)): ?>
                    <div class="no-plans">
                        <i class="fas fa-check-circle"></i>
                        <p>No confirmed meal plans yet. Confirm your draft plans to see them here!</p>
                    </div>
                <?php else: ?>
                    <div class="plans-list" id="plansList">
                        <?php foreach ($confirmed_plans as $plan): ?>
                            <?php
                            // Fetch linked inventory ingredients for this plan
                            try {
                                $ingStmt = $pdo->prepare("SELECT fi.item_name, mpi.quantity_required FROM meal_plan_ingredients mpi JOIN food_inventory fi ON mpi.inventory_item_id = fi.id WHERE mpi.meal_plan_id = ? ORDER BY fi.item_name ASC");
                                $ingStmt->execute([$plan['id']]);
                                $linkedIngredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $linkedIngredients = [];
                            }
                            ?>
                            <?php
                            // Build searchable text including ingredients
                            $searchableText = strtolower($plan['meal_name'] . ' ' . $plan['day_of_week'] . ' ' . $plan['meal_type']);
                            if (!empty($linkedIngredients)) {
                                foreach ($linkedIngredients as $li) {
                                    $searchableText .= ' ' . strtolower($li['item_name']);
                                }
                            } elseif ($plan['ingredients']) {
                                $searchableText .= ' ' . strtolower($plan['ingredients']);
                            }
                            if ($plan['notes']) {
                                $searchableText .= ' ' . strtolower($plan['notes']);
                            }
                            ?>
                            <div class="plan-card confirmed" data-meal-type="<?php echo strtolower($plan['meal_type']); ?>" data-search-text="<?php echo htmlspecialchars($searchableText); ?>">
                                <div class="plan-card-header">
                                    <div class="plan-title-section">
                                        <h4 class="plan-title"><?php echo htmlspecialchars($plan['meal_name']); ?></h4>
                                        <div class="plan-meta">
                                            <span class="plan-day"><?php echo ucfirst($plan['day_of_week']); ?></span>
                                            <span class="plan-type"><?php echo ucfirst($plan['meal_type']); ?></span>
                                        </div>
                                    </div>
                                    <div class="plan-status-badge">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Confirmed</span>
                                    </div>
                                </div>
                                
                                <div class="plan-card-body">
                                    <?php if (!empty($linkedIngredients)): ?>
                                        <div class="ingredients-section">
                                            <h5><i class="fas fa-list-ul"></i> Ingredients Used</h5>
                                            <div class="ingredients-list">
                                                <?php foreach ($linkedIngredients as $li): ?>
                                                    <span class="ingredient-tag">
                                                        <i class="fas fa-circle"></i>
                                                        <?php echo htmlspecialchars($li['item_name']) . ' × ' . number_format((float)$li['quantity_required'], 0, '.', ''); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($plan['ingredients']): ?>
                                        <div class="ingredients-section">
                                            <h5><i class="fas fa-list-ul"></i> Ingredients</h5>
                                            <p class="ingredients-text"><?php echo htmlspecialchars($plan['ingredients']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($plan['notes']): ?>
                                        <div class="notes-section">
                                            <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                            <p class="notes-text"><?php echo htmlspecialchars($plan['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Inventory -->
        <div class="inventory-container">
            <div class="inventory-header">
                <h2 class="inventory-title"><i class="fas fa-list"></i> Your Available Ingredients </h2>
                <a href="add_item.php" class="btn btn-primary add-item-btn"><i class="fas fa-plus"></i> Add New Item</a>
            </div>
            
            <?php if (empty($inventory_items)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                    <p>No items in your inventory. <a href="/bit216_assignment/add_item.php">Add some items</a> to start meal planning!</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_items as $item): ?>
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
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                    <?php 
                                        $reserved = (float)($item['reserved_quantity'] ?? 0);
                                        $total_quantity = (float)$item['quantity'];
                                        $available_quantity = max(0, $total_quantity - $reserved);
                                    ?>
                                    <td><?php echo htmlspecialchars($available_quantity); ?></td>
                                    <td><?php echo htmlspecialchars((string)($item['reserved_quantity'] ?? 0)); ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    </div>


    <!-- Simple Footer -->
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
                        <button type="button" id="editProfileBtn" class="btn btn-warning" style="background-color: #FFD700; color: #000; border: 2px solid #000;">
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



    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Desktop collapse/expand toggle via brand + persistence
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            const collapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}

            // Adjust footer margin when sidebar collapses/expands
            const footer = document.querySelector('.footer');
            if (collapsed) {
                footer.style.marginLeft = '72px';
            } else {
                footer.style.marginLeft = '280px';
            }
            
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
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

        // Add some interactivity to KPI cards
        document.querySelectorAll('.kpi-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add a subtle animation
                this.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-2px)';
                }, 150);
            });
        });

        // Restore collapsed state and wire brand click
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    
                    // Adjust footer margin when sidebar is collapsed
                    const footer = document.querySelector('.footer');
                    if (footer) {
                        footer.style.marginLeft = '72px';
                    }
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
        });

        // Profile Modal Functions
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

        // Meal Planning Modal Functions
        function openMealModal(day, mealType, mealName, ingredients, notes) {
            // First, clear all form fields to ensure clean state
            document.getElementById('modal-day').value = day;
            document.getElementById('modal-meal-type').value = mealType;
            document.getElementById('meal-name').value = '';
            document.getElementById('notes').value = '';
            
            // Display the meal type in the visible field
            const mealTypeDisplay = document.getElementById('meal-type-display');
            if (mealTypeDisplay) {
                const displayValue = mealType.charAt(0).toUpperCase() + mealType.slice(1);
                mealTypeDisplay.value = displayValue;
            }
            
            // If editing existing meal, populate the form
            if (mealName && mealName !== 'Add ' + mealType.charAt(0).toUpperCase() + mealType.slice(1)) {
                document.getElementById('meal-name').value = mealName;
                document.getElementById('notes').value = notes || '';
            }
            
            // Clear ingredient dropdown and selected ingredients
            const dropdown = document.getElementById('ingredient-dropdown');
            if (dropdown) {
                dropdown.selectedIndex = 0;
            }
            
            const ingredientsContainer = document.getElementById('ingredients-container');
            if (ingredientsContainer) {
                ingredientsContainer.innerHTML = '';
            }
            
            document.getElementById('mealModal').classList.add('show');
        }

        function closeMealModal() {
            const modal = document.getElementById('mealModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                
                // Clear the form completely when closing
                document.getElementById('modal-day').value = '';
                document.getElementById('modal-meal-type').value = '';
                document.getElementById('meal-type-display').value = '';
                document.getElementById('meal-name').value = '';
                document.getElementById('notes').value = '';
                
                // Clear ingredient dropdown and selected ingredients
                const dropdown = document.getElementById('ingredient-dropdown');
                if (dropdown) {
                    dropdown.selectedIndex = 0;
                }
                
                const ingredientsContainer = document.getElementById('ingredients-container');
                if (ingredientsContainer) {
                    ingredientsContainer.innerHTML = '';
                }
            }
        }

        function addIngredientToList() {
            const dropdown = document.getElementById('ingredient-dropdown');
            const selectedOption = dropdown.options[dropdown.selectedIndex];
            
            if (selectedOption.value === '') return;
            
            const ingredientId = selectedOption.value;
            const ingredientName = selectedOption.getAttribute('data-name');
            const availableQuantity = selectedOption.getAttribute('data-available');
            
            // Check if ingredient is already added
            const existingIngredient = document.querySelector(`[data-ingredient-id="${ingredientId}"]`);
            if (existingIngredient) {
                alert('This ingredient is already added to your meal!');
                dropdown.selectedIndex = 0; // Reset dropdown
                return;
            }
            
            // Create ingredient item
            const ingredientItem = document.createElement('div');
            ingredientItem.className = 'selected-ingredient-item';
            ingredientItem.setAttribute('data-ingredient-id', ingredientId);
            ingredientItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                    <span style="flex: 1; font-weight: 500;">${ingredientName}</span>
                    <span style="color: #6c757d; font-size: 0.9rem;">Available: ${availableQuantity}</span>
                    <input type="number" name="ingredient_quantities[]" min="1" step="1" placeholder="1" 
                           style="width: 80px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" 
                           max="${availableQuantity}" value="1">
                    <input type="hidden" name="selected_ingredients[]" value="${ingredientId}">
                    <button type="button" onclick="removeIngredient(this)" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">×</button>
                </div>
            `;
            
            document.getElementById('ingredients-container').appendChild(ingredientItem);
            dropdown.selectedIndex = 0; // Reset dropdown
        }
        
        function removeIngredient(button) {
            button.closest('.selected-ingredient-item').remove();
        }

        function toggleInventorySidebar() {
            const el = document.getElementById('inventorySidebar');
            if (!el) return;
            el.classList.toggle('open');
            document.body.classList.toggle('inventory-open', el.classList.contains('open'));
        }

        function filterInventoryItems(query) {
            const q = (query || '').toLowerCase();
            const items = document.querySelectorAll('#invList .inv-item');
            items.forEach(it => {
                const name = it.getAttribute('data-name') || '';
                it.style.display = name.includes(q) ? 'flex' : 'none';
            });
        }

        function addFromSidebar(id, name, available) {
            if (!document.getElementById('mealModal').classList.contains('show')) {
                alert('Open a meal slot first, then add ingredients.');
                return;
            }
            const existing = document.querySelector(`[data-ingredient-id="${id}"]`);
            if (existing) {
                alert('This ingredient is already added to your meal!');
                return;
            }
            const container = document.getElementById('ingredients-container');
            if (!container) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'selected-ingredient-item';
            wrapper.setAttribute('data-ingredient-id', String(id));
            wrapper.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                    <span style="flex: 1; font-weight: 500;">${name}</span>
                    <span style="color: #6c757d; font-size: 0.9rem;">Available: ${available}</span>
                    <input type="number" name="ingredient_quantities[]" min="1" step="1" placeholder="1" 
                           style="width: 80px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" 
                           max="${available}" value="1">
                    <input type="hidden" name="selected_ingredients[]" value="${id}">
                    <button type="button" onclick="removeIngredient(this)" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">×</button>
                </div>`;
            container.appendChild(wrapper);
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const mealModal = document.getElementById('mealModal');
            if (event.target == mealModal) {
                closeMealModal();
            }
        }





        // Toggle ingredient quantity input when checkbox is checked
        function toggleIngredientQuantity(checkbox) {
            const ingredientItem = checkbox.closest('.ingredient-item');
            const quantityDiv = ingredientItem.querySelector('.ingredient-quantity');
            const quantityInput = quantityDiv.querySelector('input[type="number"]');
            const available = parseFloat(ingredientItem.getAttribute('data-available') || '0');
            
            if (checkbox.checked) {
                if (available <= 0) { checkbox.checked = false; return; }
                quantityDiv.style.display = 'flex';
                const defaultQty = Math.min(1, available);
                quantityInput.value = defaultQty;
                quantityInput.required = true;
                quantityInput.max = String(available);
                quantityInput.disabled = false;
            } else {
                quantityDiv.style.display = 'none';
                quantityInput.value = '';
                quantityInput.required = false;
                quantityInput.disabled = true;
            }
        }

        function closeMealModal() {
            // Remove quick selectors if they exist
            const quickSelectors = document.getElementById('quick-selectors');
            if (quickSelectors) {
                quickSelectors.remove();
            }
            
            document.getElementById('mealModal').classList.remove('show');
        }

        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Search and filter functionality for meal plans
        function filterMealPlans() {
            const searchTerm = document.getElementById('mealPlanSearch').value.toLowerCase().trim();
            const mealTypeFilter = document.getElementById('mealTypeFilter').value.toLowerCase();
            const planCards = document.querySelectorAll('.plan-card');
            let visibleCount = 0;

            planCards.forEach(card => {
                const searchText = card.getAttribute('data-search-text') || '';
                const mealType = card.getAttribute('data-meal-type') || '';
                
                // More flexible search - check if any word in search term matches
                let matchesSearch = true;
                if (searchTerm) {
                    const searchWords = searchTerm.split(/\s+/);
                    matchesSearch = searchWords.every(word => searchText.includes(word));
                }
                
                const matchesMealType = !mealTypeFilter || mealType === mealTypeFilter;
                
                if (matchesSearch && matchesMealType) {
                    card.style.display = 'block';
                    visibleCount++;
                    // Add highlight effect for search terms
                    if (searchTerm) {
                        highlightSearchTerms(card, searchTerm);
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const plansList = document.getElementById('plansList');
            let noResultsMsg = document.getElementById('noResultsMessage');
            
            if (visibleCount === 0 && (searchTerm || mealTypeFilter)) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMessage';
                    noResultsMsg.className = 'no-results';
                    noResultsMsg.innerHTML = `
                        <div class="no-results-content">
                            <div class="no-results-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="no-results-title">No meal plans found</h3>
                            <p class="no-results-subtitle">Try searching for:</p>
                            <ul class="no-results-suggestions">
                                <li>Meal names (e.g., "pasta", "salad")</li>
                                <li>Days (e.g., "monday", "thursday")</li>
                                <li>Meal types (e.g., "breakfast", "lunch")</li>
                                <li>Ingredients (e.g., "salmon", "chicken")</li>
                            </ul>
                        </div>
                    `;
                    plansList.appendChild(noResultsMsg);
                }
                noResultsMsg.style.display = 'block';
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }

        }

        // Highlight search terms in visible cards
        function highlightSearchTerms(card, searchTerm) {
            const searchWords = searchTerm.split(/\s+/);
            const title = card.querySelector('.plan-title');
            const ingredients = card.querySelectorAll('.ingredient-tag');
            
            // Remove existing highlights
            card.querySelectorAll('.search-highlight').forEach(el => {
                el.outerHTML = el.innerHTML;
            });
            
            // Highlight in title
            if (title) {
                searchWords.forEach(word => {
                    if (word.length > 1) {
                        const regex = new RegExp(`(${word})`, 'gi');
                        title.innerHTML = title.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            }
            
            // Highlight in ingredients
            ingredients.forEach(ingredient => {
                searchWords.forEach(word => {
                    if (word.length > 1) {
                        const regex = new RegExp(`(${word})`, 'gi');
                        ingredient.innerHTML = ingredient.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            });
        }



        // Week navigation
        function changeWeek(direction) {
            const url = new URL(window.location.href);
            const currentOffset = parseInt(url.searchParams.get('week_offset') || '0', 10);
            const nextOffset = currentOffset + direction;
            url.searchParams.set('week_offset', String(nextOffset));
            window.location.href = url.toString();
        }

        // Close modal when clicking outside
        document.getElementById('mealModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMealModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);

        function toggleRecipes() {
            var recipesDiv = document.getElementById('recipes-toggle-section');
            if (recipesDiv.style.display === 'none' || recipesDiv.style.display === '') {
                recipesDiv.style.display = 'block';
            } else {
                recipesDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>