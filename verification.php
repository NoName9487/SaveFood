<?php
    require 'connect.php';
    $pdo = getConnection();

    session_start();



    // Initialize variables
    $email = '';
    $error = '';
    $success = '';
    $otp_sent = false;

    // Check if email is in session (from registration)
    if (isset($_SESSION['register_email'])) {
        $email = $_SESSION['register_email'];
        $otp_sent = true;
        
        // Auto-reset: Clear old session data for fresh verification
        if (!isset($_SESSION['current_email']) || $_SESSION['current_email'] !== $email || isset($_SESSION['verification_success'])) {
            // Clear ALL verification-related session data
            unset($_SESSION['otp']);
            unset($_SESSION['verification_attempted']);
            unset($_SESSION['verification_success']);
            unset($_SESSION['redirect_after_verification']);
            unset($_SESSION['last_verified_email']);
            
            // Set the current email being verified
            $_SESSION['current_email'] = $email;
            
            // Generate new OTP for new email
            $_SESSION['otp'] = generateOTP();
            $email_result = sendOTPEmail($email, $_SESSION['otp']);
            
            if ($email_result) {
                $success = "OTP has been sent to your email: " . $email;
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        } else {
            // Same email, no previous verification - generate OTP if not already set
            if (!isset($_SESSION['otp'])) {
                $_SESSION['otp'] = generateOTP();
                $email_result = sendOTPEmail($email, $_SESSION['otp']);
                
                if ($email_result) {
                    $success = "OTP has been sent to your email: " . $email;
                } else {
                    $error = "Failed to send OTP. Please try again.";
                }
            } else {
                // OTP already exists, show message that it was sent
                $success = "OTP has been sent to your email: " . $email;
            }
        }
    } else {
        // No email in session, redirect to registration
        header("Location: /bit216_assignment/login_register.php");
        exit();
    }

    // Handle OTP verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
        // Safety check: If there's any old verification success, clear it immediately
        if (isset($_SESSION['verification_success']) && $_SESSION['verification_success'] === 'completed') {
            if (isset($_SESSION['last_verified_email']) && $_SESSION['last_verified_email'] !== $_SESSION['register_email']) {
                unset($_SESSION['verification_success']);
                unset($_SESSION['last_verified_email']);
                unset($_SESSION['otp']);
                unset($_SESSION['verification_attempted']);
                unset($_SESSION['redirect_after_verification']);
            } else {
                $success = 'OTP verified successfully! Redirecting to login page...';
            }
        }
        
        // Now proceed with verification if not already verified
        if (!isset($success) || !strpos($success, 'verified successfully')) {
            // Mark that a verification attempt was made
            $_SESSION['verification_attempted'] = true;
            
            // Check if all OTP digits are provided
            if (!isset($_POST['digit1']) || !isset($_POST['digit2']) || !isset($_POST['digit3']) || !isset($_POST['digit4']) || 
                $_POST['digit1'] === '' || $_POST['digit2'] === '' || $_POST['digit3'] === '' || $_POST['digit4'] === '') {
                $error = 'Please enter all 4 digits of the OTP code.';
            } else {
                $user_otp = $_POST['digit1'] . $_POST['digit2'] . $_POST['digit3'] . $_POST['digit4'];
                
                if ($user_otp === $_SESSION['otp']) {
                    // Update user as verified in database
                    if (isset($_SESSION['register_email'])) {
                        try {
                            // Check if user exists
                            $check_stmt = $pdo->prepare("SELECT id, username, verified FROM users WHERE email = ?");
                            $check_stmt->execute([$_SESSION['register_email']]);
                            $user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($user_data) {
                                // Update the verified status
                                $stmt = $pdo->prepare("UPDATE users SET verified = 1 WHERE email = ?");
                                $result = $stmt->execute([$_SESSION['register_email']]);
                                
                                if ($result) {
                                    // OTP verified successfully
                                    $success_message = 'OTP verified successfully! Redirecting to login page...';
                                    $_SESSION['verification_success'] = 'completed';
                                    $_SESSION['last_verified_email'] = $_SESSION['register_email'];
                                    $success = $success_message;
                                    
                                    // Clear the OTP session data but keep success message
                                    unset($_SESSION['otp']);
                                    unset($_SESSION['register_email']);
                                    unset($_SESSION['verification_attempted']);
                                    
                                    // Set a flag to trigger JavaScript redirect
                                    $_SESSION['redirect_after_verification'] = true;
                                } else {
                                    $error = 'Failed to update verification status. Please try again.';
                                }
                            } else {
                                $error = 'User not found in database. Please register again.';
                            }
                        } catch (Exception $e) {
                            $error = 'Database error occurred. Please try again.';
                        }
                    } else {
                        $error = 'Email not found in session. Please register again.';
                    }
                } else {
                    $error = 'Invalid OTP code. Please try again.';
                }
            }
        }
    }

    // Handle resend OTP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
        if (isset($_SESSION['register_email'])) {
            $_SESSION['otp'] = generateOTP();
            // Clear the verification attempt flag when resending
            unset($_SESSION['verification_attempted']);
            sendOTPEmail($_SESSION['register_email'], $_SESSION['otp']);
            $success = 'New OTP code has been sent to your email!';
        } else {
            $error = 'Email not found. Please register again.';
        }
    }

    // Generate a 4-digit OTP
    function generateOTP() {
        return sprintf("%04d", rand(0, 9999));
    }

    // Send OTP via email
    function sendOTPEmail($email, $otp) {
        // Include the SMTP mailer
        require_once 'smtp_mailer.php';
        
        $subject = 'Your OTP Verification Code';
        $message = "
        <html>
        <head>
            <title>OTP Verification</title>
        </head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4CAF50;'>SavePlate Verification</h2>
                <p>Thank you for registering with SavePlate!</p>
                <p>Your OTP verification code is:</p>
                <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 5px; font-weight: bold; color: #4CAF50; border-radius: 5px; margin: 20px 0;'>
                    $otp
                </div>
                <p>Enter this code on the verification page to complete your registration.</p>
                <p>This code will expire in 10 minutes.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #777;'>If you didn't request this code, please ignore this email.</p>
            </div>
        </body>
        </html>
        ";
        
        // Send email via Gmail SMTP
        try {
            $result = sendEmailViaSMTP($email, $subject, $message);
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(90deg, #e2e2e2, #a2f7b2ff);
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        .header {
            background: #a2f7b2ff;
            color: white;
            text-align: center;
            padding: 25px 20px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .content {
            padding: 30px;
        }
        
        .sub-title {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .email-display {
            text-align: center;
            color: #4CAF50;
            font-weight: bold;
            margin-bottom: 15px;
            word-break: break-all;
        }
        
        .otp-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .otp-input {
            width: 55px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 10px;
            background: #f9f9f9;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #a2f7b2ff;
            box-shadow: 0 0 10px rgba(106, 17, 203, 0.3);
            outline: none;
            background: #fff;
        }
        
        .resend {
            text-align: center;
            margin-bottom: 25px;
            color: #666;
            font-size: 14px;
        }
        
        .resend button {
            background: none;
            border: none;
            color: #6a11cb;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .resend button:hover {
            text-decoration: underline;
        }
        
        .resend button:disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        .verify-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #9df3b7ff, #50ea6cff);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }
        
        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.6);
        }
        
        .verify-btn:active {
            transform: translateY(0);
        }
        
        .verify-btn:disabled {
            background: linear-gradient(to right, #cccccc, #999999);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 15px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        .footer a {
            color: #6a11cb;
            text-decoration: none;
            font-weight: 600;
        }
        
        .channel {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }
        
        .channel i {
            color: green;
            font-size: 20px;
            margin-right: 8px;
        }
        
        /* Popup notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            align-items: center;
            max-width: 350px;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: #4CAF50;
            border-left: 5px solid #388E3C;
        }
        
        .notification.error {
            background: #F44336;
            border-left: 5px solid #D32F2F;
        }
        
        .notification.info {
            background: #2196F3;
            border-left: 5px solid #1976D2;
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .notification .close-btn {
            margin-left: 15px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }
        
        @media (max-width: 480px) {
            .container {
                border-radius: 15px;
            }
            
            .otp-input {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }
            
            .content {
                padding: 20px;
            }
            
            .notification {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <!-- Notification Popup -->
    <div class="notification" id="notification">
        <i id="notification-icon"></i>
        <span id="notification-message"></span>
        <button class="close-btn" id="close-notification">&times;</button>
    </div>

    <div class="container">
        <div class="header">
            <h1><b>OTP Verification</b></h1>
        </div>
        
        <div class="content">
            <p class="sub-title">Enter the 4-digit code sent to your email</p>
            
            <div class="email-display"><?php echo htmlspecialchars($email ?: ''); ?></div>
            

            
            <?php if ($success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showNotification('<?php echo addslashes($success); ?>', 'success');
                        <?php if (strpos($success, 'verified successfully') !== false): ?>
                        setTimeout(function() {
                            window.location.href = '/bit216_assignment/login_register.php';
                        }, 3000);
                        <?php endif; ?>
                    });
                </script>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showNotification('<?php echo addslashes($error); ?>', 'error');
                    });
                </script>
            <?php endif; ?>
            
            <form method="POST" action="" id="main-form">
                <div class="otp-container">
                    <input type="text" class="otp-input" maxlength="1" id="digit1" name="digit1" autofocus>
                    <input type="text" class="otp-input" maxlength="1" id="digit2" name="digit2">
                    <input type="text" class="otp-input" maxlength="1" id="digit3" name="digit3">
                    <input type="text" class="otp-input" maxlength="1" id="digit4" name="digit4">
                </div>
                
                <p class="resend">
                    Didn't receive the code? 
                    <button type="submit" name="resend" id="resend-btn">Resend</button>
                </p>
                

                
                <button type="submit" name="verify" class="verify-btn" id="verify-btn"><b>Verify</b></button>
            </form>
            

        </div>
        
        <div class="footer">
            <p>Having a problem? Get Help</p>
            <div class="channel">
                <i class="fab fa-whatsapp"></i>
                <a href="#">@SavePlate</a>
            </div>
        </div>
    </div>

    <script>
        // Function to show notification popup
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            const notificationIcon = document.getElementById('notification-icon');
            const notificationMessage = document.getElementById('notification-message');
            
            // Set the message
            notificationMessage.textContent = message;
            
            // Set the icon and type
            notification.className = 'notification ' + type;
            if (type === 'success') {
                notificationIcon.className = 'fas fa-check-circle';
            } else if (type === 'error') {
                notificationIcon.className = 'fas fa-exclamation-circle';
            } else if (type === 'info') {
                notificationIcon.className = 'fas fa-info-circle';
            }
            
            // Show the notification
            notification.classList.add('show');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                hideNotification();
            }, 5000);
        }
        
        // Function to hide notification
        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.otp-input');
            const closeNotification = document.getElementById('close-notification');
            const notification = document.getElementById('notification');
            const verifyBtn = document.getElementById('verify-btn');

            closeNotification.addEventListener('click', hideNotification);



            // No event listener on verify button - let it work naturally

            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    if (this.value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace') {
                        if (this.value === '' && index > 0) {
                            inputs[index - 1].focus();
                        }
                    }
                    // Handle Enter key press to auto-submit form with verify action
                    if (e.key === 'Enter') {
                        e.preventDefault(); // Prevent default form submission
                        
                        // Check if all 4 digits are filled
                        const digit1 = document.getElementById('digit1').value;
                        const digit2 = document.getElementById('digit2').value;
                        const digit3 = document.getElementById('digit3').value;
                        const digit4 = document.getElementById('digit4').value;
                        
                        if (digit1 && digit2 && digit3 && digit4) {
                            // All digits filled, submit with verify action
                            const verifyBtn = document.createElement('input');
                            verifyBtn.type = 'hidden';
                            verifyBtn.name = 'verify';
                            verifyBtn.value = '1';
                            
                            const form = document.getElementById('main-form');
                            form.appendChild(verifyBtn);
                            form.submit();
                        } else {
                            // Focus on the next empty field
                            if (!digit1) document.getElementById('digit1').focus();
                            else if (!digit2) document.getElementById('digit2').focus();
                            else if (!digit3) document.getElementById('digit3').focus();
                            else if (!digit4) document.getElementById('digit4').focus();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>