<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view hearings
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if hearing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid hearing ID.', 'danger');
    redirect('hearings.php');
}

$hearingId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get user's role
$userRole = $_SESSION['role'];

// Get hearing details
$db->query("SELECT h.*, c.case_number, c.case_title, c.case_type, c.status as case_status, 
            CONCAT(j.first_name, ' ', j.last_name) as judge_name, 
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
            FROM hearings h 
            JOIN cases c ON h.case_id = c.case_id 
            LEFT JOIN users j ON c.assigned_judge = j.user_id 
            LEFT JOIN users u ON h.created_by = u.user_id 
            WHERE h.hearing_id = :hearing_id");
$db->bind(':hearing_id', $hearingId);
$hearing = $db->single();

if (!$hearing) {
    setFlashMessage('Hearing not found.', 'danger');
    redirect('hearings.php');
}

// Check if the user has access to this hearing
$hasAccess = false;

if (in_array($userRole, ['admin', 'court_clerk'])) {
    // Admins and court clerks have access to all hearings
    $hasAccess = true;
} elseif ($userRole === 'judge') {
    // Judges have access to hearings for cases assigned to them
    $db->query("SELECT COUNT(*) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                WHERE h.hearing_id = :hearing_id AND c.assigned_judge = :user_id");
    $db->bind(':hearing_id', $hearingId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'lawyer') {
    // Lawyers have access to hearings for cases they're associated with
    $db->query("SELECT COUNT(*) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_lawyers cl ON c.case_id = cl.case_id 
                JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                WHERE h.hearing_id = :hearing_id AND l.user_id = :user_id");
    $db->bind(':hearing_id', $hearingId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'litigant') {
    // Litigants have access to hearings for cases they're a party to
    $db->query("SELECT COUNT(*) as count 
                FROM hearings h 
                JOIN cases c ON h.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE h.hearing_id = :hearing_id AND cp.user_id = :user_id");
    $db->bind(':hearing_id', $hearingId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
}

if (!$hasAccess) {
    setFlashMessage('You do not have permission to view this hearing.', 'danger');
    redirect('hearings.php');
}

// Get case parties
$db->query("SELECT * FROM case_parties WHERE case_id = :case_id");
$db->bind(':case_id', $hearing['case_id']);
$parties = $db->resultSet();

// Get case lawyers
$db->query("SELECT cl.*, l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name, 
            cp.party_name, cp.party_type 
            FROM case_lawyers cl 
            JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
            JOIN users u ON l.user_id = u.user_id 
            JOIN case_parties cp ON cl.party_id = cp.party_id 
            WHERE cl.case_id = :case_id");
$db->bind(':case_id', $hearing['case_id']);
$lawyers = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Hearing Details
        </h1>
        <div>
            <a href="hearings.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Hearings
            </a>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="hearing_edit.php?id=<?php echo $hearingId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit Hearing
            </a>
            <button class="btn btn-info btn-sm btn-print">
                <i class="fas fa-print"></i> Print
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hearing Information Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Hearing Information</h6>
            <span class="badge bg-<?php echo getStatusClass($hearing['status']); ?> px-3 py-2">
                <?php echo ucfirst($hearing['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Case:</th>
                            <td>
                                <a href="case_view.php?id=<?php echo $hearing['case_id']; ?>">
                                    <?php echo $hearing['case_number']; ?> - <?php echo $hearing['case_title']; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Case Type:</th>
                            <td><?php echo ucfirst($hearing['case_type']); ?></td>
                        </tr>
                        <tr>
                            <th>Case Status:</th>
                            <td><?php echo getCaseStatusLabel($hearing['case_status']); ?></td>
                        </tr>
                        <tr>
                            <th>Judge:</th>
                            <td><?php echo $hearing['judge_name'] ?: 'Not Assigned'; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Hearing Date:</th>
                            <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Hearing Type:</th>
                            <td><?php echo $hearing['hearing_type']; ?></td>
                        </tr>
                        <tr>
                            <th>Location:</th>
                            <td><?php echo $hearing['location']; ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><?php echo getHearingStatusLabel($hearing['status']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <h6 class="font-weight-bold">Notes:</h6>
                    <p><?php echo nl2br($hearing['notes']) ?: 'No notes available.'; ?></p>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">
                            <strong>Created By:</strong> <?php echo $hearing['created_by_name']; ?> on <?php echo formatDateTime($hearing['created_at']); ?>
                        </small>
                        <small class="text-muted">
                            <strong>Last Updated:</strong> <?php echo formatDateTime($hearing['updated_at']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Case Parties Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Case Parties</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Party Name</th>
                            <th>Party Type</th>
                            <th>Contact Information</th>
                            <th>Lawyer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($parties) > 0): ?>
                            <?php foreach ($parties as $party): ?>
                            <tr>
                                <td><?php echo $party['party_name']; ?></td>
                                <td><?php echo ucfirst($party['party_type']); ?></td>
                                <td><?php echo $party['contact_info'] ?: 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $partyLawyers = array_filter($lawyers, function($lawyer) use ($party) {
                                        return $lawyer['party_id'] == $party['party_id'];
                                    });
                                    
                                    if (count($partyLawyers) > 0) {
                                        foreach ($partyLawyers as $lawyer) {
                                            echo $lawyer['lawyer_name'] . ' (Bar #: ' . $lawyer['bar_number'] . ')<br>';
                                        }
                                    } else {
                                        echo 'No lawyer assigned';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No parties found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hearing Timeline -->
    <?php if (hasRole(['court_clerk', 'admin', 'judge'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Hearing Timeline</h6>
        </div>
        <div class="card-body">
            <div id="hearingTimeline">
                <div class="timeline-item">
                    <div class="timeline-item-marker">
                        <div class="timeline-item-marker-text">
                            <?php echo date('M d', strtotime($hearing['created_at'])); ?>
                        </div>
                        <div class="timeline-item-marker-indicator bg-primary"></div>
                    </div>
                    <div class="timeline-item-content">
                        <p class="fw-bold">Hearing Scheduled</p>
                        <p class="text-muted mb-0">
                            Hearing was scheduled by <?php echo $hearing['created_by_name']; ?> on <?php echo formatDateTime($hearing['created_at']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($hearing['status'] !== 'scheduled'): ?>
                <div class="timeline-item">
                    <div class="timeline-item-marker">
                        <div class="timeline-item-marker-text">
                            <?php echo date('M d', strtotime($hearing['updated_at'])); ?>
                        </div>
                        <div class="timeline-item-marker-indicator bg-<?php echo getStatusClass($hearing['status']); ?>"></div>
                    </div>
                    <div class="timeline-item-content">
                        <p class="fw-bold">Status Updated to <?php echo ucfirst($hearing['status']); ?></p>
                        <p class="text-muted mb-0">
                            Hearing status was updated on <?php echo formatDateTime($hearing['updated_at']); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($hearing['status'] === 'scheduled' && strtotime($hearing['hearing_date']) > time()): ?>
                <div class="timeline-item">
                    <div class="timeline-item-marker">
                        <div class="timeline-item-marker-text">
                            <?php echo date('M d', strtotime($hearing['hearing_date'])); ?>
                        </div>
                        <div class="timeline-item-marker-indicator bg-warning"></div>
                    </div>
                    <div class="timeline-item-content">
                        <p class="fw-bold">Upcoming</p>
                        <p class="text-muted mb-0">
                            Hearing is scheduled for <?php echo formatDateTime($hearing['hearing_date']); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions Card -->
    <?php if (hasRole(['court_clerk', 'admin'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Update Hearing Status</h5>
                            <p class="card-text">Change the status of this hearing.</p>
                            <form action="hearing_status_update.php" method="post" class="d-inline">
                                <input type="hidden" name="hearing_id" value="<?php echo $hearingId; ?>">
                                <div class="input-group mb-3">
                                    <select class="form-select" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="scheduled" <?php echo ($hearing['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo ($hearing['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="postponed" <?php echo ($hearing['status'] === 'postponed') ? 'selected' : ''; ?>>Postponed</option>
                                        <option value="cancelled" <?php echo ($hearing['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button class="btn btn-primary" type="submit">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Send Notifications</h5>
                            <p class="card-text">Send notifications about this hearing to relevant parties.</p>
                            <form action="hearing_send_notifications.php" method="post">
                                <input type="hidden" name="hearing_id" value="<?php echo $hearingId; ?>">
                                <div class="mb-3">
                                    <label for="notificationMessage" class="form-label">Message</label>
                                    <textarea class="form-control" id="notificationMessage" name="message" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Send Notifications</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper function to get status class
function getStatusClass($status) {
    $classes = [
        'scheduled' => 'primary',
        'completed' => 'success',
        'postponed' => 'warning',
        'cancelled' => 'danger'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'secondary';
}
?>

<style>
.timeline-item {
    display: flex;
    position: relative;
    padding-bottom: 1rem;
    border-left: 2px solid #dee2e6;
    padding-left: 2.5rem;
}

.timeline-item:last-child {
    border-left-color: transparent;
}

.timeline-item-marker {
    position: absolute;
    left: -0.75rem;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.timeline-item-marker-text {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
    width: 4rem;
    text-align: center;
    position: relative;
    left: -1.25rem;
}

.timeline-item-marker-indicator {
    width: 1rem;
    height: 1rem;
    border-radius: 100%;
    background-color: #dee2e6;
}

.timeline-item-content {
    padding-top: 0;
    padding-bottom: 1.5rem;
    padding-left: 1rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
