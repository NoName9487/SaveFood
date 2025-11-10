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

    // Get analytics data
    $analyticsData = getAnalyticsData($pdo, $_SESSION['user_id']);
    
    // Sync goal progress with actual database data
    syncGoalProgress($pdo, $_SESSION['user_id']);
    
    // Get user goals
    $userGoals = getUserGoals($pdo, $_SESSION['user_id']);

    // Handle goal actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'save_goal') {
                $goalType = $_POST['goal_type'] ?? '';
                $targetValue = $_POST['target_value'] ?? 0;
                $description = $_POST['description'] ?? '';
                
                if ($goalType && $targetValue > 0) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO user_goals (user_id, goal_type, target_value, description) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$_SESSION['user_id'], $goalType, $targetValue, $description]);
                        
                        echo json_encode(['success' => true, 'message' => 'Goal saved successfully']);
                        exit;
                    } catch(Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'Error saving goal']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid goal data']);
                    exit;
                }
            } elseif ($_POST['action'] === 'delete_goal') {
                $goalId = $_POST['goal_id'] ?? 0;
                
                if ($goalId > 0) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE user_goals 
                            SET status = 'paused' 
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$goalId, $_SESSION['user_id']]);
                        
                        echo json_encode(['success' => true, 'message' => 'Goal deleted successfully']);
                        exit;
                    } catch(Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'Error deleting goal']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid goal ID']);
                    exit;
                }
            }
        }
    }

    // Function to get analytics data
    function getAnalyticsData($pdo, $userId) {
        $data = [];
        
        try {
            // Total food saved (from donations) - all donations regardless of status
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_donations, 
                       COALESCE(SUM(CASE 
                           WHEN quantity REGEXP '^[0-9]+$' THEN CAST(quantity AS UNSIGNED)
                           WHEN quantity REGEXP '^[0-9]+\\.[0-9]+$' THEN CAST(quantity AS DECIMAL(10,2))
                           ELSE 0 
                       END), 0) as total_quantity
                FROM donations 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $donationData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Current inventory count
            $stmt = $pdo->prepare("SELECT COUNT(*) as inventory_count FROM food_inventory WHERE user_id = ?");
            $stmt->execute([$userId]);
            $inventoryData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Expiring items (next 3 days)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as expiring_count 
                FROM food_inventory 
                WHERE user_id = ? AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND expiry_date >= CURDATE()
            ");
            $stmt->execute([$userId]);
            $expiringData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Monthly donation trend (last 6 months) - all donations
            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
                FROM donations 
                WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$userId]);
            $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Monthly inventory addition trend (last 6 months)
        $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
            FROM food_inventory 
                WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$userId]);
            $inventoryTrendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Category breakdown - all donations
            $stmt = $pdo->prepare("
                SELECT category, COUNT(*) as count
                FROM donations 
                WHERE user_id = ?
                GROUP BY category
            ");
            $stmt->execute([$userId]);
            $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Additional inventory data
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_items FROM food_inventory WHERE user_id = ?");
            $stmt->execute([$userId]);
            $totalItems = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as expired_items FROM food_inventory WHERE user_id = ? AND expiry_date < CURDATE()");
            $stmt->execute([$userId]);
            $expiredItems = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total_quantity FROM food_inventory WHERE user_id = ?");
            $stmt->execute([$userId]);
            $totalQuantity = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate total money saved from claimed donations (RM5 per claimed donation)
            $stmt = $pdo->prepare("SELECT COUNT(*) as claimed_donations FROM donations WHERE user_id = ? AND status = 'claimed'");
            $stmt->execute([$userId]);
            $claimedDonations = $stmt->fetch(PDO::FETCH_ASSOC);
            $money_saved = ($claimedDonations['claimed_donations'] ?? 0) * 5;
            
            $data = [
                'total_donations' => $donationData['total_donations'] ?? 0,
                'total_quantity' => $donationData['total_quantity'] ?? 0,
                'inventory_count' => $inventoryData['inventory_count'] ?? 0,
                'expiring_count' => $expiringData['expiring_count'] ?? 0,
                'trend_data' => $trendData,
                'inventory_trend_data' => $inventoryTrendData,
                'category_data' => $categoryData,
                'total_items' => $totalItems['total_items'] ?? 0,
                'expired_items' => $expiredItems['expired_items'] ?? 0,
                'total_quantity_inventory' => $totalQuantity['total_quantity'] ?? 0,
                'money_saved' => $money_saved
            ];

    } catch (Exception $e) {
            error_log("Error getting analytics data: " . $e->getMessage());
            $data = [
                'total_donations' => 0,
                'total_quantity' => 0,
                'inventory_count' => 0,
                'expiring_count' => 0,
                'trend_data' => [],
                'inventory_trend_data' => [],
                'category_data' => [],
                'total_items' => 0,
                'expired_items' => 0,
                'total_quantity_inventory' => 0,
                'money_saved' => 0
            ];
        }
        
        return $data;
    }

    // Function to get user goals with updated progress
    function getUserGoals($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM user_goals 
                WHERE user_id = ? AND status = 'active' 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update current values based on actual database data
            foreach ($goals as &$goal) {
                $goal['current_value'] = getCurrentGoalProgress($pdo, $userId, $goal['goal_type']);
            }
            
            return $goals;
        } catch(Exception $e) {
            error_log("Error fetching user goals: " . $e->getMessage());
            return [];
        }
    }


    $current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - Food Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .kpi-icon.purple {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.3);
        }

        .kpi-icon.teal {
            background: linear-gradient(135deg, #00BCD4, #0097A7);
            box-shadow: 0 4px 15px rgba(0, 188, 212, 0.3);
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
            display: flex;
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

        .modal-content {
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        width: 450px;
        max-width: 90%;
        position: relative;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .modal-content h2 {
        margin-bottom: 20px;
        color: #2c3e50;
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
                <a href="/bit216_assignment/meal_plan1.php" class="nav-link <?php echo $current_page == 'meal_plan1.php' && isset($_GET['section']) && $_GET['section'] == 'stats' ? 'active' : ''; ?>">
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Food Analytics Dashboard</h1>
                    <p class="page-subtitle">Track your food saving impact and manage your inventory</p>
                </div>
                <div class="header-right">
                    <div class="export-actions">
                        <button class="btn btn-outline" onclick="downloadSimplePDF()" title="Download Simple PDF with Charts">
                            <i class="fas fa-file-pdf"></i> PDF Report
                        </button>
                    </div>
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
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <!-- Priority 1: Current Inventory - Most critical for daily operations -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value"><?php echo $analyticsData['inventory_count']; ?></div>
                        <div class="kpi-label">Current Inventory</div>
                        <div class="kpi-subtitle">Items in your inventory</div>
                    </div>
                    <div class="kpi-icon blue">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>

            <!-- Priority 2: Expiring Soon - Urgent action needed -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value"><?php echo $analyticsData['expiring_count']; ?></div>
                        <div class="kpi-label">Expiring Soon</div>
                        <div class="kpi-subtitle">Items expiring in 3 days</div>
                    </div>
                    <div class="kpi-icon green">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <!-- Priority 3: Already Expired - Immediate attention required -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value"><?php echo $analyticsData['expired_items']; ?></div>
                        <div class="kpi-label">Already Expired</div>
                        <div class="kpi-subtitle">Items past expiry date</div>
                    </div>
                    <div class="kpi-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <!-- Priority 4: Donations Made - Shows positive impact -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value"><?php echo $analyticsData['total_donations']; ?></div>
                        <div class="kpi-label">Donations Made</div>
                        <div class="kpi-subtitle">Helped <?php echo ceil($analyticsData['total_donations'] * 0.7); ?> families</div>
                    </div>
                    <div class="kpi-icon orange">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
            </div>

            <!-- Priority 5: Money Saved - Motivational metric -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value">RM<?php echo number_format($analyticsData['money_saved'], 2); ?></div>
                        <div class="kpi-label">Money Saved</div>
                        <div class="kpi-subtitle">From claimed donations</div>
                    </div>
                    <div class="kpi-icon teal">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                </div>
            </div>

            <!-- Priority 6: Total Food Saved - Overall achievement metric -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-value"><?php echo number_format($analyticsData['total_quantity'], 1); ?> kg</div>
                        <div class="kpi-label">Total Food Saved</div>
                        <div class="kpi-subtitle">Equivalent to <?php echo number_format($analyticsData['total_quantity'] * 4); ?> meals</div>
                    </div>
                    <div class="kpi-icon">
                        <i class="fas fa-apple-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiry Alerts Section -->
        <?php if ($analyticsData['expiring_count'] > 0): ?>
        <div class="alert-section">
            <div class="alert-container">
                <div class="alert-header">
                    <div class="alert-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Items Expiring Soon</span>
                    </div>
                    <button class="btn btn-primary" onclick="window.location.href='view_inventory.php'">
                        <i class="fas fa-eye"></i> View Inventory
                    </button>
                </div>
                <div class="alert-content">
                    <p>You have <strong><?php echo $analyticsData['expiring_count']; ?></strong> items expiring in the next 3 days. Consider donating them or using them in your meals!</p>
                    <div class="alert-actions">
                        <button class="btn btn-secondary" onclick="window.location.href='mydonation.php'">
                            <i class="fas fa-gift"></i> Make Donation
                        </button>
                        <button class="btn btn-outline" onclick="showMealSuggestions()">
                            <i class="fas fa-utensils"></i> Get Meal Ideas
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Donations Over Time</h3>
                    <select class="chart-dropdown" id="donationTimeFilter">
                        <option value="week" selected>Last Week</option>
                        <option value="month">Last Month</option>
                        <option value="year">Last Year</option>
                    </select>
                </div>
                <canvas id="donationChart"></canvas>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Donations by Category</h3>
                    <select class="chart-dropdown" id="categoryFilter">
                        <option value="all" selected>All Categories</option>
                        <?php 
                        // Get unique categories from the database
                        $stmt = $pdo->prepare("SELECT DISTINCT category FROM donations WHERE user_id = ? ORDER BY category");
                        $stmt->execute([$_SESSION['user_id']]);
                        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($categories as $category) {
                            if (!empty($category)) {
                                echo '<option value="' . htmlspecialchars($category) . '">' . htmlspecialchars($category) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <canvas id="categoryChart"></canvas>
                <div class="category-breakdown" id="categoryBreakdown">
                    <!-- Category breakdown will be displayed here -->
                </div>
                    </div>
                </div>

        <!-- Goals Tracking Section -->
        <div class="goals-section">
            <div class="goals-container">
                <div class="goals-header">
                    <h3><i class="fas fa-trophy"></i> Your Goals & Achievements</h3>
            </div>
                <div class="goals-grid">
                    <?php
                    // Get current progress for default goals
                    $donationProgress = getCurrentGoalProgress($pdo, $_SESSION['user_id'], 'donations');
                    $quantityProgress = getCurrentGoalProgress($pdo, $_SESSION['user_id'], 'quantity');
                    $inventoryProgress = getCurrentGoalProgress($pdo, $_SESSION['user_id'], 'inventory');
                    
                    // Default goal targets (auto-increase when reached) - using new progression: 5→20→50→100
                    $donationTarget = getCurrentTarget('donations', $donationProgress);
                    $quantityTarget = getCurrentTarget('quantity', $quantityProgress);
                    $inventoryTarget = getCurrentTarget('inventory', $inventoryProgress);
                    
                    $defaultGoals = [
                        [
                            'type' => 'donations',
                            'icon' => 'fas fa-gift',
                            'title' => 'Donations',
                            'current' => $donationProgress,
                            'target' => $donationTarget,
                            'unit' => 'donations'
                        ],
                        [
                            'type' => 'quantity',
                            'icon' => 'fas fa-weight',
                            'title' => 'Food Saved',
                            'current' => $quantityProgress,
                            'target' => $quantityTarget,
                            'unit' => 'kg'
                        ],
                        [
                            'type' => 'inventory',
                            'icon' => 'fas fa-box',
                            'title' => 'Inventory Items',
                            'current' => $inventoryProgress,
                            'target' => $inventoryTarget,
                            'unit' => 'items'
                        ]
                    ];
                    
                    foreach ($defaultGoals as $goal):
                        $progressPercentage = $goal['target'] > 0 ? min(100, ($goal['current'] / $goal['target']) * 100) : 0;
                        $isCompleted = $goal['current'] >= $goal['target'];
                    ?>
                        <div class="goal-card <?php echo $isCompleted ? 'completed' : ''; ?>">
                            <div class="goal-icon">
                                <i class="<?php echo $goal['icon']; ?>"></i>
                            </div>
                            <div class="goal-content">
                                <h4><?php echo $goal['title']; ?></h4>
                                <div class="goal-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo $goal['current']; ?>/<?php echo $goal['target']; ?> <?php echo $goal['unit']; ?></span>
                                </div>
                                <?php if ($isCompleted): ?>
                                    <div class="goal-completed">
                                        <i class="fas fa-check-circle"></i> Goal Achieved!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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

            // Get real data from PHP
            const donationTrendData = <?php echo json_encode($analyticsData['trend_data']); ?>;
            const categoryData = <?php echo json_encode($analyticsData['category_data']); ?>;
            
            // Process donation trend data for chart
            const trendLabels = donationTrendData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            const trendValues = donationTrendData.map(item => parseInt(item.count));
            
            // Process category data for chart
            const categoryLabels = categoryData.map(item => item.category);
            const categoryValues = categoryData.map(item => parseInt(item.count));
            
            // Calculate percentages and create breakdown
            const totalDonations = categoryValues.reduce((sum, value) => sum + value, 0);
            const categoryPercentages = categoryValues.map(value => 
                totalDonations > 0 ? Math.round((value / totalDonations) * 100) : 0
            );
            
            // Donations Over Time Chart
            const donationCtx = document.getElementById('donationChart').getContext('2d');
            const donationChart = new Chart(donationCtx, {
                type: 'line',
                data: {
                    labels: trendLabels.length > 0 ? trendLabels : ['No Data'],
                    datasets: [{
                        label: 'Donations Made',
                        data: trendValues.length > 0 ? trendValues : [0],
                        backgroundColor: 'rgba(76, 175, 80, 0.2)',
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Donations'
                            }
                        }
                    }
                }
            });
            
            // Donations by Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels.length > 0 ? categoryLabels : ['No Data'],
                    datasets: [{
                        data: categoryValues.length > 0 ? categoryValues : [1],
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(33, 150, 243, 0.8)',
                            'rgba(255, 152, 0, 0.8)',
                            'rgba(156, 39, 176, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)'
                        ],
                        borderColor: [
                            'rgba(76, 175, 80, 1)',
                            'rgba(33, 150, 243, 1)',
                            'rgba(255, 152, 0, 1)',
                            'rgba(156, 39, 176, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    },
                    onClick: function(event, elements) {
                        if (elements.length > 0) {
                            const clickedIndex = elements[0].index;
                            const category = categoryLabels[clickedIndex];
                            showCategoryItems(category);
                        }
                    }
                }
            });
            
            // Display category breakdown
            displayCategoryBreakdown(categoryLabels, categoryValues, categoryPercentages);
            
            // Filter functionality
            document.getElementById('donationTimeFilter').addEventListener('change', function() {
                updateChartData();
            });
            
            document.getElementById('categoryFilter').addEventListener('change', function() {
                updateCategoryChart(this.value);
            });
            
            // Load "Last Week" data by default
            updateChartData();
            
            // Function to update chart data based on filters
            function updateChartData() {
                const timeframe = document.getElementById('donationTimeFilter').value;
                console.log(`Timeframe changed to: ${timeframe}`);
                
                // Fetch real data from server based on timeframe
                fetch('get_chart_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `timeframe=${timeframe}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the chart with real data
                        donationChart.data.labels = data.labels;
                        donationChart.data.datasets[0].data = data.values;
                        donationChart.update();
                    } else {
                        console.error('Error fetching chart data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
            
            // Function to update category chart
            function updateCategoryChart(category) {
                // Fetch real category data from server
                fetch('get_chart_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=category&category=${category}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the category chart with real data
                        categoryChart.data.labels = data.labels;
                        categoryChart.data.datasets[0].data = data.values;
                        categoryChart.update();
                        
                        // Update category breakdown
                        displayCategoryBreakdown(data.labels, data.values, data.percentages);
                    } else {
                        console.error('Error fetching category data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
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

        // Meal suggestions function
        function showMealSuggestions() {
            const mealIdeas = [
                { emoji: "🍳", name: "Scrambled eggs with vegetables", difficulty: "Easy", time: "15 min" },
                { emoji: "🥗", name: "Fresh salad with mixed greens", difficulty: "Easy", time: "10 min" },
                { emoji: "🍝", name: "Pasta with tomato sauce", difficulty: "Medium", time: "25 min" },
                { emoji: "🥪", name: "Grilled cheese sandwich", difficulty: "Easy", time: "10 min" },
                { emoji: "🍲", name: "Vegetable soup", difficulty: "Medium", time: "30 min" },
                { emoji: "🥘", name: "Stir-fried vegetables", difficulty: "Easy", time: "20 min" },
                { emoji: "🍕", name: "Homemade pizza", difficulty: "Hard", time: "45 min" },
                { emoji: "🥙", name: "Wraps with fresh ingredients", difficulty: "Easy", time: "15 min" },
                { emoji: "🍛", name: "Rice bowl with vegetables", difficulty: "Medium", time: "25 min" },
                { emoji: "🥞", name: "Pancakes with fruit", difficulty: "Easy", time: "20 min" }
            ];
            
            const randomMeals = mealIdeas.sort(() => 0.5 - Math.random()).slice(0, 3);
            
            const modal = document.createElement('div');
            modal.className = 'modal meal-suggestions-modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content meal-modal-content">
                    <div class="modal-header meal-modal-header">
                        <div class="header-content">
                            <div class="header-icon">🍽️</div>
                            <div class="header-text">
                                <h2 class="modal-title">Meal Suggestions</h2>
                                <p class="modal-subtitle">Delicious ideas for your expiring items</p>
                            </div>
                        </div>
                        <button class="close-btn meal-close-btn" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body meal-modal-body">
                        <div class="meal-cards">
                            ${randomMeals.map((meal, index) => `
                                <div class="meal-card" style="animation-delay: ${index * 0.1}s">
                                    <div class="meal-emoji">${meal.emoji}</div>
                                    <div class="meal-info">
                                        <h3 class="meal-name">${meal.name}</h3>
                                        <div class="meal-meta">
                                            <span class="difficulty ${meal.difficulty.toLowerCase()}">${meal.difficulty}</span>
                                            <span class="time">⏱️ ${meal.time}</span>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="tip-section">
                            <div class="tip-icon">💡</div>
                            <div class="tip-content">
                                <h4>Pro Tip</h4>
                                <p>Use your expiring items in creative ways to reduce waste and save money while enjoying delicious meals!</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer meal-modal-footer">
                        <button class="btn btn-primary meal-btn" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-check"></i>
                            Got it!
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Function to display category breakdown
        function displayCategoryBreakdown(labels, values, percentages) {
            const breakdownContainer = document.getElementById('categoryBreakdown');
            
            if (labels.length === 0 || values.every(v => v === 0)) {
                breakdownContainer.innerHTML = `
                    <div class="breakdown-title">No donations yet</div>
                    <div class="breakdown-items">
                        <div class="breakdown-item">
                            <div class="breakdown-color" style="background-color: #e9ecef;"></div>
                            <span class="breakdown-label">Start donating to see breakdown</span>
                        </div>
                    </div>
                `;
                return;
            }

            const colors = [
                'rgba(76, 175, 80, 0.8)',   // Green
                'rgba(244, 67, 54, 0.8)',   // Red
                'rgba(33, 150, 243, 0.8)',  // Blue
                'rgba(255, 152, 0, 0.8)',   // Orange
                'rgba(156, 39, 176, 0.8)',  // Purple
                'rgba(0, 188, 212, 0.8)'    // Cyan
            ];

            const breakdownItems = labels.map((label, index) => {
                const percentage = percentages[index];
                const value = values[index];
                const color = colors[index % colors.length];
                
                return `
                    <div class="breakdown-item">
                        <div class="breakdown-color" style="background-color: ${color};"></div>
                        <span class="breakdown-label">${label}</span>
                        <span class="breakdown-percentage">${percentage}%</span>
                    </div>
                `;
            }).join('');

            breakdownContainer.innerHTML = `
                <div class="breakdown-items">
                    ${breakdownItems}
                </div>
            `;
        }

        // Function to show category items
        function showCategoryItems(category) {
            // Create a modal to show items in the selected category
            const modal = document.createElement('div');
            modal.className = 'modal category-items-modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content category-modal-content">
                    <div class="modal-header category-modal-header">
                        <div class="header-content">
                            <div class="header-icon">📦</div>
                            <div class="header-text">
                                <h2 class="modal-title">${category} Items</h2>
                                <p class="modal-subtitle">Items donated in this category</p>
                            </div>
                        </div>
                        <button class="close-btn category-close-btn" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body category-modal-body">
                        <div class="items-list" id="categoryItemsList">
                            <div class="loading">Loading items...</div>
                        </div>
                    </div>
                    <div class="modal-footer category-modal-footer">
                        <button class="btn btn-primary category-btn" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-check"></i>
                            Close
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Fetch items for this category
            fetchCategoryItems(category);
        }

        // Function to fetch category items from database
        function fetchCategoryItems(category) {
            // For now, we'll show sample data. In a real implementation, this would make an AJAX call
            const sampleItems = {
                'snacks': [
                    { name: 'Mixed Nuts', quantity: '1 bag', date: '2024-01-15' },
                    { name: 'Granola Bars', quantity: '3 boxes', date: '2024-01-10' },
                    { name: 'Trail Mix', quantity: '2 packets', date: '2024-01-08' }
                ],
                'produce': [
                    { name: 'Fresh Apples', quantity: '5 lbs', date: '2024-01-12' },
                    { name: 'Bananas', quantity: '1 bunch', date: '2024-01-14' }
                ],
                'dairy': [
                    { name: 'Milk', quantity: '1 gallon', date: '2024-01-13' },
                    { name: 'Cheese', quantity: '2 blocks', date: '2024-01-11' }
                ],
                'bakery': [
                    { name: 'Bread', quantity: '2 loaves', date: '2024-01-09' },
                    { name: 'Muffins', quantity: '6 pieces', date: '2024-01-07' }
                ]
            };

            const items = sampleItems[category.toLowerCase()] || [
                { name: 'Sample Item', quantity: '1 unit', date: '2024-01-01' }
            ];

            const itemsList = document.getElementById('categoryItemsList');
            itemsList.innerHTML = items.map(item => `
                <div class="item-card">
                    <div class="item-info">
                        <h3 class="item-name">${item.name}</h3>
                        <div class="item-details">
                            <span class="item-quantity">📦 ${item.quantity}</span>
                            <span class="item-date">📅 ${item.date}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Export data function - make it globally accessible
        window.downloadPDFReport = function() {
            console.log('PDF download function called');
            
            // Check if charts exist and are ready
            const donationChartElement = document.getElementById('donationChart');
            const categoryChartElement = document.getElementById('categoryChart');
            
            if (!donationChartElement || !categoryChartElement) {
                alert('Charts are not ready yet. Please wait a moment and try again.');
                return;
            }
            
            // Convert charts to images with error handling
            let donationChartImage = '';
            let categoryChartImage = '';
            
            try {
                donationChartImage = donationChartElement.toDataURL('image/png');
                categoryChartImage = categoryChartElement.toDataURL('image/png');
            } catch (error) {
                console.error('Error converting charts to images:', error);
                // Continue without chart images if conversion fails
                donationChartImage = '';
                categoryChartImage = '';
            }
            
            // Get current analytics data
            const data = {
                total_donations: <?php echo $analyticsData['total_donations']; ?>,
                total_quantity: <?php echo $analyticsData['total_quantity']; ?>,
                inventory_count: <?php echo $analyticsData['inventory_count']; ?>,
                expiring_count: <?php echo $analyticsData['expiring_count']; ?>,
                total_items: <?php echo $analyticsData['total_items']; ?>,
                expired_items: <?php echo $analyticsData['expired_items']; ?>,
                export_date: new Date().toLocaleDateString(),
                user_name: '<?php echo htmlspecialchars($userData['username']); ?>',
                donation_chart: donationChartImage,
                category_chart: categoryChartImage
            };
            
            // Create a new window for PDF generation
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString();
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Food Analytics Report - ${currentDate}</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px; 
                            color: #333;
                            line-height: 1.6;
                        }
                        .header { 
                            text-align: center; 
                            border-bottom: 3px solid #4CAF50; 
                            padding-bottom: 20px; 
                            margin-bottom: 30px;
                        }
                        .header h1 { 
                            color: #4CAF50; 
                            margin: 0; 
                            font-size: 28px;
                        }
                        .header p { 
                            color: #666; 
                            margin: 5px 0 0 0; 
                            font-size: 14px;
                        }
                        .section { 
                            margin-bottom: 30px; 
                            page-break-inside: avoid;
                        }
                        .section h2 { 
                            color: #2c3e50; 
                            border-bottom: 2px solid #4CAF50; 
                            padding-bottom: 10px; 
                            margin-bottom: 20px;
                        }
                        .summary-table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin-top: 20px;
                        }
                        .summary-table th, .summary-table td { 
                            border: 1px solid #ddd; 
                            padding: 12px; 
                            text-align: left;
                        }
                        .summary-table th { 
                            background-color: #4CAF50; 
                            color: white; 
                            font-weight: bold;
                        }
                        .summary-table tr:nth-child(even) { 
                            background-color: #f2f2f2;
                        }
                        .footer { 
                            margin-top: 40px; 
                            text-align: center; 
                            color: #666; 
                            font-size: 12px; 
                            border-top: 1px solid #ddd; 
                            padding-top: 20px;
                        }
                        .chart-section {
                            margin: 30px 0;
                            page-break-before: always;
                        }
                        .chart-container {
                            display: flex;
                            justify-content: space-between;
                            gap: 20px;
                            margin: 20px 0;
                        }
                        .chart-item {
                            flex: 1;
                            text-align: center;
                        }
                        .chart-item img {
                            max-width: 100%;
                            height: auto;
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        }
                        .chart-title {
                            font-size: 16px;
                            font-weight: bold;
                            color: #2c3e50;
                            margin-bottom: 10px;
                        }
                        .details-section {
                            page-break-after: always;
                        }
                        @media print {
                            body { margin: 0; }
                            .section { page-break-inside: avoid; }
                            .chart-container { 
                                display: block; 
                            }
                            .chart-item { 
                                margin-bottom: 30px; 
                            }
                            .chart-section {
                                page-break-before: always;
                            }
                            .details-section {
                                page-break-after: always;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Food Analytics Report</h1>
                        <p>Generated on ${currentDate} for ${data.user_name}</p>
                    </div>
                    
                    
                    <div class="section details-section">
                        <h2>Detailed Summary</h2>
                        <table class="summary-table">
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                            <tr>
                                <td>Total Food Saved</td>
                                <td>${data.total_quantity} kg</td>
                                <td>Total quantity of food saved from waste</td>
                            </tr>
                            <tr>
                                <td>Donations Made</td>
                                <td>${data.total_donations}</td>
                                <td>Number of successful donations</td>
                            </tr>
                            <tr>
                                <td>Current Inventory</td>
                                <td>${data.inventory_count}</td>
                                <td>Items currently in inventory</td>
                            </tr>
                            <tr>
                                <td>Total Items Added</td>
                                <td>${data.total_items}</td>
                                <td>Total items added to inventory</td>
                            </tr>
                            <tr>
                                <td>Expiring Soon</td>
                                <td>${data.expiring_count}</td>
                                <td>Items expiring in next 3 days</td>
                            </tr>
                            <tr>
                                <td>Already Expired</td>
                                <td>${data.expired_items}</td>
                                <td>Items past their expiry date</td>
                            </tr>
                            <tr>
                                <td>Money Saved</td>
                                <td>RM${data.money_saved.toFixed(2)}</td>
                                <td>Total money saved from claimed donations</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="section chart-section">
                        <h2>Analytics Charts</h2>
                        <div class="chart-container">
                            ${data.donation_chart ? `
                            <div class="chart-item">
                                <div class="chart-title">Donations Over Time</div>
                                <img src="${data.donation_chart}" alt="Donations Over Time Chart" />
                            </div>
                            ` : '<div class="chart-item"><div class="chart-title">Donations Over Time</div><p>Chart not available</p></div>'}
                            ${data.category_chart ? `
                            <div class="chart-item">
                                <div class="chart-title">Donations by Category</div>
                                <img src="${data.category_chart}" alt="Donations by Category Chart" />
                            </div>
                            ` : '<div class="chart-item"><div class="chart-title">Donations by Category</div><p>Chart not available</p></div>'}
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>This report was generated automatically by the Food Analytics Dashboard</p>
                        <p>For more detailed analytics, visit your dashboard</p>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load, then trigger print
            setTimeout(() => {
                try {
                    printWindow.print();
                    console.log('PDF generated successfully');
                    // Don't close immediately, let user see the print dialog
                    setTimeout(() => {
                        printWindow.close();
                    }, 2000);
                } catch (error) {
                    console.error('Error generating PDF:', error);
                    alert('Error generating PDF. Please try again or check your browser settings.');
                    printWindow.close();
                }
            }, 1500);
        }
        
        // Simple PDF generation with charts
        window.downloadSimplePDF = function() {
            console.log('Simple PDF download function called');
            
            // Check if charts exist and are ready
            const donationChartElement = document.getElementById('donationChart');
            const categoryChartElement = document.getElementById('categoryChart');
            
            if (!donationChartElement || !categoryChartElement) {
                alert('Charts are not ready yet. Please wait a moment and try again.');
                return;
            }
            
            // Convert charts to images with error handling
            let donationChartImage = '';
            let categoryChartImage = '';
            
            try {
                donationChartImage = donationChartElement.toDataURL('image/png');
                categoryChartImage = categoryChartElement.toDataURL('image/png');
            } catch (error) {
                console.error('Error converting charts to images:', error);
                // Continue without chart images if conversion fails
                donationChartImage = '';
                categoryChartImage = '';
            }
            
            // Get current analytics data
            const data = {
                total_donations: <?php echo $analyticsData['total_donations']; ?>,
                total_quantity: <?php echo $analyticsData['total_quantity']; ?>,
                inventory_count: <?php echo $analyticsData['inventory_count']; ?>,
                expiring_count: <?php echo $analyticsData['expiring_count']; ?>,
                expired_items: <?php echo $analyticsData['expired_items']; ?>,
                total_items: <?php echo $analyticsData['total_items']; ?>,
                money_saved: <?php echo $analyticsData['money_saved']; ?>,
                donation_chart: donationChartImage,
                category_chart: categoryChartImage
            };
            
            // Create a new window for PDF generation
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString();
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Food Analytics Report - ${currentDate}</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px; 
                            color: #333;
                            line-height: 1.6;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 2px solid #4CAF50;
                            padding-bottom: 20px;
                            margin-bottom: 30px;
                        }
                        .header h1 {
                            color: #4CAF50;
                            margin: 0;
                        }
                        .section {
                            margin-bottom: 30px;
                        }
                        .section h2 {
                            color: #2c3e50;
                            border-bottom: 1px solid #eee;
                            padding-bottom: 10px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 15px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 12px;
                            text-align: left;
                        }
                        th {
                            background-color: #f8f9fa;
                            font-weight: bold;
                        }
                        .footer {
                            margin-top: 40px;
                            text-align: center;
                            color: #666;
                            font-size: 0.9em;
                        }
                        .chart-container {
                            display: flex;
                            flex-direction: column;
                            gap: 20px;
                            margin-top: 20px;
                        }
                        .chart-item {
                            text-align: center;
                            border: 1px solid #eee;
                            border-radius: 8px;
                            padding: 15px;
                            background-color: #f9f9f9;
                        }
                        .chart-title {
                            font-weight: bold;
                            color: #2c3e50;
                            margin-bottom: 10px;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Food Analytics Report</h1>
                        <p>Generated on: ${currentDate}</p>
                    </div>
                    
                    <div class="section">
                        <h2>Key Metrics Summary</h2>
                        <table>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                            <tr>
                                <td>Total Food Saved</td>
                                <td>${data.total_quantity} kg</td>
                                <td>Total quantity of food saved from waste</td>
                            </tr>
                            <tr>
                                <td>Donations Made</td>
                                <td>${data.total_donations}</td>
                                <td>Number of successful donations</td>
                            </tr>
                            <tr>
                                <td>Current Inventory</td>
                                <td>${data.inventory_count}</td>
                                <td>Items currently in inventory</td>
                            </tr>
                            <tr>
                                <td>Total Items Added</td>
                                <td>${data.total_items}</td>
                                <td>Total items added to inventory</td>
                            </tr>
                            <tr>
                                <td>Expiring Soon</td>
                                <td>${data.expiring_count}</td>
                                <td>Items expiring in next 3 days</td>
                            </tr>
                            <tr>
                                <td>Already Expired</td>
                                <td>${data.expired_items}</td>
                                <td>Items past their expiry date</td>
                            </tr>
                            <tr>
                                <td>Money Saved</td>
                                <td>RM${data.money_saved.toFixed(2)}</td>
                                <td>Total money saved from claimed donations</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="section chart-section">
                        <h2>Analytics Charts</h2>
                        <div class="chart-container">
                            ${data.donation_chart ? `
                            <div class="chart-item">
                                <div class="chart-title">Donations Over Time</div>
                                <img src="${data.donation_chart}" alt="Donations Over Time Chart" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 8px; margin: 10px 0;" />
                            </div>
                            ` : '<div class="chart-item"><div class="chart-title">Donations Over Time</div><p>Chart not available</p></div>'}
                            ${data.category_chart ? `
                            <div class="chart-item">
                                <div class="chart-title">Donations by Category</div>
                                <img src="${data.category_chart}" alt="Donations by Category Chart" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 8px; margin: 10px 0;" />
                            </div>
                            ` : '<div class="chart-item"><div class="chart-title">Donations by Category</div><p>Chart not available</p></div>'}
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>This report was generated automatically by the Food Analytics Dashboard</p>
                        <p>For more detailed analytics, visit your dashboard</p>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load, then trigger print
            setTimeout(() => {
                try {
                    printWindow.print();
                    console.log('Simple PDF generated successfully');
                    setTimeout(() => {
                        printWindow.close();
                    }, 2000);
                } catch (error) {
                    console.error('Error generating simple PDF:', error);
                    alert('Error generating PDF. Please try again or check your browser settings.');
                    printWindow.close();
                }
            }, 1000);
        }
        
        
        // Save goal function
        function saveGoal() {
            const goalType = document.getElementById('goalType').value;
            const goalTarget = document.getElementById('goalTarget').value;
            const goalDescription = document.getElementById('goalDescription').value;
            
            if (!goalType || !goalTarget) {
                showNotification('Please fill in all required fields', 'error');
                return;
            }
            
            // Send data to server
            const formData = new FormData();
            formData.append('action', 'save_goal');
            formData.append('goal_type', goalType);
            formData.append('target_value', goalTarget);
            formData.append('description', goalDescription);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Goal saved successfully!', 'success');
                    // Close the modal
                    document.querySelector('.modal').remove();
                    // Reload the page to show the new goal
                    window.location.reload();
                } else {
                    showNotification('Error saving goal: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving goal. Please try again.', 'error');
            });
        }

        function deleteGoal(goalId) {
            if (!confirm('Are you sure you want to delete this goal?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_goal');
            formData.append('goal_id', goalId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Goal deleted successfully!', 'success');
                    // Reload the page to update the goals
                    window.location.reload();
                } else {
                    showNotification('Error deleting goal: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deleting goal. Please try again.', 'error');
            });
        }

        // Show notification function
        function showNotification(message, type) {
            // Create notification element if it doesn't exist
            let notification = document.getElementById('notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'notification';
                notification.className = 'notification';
                notification.innerHTML = `
                    <i id="notification-icon"></i>
                    <span id="notification-message"></span>
                    <button class="close-btn" id="close-notification">&times;</button>
                `;
                document.body.appendChild(notification);
                
                // Add event listener for close button
                document.getElementById('close-notification').addEventListener('click', hideNotification);
            }
            
            // Set notification content and style
            document.getElementById('notification-message').textContent = message;
            notification.className = 'notification ' + type;
            
            // Set icon based on type
            if (type === 'success') {
                document.getElementById('notification-icon').className = 'fas fa-check-circle';
            } else if (type === 'error') {
                document.getElementById('notification-icon').className = 'fas fa-exclamation-circle';
            } else {
                document.getElementById('notification-icon').className = 'fas fa-info-circle';
            }
            
            // Show notification
            notification.classList.add('show');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        // Hide notification function
        function hideNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.classList.remove('show');
            }
        }
    </script>
</body>
</html>