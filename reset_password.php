<?php
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Initialize variables
$token = '';
$message = '';
$alertType = '';
$validToken = false;
$userId = null;

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    // Verify token
    $db = new Database();
    $db->query("SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW() AND used = 0");
    $db->bind(':token', $token);
    $reset = $db->single();
    
    if ($reset) {
        $validToken = true;
        $userId = $reset['user_id'];
    } else {
        $message = 'Invalid or expired token. Please request a new password reset link.';
        $alertType = 'danger';
    }
} else {
    $message = 'Token is required to reset password.';
    $alertType = 'danger';
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    // Get form data
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate form data
    if (empty($password) || empty($confirmPassword)) {
        $message = 'Please enter both password fields';
        $alertType = 'danger';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match';
        $alertType = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long';
        $alertType = 'danger';
    } else {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Update user password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id");
            $db->bind(':password', $hashedPassword);
            $db->bind(':user_id', $userId);
            $db->execute();
            
            // Mark token as used
            $db->query("UPDATE password_resets SET used = 1 WHERE token = :token");
            $db->bind(':token', $token);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            // Log action
            logAction('Password reset', 'users', $userId);
            
            $message = 'Your password has been reset successfully. You can now login with your new password.';
            $alertType = 'success';
            
            // Clear token
            $token = '';
            $validToken = false;
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $message = 'An error occurred while resetting your password: ' . $e->getMessage();
            $alertType = 'danger';
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
    <title>Reset Password - <?php echo $systemName; ?></title>
    
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
                            <h2 class="text-center mb-4">Reset Password</h2>
                            
                            <?php if ($message): ?>
                            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($validToken): ?>
                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?token=' . $token); ?>" method="post">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required autofocus>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" toggle="#password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" toggle="#confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Update Password</button>
                                </div>
                            </form>
                            <?php else: ?>
                            <div class="d-grid gap-2">
                                <a href="forgot_password.php" class="btn btn-primary btn-lg">Request New Reset Link</a>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                            <?php endif; ?>
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
    
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const target = $(this).attr('toggle');
                const input = $(target);
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>
</body>
</html>
