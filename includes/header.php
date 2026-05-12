<?php
require_once 'includes/functions.php';

// Redirect to login page if not logged in (except for login and register pages)
$currentPage = basename($_SERVER['PHP_SELF']);
$publicPages = ['login.php', 'register.php', 'index.php', 'forgot_password.php', 'reset_password.php', 'case_public.php'];

if (!isLoggedIn() && !in_array($currentPage, $publicPages)) {
    redirect('login.php');
}

// Get user data if logged in
$user = null;
if (isLoggedIn()) {
    $user = getUserById($_SESSION['user_id']);
}

// Get system name from settings
$systemName = getSetting('system_name') ?: 'Court Clerk Management System';
$courtName = getSetting('court_name') ?: 'High Court of Ghana';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $systemName; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/background.css" rel="stylesheet">
    <link href="assets/css/navbar-logo.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet Geocoder CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <!-- Leaflet Locate Control CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.83.0/dist/L.Control.Locate.min.css" />
    <!-- Leaflet Measure Control CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-measure@2.1.7/dist/leaflet-measure.css" />

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Geolocation enforcement and tracking scripts -->
    <script src="assets/js/geolocation_enforcer.js"></script>
    <script src="assets/js/document_geolocation.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Geocoder JS -->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <!-- Leaflet Locate Control JS -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.83.0/dist/L.Control.Locate.min.js" charset="UTF-8"></script>
    <!-- Leaflet Measure Control JS -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet-measure@2.1.7/dist/leaflet-measure.js"></script>
</head>
<body class="ghana-background">
    <?php if (isLoggedIn()): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <span class="navbar-logo"></span><?php echo $systemName; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if (hasRole(['court_clerk', 'admin', 'judge'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="casesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-folder me-1"></i> Cases
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="casesDropdown">
                            <?php if (hasRole(['court_clerk', 'admin'])): ?>
                            <li><a class="dropdown-item" href="case_add.php">Add New Case</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="cases.php">All Cases</a></li>
                            <li><a class="dropdown-item" href="cases.php?status=active">Active Cases</a></li>
                            <li><a class="dropdown-item" href="cases.php?status=pending">Pending Cases</a></li>
                            <li><a class="dropdown-item" href="cases.php?status=closed">Closed Cases</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['court_clerk', 'admin', 'judge'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="hearingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar-alt me-1"></i> Hearings
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="hearingsDropdown">
                            <?php if (hasRole(['court_clerk', 'admin'])): ?>
                            <li><a class="dropdown-item" href="hearing_add.php">Schedule Hearing</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="hearings.php">All Hearings</a></li>
                            <li><a class="dropdown-item" href="hearings.php?status=scheduled">Upcoming Hearings</a></li>
                            <li><a class="dropdown-item" href="hearings.php?status=completed">Completed Hearings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['court_clerk', 'admin'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="documentsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-alt me-1"></i> Documents
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="documentsDropdown">
                            <li><a class="dropdown-item" href="document_upload.php">Upload Document</a></li>
                            <li><a class="dropdown-item" href="documents.php">All Documents</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="external_lawyers.php">External Lawyers</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['court_clerk', 'admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'payments.php') ? 'active' : ''; ?>" href="payments.php">
                            <i class="fas fa-money-bill-wave me-1"></i> Payments
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['lawyer'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="lawyerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-briefcase me-1"></i> My Cases
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="lawyerDropdown">
                            <li><a class="dropdown-item" href="lawyer_cases.php">View My Cases</a></li>
                            <li><a class="dropdown-item" href="lawyer_hearings.php">My Hearings</a></li>
                            <li><a class="dropdown-item" href="lawyer_documents.php">My Documents</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['litigant'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="litigantDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-tie me-1"></i> My Cases
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="litigantDropdown">
                            <li><a class="dropdown-item" href="litigant_cases.php">View My Cases</a></li>
                            <li><a class="dropdown-item" href="litigant_hearings.php">My Hearings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['admin', 'it_admin'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs me-1"></i> Administration
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="users.php">User Management</a></li>
                            <li><a class="dropdown-item" href="reports.php">Reports</a></li>
                            <li><a class="dropdown-item" href="audit_logs.php">Audit Logs</a></li>
                            <li><a class="dropdown-item" href="settings.php">System Settings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php 
                            $notificationCount = getUnreadNotificationsCount($_SESSION['user_id']);
                            if ($notificationCount > 0): 
                            ?>
                            <span class="badge rounded-pill bg-danger"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                            $db = new Database();
                            $db->query("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
                            $db->bind(':user_id', $_SESSION['user_id']);
                            $notifications = $db->resultSet();
                            
                            if (count($notifications) > 0):
                                foreach ($notifications as $notification):
                            ?>
                            <li>
                                <a class="dropdown-item <?php echo ($notification['is_read'] == 0) ? 'fw-bold' : ''; ?>" href="notifications.php?id=<?php echo $notification['notification_id']; ?>">
                                    <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small><br>
                                    <?php echo $notification['title']; ?>
                                </a>
                            </li>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <li><a class="dropdown-item" href="#">No notifications</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">View All</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-envelope"></i>
                            <?php 
                            $messageCount = getUnreadMessagesCount($_SESSION['user_id']);
                            if ($messageCount > 0): 
                            ?>
                            <span class="badge rounded-pill bg-danger"><?php echo $messageCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="messagesDropdown">
                            <li><h6 class="dropdown-header">Messages</h6></li>
                            <?php 
                            // Get recent messages
                            $db = new Database();
                            $db->query("SELECT m.*, 
                                        CONCAT(u.first_name, ' ', u.last_name) as sender_name
                                        FROM messages m 
                                        JOIN users u ON m.sender_id = u.user_id 
                                        WHERE m.receiver_id = :user_id AND (m.is_read = 0 OR m.is_read IS NULL)
                                        ORDER BY m.created_at DESC LIMIT 5");
                            $db->bind(':user_id', $_SESSION['user_id']);
                            $recentMessages = $db->resultSet();
                            
                            if (!empty($recentMessages)):
                                foreach ($recentMessages as $message):
                            ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="message_view.php?id=<?php echo $message['message_id']; ?>">
                                    <div class="me-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    <div class="">
                                        <div class="small text-gray-500"><?php echo formatDateTime($message['created_at']); ?></div>
                                        <span class="fw-bold"><?php echo $message['sender_name']; ?></span>: <?php echo substr($message['subject'], 0, 30) . (strlen($message['subject']) > 30 ? '...' : ''); ?>
                                    </div>
                                </a>
                            </li>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <li><span class="dropdown-item">No new messages</span></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="chat.php"><i class="fas fa-comments me-1"></i> WhatsApp-Style Chat</a></li>
                            <li><a class="dropdown-item" href="messages.php"><i class="fas fa-envelope me-1"></i> Traditional Messages</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo $user['profile_picture']; ?>" class="rounded-circle me-1" width="24" height="24" alt="Profile">
                            <?php else: ?>
                            <i class="fas fa-user-circle me-1"></i>
                            <?php endif; ?>
                            <?php echo $user['first_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="container-fluid my-4">
        <?php echo displayFlashMessage(); ?>
