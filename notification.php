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




    $current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - Notifications</title>
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
            background: linear-gradient(145deg, #f5f5f5 0%, #fafafa 100%);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            opacity: 0.9;
        }

        .notification-card.read .notification-title {
            color: #6c757d;
            font-weight: 500;
        }

        .notification-card.read .notification-message {
            color: #868e96;
        }

        .notification-card.read .notification-icon {
            opacity: 0.6;
            filter: grayscale(30%);
        }

        .notification-card.read .notification-item {
            background: #e9ecef;
            color: #6c757d;
            opacity: 0.8;
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
            background: linear-gradient(145deg, #e8f5e8 0%, #f0fdf4 100%);
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
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.claim_request {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }

        .notification-icon.donation_claimed {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
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

        /* Meal Reminder Notification Styling */
        .notification-card[data-type="meal_reminder"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="meal_reminder"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="meal_reminder"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="meal_reminder"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="meal_reminder"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="meal_reminder"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="meal_reminder"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Claim Request Notification Styling */
        .notification-card[data-type="claim_request"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="claim_request"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="claim_request"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="claim_request"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="claim_request"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="claim_request"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="claim_request"] .action-btn.dismiss:hover {
            background: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        /* Donation Claimed Notification Styling */
        .notification-card[data-type="donation_claimed"] {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #4CAF50;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.15);
            position: relative;
        }

        .notification-card[data-type="donation_claimed"]::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 2px 0 0 2px;
        }

        .notification-card[data-type="donation_claimed"] .notification-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .notification-card[data-type="donation_claimed"] .notification-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-card[data-type="donation_claimed"] .notification-item {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-card[data-type="donation_claimed"] .action-btn.dismiss {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .notification-card[data-type="donation_claimed"] .action-btn.dismiss:hover {
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Notification Center</h1>
                    <p class="page-subtitle">Stay updated with your food management alerts and important updates</p>
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

        <!-- Notifications Content -->
        <div class="container">
            <!-- Notifications Box Container -->
            <div class="notifications-box">
        <!-- Notifications Tab -->
        <div id="notifications-tab" class="tab-content active">
            <div class="notifications-header">
                <div class="section-title">
                    <i class="fas fa-bell"></i>
                    <h2>Your Notifications</h2>
                </div>
                <div>
                    <button class="mark-all-read" onclick="markAllAsRead()">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </div>
            </div>

            <div class="filters-bar">
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterNotifications('all')">All</button>
                    <button class="filter-btn" onclick="filterNotifications('unread')">Unread</button>
                    <button class="filter-btn" onclick="filterNotifications('inventory')">Inventory Alerts</button>
                    <button class="filter-btn" onclick="filterNotifications('donations')">Donation Updates</button>
                    <button class="filter-btn" onclick="filterNotifications('meals')">Meal Planning Reminders</button>
                </div>
            </div>

            <div class="notifications-container" id="notifications-list">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Loading notifications...</p>
                </div>
            </div>
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

        // Notification functionality
        let notifications = [];
        let currentFilter = 'all';

        // Load notifications from the database
        function loadNotifications() {
            const container = document.getElementById('notifications-list');
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading notifications...</p></div>';

            const userId = <?php echo $_SESSION['user_id']; ?>;
            console.log('Loading notifications for user ID:', userId);

            const formData = new FormData();
            formData.append('action', 'get_notifications');
            formData.append('user_id', userId);
            formData.append('page', 1);
            formData.append('limit', 50);
            formData.append('include_read', true);

            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Notification data received:', data);
                if (data.success) {
                    notifications = data.notifications || [];
                    console.log('Notifications loaded:', notifications.length);
                    displayNotifications();
                } else {
                    console.log('No notifications found or error:', data.message);
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-bell-slash"></i><h3>No notifications found</h3><p>You\'re all caught up!</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading notifications</h3><p>Please try again later.</p></div>';
            });
        }

        // Display notifications based on current filter
        function displayNotifications() {
            const container = document.getElementById('notifications-list');
            
            if (notifications.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-bell-slash"></i><h3>No notifications yet</h3><p>You\'ll see important alerts here about expiring items, donations, and more.</p></div>';
                return;
            }

            let filteredNotifications = notifications;
            
            // Apply filters
            if (currentFilter === 'unread') {
                filteredNotifications = notifications.filter(n => !n.is_read);
            } else if (currentFilter === 'inventory') {
                filteredNotifications = notifications.filter(n => 
                    n.type === 'expiry_warning' || n.type === 'inventory_alert');
            } else if (currentFilter === 'donations') {
                filteredNotifications = notifications.filter(n => 
                    n.type === 'donation_update' || n.type === 'pickup_arrangement' || n.type === 'donation_claimed' || n.type === 'claim_request' || n.type === 'claim_approved' || n.type === 'claim_rejected' || n.type === 'pickup_request_sent' || n.type === 'donation_created');
            } else if (currentFilter === 'meals') {
                filteredNotifications = notifications.filter(n => n.type === 'meal_reminder');
            }

            if (filteredNotifications.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-bell-slash"></i><h3>No notifications match your filter</h3><p>Try changing your filter or check back later.</p></div>';
                return;
            }

            container.innerHTML = filteredNotifications.map(notification => {
                const iconClass = getNotificationIcon(notification.type);
                const iconSymbol = getNotificationSymbol(notification.type);
                const categoryName = getCategoryName(notification.type);
                
                return `
                    <div class="notification-card ${notification.is_read ? 'read' : 'unread'}" data-type="${notification.type}" data-id="${notification.id}">
                        <div class="notification-datetime">
                            <i class="fas fa-clock"></i> ${formatDate(notification.created_at)}
                        </div>
                        <div class="notification-icon ${iconClass}">
                            ${iconSymbol}
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-meta">
                                <span class="notification-item">${categoryName}</span>
                            </div>
                            <div class="notification-actions">
                                ${notification.type === 'claim_request' && !notification.is_read ? 
                                    `<button class="action-btn approve" onclick="approveClaim(${notification.id})">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="action-btn reject" onclick="rejectClaim(${notification.id})">
                                        <i class="fas fa-times"></i> Reject
                                    </button>` : ''
                                }
                                <div class="action-buttons-right">
                                    ${!notification.is_read ? 
                                        `<button class="action-btn view" onclick="viewNotification(${notification.id})">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>` : ''
                                }
                                <button class="action-btn dismiss" onclick="dismissNotification(${notification.id})">
                                        <i class="fas fa-trash"></i> Delete
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Get notification icon class
        function getNotificationIcon(type) {
            switch(type) {
                case 'welcome': return 'welcome';
                case 'expiry_warning': return 'expiry_warning';
                case 'donation_update': 
                case 'donation_claimed':
                case 'pickup_arrangement': 
                case 'claim_request': 
                case 'claim_approved': 
                case 'claim_rejected': 
                case 'pickup_request_sent': 
                case 'donation_created': return 'donation_update';
                case 'meal_reminder': return 'meal_reminder';
                case 'inventory_alert': return 'inventory_alert';
                case 'success': return 'goal_achievement';
                default: return 'inventory_alert';
            }
        }

        // Get notification icon symbol
        function getNotificationSymbol(type) {
            switch(type) {
                case 'welcome': return '<i class="fas fa-leaf"></i>';
                case 'expiry_warning': return '<i class="fas fa-exclamation-triangle"></i>';
                case 'donation_update': 
                case 'donation_claimed':
                case 'pickup_arrangement': return '<i class="fas fa-hand-holding-heart"></i>';
                case 'claim_request': return '<i class="fas fa-user-check"></i>';
                case 'claim_approved': return '<i class="fas fa-check-circle"></i>';
                case 'claim_rejected': return '<i class="fas fa-times-circle"></i>';
                case 'pickup_request_sent': return '<i class="fas fa-paper-plane"></i>';
                case 'donation_created': return '<i class="fas fa-plus-circle"></i>';
                case 'meal_reminder': return '<i class="fas fa-utensils"></i>';
                case 'inventory_alert': return '<i class="fas fa-archive"></i>';
                case 'success': return '<i class="fas fa-trophy"></i>';
                default: return '<i class="fas fa-bell"></i>';
            }
        }

        // Get category name
        function getCategoryName(type) {
            switch(type) {
                case 'welcome': return 'Welcome';
                case 'expiry_warning': return 'Expiry Alert';
                case 'donation_update': return 'Donation Update';
                case 'donation_claimed': return 'DONATION CLAIMED';
                case 'pickup_arrangement': return 'Pickup Arrangement';
                case 'claim_request': return 'CLAIM REQUEST';
                case 'claim_approved': return 'Claim Update';
                case 'claim_rejected': return 'Claim Update';
                case 'pickup_request_sent': return 'Request Confirmation';
                case 'donation_created': return 'DONATION CREATED';
                case 'meal_reminder': return 'MEAL REMINDER';
                case 'inventory_alert': return 'Inventory Alert';
                case 'success': return 'Goal Achievement';
                default: return 'Notification';
            }
        }

        // Filter notifications by type
        function filterNotifications(type) {
            currentFilter = type;
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            // Update active filter button by exact label match
            filterButtons.forEach(btn => {
                const label = btn.textContent.trim().toLowerCase();
                if ((type === 'all' && label === 'all') ||
                    (type === 'unread' && label === 'unread') ||
                    (type === 'inventory' && label === 'inventory alerts') ||
                    (type === 'donations' && label === 'donation updates') ||
                    (type === 'meals' && label === 'meal planning reminders')) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            displayNotifications();
        }
        
        // Mark a notification as read and handle navigation
        function viewNotification(id) {
            const notification = notifications.find(n => n.id === id);
            if (!notification) return;
            
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', id);
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);

            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local notification
                    notification.is_read = true;
                    displayNotifications();
                    loadNotificationCount();
                    
                    // Handle navigation based on notification type
                    if (notification.type === 'claim_approved' || notification.type === 'claim_rejected') {
                        // Redirect to claimed donations page
                        window.location.href = '/bit216_assignment/claimed_donation.php';
                    } else if (notification.type === 'donation_claimed') {
                        // Redirect to my donations page
                        window.location.href = '/bit216_assignment/mydonation.php';
                    } else if (notification.type === 'pickup_request_sent') {
                        // Redirect to claimed donations page to see pending requests
                        window.location.href = '/bit216_assignment/claimed_donation.php';
                    } else if (notification.type === 'donation_created') {
                        // Redirect to my donations page to see the created donation
                        window.location.href = '/bit216_assignment/mydonation.php';
                    } else if (notification.type === 'expiry_warning' || notification.type === 'inventory_alert') {
                        // Redirect to view inventory page
                        window.location.href = '/bit216_assignment/view_inventory.php';
                    } else if (notification.type === 'donation_update') {
                        // Redirect to my donations page for donation updates
                        window.location.href = '/bit216_assignment/mydonation.php';
                    } else if (notification.type === 'meal_reminder') {
                        // Redirect to meal planning page
                        window.location.href = '/bit216_assignment/meal_plan1.php';
                    } else {
                        // Default behavior - just mark as read
                        alert('Notification marked as read');
                    }
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }
        
        // Delete a notification
        function dismissNotification(id) {
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_notification');
            formData.append('notification_id', id);
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);

            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from local notifications
                    notifications = notifications.filter(n => n.id !== id);
                    displayNotifications();
                    loadNotificationCount();
                }
            })
            .catch(error => console.error('Error deleting notification:', error));
        }
        
        // Approve claim request
        function approveClaim(notificationId) {
            if (!confirm('Are you sure you want to approve this claim request?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'approve_claim');
            formData.append('notification_id', notificationId);
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);
            
            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Claim request approved successfully!');
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error approving claim:', error);
                alert('Error approving claim request');
            });
        }
        
        // Reject claim request
        function rejectClaim(notificationId) {
            if (!confirm('Are you sure you want to reject this claim request?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'reject_claim');
            formData.append('notification_id', notificationId);
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);
            
            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Claim request rejected successfully!');
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error rejecting claim:', error);
                alert('Error rejecting claim request');
            });
        }
        
        // Mark all notifications as read
        function markAllAsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_as_read');
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);

            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark all local notifications as read
                    notifications.forEach(n => n.is_read = true);
                    displayNotifications();
                    loadNotificationCount();
                    alert('All notifications marked as read!');
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
        }

        // Load notification count for sidebar badge
        function loadNotificationCount() {
            const formData = new FormData();
            formData.append('action', 'get_unread_count');
            formData.append('user_id', <?php echo $_SESSION['user_id']; ?>);

            fetch('notification_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const count = data.unread_count;
                    const badge = document.getElementById('sidebar-notification-count');
                    
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error loading notification count:', error));
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            
            // Format as YYYY-MM-DD HH:MM
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${year}-${month}-${day} ${hours}:${minutes}`;
        }
        
        // Initialize notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            loadNotificationCount();

        // Refresh notification count every 30 seconds
        setInterval(loadNotificationCount, 30000);
        });
    </script>
</body>
</html>