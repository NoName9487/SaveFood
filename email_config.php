<?php
// Email Configuration for Gmail SMTP
// You need to update these with your Gmail credentials

return [
    'smtp_host' => 'smtp.gmail.com',          // Gmail SMTP server (NOT your email)
    'smtp_port' => 587,
    'smtp_username' => 'saveplate123@gmail.com',    // Your Gmail address
    'smtp_password' => 'dgnj qqqm ouwk lcwl', // Your Gmail App Password
    'from_email' => 'saveplate123@gmail.com',       // Your Gmail address
    'from_name' => 'SavePlate'
];

/* 
IMPORTANT: To use Gmail SMTP, you need to:

1. Enable 2-Factor Authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security > 2-Step Verification > App passwords
   - Generate a new app password for "Mail"
   - Use this app password instead of your regular Gmail password

3. Update the credentials above:
   - Replace 'your-email@gmail.com' with your actual Gmail address
   - Replace 'your-app-password' with the app password you generated

Example:
'smtp_username' => 'john.doe@gmail.com',
'smtp_password' => 'abcd efgh ijkl mnop',  // 16-character app password
'from_email' => 'john.doe@gmail.com',
*/
?>
