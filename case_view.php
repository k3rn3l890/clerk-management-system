<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view cases
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('cases.php');
}

$caseId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get user's role
$userRole = $_SESSION['role'];

// Check if the user has access to this case
$hasAccess = false;

if ($userRole === 'admin') {
    // Admins have access to all cases
    $hasAccess = true;
} elseif ($userRole === 'court_clerk') {
    // Court clerks only have access to cases they created
    $db->query("SELECT COUNT(*) as count FROM cases WHERE case_id = :case_id AND created_by = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'judge') {
    // Judges have access to cases assigned to them
    $db->query("SELECT COUNT(*) as count FROM cases WHERE case_id = :case_id AND assigned_judge = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'lawyer') {
    // Lawyers have access to cases they're associated with
    $db->query("SELECT COUNT(*) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE c.case_id = :case_id AND l.user_id = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'litigant') {
    // Litigants have access to cases they're a party to
    $db->query("SELECT COUNT(*) as count 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE c.case_id = :case_id AND cp.user_id = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
}

if (!$hasAccess) {
    setFlashMessage('You do not have permission to view this case.', 'danger');
    redirect('cases.php');
}

// Get case details
$db->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name, 
            CONCAT(j.first_name, ' ', j.last_name) as judge_name 
            FROM cases c 
            LEFT JOIN users u ON c.created_by = u.user_id 
            LEFT JOIN users j ON c.assigned_judge = j.user_id 
            WHERE c.case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('cases.php');
}

// Get case parties
$db->query("SELECT * FROM case_parties WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$parties = $db->resultSet();

try {
    // Try the new query with party_lawyers table first
    $db->query("SELECT pl.id as party_lawyer_id, pl.lawyer_id, pl.external_lawyer_id, pl.case_party_id, 
                l.bar_number, 
                CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                el.lawyer_name as external_lawyer_name,
                el.bar_number as external_lawyer_bar,
                el.law_firm,
                cp.party_name, 
                cp.party_type 
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                LEFT JOIN external_lawyers el ON pl.external_lawyer_id = el.external_lawyer_id
                JOIN case_parties cp ON pl.case_party_id = cp.party_id 
                WHERE cp.case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $lawyers = $db->resultSet();
} catch (PDOException $e) {
    // Fallback to query without external_lawyers join (works even if that table is missing)
    $db->query("SELECT pl.id as party_lawyer_id, pl.lawyer_id, pl.external_lawyer_id, pl.case_party_id, 
                l.bar_number, 
                CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                NULL as external_lawyer_name,
                NULL as external_lawyer_bar,
                NULL as law_firm,
                cp.party_name, 
                cp.party_type 
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                JOIN case_parties cp ON pl.case_party_id = cp.party_id 
                WHERE cp.case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $lawyers = $db->resultSet();
}

// Get case hearings
$db->query("SELECT * FROM hearings WHERE case_id = :case_id ORDER BY hearing_date DESC");
$db->bind(':case_id', $caseId);
$hearings = $db->resultSet();

// Get case documents
$db->query("SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
            FROM documents d 
            JOIN users u ON d.uploaded_by = u.user_id 
            WHERE d.case_id = :case_id 
            ORDER BY d.created_at DESC");
$db->bind(':case_id', $caseId);
$documents = $db->resultSet();

// Get case payments
$db->query("SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as received_by_name 
            FROM payments p 
            JOIN users u ON p.received_by = u.user_id 
            WHERE p.case_id = :case_id 
            ORDER BY p.payment_date DESC");
$db->bind(':case_id', $caseId);
$payments = $db->resultSet();

// Calculate total payments
$totalPayments = 0;
foreach ($payments as $payment) {
    $totalPayments += $payment['amount'];
}

// Load existing public access tokens for this case (for clerks/admins)
$tokens = [];
if (hasRole(['court_clerk', 'admin'])) {
    // Ensure table exists (safe to call repeatedly)
    ensureCaseAccessTokensTable();
    $db->query("SELECT id, token, created_by, created_at FROM case_access_tokens WHERE case_id = :case_id ORDER BY created_at DESC");
    $db->bind(':case_id', $caseId);
    $tokens = $db->resultSet();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Case Details: <?php echo $case['case_number']; ?>
        </h1>
        <div>
            <a href="cases.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Cases
            </a>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="case_edit.php?id=<?php echo $caseId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit Case
            </a>
            <a href="case_status.php?id=<?php echo $caseId; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-exchange-alt"></i> Change Status
            </a>
            <button class="btn btn-info btn-sm btn-print">
                <i class="fas fa-print"></i> Print
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (hasRole(['court_clerk', 'judge', 'lawyer', 'admin'])): ?>
    <?php
        // Build timeline events for internal view
        $timelineEvents = [];

        // Filing
        if (!empty($case['filing_date'])) {
            $timelineEvents[] = [
                'datetime' => $case['filing_date'],
                'title' => 'Case Filed',
                'description' => 'Case was filed with case number ' . htmlspecialchars($case['case_number']),
                'type' => 'filing'
            ];
        }

        // Record created
        if (!empty($case['created_at']) && $case['created_at'] !== $case['filing_date']) {
            $timelineEvents[] = [
                'datetime' => $case['created_at'],
                'title' => 'Case Record Created',
                'description' => 'Record created in the system',
                'type' => 'system'
            ];
        }

        // Status history (optional)
        try {
            $db->query("SELECT old_status, new_status, reason, remarks, changed_at FROM case_status_history WHERE case_id = :cid ORDER BY changed_at ASC");
            $db->bind(':cid', $caseId);
            $statusHistory = $db->resultSet();
            foreach ($statusHistory as $row) {
                $descParts = [];
                if (!empty($row['reason'])) { $descParts[] = 'Reason: ' . htmlspecialchars($row['reason']); }
                if (!empty($row['remarks'])) { $descParts[] = 'Remarks: ' . htmlspecialchars($row['remarks']); }
                $timelineEvents[] = [
                    'datetime' => $row['changed_at'],
                    'title' => 'Status Changed: ' . ucfirst($row['new_status']),
                    'description' => implode(' | ', $descParts),
                    'type' => 'status'
                ];
            }
        } catch (Throwable $e) {
            // Ignore if table doesn't exist
        }

        // Hearings
        if (!empty($hearings)) {
            foreach ($hearings as $h) {
                $timelineEvents[] = [
                    'datetime' => $h['hearing_date'],
                    'title' => 'Hearing: ' . htmlspecialchars($h['hearing_type']),
                    'description' => 'Status: ' . strip_tags(getHearingStatusLabel($h['status'])) . (empty($h['location']) ? '' : ' | Location: ' . htmlspecialchars($h['location'])),
                    'type' => 'hearing'
                ];
            }
        }

        // Documents
        if (!empty($documents)) {
            foreach ($documents as $d) {
                $timelineEvents[] = [
                    'datetime' => $d['created_at'],
                    'title' => 'Document Uploaded',
                    'description' => htmlspecialchars($d['document_title']) . ' (' . htmlspecialchars($d['document_type']) . ') by ' . htmlspecialchars($d['uploaded_by_name']),
                    'type' => 'document'
                ];
            }
        }

        // Sort chronologically
        usort($timelineEvents, function($a, $b) {
            return strtotime($a['datetime']) <=> strtotime($b['datetime']);
        });
    ?>

    <?php if (!empty($timelineEvents)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Case Progress</h6>
        </div>
        <div class="card-body">
            <style>
                .timeline { position: relative; margin-left: 1rem; padding-left: 1rem; }
                .timeline:before { content: ""; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: #e0e0e0; }
                .timeline-item { position: relative; margin-bottom: 1rem; }
                .timeline-item .dot { position: absolute; left: -2px; top: 6px; width: 12px; height: 12px; border-radius: 50%; background: #0d6efd; border: 2px solid #fff; box-shadow: 0 0 0 2px #0d6efd22; }
                .timeline-item .content { margin-left: 1.5rem; }
                .timeline-item .time { font-size: .85rem; color: #6c757d; }
                .badge-type { font-size: .7rem; }
            </style>
            <div class="timeline">
                <?php foreach ($timelineEvents as $ev): ?>
                <?php
                    $badgeClass = 'bg-secondary';
                    if ($ev['type'] === 'filing') $badgeClass = 'bg-primary';
                    elseif ($ev['type'] === 'status') $badgeClass = 'bg-info';
                    elseif ($ev['type'] === 'hearing') $badgeClass = 'bg-warning text-dark';
                    elseif ($ev['type'] === 'document') $badgeClass = 'bg-success';
                    elseif ($ev['type'] === 'system') $badgeClass = 'bg-dark';
                ?>
                <div class="timeline-item">
                    <span class="dot"></span>
                    <div class="content">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge badge-type <?php echo $badgeClass; ?> me-2 text-uppercase"><?php echo ucfirst($ev['type']); ?></span>
                            <strong><?php echo $ev['title']; ?></strong>
                        </div>
                        <div class="time"><?php echo formatDateTime($ev['datetime']); ?></div>
                        <?php if (!empty($ev['description'])): ?>
                            <div><?php echo $ev['description']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (hasRole(['court_clerk', 'admin'])): ?>
    <!-- Public Access Tokens Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Public Access Tokens</h6>
            <form class="d-inline" method="post" action="generate_case_token.php">
                <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-key"></i> Generate 10-char Token
                </button>
            </form>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Share any of the tokens below with litigants to allow read-only public access to this case details. Tokens are reusable.</p>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Public Link</th>
                            <th>Created By</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tokens)): ?>
                            <?php foreach ($tokens as $t): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($t['token']); ?></code></td>
                                <td>
                                    <?php $link = getBaseUrl() . '/case_public.php?token=' . urlencode($t['token']); ?>
                                    <a href="<?php echo $link; ?>" target="_blank"><?php echo htmlspecialchars($link); ?></a>
                                </td>
                                <td><?php echo $t['created_by'] ? htmlspecialchars(getUserFullName($t['created_by'])) : 'System'; ?></td>
                                <td><?php echo formatDateTime($t['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No tokens generated yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Case Information Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Case Information</h6>
            <span class="badge bg-<?php echo getStatusClass($case['status']); ?> px-3 py-2">
                <?php echo ucfirst($case['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Case Number:</th>
                            <td><?php echo $case['case_number']; ?></td>
                        </tr>
                        <tr>
                            <th>Title:</th>
                            <td><?php echo $case['case_title']; ?></td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td><?php echo ucfirst($case['case_type']); ?></td>
                        </tr>
                        <tr>
                            <th>Filing Date:</th>
                            <td><?php echo formatDate($case['filing_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td><?php echo nl2br($case['description']); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Assigned Judge:</th>
                            <td><?php echo $case['judge_name'] ?: 'Not Assigned'; ?></td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td><?php echo $case['created_by_name']; ?></td>
                        </tr>
                        <tr>
                            <th>Created On:</th>
                            <td><?php echo formatDateTime($case['created_at']); ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?php echo formatDateTime($case['updated_at']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Case Parties Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
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
                                        return $lawyer['case_party_id'] == $party['party_id'];
                                    });
                                    
                                    if (count($partyLawyers) > 0) {
                                        foreach ($partyLawyers as $lawyer) {
                                            // Display registered lawyer
                                            if (!empty($lawyer['lawyer_name'])) {
                                                echo $lawyer['lawyer_name'] . ' (Bar #: ' . $lawyer['bar_number'] . ')<br>';
                                            } 
                                            // Display external lawyer
                                            elseif (!empty($lawyer['external_lawyer_name'])) {
                                                echo $lawyer['external_lawyer_name'] . 
                                                     ' (Bar #: ' . ($lawyer['external_lawyer_bar'] ?? 'N/A') . ')' .
                                                     (!empty($lawyer['law_firm']) ? ' - ' . $lawyer['law_firm'] : '') .
                                                     '<br>';
                                            }
                                        }
                                    } else {
                                        echo 'No lawyer assigned';
                                    }
                                    
                                    if (hasRole(['court_clerk', 'admin'])): ?>
                                        <div class="mt-2">
                                            <a href="party_lawyer_assign.php?case_id=<?php echo $caseId; ?>&party_id=<?php echo $party['party_id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-user-plus"></i> Assign Lawyer
                                            </a>
                                        </div>
                                    <?php endif; ?>
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

    <!-- Hearings Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Hearings</h6>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="hearing_add.php?case_id=<?php echo $caseId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Schedule Hearing
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($hearings) > 0): ?>
                            <?php foreach ($hearings as $hearing): ?>
                            <tr>
                                <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                <td><?php echo $hearing['hearing_type']; ?></td>
                                <td><?php echo $hearing['location']; ?></td>
                                <td><?php echo getHearingStatusLabel($hearing['status']); ?></td>
                                <td><?php echo $hearing['notes'] ?: 'N/A'; ?></td>
                                <td>
                                    <a href="hearing_view.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Hearing">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole(['court_clerk', 'admin'])): ?>
                                    <a href="hearing_edit.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Edit Hearing">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No hearings scheduled.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Documents Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
            <?php if (hasRole(['court_clerk', 'admin', 'lawyer'])): ?>
            <a href="document_upload.php?case_id=<?php echo $caseId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-upload"></i> Upload Document
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Uploaded By</th>
                            <th>Date Uploaded</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($documents) > 0): ?>
                            <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo $document['document_title']; ?></td>
                                <td><?php echo $document['document_type']; ?></td>
                                <td><?php echo $document['uploaded_by_name']; ?></td>
                                <td><?php echo formatDateTime($document['created_at']); ?></td>
                                <td><?php echo formatFileSize($document['file_size']); ?></td>
                                <td>
                                    <a href="document_view.php?id=<?php echo $document['document_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Document">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if (hasRole(['court_clerk', 'admin']) || $document['uploaded_by'] == $_SESSION['user_id']): ?>
                                    <a href="document_delete.php?id=<?php echo $document['document_id']; ?>" class="btn btn-danger btn-sm confirm-delete" data-bs-toggle="tooltip" title="Delete Document">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No documents found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payments Card -->
    <?php if (hasRole(['court_clerk', 'admin'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Payments</h6>
            <a href="payment_add.php?case_id=<?php echo $caseId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Payment
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Receipt Number</th>
                            <th>Received By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                                <td>GH₵ <?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo $payment['receipt_number']; ?></td>
                                <td><?php echo $payment['received_by_name']; ?></td>
                                <td>
                                    <a href="payment_view.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Payment">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="payment_receipt.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-success btn-sm" data-bs-toggle="tooltip" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="payment_edit.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Edit Payment">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-primary">
                                <th colspan="1">Total</th>
                                <th>GH₵ <?php echo number_format($totalPayments, 2); ?></th>
                                <td colspan="5"></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No payments recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper function to get status class
function getStatusClass($status) {
    $classes = [
        'pending' => 'warning',
        'active' => 'primary',
        'closed' => 'success',
        'appealed' => 'info',
        'archived' => 'secondary'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'dark';
}
?>

<?php require_once 'includes/footer.php'; ?>
