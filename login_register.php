<?php   
        require 'connect.php';
        $pdo = getConnection();

        session_start();
        
        // OAuth Configuration
        $google_client_id = '333877730870-op4qit8cbo397l1kb5tg8drs8fqmesgt.apps.googleusercontent.com'; // Replace with your Google OAuth client ID
        $google_client_secret = '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'; // Replace with your Google OAuth client secret
        $google_redirect_uri = 'http://localhost/bit216_assignment/login_register.php?oauth=google';
        
        $facebook_app_id = '1974465353315392'; // Replace with your Facebook app ID
        $facebook_app_secret = 'af24f6e8e8f7ef5847b42aee88b0cac6'; // Replace with your Facebook app secret
        $facebook_redirect_uri = 'http://localhost/bit216_assignment/login_register.php?oauth=facebook';
        
        $github_client_id = 'Ov23liM3FZpLmDXFJgQV'; // Replace with your GitHub OAuth app client ID
        $github_client_secret = 'cf806eaa7a1202c2c842d0ce0b4f4424873f32f6'; // Replace with your GitHub OAuth app client secret
        $github_redirect_uri = 'http://localhost/bit216_assignment/login_register.php?oauth=github';
        
        $linkedin_client_id = 'YOUR_LINKEDIN_CLIENT_ID'; // Replace with your LinkedIn app client ID
        $linkedin_client_secret = 'YOUR_LINKEDIN_CLIENT_SECRET'; // Replace with your LinkedIn app client secret
        $linkedin_redirect_uri = 'http://localhost/bit216_assignment/login_register.php?oauth=linkedin';
        
        // Handle OAuth callbacks
        if (isset($_GET['oauth'])) {
            $oauth_provider = $_GET['oauth'];
            
            switch ($oauth_provider) {
                case 'google':
                    handleGoogleOAuth();
                    break;
                case 'facebook':
                    handleFacebookOAuth();
                    break;
                case 'github':
                    handleGitHubOAuth();
                    break;
                case 'linkedin':
                    handleLinkedInOAuth();
                    break;
            }
        }
        
        // Handle form submissions
        $login_error = '';
        $register_error = '';
        $register_success = '';

        // Track if we should stay on registration page
        $stay_on_registration = false;
        
        // Handle OAuth errors
        if (isset($_GET['error']) && $_GET['error'] === 'oauth_failed') {
            $login_error = "Social media login failed. Please try again or use your username and password.";
        }

        // Process login
        if (isset($_POST['login'])) {
            $user = $_POST['username'];
            $pass = $_POST['password'];
            
            // Check user credentials and verification status
            $stmt = $pdo->prepare("SELECT id, password, verified, email FROM users WHERE username = ?");
            $stmt->execute([$user]);
            $user_data = $stmt->fetch();
            
            if ($user_data && password_verify($pass, $user_data['password'])) {
                // Check if user is verified
                if ($user_data['verified'] == 1) {
                    // Start session and redirect to dashboard
                    session_start();
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['username'] = $user;
                    $_SESSION['user_email'] = $user_data['email'];
                    
                    // Create welcome notification for first-time login
                    createWelcomeNotification($user_data['id']);
                    
                    header("Location: /bit216_assignment/mainpage_aftlogin.php");
                    exit();
                } else {
                    // User exists but not verified
                    $login_error = "Account not verified! Please check your email for verification code.";
                    
                    // Store email in session for verification
                    $_SESSION['register_email'] = $user_data['email'];
                    $_SESSION['register_username'] = $user;
                }
            } else {
                $login_error = "Invalid username or password!";
            }
        }

        // Process registration
        if (isset($_POST['register'])) {
            $user = $_POST['username'];
            $email = $_POST['email'];
            $pass = $_POST['password'];
            $household_size = $_POST['household_size'];
            $address = $_POST['address'];
            
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$user, $email]);
            
            if ($stmt->rowCount() > 0) {
                $register_error = "Username or email already exists!";
                $stay_on_registration = true; // Stay on registration page
            } else {
                // Hash password and insert new user
                $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password,household_size,address) VALUES (?, ?, ? ,?, ?)");
                
                if ($stmt->execute([$user, $email, $hashed_password,$household_size,$address])) {
                    // Store email in session for verification
                    $_SESSION['register_email'] = $email;
                    $_SESSION['register_username'] = $user;
                    $register_success = "Registration successful! Please verify your email.";
                    header("Location: /bit216_assignment/verification.php");
                    exit();
                } else {
                    $register_error = "Registration failed. Please try again.";
                    $stay_on_registration = true; // Stay on registration page
                }
            }
        }
        
        // OAuth Handler Functions
        function handleGoogleOAuth() {
            global $google_client_id, $google_redirect_uri;
            
            if (!isset($_GET['code'])) {
                // Redirect to Google OAuth
                $google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
                    'client_id' => $google_client_id,
                    'redirect_uri' => $google_redirect_uri,
                    'scope' => 'email profile',
                    'response_type' => 'code',
                    'access_type' => 'offline'
                ]);
                header("Location: " . $google_auth_url);
                exit();
            } else {
                // Handle Google OAuth callback
                $code = $_GET['code'];
                $token_url = 'https://oauth2.googleapis.com/token';
                $token_data = [
                    'client_id' => $google_client_id,
                    'client_secret' => $GLOBALS['google_client_secret'],
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $google_redirect_uri
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $token_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $token_info = json_decode($response, true);
                
                if (isset($token_info['access_token'])) {
                    // Get user info from Google
                    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
                    $user_response = file_get_contents($user_info_url);
                    $user_data = json_decode($user_response, true);
                    
                    if ($user_data) {
                        // Process Google user login/registration
                        processOAuthUser('google', $user_data);
                    }
                }
            }
        }
        
        function handleFacebookOAuth() {
            global $facebook_app_id, $facebook_redirect_uri;
            
            if (!isset($_GET['code'])) {
                // Redirect to Facebook OAuth
                $facebook_auth_url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
                    'client_id' => $facebook_app_id,
                    'redirect_uri' => $facebook_redirect_uri,
                    'scope' => 'public_profile',
                    'response_type' => 'code'
                ]);
                header("Location: " . $facebook_auth_url);
                exit();
            } else {
                // Handle Facebook OAuth callback
                $code = $_GET['code'];
                $token_url = 'https://graph.facebook.com/v18.0/oauth/access_token';
                $token_data = [
                    'client_id' => $facebook_app_id,
                    'client_secret' => $GLOBALS['facebook_app_secret'],
                    'code' => $code,
                    'redirect_uri' => $facebook_redirect_uri
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $token_url . '?' . http_build_query($token_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $token_info = json_decode($response, true);
                
                if (isset($token_info['access_token'])) {
                    // Get user info from Facebook
                    $user_info_url = 'https://graph.facebook.com/me?fields=id,name&access_token=' . $token_info['access_token'];
                    $user_response = file_get_contents($user_info_url);
                    $user_data = json_decode($user_response, true);
                    
                    if ($user_data) {
                        // Process Facebook user login/registration
                        processOAuthUser('facebook', $user_data);
                    }
                }
            }
        }
        
        function handleGitHubOAuth() {
            global $github_client_id, $github_redirect_uri;
            
            if (!isset($_GET['code'])) {
                // Redirect to GitHub OAuth
                $github_auth_url = "https://github.com/login/oauth/authorize?" . http_build_query([
                    'client_id' => $github_client_id,
                    'redirect_uri' => $github_redirect_uri,
                    'scope' => 'user:email'
                ]);
                header("Location: " . $github_auth_url);
                exit();
            } else {
                // Handle GitHub OAuth callback
                $code = $_GET['code'];
                $token_url = 'https://github.com/login/oauth/access_token';
                $token_data = [
                    'client_id' => $github_client_id,
                    'client_secret' => $GLOBALS['github_client_secret'],
                    'code' => $code,
                    'redirect_uri' => $github_redirect_uri
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $token_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $token_info = json_decode($response, true);
                
                if (isset($token_info['access_token'])) {
                    // Get user info from GitHub
                    $user_info_url = 'https://api.github.com/user';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_info_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: token ' . $token_info['access_token'],
                        'User-Agent: FoodSave-App'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $user_response = curl_exec($ch);
                    curl_close($ch);
                    
                    $user_data = json_decode($user_response, true);
                    
                    if ($user_data) {
                        // Process GitHub user login/registration
                        processOAuthUser('github', $user_data);
                    }
                }
            }
        }
        
        function handleLinkedInOAuth() {
            global $linkedin_client_id, $linkedin_redirect_uri;
            
            if (!isset($_GET['code'])) {
                // Redirect to LinkedIn OAuth
                $linkedin_auth_url = "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
                    'client_id' => $linkedin_client_id,
                    'redirect_uri' => $linkedin_redirect_uri,
                    'scope' => 'r_liteprofile r_emailaddress',
                    'response_type' => 'code'
                ]);
                header("Location: " . $linkedin_auth_url);
                exit();
            } else {
                // Handle LinkedIn OAuth callback
                $code = $_GET['code'];
                $token_url = 'https://www.linkedin.com/oauth/v2/accessToken';
                $token_data = [
                    'client_id' => $linkedin_client_id,
                    'client_secret' => $GLOBALS['linkedin_client_secret'],
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $linkedin_redirect_uri
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $token_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $token_info = json_decode($response, true);
                
                if (isset($token_info['access_token'])) {
                    // Get user info from LinkedIn
                    $user_info_url = 'https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,profilePicture,email-address)';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $user_info_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $token_info['access_token']
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $user_response = curl_exec($ch);
                    curl_close($ch);
                    
                    $user_data = json_decode($user_response, true);
                    
                    if ($user_data) {
                        // Process LinkedIn user login/registration
                        processOAuthUser('linkedin', $user_data);
                    }
                }
            }
        }
        
        function processOAuthUser($provider, $user_data) {
            global $pdo;
            
            $email = '';
            $username = '';
            $name = '';
            
            // Extract user information based on provider
            switch ($provider) {
                case 'google':
                    $email = $user_data['email'] ?? '';
                    $username = $user_data['email'] ?? '';
                    $name = $user_data['name'] ?? '';
                    break;
                case 'facebook':
                    // Facebook no longer provides email by default
                    // Generate a unique email using Facebook ID
                    $email = 'fb_' . $user_data['id'] . '@facebook.local';
                    $username = $user_data['name'] ?? 'fb_user_' . $user_data['id'];
                    $name = $user_data['name'] ?? '';
                    break;
                case 'github':
                    $email = $user_data['email'] ?? '';
                    $username = $user_data['login'] ?? '';
                    $name = $user_data['name'] ?? '';
                    break;
                case 'linkedin':
                    $email = $user_data['emailAddress'] ?? '';
                    $username = $user_data['emailAddress'] ?? '';
                    $firstName = $user_data['firstName']['localized']['en_US'] ?? '';
                    $lastName = $user_data['lastName']['localized']['en_US'] ?? '';
                    $name = $firstName . ' ' . $lastName;
                    break;
            }
            
            if ($email) {
                // Check if user already exists
                $stmt = $pdo->prepare("SELECT id, username, verified FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch();
                
                if ($existing_user) {
                    // User exists, log them in
                    if ($existing_user['verified'] == 1) {
                        $_SESSION['user_id'] = $existing_user['id'];
                        $_SESSION['username'] = $existing_user['username'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['oauth_provider'] = $provider;
                        header("Location: /bit216_assignment/mainpage_aftlogin.php");
                        exit();
                    } else {
                        // User exists but not verified
                        $_SESSION['register_email'] = $email;
                        $_SESSION['register_username'] = $existing_user['username'];
                        header("Location: /bit216_assignment/verification.php");
                        exit();
                    }
                } else {
                    // New user, create account
                    $username = $username ?: $email;
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verified, oauth_provider) VALUES (?, ?, ?, 1, ?)");
                    $dummy_password = password_hash(uniqid(), PASSWORD_DEFAULT); // Generate random password for OAuth users
                    
                    if ($stmt->execute([$username, $email, $dummy_password, $provider])) {
                        $user_id = $pdo->lastInsertId();
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['oauth_provider'] = $provider;
                        header("Location: /bit216_assignment/mainpage_aftlogin.php");
                        exit();
                    }
                }
            }
            
            // If we get here, something went wrong
            header("Location: /bit216_assignment/login_register.php?error=oauth_failed");
            exit();
        }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Signup Form</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-decoration: none;
            list-style: none;
        }

        body{
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(90deg, #e2e2e2, #a2f7b2ff);
        }

        .container{
            position: relative;
            width: 850px;
            height: 550px;
            background: #fff;
            margin: 20px;
            border-radius: 30px;
            box-shadow: 0 0 30px rgba(0, 0, 0, .2);
            overflow: hidden;
        }

                    .container h1{
            font-size: 36px;
            margin: -10px 0;
        }

        .container p{
            font-size: 14.5px;
            margin: 15px 0;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
            text-align: center;
        }

        /* Pop-out notification styles */
        .notification-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #dc3545;
            border-radius: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            padding: 15px 25px;
            z-index: 10000;
            display: none;
            min-width: 350px;
            max-width: 500px;
            animation: slideDown 0.3s ease-out;
        }

        .notification-popup.success {
            background: #4CAF50;
        }

        .notification-popup.show {
            display: block;
        }

        .notification-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #4CAF50;
            flex-shrink: 0;
            font-weight: bold;
        }

        .notification-icon.error {
            color: #dc3545;
        }

        .notification-icon.success {
            color: #4CAF50;
        }

        .notification-text {
            flex: 1;
            color: white;
        }

        .notification-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0 0 3px 0;
            color: white;
        }

        .notification-message {
            font-size: 14px;
            color: white;
            margin: 0;
            line-height: 1.4;
        }

        .notification-close {
            background: none;
            border: none;
            font-size: 18px;
            color: white;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            opacity: 0.8;
        }

        .notification-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        form{ width: 100%; }

        .form-box{
            position: absolute;
            right: 0;
            width: 50%;
            height: 100%;
            background: #fff;
            display: flex;
            align-items: center;
            color: #333;
            text-align: center;
            padding: 40px;
            z-index: 1;
            transition: .6s ease-in-out 1.2s, visibility 0s 1s;
        }

            .container.active .form-box{ right: 50%; }

            .form-box.register{ visibility: hidden; }
                .container.active .form-box.register{ visibility: visible; }

        .input-box{
            position: relative;
            margin: 30px 0;
        }

            .input-box input{
                width: 100%;
                padding: 13px 50px 13px 20px;
                background: #eee;
                border-radius: 8px;
                border: none;
                outline: none;
                font-size: 16px;
                color: #333;
                font-weight: 500;
            }

                .input-box input::placeholder{
                    color: #888;
                    font-weight: 400;
                }
            
            .input-box i{
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 20px;
            }

        .forgot-link{ margin: -15px 0 15px; }
            .forgot-link a{
                font-size: 14.5px;
                color: #333;
            }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .btn{
            width: 100%;
            height: 48px;
            background: #a2f7b2ff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: white;
            font-weight: 600;
        }

        .social-icons{
            display: flex;
            justify-content: center;
        }

            .social-icons a{
                display: inline-flex;
                padding: 10px;
                border: 2px solid #ccc;
                border-radius: 8px;
                font-size: 24px;
                color: #333;
                margin: 0 8px;
            }

        .toggle-box{
            position: absolute;
            width: 100%;
            height: 100%;
        }

            .toggle-box::before{
                content: '';
                position: absolute;
                left: -250%;
                width: 300%;
                height: 100%;
                background: #a2f7b2ff;
                border-radius: 150px;
                z-index: 2;
                transition: 1.8s ease-in-out;
            }

                .container.active .toggle-box::before{ left: 50%; }

        .toggle-panel{
            position: absolute;
            width: 50%;
            height: 100%;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 2;
            transition: .6s ease-in-out;
        }

            .toggle-panel.toggle-left{ 
                left: 0;
                transition-delay: 1.2s; 
            }
                .container.active .toggle-panel.toggle-left{
                    left: -50%;
                    transition-delay: .6s;
                }

            .toggle-panel.toggle-right{ 
                right: -50%;
                transition-delay: .6s;
            }
                .container.active .toggle-panel.toggle-right{
                    right: 0;
                    transition-delay: 1.2s;
                }

            .toggle-panel p{ margin-bottom: 20px; }

            .toggle-panel .btn{
                width: 160px;
                height: 46px;
                background: transparent;
                border: 2px solid white;
                box-shadow: none;
            }

        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .success-message {
            color: green;
            font-size: 14px;
            margin-top: 5px;
        }

        @media screen and (max-width: 650px){
            .container{ height: calc(100vh - 40px); }

            .form-box{
                bottom: 0;
                width: 100%;
                height: 70%;
            }

                .container.active .form-box{
                    right: 0;
                    bottom: 30%;
                }

            .toggle-box::before{
                left: 0;
                top: -270%;
                width: 100%;
                height: 300%;
                border-radius: 20vw;
            }

                .container.active .toggle-box::before{
                    left: 0;
                    top: 70%;
                }

                .container.active .toggle-panel.toggle-left{
                    left: 0;
                    top: -30%;
                }

            .toggle-panel{ 
                width: 100%;
                height: 30%;
            }
                .toggle-panel.toggle-left{ top: 0; }
                .toggle-panel.toggle-right{
                    right: 0;
                    bottom: -30%;
                }

                    .container.active .toggle-panel.toggle-right{ bottom: 0; }
        }

        @media screen and (max-width: 400px){
            .form-box { padding: 20px; }

            .toggle-panel h1{font-size: 30px; }
        }
    </style>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <!-- Pop-out notification -->
    <div id="notification-popup" class="notification-popup">
        <div class="notification-content">
            <div class="notification-icon">
                !
            </div>
            <div class="notification-text">
                <div class="notification-title">Error</div>
                <div class="notification-message" id="notification-message">Message will appear here</div>
            </div>
            <button class="notification-close" onclick="hideNotification()">
                ×
            </button>
        </div>
    </div>

    <div class="container <?php if ($stay_on_registration) echo 'active'; ?>">
        <div class="form-box login">
            <form method="POST" action="">
                <h1><b>Login</b></h1>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt' ></i>
                </div>
                <div class="forgot-link">
                    <a href="/bit216_assignment/forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn"><b>Login</b></button>
                <p style="margin-top: 20px;"> or login with social platforms</p>
                <div class="social-icons">
                    <a href="?oauth=google" title="Login with Google"><i class='bx bxl-google' ></i></a>
                    <a href="?oauth=facebook" title="Login with Facebook"><i class='bx bxl-facebook' ></i></a>
                    <a href="?oauth=github" title="Login with GitHub"><i class='bx bxl-github' ></i></a>
                    <a href="?oauth=linkedin" title="Login with LinkedIn"><i class='bx bxl-linkedin' ></i></a>
                </div>
            </form>
        </div>

        <div class="form-box register">
            <form method="POST" action="">
                <h1><b>Registration</b></h1>
                <?php if (!empty($register_success)): ?>
                    <div class="success-message"><?php echo $register_success; ?></div>
                <?php endif; ?>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required value="<?php if (isset($_POST['username'])) echo htmlspecialchars($_POST['username']); ?>">
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required value="<?php if (isset($_POST['email'])) echo htmlspecialchars($_POST['email']); ?>">
                    <i class='bx bxs-envelope' ></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt' ></i>
                </div>
                <div class="input-box">
                    <input type="number" name="household_size" placeholder="Household Size " min="1" max="20" required value="<?php echo isset($_POST['household_size']) ? htmlspecialchars($_POST['household_size']) : ''; ?>">
                    <i class='bx  bxs-home'  ></i> 
                </div>
                <div class="input-box">
                    <input type="address" name="address" placeholder="Address " required value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    <i class='bx  bxs-map'  ></i> 
                </div>
                <button type="submit" name="register" class="btn"><b>Register</b></button>
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1><b>Hello, Welcome!</b></h1>
                <p><b>Don't have an account?</b></p>
                <button class="btn register-btn"><b>Register</b></button>
            </div>

            <div class="toggle-panel toggle-right">
                <h1><b>Welcome Back!</b></h1>
                <p><b>Already have an account?</b></p>
                <button class="btn login-btn"><b>Login</b></button>
            </div>
        </div>
    </div>

    <script>
        // Function to show notification
        function showNotification(message, type = 'error') {
            const popup = document.getElementById('notification-popup');
            const messageElement = document.getElementById('notification-message');
            const iconElement = popup.querySelector('.notification-icon');
            const titleElement = popup.querySelector('.notification-title');
            
            if (!popup || !messageElement || !iconElement || !titleElement) {
                console.error('Required elements not found for notification');
                return;
            }
            
            // Set message
            //
            messageElement.textContent = message;
            
            // Set type (error or success)
            popup.className = `notification-popup ${type}`;
            
            if (type === 'error') {
                iconElement.textContent = '!';
                titleElement.textContent = 'Error';
            } else {
                iconElement.textContent = '✓';
                titleElement.textContent = 'Success';
            }
            
            // Show notification
            popup.classList.add('show');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }
        
        // Function to hide notification
        function hideNotification() {
            const popup = document.getElementById('notification-popup');
            popup.classList.remove('show');
        }

        const container = document.querySelector('.container');
        const registerBtn = document.querySelector('.register-btn');
        const loginBtn = document.querySelector('.login-btn');

        registerBtn.addEventListener('click', () => {
            container.classList.add('active');
        })

        loginBtn.addEventListener('click', () => {
            container.classList.remove('active');
        })
        
        // Check for PHP error messages and show them as pop-ups
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($register_error)): ?>
                showNotification('<?php echo addslashes($register_error); ?>', 'error');
            <?php endif; ?>
            
            <?php if (!empty($login_error)): ?>
                showNotification('<?php echo addslashes($login_error); ?>', 'error');
            <?php endif; ?>
            
            <?php if (!empty($register_success)): ?>
                showNotification('<?php echo addslashes($register_success); ?>', 'success');
            <?php endif; ?>
        });
        
        // If there was a registration error, keep the registration form active
        <?php if ($stay_on_registration): ?>
            document.addEventListener('DOMContentLoaded', function() {
                container.classList.add('active');
            });
        <?php endif; ?>
    </script>
</body>
</html>

