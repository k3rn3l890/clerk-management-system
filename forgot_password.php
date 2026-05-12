<?php
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Initialize variables
$email = '';
$message = '';
$alertType = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = sanitize($_POST['email']);
    
    // Validate form data
    if (empty($email)) {
        $message = 'Please enter your email address';
        $alertType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format';
        $alertType = 'danger';
    } else {
        // Check if email exists
        $db = new Database();
        $db->query("SELECT * FROM users WHERE email = :email AND status = 'active'");
        $db->bind(':email', $email);
        $user = $db->single();
        
        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $db->query("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $db->bind(':user_id', $user['user_id']);
            $db->bind(':token', $token);
            $db->bind(':expires_at', $expires);
            $db->execute();
            
            // Create reset link
            $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . '/clerk_mgmt1/reset_password.php?token=' . $token;
            
            // In a real-world application, you would send an email here
            // For this demo, we'll just show the link
            $message = 'Password reset link has been generated. In a production environment, this would be emailed to you.<br><br>For testing purposes, you can use this link: <a href="' . $resetLink . '">' . $resetLink . '</a>';
            $alertType = 'success';
            
            // Log action
            logAction('Password reset requested', 'users', $user['user_id']);
        } else {
            // Don't reveal that the email doesn't exist for security reasons
            $message = 'If your email is registered in our system, you will receive a password reset link shortly.';
            $alertType = 'info';
        }
    }
}

// Get system settings
$systemName = getSetting('system_name') ?: 'Ghana Court Clerk Management System';
$courtName = getSetting('court_name') ?: 'High Court of Ghana';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo $systemName; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/background.css" rel="stylesheet">
    <link href="assets/css/navbar-logo.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body class="bg-light ghana-background login-background">
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card auth-card shadow">
                        <div class="auth-header text-center py-4">
                            <h1 class="h4 mb-0"><span class="login-logo"></span><?php echo $systemName; ?></h1>
                        </div>
                        <div class="card-body p-4">
                            <h2 class="text-center mb-4">Forgot Password</h2>
                            
                            <?php if ($message): ?>
                            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required autofocus>
                                    </div>
                                    <small class="form-text text-muted">Enter the email address associated with your account.</small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                                    <a href="login.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                                    </a>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center py-3">
                            <p class="mb-0"><?php echo $courtName; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
