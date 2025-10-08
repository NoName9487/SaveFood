<?php
    require 'connect.php';
    $pdo = getConnection();

    session_start();

    $step = 'request_email';
    $email = '';
    $error = '';
    $success = '';

    // Lightweight AJAX handlers for OTP verify/resend without full page refresh
    // 
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        $response = [ 'ok' => false, 'message' => 'Unknown request.' ];

        // AJAX: resend OTP
        if (isset($_POST['resend_otp'])) {
            if (!isset($_SESSION['fp_email'])) {
                $response = [ 'ok' => false, 'message' => 'No email in session.' ];
            } else {
                $_SESSION['fp_otp'] = generateOTP();
                if (sendForgotOTPEmail($_SESSION['fp_email'], $_SESSION['fp_otp'])) {
                    $response = [ 'ok' => true, 'message' => 'A new OTP has been sent to your email.' ];
                } else {
                    $response = [ 'ok' => false, 'message' => 'Failed to resend OTP. Please try again later.' ];
                }
            }
        }

        // AJAX: verify OTP
        if (isset($_POST['verify_otp'])) {
            $d1 = $_POST['digit1'] ?? '';
            $d2 = $_POST['digit2'] ?? '';
            $d3 = $_POST['digit3'] ?? '';
            $d4 = $_POST['digit4'] ?? '';
            if ($d1 === '' || $d2 === '' || $d3 === '' || $d4 === '') {
                $response = [ 'ok' => false, 'message' => 'Please enter all 4 digits.' ];
            } else {
                $entered = $d1 . $d2 . $d3 . $d4;
                if (isset($_SESSION['fp_otp']) && $entered === $_SESSION['fp_otp']) {
                    $_SESSION['fp_verified'] = true;
                    unset($_SESSION['fp_otp']);
                    $response = [ 'ok' => true, 'message' => 'OTP verified. You can set a new password now.', 'step' => 'reset_password' ];
                } else {
                    $response = [ 'ok' => false, 'message' => 'Invalid OTP. Please try again.' ];
                }
            }
        }

        echo json_encode($response);
        exit();
    }

    // If coming back mid-flow
    if (isset($_SESSION['fp_email'])) {
        $email = $_SESSION['fp_email'];
        if (isset($_SESSION['fp_otp'])) {
            $step = 'verify_otp';
        }
        if (isset($_SESSION['fp_verified']) && $_SESSION['fp_verified'] === true) {
            $step = 'reset_password';
        }
    }

    // Handle submit: request email
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_email'])) {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $error = 'Please enter your email.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $error = 'Email not found.';
            } else {
                $_SESSION['fp_email'] = $email;
                $_SESSION['fp_otp'] = generateOTP();
                if (sendForgotOTPEmail($email, $_SESSION['fp_otp'])) {
                    $success = 'An OTP has been sent to your email.';
                    $step = 'verify_otp';
                } else {
                    $error = 'Failed to send OTP. Please try again later.';
                }
            }
        }
    }

    // Handle submit: resend OTP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
        if (!isset($_SESSION['fp_email'])) {
            header('Location: /bit216_assignment/forgot_password.php');
            exit();
        }
        $_SESSION['fp_otp'] = generateOTP();
        if (sendForgotOTPEmail($_SESSION['fp_email'], $_SESSION['fp_otp'])) {
            $success = 'A new OTP has been sent to your email.';
            $step = 'verify_otp';
        } else {
            $error = 'Failed to resend OTP. Please try again later.';
        }
    }

    // Handle submit: verify OTP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
        $d1 = $_POST['digit1'] ?? '';
        $d2 = $_POST['digit2'] ?? '';
        $d3 = $_POST['digit3'] ?? '';
        $d4 = $_POST['digit4'] ?? '';
        if ($d1 === '' || $d2 === '' || $d3 === '' || $d4 === '') {
            $error = 'Please enter all 4 digits.';
            $step = 'verify_otp';
        } else {
            $entered = $d1 . $d2 . $d3 . $d4;
            if (isset($_SESSION['fp_otp']) && $entered === $_SESSION['fp_otp']) {
                $_SESSION['fp_verified'] = true;
                unset($_SESSION['fp_otp']);
                $success = 'OTP verified. You can set a new password now.';
                $step = 'reset_password';
            } else {
                $error = 'Invalid OTP. Please try again.';
                $step = 'verify_otp';
            }
        }
    }

    // Handle submit: reset password
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        if (!isset($_SESSION['fp_email']) || !isset($_SESSION['fp_verified']) || $_SESSION['fp_verified'] !== true) {
            header('Location: /bit216_assignment/forgot_password.php');
            exit();
        }
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new === '' || $confirm === '') {
            $error = 'Please enter and confirm your new password.';
            $step = 'reset_password';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
            $step = 'reset_password';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 'reset_password';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
            if ($stmt->execute([$hash, $_SESSION['fp_email']])) {
                // Clear session state for flow
                unset($_SESSION['fp_email']);
                unset($_SESSION['fp_verified']);
                $success = 'Password updated successfully. Redirecting to login...';
                echo '<script>setTimeout(function(){ window.location.href = "/bit216_assignment/login_register.php"; }, 2000);</script>';
                $step = 'done';
            } else {
                $error = 'Failed to update password. Please try again.';
                $step = 'reset_password';
            }
        }
    }

    function generateOTP() {
        return sprintf("%04d", rand(0, 9999));
    }

    function sendForgotOTPEmail($email, $otp) {
        require_once 'smtp_mailer.php';
        $subject = 'Your Password Reset OTP Code';
        $message = "
        <html>
        <head><title>Password Reset OTP</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4CAF50;'>SavePlate Password Reset</h2>
                <p>You requested to reset your password.</p>
                <p>Your OTP code is:</p>
                <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 5px; font-weight: bold; color: #4CAF50; border-radius: 5px; margin: 20px 0;'>$otp</div>
                <p>This code will expire in 10 minutes.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #777;'>If you did not request this, you can ignore this email.</p>
            </div>
        </body>
        </html>";
        try { return sendEmailViaSMTP($email, $subject, $message); } catch (Exception $e) { return false; }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body{ display:flex; justify-content:center; align-items:center; min-height:100vh; background: linear-gradient(90deg, #e2e2e2, #a2f7b2ff); padding:20px; }
        .container{ width:100%; max-width:420px; background:#fff; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,.2); overflow:hidden; }
        .header{ background:#a2f7b2ff; color:#fff; text-align:center; padding:25px 20px; }
        .content{ padding:30px; }
        .input-box{ margin-bottom:20px; }
        .input-box input{ width:100%; padding:13px 15px; background:#eee; border:none; border-radius:8px; outline:none; font-size:16px; }
        .btn{ width:100%; height:48px; background:#a2f7b2ff; border-radius:8px; border:none; cursor:pointer; font-size:16px; color:#fff; font-weight:600; }
        .sub-title{ text-align:center; color:#666; margin-bottom:20px; font-size:14px; }
        .otp-container{ display:flex; justify-content:space-between; margin-bottom:20px; }
        .otp-input{ width:55px; height:55px; text-align:center; font-size:24px; font-weight:bold; border:2px solid #ddd; border-radius:10px; background:#f9f9f9; }
        .message{ padding:10px; border-radius:5px; margin:10px 0; border:1px solid; }
        .success{ background:#d4edda; color:#155724; border-color:#c3e6cb; }
        .error{ background:#f8d7da; color:#721c24; border-color:#f5c6cb; }
        .resend{ text-align:center; margin-bottom:15px; }
        .resend button{ background:none; border:none; color:#2196F3; font-weight:600; cursor:pointer; }
        .footer{ text-align:center; padding:15px; color:#666; font-size:14px; border-top:1px solid #eee; }
        .email-display{ text-align:center; color:#4CAF50; font-weight:bold; margin-bottom:10px; word-break:break-all; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const inputs = document.querySelectorAll('.otp-input');
            const verifyBtn = document.getElementById('verify-btn');
            const resendBtn = document.getElementById('resend-btn');
            const msgBox = document.getElementById('ajax-message');

            function showMessage(type, text) {
                if (!msgBox) return;
                msgBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
                msgBox.textContent = text;
            }

            async function ajaxPost(data) {
                const params = new URLSearchParams(data);
                params.append('ajax', '1');
                const res = await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
                return res.json();
            }

            async function doVerify() {
                const [d1, d2, d3, d4] = Array.from(inputs).map(i => i.value.trim());
                if (!d1 || !d2 || !d3 || !d4) {
                    showMessage('error', 'Please enter all 4 digits.');
                    return;
                }
                const resp = await ajaxPost({ verify_otp: '1', digit1: d1, digit2: d2, digit3: d3, digit4: d4 });
                if (resp.ok) {
                    showMessage('success', resp.message || 'Verified.');
                    if (resp.step === 'reset_password') {
                        // Swap to reset password UI without full refresh
                        const content = document.querySelector('.content');
                        if (content) {
                            content.innerHTML = `
                                <div class="message success">${resp.message}</div>
                                <form method="POST" action="">
                                    <p class="sub-title">Set a new password for your account</p>
                                    <div class="input-box"><input type="password" name="new_password" placeholder="New Password" required></div>
                                    <div class="input-box"><input type="password" name="confirm_password" placeholder="Confirm Password" required></div>
                                    <button type="submit" name="reset_password" class="btn"><b>Update Password</b></button>
                                </form>
                            `;
                        }
                    }
                } else {
                    showMessage('error', resp.message || 'Verification failed.');
                }
            }

            if (inputs.length) {
                inputs.forEach((input, index) => {
                    input.addEventListener('input', function(){
                        if (this.value.length === 1 && index < inputs.length - 1) inputs[index+1].focus();
                    });
                    input.addEventListener('keydown', function(e){
                        if (e.key === 'Backspace' && this.value === '' && index > 0) inputs[index-1].focus();
                        if (e.key === 'Enter') { e.preventDefault(); doVerify(); }
                    });
                });
            }

            if (verifyBtn) {
                verifyBtn.addEventListener('click', function(e){ e.preventDefault(); doVerify(); });
            }

            if (resendBtn) {
                resendBtn.addEventListener('click', async function(e){
                    e.preventDefault();
                    resendBtn.disabled = true;
                    const resp = await ajaxPost({ resend_otp: '1' });
                    if (resp.ok) showMessage('success', resp.message);
                    else showMessage('error', resp.message || 'Failed to resend OTP.');
                    setTimeout(() => { resendBtn.disabled = false; }, 30000);
                });
            }
        });
    </script>
    </head>
<body>
    <div class="container">
        <div class="header"><h1><b>Forgot Password</b></h1></div>
        <div class="content">
            <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($step === 'request_email'): ?>
                <form method="POST" action="">
                    <p class="sub-title">Enter your account email to receive an OTP.</p>
                    <div class="input-box">
                        <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <button type="submit" name="request_email" class="btn"><b>Send OTP</b></button>
                </form>
            <?php elseif ($step === 'verify_otp'): ?>
                <form method="POST" action="" id="otp-form" onsubmit="return false;">
                    <p class="sub-title">Enter the 4-digit OTP sent to your email</p>
                    <div class="email-display"><?php echo htmlspecialchars($_SESSION['fp_email'] ?? ''); ?></div>
                    <div id="ajax-message"></div>
                    <div class="otp-container">
                        <input type="text" class="otp-input" maxlength="1" name="digit1" autofocus>
                        <input type="text" class="otp-input" maxlength="1" name="digit2">
                        <input type="text" class="otp-input" maxlength="1" name="digit3">
                        <input type="text" class="otp-input" maxlength="1" name="digit4">
                    </div>
                    <div class="resend">
                        Didn't get the code?
                        <button type="button" id="resend-btn" name="resend_otp">Resend</button>
                    </div>
                    <button type="button" id="verify-btn" name="verify_otp" class="btn"><b>Verify</b></button>
                </form>
            <?php elseif ($step === 'reset_password'): ?>
                <form method="POST" action="">
                    <p class="sub-title">Set a new password for your account</p>
                    <div class="input-box"><input type="password" name="new_password" placeholder="New Password" required></div>
                    <div class="input-box"><input type="password" name="confirm_password" placeholder="Confirm Password" required></div>
                    <button type="submit" name="reset_password" class="btn"><b>Update Password</b></button>
                </form>
            <?php else: ?>
                <div style="text-align:center;">
                    <p>You can now login with your new password.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer">
            <a href="/bit216_assignment/login_register.php">Back to Login</a>
        </div>
    </div>
</body>
</html>



