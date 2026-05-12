<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user's role
$userRole = $_SESSION['role'];

// Get counts for dashboard stats based on user role
$db = new Database();

// Total cases count
if ($userRole === 'admin' || $userRole === 'it_admin') {
    // For admin and IT admin - show all cases
    $db->query("SELECT COUNT(*) as count FROM cases");
} elseif ($userRole === 'court_clerk') {
    // For court clerks - only show their cases
    $db->query("SELECT COUNT(*) as count FROM cases WHERE created_by = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'judge') {
    // For judges - show cases assigned to them
    $db->query("SELECT COUNT(*) as count FROM cases WHERE assigned_judge = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    // For lawyers - show only their cases (via party_lawyers)
    $db->query("SELECT COUNT(DISTINCT c.case_id) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    // For litigants - show only their cases
    $db->query("SELECT COUNT(DISTINCT c.case_id) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
}
$result = $db->single();
$totalCases = $result['count'] ?? 0;

// Active cases count
if ($userRole === 'admin' || $userRole === 'it_admin') {
    $db->query("SELECT COUNT(*) as count FROM cases WHERE status = 'active'");
} elseif ($userRole === 'court_clerk') {
    $db->query("SELECT COUNT(*) as count FROM cases WHERE status = 'active' AND created_by = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'judge') {
    $db->query("SELECT COUNT(*) as count FROM cases WHERE status = 'active' AND assigned_judge = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT COUNT(DISTINCT c.case_id) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id AND c.status = 'active'");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT COUNT(DISTINCT c.case_id) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id AND c.status = 'active'");
    $db->bind(':user_id', $_SESSION['user_id']);
}
$result = $db->single();
$activeCases = $result['count'] ?? 0;

// Upcoming hearings count
if ($userRole === 'admin' || $userRole === 'it_admin') {
    $db->query("SELECT COUNT(*) as count FROM hearings WHERE hearing_date >= CURDATE() AND status = 'scheduled'");
} elseif ($userRole === 'court_clerk') {
    $db->query("SELECT COUNT(*) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled'
                AND c.created_by = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'judge') {
    $db->query("SELECT COUNT(*) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled' 
                AND c.assigned_judge = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT COUNT(DISTINCT h.hearing_id) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id 
                AND h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled'");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT COUNT(DISTINCT h.hearing_id) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id 
                AND h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled'");
    $db->bind(':user_id', $_SESSION['user_id']);
}
$result = $db->single();
$upcomingHearings = $result['count'] ?? 0;

// Documents count
if ($userRole === 'admin' || $userRole === 'it_admin') {
    $db->query("SELECT COUNT(*) as count FROM documents");
} elseif ($userRole === 'court_clerk') {
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE c.created_by = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'judge') {
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE c.assigned_judge = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT COUNT(DISTINCT d.document_id) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT COUNT(DISTINCT d.document_id) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id AND (d.is_public = 1 OR d.uploaded_by = :user_id2)");
    $db->bind(':user_id', $_SESSION['user_id']);
    $db->bind(':user_id2', $_SESSION['user_id']);
}
$result = $db->single();
$totalDocuments = $result['count'] ?? 0;

// Recent cases
if ($userRole === 'admin' || $userRole === 'it_admin') {
    $db->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                FROM cases c 
                LEFT JOIN users u ON c.created_by = u.user_id 
                ORDER BY c.created_at DESC LIMIT 5");
} elseif ($userRole === 'judge') {
    $db->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                FROM cases c 
                LEFT JOIN users u ON c.created_by = u.user_id 
                WHERE c.assigned_judge = :user_id 
                ORDER BY c.created_at DESC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'court_clerk') {
    $db->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                FROM cases c 
                LEFT JOIN users u ON c.created_by = u.user_id 
                WHERE c.created_by = :user_id 
                ORDER BY c.created_at DESC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT DISTINCT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                FROM cases c 
                LEFT JOIN users u ON c.created_by = u.user_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id 
                ORDER BY c.created_at DESC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT DISTINCT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
                FROM cases c 
                LEFT JOIN users u ON c.created_by = u.user_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id 
                ORDER BY c.created_at DESC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
}
$recentCases = $db->resultSet();

// Upcoming hearings
if ($userRole === 'admin' || $userRole === 'it_admin') {
    $db->query("SELECT h.*, c.case_number, c.case_title 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled' 
                ORDER BY h.hearing_date ASC LIMIT 5");
} elseif ($userRole === 'court_clerk') {
    $db->query("SELECT h.*, c.case_number, c.case_title 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled'
                AND c.created_by = :user_id 
                ORDER BY h.hearing_date ASC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'judge') {
    $db->query("SELECT h.*, c.case_number, c.case_title 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled' 
                AND c.assigned_judge = :user_id 
                ORDER BY h.hearing_date ASC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT DISTINCT h.*, c.case_number, c.case_title 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id 
                AND h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled' 
                ORDER BY h.hearing_date ASC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT DISTINCT h.*, c.case_number, c.case_title 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id 
                AND h.hearing_date >= CURDATE() 
                AND h.status = 'scheduled' 
                ORDER BY h.hearing_date ASC LIMIT 5");
    $db->bind(':user_id', $_SESSION['user_id']);
}
$upcomingHearingsList = $db->resultSet();

// Recent notifications
$db->query("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
$db->bind(':user_id', $_SESSION['user_id']);
$recentNotifications = $db->resultSet();

// Get case statistics for admin dashboard
$casesByStatus = [];
$casesByType = [];

if ($userRole === 'admin' || $userRole === 'it_admin') {
    // Cases by status - admin sees all
    $db->query("SELECT status, COUNT(*) as count FROM cases GROUP BY status");
    $statusResults = $db->resultSet();
    
    foreach ($statusResults as $result) {
        $casesByStatus[$result['status']] = $result['count'];
    }
    
    // Cases by type - admin sees all
    $db->query("SELECT case_type, COUNT(*) as count FROM cases GROUP BY case_type");
    $typeResults = $db->resultSet();
    
    foreach ($typeResults as $result) {
        $casesByType[$result['case_type']] = $result['count'];
    }
} elseif ($userRole === 'court_clerk') {
    // Cases by status - only clerk's cases
    $db->query("SELECT status, COUNT(*) as count FROM cases WHERE created_by = :user_id GROUP BY status");
    $db->bind(':user_id', $_SESSION['user_id']);
    $statusResults = $db->resultSet();
    
    foreach ($statusResults as $result) {
        $casesByStatus[$result['status']] = $result['count'];
    }
    
    // Cases by type for court clerk only
    $db->query("SELECT case_type, COUNT(*) as count FROM cases WHERE created_by = :user_id GROUP BY case_type");
    $db->bind(':user_id', $_SESSION['user_id']);
    $typeResults = $db->resultSet();
    
    foreach ($typeResults as $result) {
        $casesByType[$result['case_type']] = $result['count'];
    }
}

// Get user statistics for admin dashboard
$usersByRole = [];

if ($userRole === 'admin') {
    $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roleResults = $db->resultSet();
    
    foreach ($roleResults as $result) {
        $usersByRole[$result['role']] = $result['count'];
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <div>
            <?php if ($userRole === 'admin'): ?>
            <a href="document_tracking.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm me-2">
                <i class="fas fa-map-marker-alt fa-sm text-white-50"></i> Document Tracking
            </a>
            <?php endif; ?>
            <?php if (in_array($userRole, ['admin', 'court_clerk'])): ?>
            <a href="reports.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($userRole === 'litigant'): ?>
    <!-- Token Access (Litigant) -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-key me-1"></i> Access a Case with Token</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="case_public.php" class="row g-2 align-items-end">
                        <div class="col-md-8 col-lg-10">
                            <label for="tokenInput" class="form-label">Enter 10-character token</label>
                            <input type="text" class="form-control" id="tokenInput" name="token" placeholder="e.g. A1b2C3d4E5" maxlength="64" pattern="[A-Za-z0-9]{10}" required>
                        </div>
                        <div class="col-md-4 col-lg-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> View Case</button>
                        </div>
                        <div class="col-12">
                            <small class="form-text text-muted">Paste the token you received to view your case details in read-only mode.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dashboard Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card card-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Cases</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalCases; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="cases.php" class="text-primary">View Details <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card card-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Cases</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeCases; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="cases.php?status=active" class="text-success">View Details <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card card-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Upcoming Hearings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $upcomingHearings; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="hearings.php?status=scheduled" class="text-info">View Details <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card card-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Documents</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalDocuments; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="documents.php" class="text-warning">View Details <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!in_array($userRole, ['it_admin', 'admin']) && in_array($userRole, ['court_clerk', 'lawyer'])): ?>
    <!-- Action buttons -->
    <div class="row mb-4">
        <?php if (in_array($userRole, ['court_clerk'])): ?>
        <div class="col-md-4">
            <a href="case_add.php" class="btn btn-primary btn-block">
                <i class="fas fa-plus"></i> Add New Case
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($userRole, ['court_clerk'])): ?>
        <div class="col-md-4">
            <a href="hearing_add.php" class="btn btn-success btn-block">
                <i class="fas fa-calendar-plus"></i> Schedule Hearing
            </a>
        </div>
        <?php endif; ?>
        
        <div class="col-md-4">
            <a href="document_upload.php" class="btn btn-info btn-block">
                <i class="fas fa-file-upload"></i> Upload Document
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Content Row -->
    <div class="row">
        <!-- Recent Cases -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Cases</h6>
                    <a href="cases.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentCases) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Filed On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCases as $case): ?>
                                <tr>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $case['case_id']; ?>">
                                            <?php echo $case['case_number']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $case['case_title']; ?></td>
                                    <td><?php echo getCaseStatusLabel($case['status']); ?></td>
                                    <td><?php echo formatDate($case['filing_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No cases found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Hearings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Hearings</h6>
                    <a href="hearings.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingHearingsList) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Case</th>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingHearingsList as $hearing): ?>
                                <tr>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $hearing['case_id']; ?>">
                                            <?php echo $hearing['case_number']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                    <td><?php echo $hearing['hearing_type']; ?></td>
                                    <td><?php echo $hearing['location']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No upcoming hearings.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($userRole, ['admin', 'court_clerk'])): ?>
    <!-- Admin Statistics -->
    <div class="row">
        <!-- Cases by Status -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cases by Status</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="casesByStatusChart" data-chart='<?php echo json_encode([
                            'labels' => array_map('ucfirst', array_keys($casesByStatus)),
                            'data' => array_values($casesByStatus)
                        ]); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cases by Type -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cases by Type</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="casesByTypeChart" data-chart='<?php echo json_encode([
                            'labels' => array_map('ucfirst', array_keys($casesByType)),
                            'data' => array_values($casesByType)
                        ]); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($userRole === 'admin'): ?>
    <!-- User Statistics -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Users</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="usersByRoleChart" data-chart='<?php echo json_encode([
                            'labels' => array_map(function($role) {
                                return ucwords(str_replace('_', ' ', $role));
                            }, array_keys($usersByRole)),
                            'data' => array_values($usersByRole)
                        ]); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Location Tracking Status (admin only) -->
    <?php if (hasRole(['admin', 'it_admin'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-map-marker-alt mr-1"></i> Location Tracking Status
            </h6>
        </div>
        <div class="card-body">
            <?php
            // Get location agreement statistics
            $db->query("SELECT 
                        (SELECT COUNT(*) FROM location_policy_agreements) as agreements,
                        (SELECT COUNT(DISTINCT user_id) FROM document_access_logs WHERE latitude IS NOT NULL AND longitude IS NOT NULL) as tracked_users,
                        (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users");
            $locationStats = $db->single();
            
            $agreementCount = $locationStats['agreements'] ?? 0;
            $trackedUserCount = $locationStats['tracked_users'] ?? 0;
            $totalUsers = $locationStats['total_users'] ?? 1; // Prevent division by zero
            $percentAgreed = round(($agreementCount / $totalUsers) * 100);
            $percentTracked = round(($trackedUserCount / $totalUsers) * 100);
            ?>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h4 class="small font-weight-bold">Users Agreed to Location Tracking <span class="float-right"><?php echo $percentAgreed; ?>%</span></h4>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percentAgreed; ?>%" aria-valuenow="<?php echo $percentAgreed; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <p class="text-sm mb-0">
                        <i class="fas fa-check-circle text-success"></i> <?php echo $agreementCount; ?> out of <?php echo $totalUsers; ?> users have agreed to location tracking
                    </p>
                </div>
                
                <div class="col-md-6 mb-4">
                    <h4 class="small font-weight-bold">Users with Location Data <span class="float-right"><?php echo $percentTracked; ?>%</span></h4>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentTracked; ?>%" aria-valuenow="<?php echo $percentTracked; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <p class="text-sm mb-0">
                        <i class="fas fa-map-marker-alt text-danger"></i> <?php echo $trackedUserCount; ?> users have location data recorded
                    </p>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="document_tracking.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-map-marked-alt mr-1"></i> View Document Tracking Map
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Notifications -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Notifications</h6>
                    <a href="notifications.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentNotifications) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($recentNotifications as $notification): ?>
                        <a href="notifications.php?id=<?php echo $notification['notification_id']; ?>" 
                           class="list-group-item list-group-item-action <?php echo ($notification['is_read'] == 0) ? 'fw-bold' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?php echo $notification['title']; ?></h5>
                                <small><?php echo formatDateTime($notification['created_at']); ?></small>
                            </div>
                            <p class="mb-1"><?php echo substr($notification['message'], 0, 100) . (strlen($notification['message']) > 100 ? '...' : ''); ?></p>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No notifications found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize charts when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Cases by Status Chart
        if (document.getElementById('casesByStatusChart')) {
            const caseStatusCtx = document.getElementById('casesByStatusChart').getContext('2d');
            const caseStatusData = JSON.parse(document.getElementById('casesByStatusChart').getAttribute('data-chart'));
            
            new Chart(caseStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: caseStatusData.labels,
                    datasets: [{
                        data: caseStatusData.data,
                        backgroundColor: [
                            '#4e73df', // Primary
                            '#1cc88a', // Success
                            '#36b9cc', // Info
                            '#f6c23e', // Warning
                            '#e74a3b'  // Danger
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Cases by Type Chart
        if (document.getElementById('casesByTypeChart')) {
            const caseTypeCtx = document.getElementById('casesByTypeChart').getContext('2d');
            const caseTypeData = JSON.parse(document.getElementById('casesByTypeChart').getAttribute('data-chart'));
            
            new Chart(caseTypeCtx, {
                type: 'bar',
                data: {
                    labels: caseTypeData.labels,
                    datasets: [{
                        label: 'Number of Cases',
                        data: caseTypeData.data,
                        backgroundColor: '#4e73df',
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Users by Role Chart
        if (document.getElementById('usersByRoleChart')) {
            const userRoleCtx = document.getElementById('usersByRoleChart').getContext('2d');
            const userRoleData = JSON.parse(document.getElementById('usersByRoleChart').getAttribute('data-chart'));
            
            new Chart(userRoleCtx, {
                type: 'pie',
                data: {
                    labels: userRoleData.labels,
                    datasets: [{
                        data: userRoleData.data,
                        backgroundColor: [
                            '#4e73df', // Primary
                            '#1cc88a', // Success
                            '#36b9cc', // Info
                            '#f6c23e', // Warning
                            '#e74a3b', // Danger
                            '#5a5c69'  // Secondary
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
