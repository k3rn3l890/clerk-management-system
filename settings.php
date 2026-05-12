<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to manage settings
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Define available settings with their descriptions and types
$availableSettings = [
    'system_name' => [
        'label' => 'System Name',
        'description' => 'The name of the system displayed in the header and title',
        'type' => 'text'
    ],
    'court_name' => [
        'label' => 'Court Name',
        'description' => 'The name of the court displayed in reports and documents',
        'type' => 'text'
    ],
    'admin_email' => [
        'label' => 'Admin Email',
        'description' => 'Email address for system notifications',
        'type' => 'email'
    ],
    'case_prefix' => [
        'label' => 'Case Number Prefix',
        'description' => 'Prefix used for generating case numbers',
        'type' => 'text'
    ],
    'pagination_limit' => [
        'label' => 'Pagination Limit',
        'description' => 'Number of items to display per page in tables',
        'type' => 'number'
    ],
    'enable_notifications' => [
        'label' => 'Enable Notifications',
        'description' => 'Enable or disable system notifications',
        'type' => 'select',
        'options' => [
            'yes' => 'Yes',
            'no' => 'No'
        ]
    ],
    'enforce_location_tracking' => [
        'label' => 'Enforce Location Tracking',
        'description' => 'Require users to enable location tracking to use the system',
        'type' => 'select',
        'options' => [
            'yes' => 'Yes - Required for all users',
            'partial' => 'Partial - Required only for document access',
            'no' => 'No - Optional for users'
        ]
    ],
    'location_tracking_message' => [
        'label' => 'Location Tracking Message',
        'description' => 'Message displayed to users when requesting location permissions',
        'type' => 'textarea'
    ],
    'maintenance_mode' => [
        'label' => 'Maintenance Mode',
        'description' => 'Put the system in maintenance mode',
        'type' => 'select',
        'options' => [
            'off' => 'Off',
            'on' => 'On'
        ]
    ]
];

// Process form submission
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Process each setting
        foreach ($availableSettings as $settingName => $settingInfo) {
            if (isset($_POST[$settingName])) {
                $settingValue = sanitize($_POST[$settingName]);
                // Capture old value BEFORE updating for accurate audit log
                $oldValue = getSetting($settingName);
                
                // Check if setting exists
                $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = :setting_name");
                $db->bind(':setting_name', $settingName);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    // Update existing setting
                    $db->query("UPDATE settings SET setting_value = :setting_value, updated_at = NOW(), updated_by = :updated_by WHERE setting_name = :setting_name");
                } else {
                    // Insert new setting
                    $db->query("INSERT INTO settings (setting_name, setting_value, updated_by) VALUES (:setting_name, :setting_value, :updated_by)");
                }
                
                $db->bind(':setting_name', $settingName);
                $db->bind(':setting_value', $settingValue);
                $db->bind(':updated_by', $_SESSION['user_id']);
                $db->execute();
                
                // Log the action
                logAction('Setting updated', 'settings', 0, $oldValue, $settingValue);
            }
        }
        
        // Commit transaction (only if active)
        if ($db->inTransaction()) {
            $db->endTransaction();
        }
        
        $success = true;
        setFlashMessage('Settings updated successfully.', 'success');
        // Reload page so header picks updated settings immediately
        redirect('settings.php');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        $errors[] = 'An error occurred while updating settings: ' . $e->getMessage();
    }
}

// Get current settings
$currentSettings = [];
foreach ($availableSettings as $settingName => $settingInfo) {
    $currentSettings[$settingName] = getSetting($settingName);
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">System Settings</h1>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Settings updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">System Configuration</h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <?php foreach ($availableSettings as $settingName => $settingInfo): ?>
                <div class="mb-3">
                    <label for="<?php echo $settingName; ?>" class="form-label"><?php echo $settingInfo['label']; ?></label>
                    
                    <?php if ($settingInfo['type'] === 'select'): ?>
                    <select class="form-select" id="<?php echo $settingName; ?>" name="<?php echo $settingName; ?>">
                        <?php foreach ($settingInfo['options'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($currentSettings[$settingName] === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="<?php echo $settingInfo['type']; ?>" class="form-control" id="<?php echo $settingName; ?>" name="<?php echo $settingName; ?>" value="<?php echo htmlspecialchars($currentSettings[$settingName] ?? ''); ?>">
                    <?php endif; ?>
                    
                    <small class="form-text text-muted"><?php echo $settingInfo['description']; ?></small>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
