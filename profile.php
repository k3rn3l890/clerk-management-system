<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize database
$db = new Database();

// Get user data
$userId = $_SESSION['user_id'];
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$user = $db->single();

// Check if profile_picture column exists
$db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
$hasProfilePictureColumn = $db->single();

// Create uploads directory if it doesn't exist
$uploadsDir = UPLOAD_PATH . '/profile_pictures';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Check if the users table has a phone column
$db->query("SHOW COLUMNS FROM users LIKE 'phone'");
$hasPhoneColumn = $db->single();

// Process form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['upload_profile_picture'])) {
        // Profile picture upload form
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = pathinfo($_FILES['profile_picture']['name']);
            $extension = strtolower($fileInfo['extension']);
            
            // Validate file type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
            } else {
                // Generate unique filename
                $newFilename = 'profile_' . $userId . '_' . time() . '.' . $extension;
                $targetFile = $uploadsDir . '/' . $newFilename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
                    // Update user profile_picture in database
                    if ($hasProfilePictureColumn) {
                        $relativePath = 'uploads/profile_pictures/' . $newFilename;
                        $db->query("UPDATE users SET profile_picture = :profile_picture WHERE user_id = :user_id");
                        $db->bind(':profile_picture', $relativePath);
                        $db->bind(':user_id', $userId);
                        
                        if ($db->execute()) {
                            $success = 'Profile picture updated successfully';
                            
                            // Log the action
                            logAction('Profile picture updated', 'users', $userId);
                            
                            // Refresh user data
                            $db->query("SELECT * FROM users WHERE user_id = :user_id");
                            $db->bind(':user_id', $userId);
                            $user = $db->single();
                            
                            // Force page reload to show updated data
                            echo "<meta http-equiv='refresh' content='1;url=profile.php'>";
                        } else {
                            $errors[] = 'Failed to update profile picture in database';
                        }
                    } else {
                        $errors[] = 'Profile picture column does not exist in users table. Please run check_users_table.php first.';
                    }
                } else {
                    $errors[] = 'Failed to upload profile picture';
                }
            }
        } else {
            $errors[] = 'Please select a profile picture to upload';
        }
    } elseif (isset($_POST['update_profile'])) {
        // Profile update form
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        
        // Validate inputs
        if (empty($firstName)) {
            $errors[] = 'First name is required';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Last name is required';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Check if email already exists (excluding current user)
        if (!empty($email)) {
            $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email AND user_id != :user_id");
            $db->bind(':email', $email);
            $db->bind(':user_id', $userId);
            $result = $db->single();
            
            if ($result['count'] > 0) {
                $errors[] = 'Email already in use by another account';
            }
        }
        
        // If no errors, update user profile
        if (empty($errors)) {
            try {
                // Check if the users table has a phone column
                $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
                $hasPhoneColumn = $db->single();
                
                // Check if the users table has an updated_at column
                $db->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
                $hasUpdatedAt = $db->single();
                
                // Build the query based on available columns
                if ($hasPhoneColumn && $hasUpdatedAt) {
                    $db->query("UPDATE users SET first_name = :first_name, last_name = :last_name, 
                                email = :email, phone = :phone, updated_at = NOW() 
                                WHERE user_id = :user_id");
                    $db->bind(':phone', $phone);
                } elseif ($hasPhoneColumn) {
                    $db->query("UPDATE users SET first_name = :first_name, last_name = :last_name, 
                                email = :email, phone = :phone 
                                WHERE user_id = :user_id");
                    $db->bind(':phone', $phone);
                } elseif ($hasUpdatedAt) {
                    $db->query("UPDATE users SET first_name = :first_name, last_name = :last_name, 
                                email = :email, updated_at = NOW() 
                                WHERE user_id = :user_id");
                } else {
                    $db->query("UPDATE users SET first_name = :first_name, last_name = :last_name, 
                                email = :email 
                                WHERE user_id = :user_id");
                }
                
                $db->bind(':first_name', $firstName);
                $db->bind(':last_name', $lastName);
                $db->bind(':email', $email);
                $db->bind(':user_id', $userId);
                
                if ($db->execute()) {
                    // Update session data
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    
                    $success = 'Profile updated successfully';
                    
                    // Log the action
                    logAction('Profile updated', 'users', $userId);
                    
                    // Refresh user data
                    $db->query("SELECT * FROM users WHERE user_id = :user_id");
                    $db->bind(':user_id', $userId);
                    $user = $db->single();
                    
                    // Force page reload to show updated data
                    echo "<meta http-equiv='refresh' content='1;url=profile.php'>";
                } else {
                    $errors[] = 'Failed to update profile: Database error';
                }
            } catch (Exception $e) {
                $errors[] = 'Error updating profile: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change form
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }
        
        // Verify current password
        if (!empty($currentPassword) && !password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        // If no errors, update password
        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $db->query("UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id");
            $db->bind(':password', $hashedPassword);
            $db->bind(':user_id', $userId);
            
            if ($db->execute()) {
                $success = 'Password changed successfully';
                
                // Log the action
                logAction('Password changed', 'users', $userId);
            } else {
                $errors[] = 'Failed to change password';
            }
        }
    } elseif (isset($_POST['update_preferences'])) {
        // Preferences form
        $notificationEmail = isset($_POST['notification_email']) ? 1 : 0;
        $notificationSms = isset($_POST['notification_sms']) ? 1 : 0;
        $theme = sanitize($_POST['theme']);
        
        try {
            // Check if user_preferences table exists
            $db->query("SHOW TABLES LIKE 'user_preferences'");
            $tableExists = $db->single();
            
            if (!$tableExists) {
                // Create user_preferences table if it doesn't exist
                $db->query("CREATE TABLE IF NOT EXISTS user_preferences (
                    preference_id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL,
                    notification_email TINYINT(1) NOT NULL DEFAULT 1,
                    notification_sms TINYINT(1) NOT NULL DEFAULT 0,
                    theme VARCHAR(50) NOT NULL DEFAULT 'light',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (preference_id),
                    KEY user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $db->execute();
            }
            
            // Check if preferences exist for this user
            $db->query("SELECT COUNT(*) as count FROM user_preferences WHERE user_id = :user_id");
            $db->bind(':user_id', $userId);
            $result = $db->single();
            
            if ($result && $result['count'] > 0) {
                // Update existing preferences
                $db->query("UPDATE user_preferences SET 
                            notification_email = :notification_email, 
                            notification_sms = :notification_sms, 
                            theme = :theme, 
                            updated_at = NOW() 
                            WHERE user_id = :user_id");
            } else {
                // Insert new preferences
                $db->query("INSERT INTO user_preferences (user_id, notification_email, notification_sms, theme, created_at, updated_at) 
                            VALUES (:user_id, :notification_email, :notification_sms, :theme, NOW(), NOW())");
            }
            
            $db->bind(':notification_email', $notificationEmail);
            $db->bind(':notification_sms', $notificationSms);
            $db->bind(':theme', $theme);
            $db->bind(':user_id', $userId);
            
            if ($db->execute()) {
                $success = 'Preferences updated successfully';
                
                // Log the action
                logAction('Preferences updated', 'users', $userId);
                
                // Refresh preferences
                $db->query("SELECT * FROM user_preferences WHERE user_id = :user_id");
                $db->bind(':user_id', $userId);
                $preferences = $db->single();
                
                if (!$preferences) {
                    $preferences = [
                        'notification_email' => $notificationEmail,
                        'notification_sms' => $notificationSms,
                        'theme' => $theme
                    ];
                }
            } else {
                $errors[] = 'Failed to update preferences';
            }
        } catch (Exception $e) {
            $errors[] = 'Error updating preferences: ' . $e->getMessage();
        }
    }
}

// Get user preferences - check if table exists first
try {
    // Check if user_preferences table exists
    $db->query("SHOW TABLES LIKE 'user_preferences'");
    $tableExists = $db->single();
    
    if ($tableExists) {
        $db->query("SELECT * FROM user_preferences WHERE user_id = :user_id");
        $db->bind(':user_id', $userId);
        $preferences = $db->single();
    } else {
        // Table doesn't exist, use defaults
        $preferences = null;
    }
} catch (Exception $e) {
    // Error occurred, use defaults
    $preferences = null;
}

// Set default preferences if not found
if (!$preferences) {
    $preferences = [
        'notification_email' => 1,
        'notification_sms' => 0,
        'theme' => 'light'
    ];
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">My Profile</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php 
                        $profileImage = 'img/undraw_profile.svg';
                        if ($hasProfilePictureColumn && !empty($user['profile_picture'])) {
                            $profileImage = $user['profile_picture'];
                        }
                        ?>
                        <img class="img-profile rounded-circle mb-3" src="<?php echo $profileImage; ?>" style="width: 150px; height: 150px; object-fit: cover;">
                        <h5><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h5>
                        <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                        
                        <!-- Profile Picture Upload Form -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" class="mt-2">
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Change Profile Picture</label>
                                <input class="form-control form-control-sm" id="profile_picture" name="profile_picture" type="file" accept="image/*">
                            </div>
                            <button type="submit" name="upload_profile_picture" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload"></i> Upload Picture
                            </button>
                        </form>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Username:</h6>
                        <p><?php echo $user['username']; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Email:</h6>
                        <p><?php echo $user['email']; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Phone:</h6>
                        <p><?php echo !empty($user['phone']) ? $user['phone'] : 'Not provided'; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Account Created:</h6>
                        <p><?php echo formatDateTime($user['created_at']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Last Updated:</h6>
                        <p><?php echo formatDateTime($user['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Tabs -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab" aria-controls="edit" aria-selected="true">Edit Profile</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">Change Password</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab" aria-controls="preferences" aria-selected="false">Preferences</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Edit Profile Tab -->
                        <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="firstName">First Name</label>
                                            <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lastName">Last Name</label>
                                            <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                </div>
                                
                                <?php if ($hasPhoneColumn): ?>
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($user['phone']) ? $user['phone'] : ''; ?>">
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                        
                        <!-- Change Password Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="form-group">
                                    <label for="currentPassword">Current Password</label>
                                    <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="newPassword">New Password</label>
                                    <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                        
                        <!-- Preferences Tab -->
                        <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <h5 class="mb-3">Notification Settings</h5>
                                
                                <div class="form-group">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="notificationEmail" name="notification_email" <?php echo (isset($preferences['notification_email']) && $preferences['notification_email']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notificationEmail">Receive Email Notifications</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="notificationSms" name="notification_sms" <?php echo (isset($preferences['notification_sms']) && $preferences['notification_sms']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notificationSms">Receive SMS Notifications</label>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Display Settings</h5>
                                
                                <div class="form-group">
                                    <label for="theme">Theme</label>
                                    <select class="form-control" id="theme" name="theme">
                                        <option value="light" <?php echo (isset($preferences['theme']) && $preferences['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo (isset($preferences['theme']) && $preferences['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                                    </select>
                                </div>
                                
                                <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
