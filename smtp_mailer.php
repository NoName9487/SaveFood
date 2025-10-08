<?php
/**
 * Simple SMTP Mailer for Gmail
 * A lightweight SMTP implementation specifically for Gmail
 */

class SMTPMailer {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function sendMail($to, $subject, $htmlMessage, $fromName = null) {
        $fromName = $fromName ?: $this->config['from_name'];
        $fromEmail = $this->config['from_email'];
        
        // Skip mail() function and go directly to SMTP
        return $this->sendViaSMTP($to, $subject, $htmlMessage, $fromName, $fromEmail);
    }
    
    private function sendViaSMTP($to, $subject, $htmlMessage, $fromName, $fromEmail) {
        try {
            // Connect to Gmail SMTP using TLS on port 587
            $socket = fsockopen($this->config['smtp_host'], $this->config['smtp_port'], $errno, $errstr, 30);
            
            if (!$socket) {
                error_log("SMTP Connection failed: $errstr ($errno)");
                return false;
            }
            
            // Read initial response
            $this->readResponse($socket);
            
            // SMTP conversation
            $this->sendCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $this->sendCommand($socket, 'STARTTLS');
            
            // Enable TLS encryption
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            $this->sendCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $this->sendCommand($socket, 'AUTH LOGIN');
            $this->sendCommand($socket, base64_encode($this->config['smtp_username']));
            $this->sendCommand($socket, base64_encode($this->config['smtp_password']));
            $this->sendCommand($socket, 'MAIL FROM: <' . $fromEmail . '>');
            $this->sendCommand($socket, 'RCPT TO: <' . $to . '>');
            $this->sendCommand($socket, 'DATA');
            
            // Email content
            $email_content = "From: $fromName <$fromEmail>\r\n";
            $email_content .= "To: $to\r\n";
            $email_content .= "Subject: $subject\r\n";
            $email_content .= "MIME-Version: 1.0\r\n";
            $email_content .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $email_content .= $htmlMessage . "\r\n.\r\n";
            
            fwrite($socket, $email_content);
            $this->readResponse($socket);
            
            $this->sendCommand($socket, 'QUIT');
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }
    
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
}

/**
 * Simple function to send email using Gmail SMTP
 */
function sendEmailViaSMTP($to, $subject, $htmlMessage, $fromName = 'SavePlate') {
    $config = require 'email_config.php';
    $mailer = new SMTPMailer($config);
    return $mailer->sendMail($to, $subject, $htmlMessage, $fromName);
}
?>
