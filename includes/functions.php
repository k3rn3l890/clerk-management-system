<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Sanitize user input
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to a specific page
 * @param string $location
 * @return void
 */
function redirect($location) {
    // Check if headers have already been sent
    if (!headers_sent()) {
        // If headers haven't been sent, use standard header redirect
        header("Location: " . $location);
        exit;
    } else {
        // If headers have been sent, use JavaScript redirect
        echo "<script>window.location.href = '" . $location . "';</script>";
        exit;
    }
}

/**
 * Check if user is logged in
 * @return boolean
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 * @param string|array $roles
 * @return boolean
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Generate a unique case number
 * @param string $prefix
 * @return string
 */
function generateCaseNumber($prefix = 'GH') {
    $year = date('Y');
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    return $prefix . '-' . $year . '-' . $random;
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd M, Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'd M, Y h:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Get user by ID
 * @param int $userId
 * @return array|false
 */
function getUserById($userId) {
    $db = new Database();
    $db->query("SELECT * FROM users WHERE user_id = :user_id");
    $db->bind(':user_id', $userId);
    return $db->single();
}

/**
 * Get user full name
 * @param int $userId
 * @return string
 */
function getUserFullName($userId) {
    $user = getUserById($userId);
    if ($user) {
        return $user['first_name'] . ' ' . $user['last_name'];
    }
    return 'Unknown User';
}

/**
 * Log an action in the audit log
 * @param string $action
 * @param string $entityType
 * @param int $entityId
 * @param mixed $oldValue
 * @param mixed $newValue
 * @return bool
 */
function logAction($action, $entityType, $entityId, $oldValue = null, $newValue = null) {
    $db = new Database();
    $db->query("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address) 
                VALUES (:user_id, :action, :entity_type, :entity_id, :old_value, :new_value, :ip_address)");
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $db->bind(':user_id', $userId);
    $db->bind(':action', $action);
    $db->bind(':entity_type', $entityType);
    $db->bind(':entity_id', $entityId);
    $db->bind(':old_value', is_array($oldValue) ? json_encode($oldValue) : $oldValue);
    $db->bind(':new_value', is_array($newValue) ? json_encode($newValue) : $newValue);
    $db->bind(':ip_address', $ipAddress);
    
    return $db->execute();
}

/**
 * Get system setting value
 * @param string $settingName
 * @return string|null
 */
function getSetting($settingName) {
    $db = new Database();
    $db->query("SELECT setting_value FROM settings WHERE setting_name = :setting_name");
    $db->bind(':setting_name', $settingName);
    $result = $db->single();
    
    return $result ? $result['setting_value'] : null;
}

/**
 * Update system setting
 * @param string $settingName
 * @param string $settingValue
 * @return bool
 */
function updateSetting($settingName, $settingValue) {
    $db = new Database();
    $db->query("UPDATE settings SET setting_value = :setting_value, updated_by = :updated_by WHERE setting_name = :setting_name");
    $db->bind(':setting_name', $settingName);
    $db->bind(':setting_value', $settingValue);
    $db->bind(':updated_by', $_SESSION['user_id']);
    
    return $db->execute();
}

/**
 * Get unread notifications count for a user
 * @param int $userId
 * @return int
 */
function getUnreadNotificationsCount($userId) {
    $db = new Database();
    $db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $db->bind(':user_id', $userId);
    $result = $db->single();
    
    return $result['count'];
}

/**
 * Get unread messages count for a user
 * @param int $userId
 * @return int
 */
function getUnreadMessagesCount($userId) {
    $db = new Database();
    $db->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = :user_id AND is_read = 0");
    $db->bind(':user_id', $userId);
    $result = $db->single();
    
    return $result['count'];
}

/**
 * Create a notification for a user
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string|null $link (URL stored in message, not used as a column)
 * @return bool
 */
function createNotification($userId, $title, $message, $link = null) {
    $db = new Database();
    // 'notifications' table has no 'link' column; store only existing fields
    $db->query("INSERT INTO notifications (user_id, title, message, created_at) 
                VALUES (:user_id, :title, :message, NOW())");
    
    $db->bind(':user_id', $userId);
    $db->bind(':title', $title);
    $db->bind(':message', $message);
    
    return $db->execute();
}

/**
 * Calculate time ago for display
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Send notification to a user
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param int|null $relatedCase
 * @param int|null $relatedHearing
 * @return bool
 */
function sendNotification($userId, $title, $message, $relatedCase = null, $relatedHearing = null) {
    $db = new Database();
    $db->query("INSERT INTO notifications (user_id, title, message, related_case, related_hearing) 
                VALUES (:user_id, :title, :message, :related_case, :related_hearing)");
    
    $db->bind(':user_id', $userId);
    $db->bind(':title', $title);
    $db->bind(':message', $message);
    $db->bind(':related_case', $relatedCase);
    $db->bind(':related_hearing', $relatedHearing);
    
    return $db->execute();
}

/**
 * Send message to a user
 * @param int $senderId
 * @param int $receiverId
 * @param string $subject
 * @param string $message
 * @param int|null $relatedCase
 * @return bool
 */
function sendMessage($senderId, $receiverId, $subject, $message, $relatedCase = null) {
    $db = new Database();
    $db->query("INSERT INTO messages (sender_id, receiver_id, subject, message, related_case) 
                VALUES (:sender_id, :receiver_id, :subject, :message, :related_case)");
    
    $db->bind(':sender_id', $senderId);
    $db->bind(':receiver_id', $receiverId);
    $db->bind(':subject', $subject);
    $db->bind(':message', $message);
    $db->bind(':related_case', $relatedCase);
    
    return $db->execute();
}

/**
 * Get case status label with appropriate color class
 * @param string $status
 * @return string
 */
function getCaseStatusLabel($status) {
    $labels = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'active' => '<span class="badge bg-primary">Active</span>',
        'on_hold' => '<span class="badge bg-info">On Hold</span>',
        'resolved' => '<span class="badge bg-success">Resolved</span>',
        'closed' => '<span class="badge bg-secondary">Closed</span>',
        'dismissed' => '<span class="badge bg-danger">Dismissed</span>',
        'archived' => '<span class="badge bg-dark">Archived</span>'
    ];
    
    return isset($labels[$status]) ? $labels[$status] : '<span class="badge bg-dark">Unknown</span>';
}

/**
 * Get hearing status label with appropriate color class
 * @param string $status
 * @return string
 */
function getHearingStatusLabel($status) {
    $labels = [
        'scheduled' => '<span class="badge bg-primary">Scheduled</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'postponed' => '<span class="badge bg-warning">Postponed</span>',
        'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
    ];
    
    return isset($labels[$status]) ? $labels[$status] : '<span class="badge bg-dark">Unknown</span>';
}

/**
 * Get file size in human-readable format
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get file extension from filename
 * @param string $filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 * @param string $extension
 * @param array $allowedExtensions
 * @return bool
 */
function isAllowedExtension($extension, $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']) {
    return in_array(strtolower($extension), $allowedExtensions);
}

/**
 * Generate a random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Display flash message
 * @return string
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];
        
        unset($_SESSION['flash_message']);
        
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                    ' . $message . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    
    return '';
}

/**
 * Set flash message
 * @param string $message
 * @param string $type
 * @return void
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get the application's base URL (scheme + host + optional port + base path)
 * @return string
 */
function getBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    // Determine base path (directory of script); assume app lives at web root
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('index.php', '', $scriptName), '/');
    // On many setups, app is at '/', so return scheme://host without basePath
    return $scheme . '://' . $host;
}

/**
 * Ensure the case_access_tokens table exists
 * Safe to call repeatedly.
 */
function ensureCaseAccessTokensTable() {
    try {
        $db = new Database();
        $sql = "CREATE TABLE IF NOT EXISTS case_access_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_case_id (case_id),
                    CONSTRAINT fk_case_access_tokens_case
                        FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
                    CONSTRAINT fk_case_access_tokens_user
                        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->query($sql);
        $db->execute();
    } catch (Throwable $e) {
        // Log but do not break the page; pages using this will handle absence gracefully
        error_log('ensureCaseAccessTokensTable error: ' . $e->getMessage());
    }
}

/**
 * Log user location policy agreement
 * @param int $userId
 * @param string $ipAddress
 * @return bool
 */
function logLocationPolicyAgreement($userId, $ipAddress = null) {
    $db = new Database();
    
    // Check if table exists, create if not
    try {
        $db->query("CREATE TABLE IF NOT EXISTS location_policy_agreements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            agreement_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            browser_info TEXT,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )");
        $db->execute();
    } catch (Exception $e) {
        // Table might already exist or there might be an issue
        error_log("Error checking/creating location_policy_agreements table: " . $e->getMessage());
    }
    
    // Insert agreement record
    try {
        $db->query("INSERT INTO location_policy_agreements 
                    (user_id, ip_address, user_agent, browser_info) 
                    VALUES (:user_id, :ip_address, :user_agent, :browser_info)");
        
        $db->bind(':user_id', $userId);
        $db->bind(':ip_address', $ipAddress ?: $_SERVER['REMOTE_ADDR']);
        $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        $db->bind(':browser_info', json_encode([
            'platform' => $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? 'Unknown',
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        return $db->execute();
    } catch (Exception $e) {
        error_log("Error logging location policy agreement: " . $e->getMessage());
        return false;
    }
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
function sendWelcomeEmail($email, $firstName, $lastName, $username, $role) {
    try {
        require_once __DIR__ . '/EmailSender.php';
        $emailSender = new EmailSender();
        return $emailSender->sendWelcomeEmail($email, $firstName, $lastName, $username, $role);
    } catch (Exception $e) {
        error_log("Error sending welcome email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 * @param string $email
 * @param string $firstName
 * @param string $resetToken
 * @return bool
 */
function sendPasswordResetEmail($email, $firstName, $resetToken) {
    try {
        require_once __DIR__ . '/EmailSender.php';
        $emailSender = new EmailSender();
        return $emailSender->sendPasswordResetEmail($email, $firstName, $resetToken);
    } catch (Exception $e) {
        error_log("Error sending password reset email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification email
 * @param string $email
 * @param string $firstName
 * @param string $subject
 * @param string $message
 * @return bool
 */
function sendNotificationEmail($email, $firstName, $subject, $message) {
    try {
        require_once __DIR__ . '/EmailSender.php';
        $emailSender = new EmailSender();
        return $emailSender->sendNotificationEmail($email, $firstName, $subject, $message);
    } catch (Exception $e) {
        error_log("Error sending notification email: " . $e->getMessage());
        return false;
    }
}
