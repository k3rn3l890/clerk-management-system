<?php
/**
 * Email Sender Class
 * Handles sending emails using PHP's built-in mail function or SMTP
 */
class EmailSender {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $encryption;
    private $enabled;
    
    public function __construct() {
        $this->host = MAIL_HOST;
        $this->port = MAIL_PORT;
        $this->username = MAIL_USERNAME;
        $this->password = MAIL_PASSWORD;
        $this->fromEmail = MAIL_FROM_EMAIL;
        $this->fromName = MAIL_FROM_NAME;
        $this->encryption = MAIL_ENCRYPTION;
        $this->enabled = MAIL_ENABLED;
    }
    
    /**
     * Send email using PHP's built-in mail function
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $headers
     * @return bool
     */
    public function sendMail($to, $subject, $message, $headers = '') {
        if (!$this->enabled) {
            error_log("Email functionality is disabled");
            return false;
        }
        
        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address: " . $to);
            return false;
        }
        
        // Set default headers if not provided
        if (empty($headers)) {
            $headers = $this->getDefaultHeaders();
        }
        
        // Send email
        $result = mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log("Email sent successfully to: " . $to);
        } else {
            error_log("Failed to send email to: " . $to);
        }
        
        return $result;
    }
    
    /**
     * Send email using SMTP (requires additional configuration)
     * This is a simplified version - for production, consider using PHPMailer
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param array $headers
     * @return bool
     */
    public function sendSMTP($to, $subject, $message, $headers = []) {
        if (!$this->enabled) {
            error_log("Email functionality is disabled");
            return false;
        }
        
        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address: " . $to);
            return false;
        }
        
        // For now, fall back to regular mail function
        // In production, you would implement proper SMTP here or use PHPMailer
        return $this->sendMail($to, $subject, $message);
    }
    
    /**
     * Get default email headers
     * @return string
     */
    private function getDefaultHeaders() {
        $headers = "From: " . $this->fromName . " <" . $this->fromEmail . ">\r\n";
        $headers .= "Reply-To: " . $this->fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return $headers;
    }
    
    /**
     * Send welcome email to new user
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $username
     * @param string $role
     * @return bool
     */
    public function sendWelcomeEmail($email, $firstName, $lastName, $username, $role) {
        $subject = "Welcome to " . APP_NAME;
        
        $message = $this->getWelcomeEmailTemplate($email, $firstName, $lastName, $username, $role);
        
        return $this->sendMail($email, $subject, $message);
    }
    
    /**
     * Send password reset email
     * @param string $email
     * @param string $firstName
     * @param string $resetToken
     * @return bool
     */
    public function sendPasswordResetEmail($email, $firstName, $resetToken) {
        $subject = "Password Reset - " . APP_NAME;
        
        $resetUrl = APP_URL . "/reset_password.php?token=" . $resetToken;
        
        $message = $this->getPasswordResetEmailTemplate($firstName, $resetUrl);
        
        return $this->sendMail($email, $subject, $message);
    }
    
    /**
     * Send notification email
     * @param string $email
     * @param string $firstName
     * @param string $subject
     * @param string $message
     * @return bool
     */
    public function sendNotificationEmail($email, $firstName, $subject, $message) {
        $emailSubject = "Notification - " . APP_NAME;
        
        $emailMessage = $this->getNotificationEmailTemplate($firstName, $subject, $message);
        
        return $this->sendMail($email, $emailSubject, $emailMessage);
    }
    
    /**
     * Get welcome email template
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $username
     * @param string $role
     * @return string
     */
    private function getWelcomeEmailTemplate($email, $firstName, $lastName, $username, $role) {
        $loginUrl = APP_URL . "/login.php";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to " . APP_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .info-box { background-color: #e8f4fd; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . APP_NAME . "</h1>
                </div>
                
                <div class='content'>
                    <h2>Welcome, " . htmlspecialchars($firstName) . "!</h2>
                    
                    <p>Your account has been successfully created in the " . APP_NAME . ". Here are your account details:</p>
                    
                    <div class='info-box'>
                        <strong>Account Information:</strong><br>
                        <strong>Name:</strong> " . htmlspecialchars($firstName . ' ' . $lastName) . "<br>
                        <strong>Username:</strong> " . htmlspecialchars($username) . "<br>
                        <strong>Role:</strong> " . ucfirst(str_replace('_', ' ', $role)) . "<br>
                        <strong>Email:</strong> " . htmlspecialchars($email) . "
                    </div>
                    
                    <p>You can now log in to the system using your username and the password that was provided to you.</p>
                    
                    <p style='text-align: center;'>
                        <a href='" . $loginUrl . "' class='button'>Login to System</a>
                    </p>
                    
                    <p><strong>Important Security Notes:</strong></p>
                    <ul>
                        <li>Keep your login credentials secure and confidential</li>
                        <li>Do not share your password with anyone</li>
                        <li>Contact your system administrator if you have any questions</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from " . APP_NAME . "</p>
                    <p>Please do not reply to this email. If you have any questions, contact your system administrator.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get password reset email template
     * @param string $firstName
     * @param string $resetUrl
     * @return string
     */
    private function getPasswordResetEmailTemplate($firstName, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset - " . APP_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .warning-box { background-color: #fdf2e9; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello, " . htmlspecialchars($firstName) . "!</h2>
                    
                    <p>You have requested to reset your password for your " . APP_NAME . " account.</p>
                    
                    <p style='text-align: center;'>
                        <a href='" . $resetUrl . "' class='button'>Reset Password</a>
                    </p>
                    
                    <div class='warning-box'>
                        <strong>Security Notice:</strong><br>
                        This link will expire in 24 hours for security reasons.<br>
                        If you did not request this password reset, please ignore this email.
                    </div>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background-color: #f0f0f0; padding: 10px;'>" . $resetUrl . "</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from " . APP_NAME . "</p>
                    <p>Please do not reply to this email. If you have any questions, contact your system administrator.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get notification email template
     * @param string $firstName
     * @param string $subject
     * @param string $message
     * @return string
     */
    private function getNotificationEmailTemplate($firstName, $subject, $message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Notification - " . APP_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .notification-box { background-color: #e8f5e8; border-left: 4px solid #27ae60; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>System Notification</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello, " . htmlspecialchars($firstName) . "!</h2>
                    
                    <div class='notification-box'>
                        <strong>" . htmlspecialchars($subject) . "</strong><br><br>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    
                    <p>Please log in to the system to view more details.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from " . APP_NAME . "</p>
                    <p>Please do not reply to this email. If you have any questions, contact your system administrator.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
