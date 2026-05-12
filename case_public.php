<?php
require_once 'includes/header.php';

// Public page: access via token
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    setFlashMessage('Missing access token.', 'danger');
    redirect('index.php');
}

$db = new Database();

// Ensure tokens table exists
ensureCaseAccessTokensTable();

// Find case by token
$db->query("SELECT c.* FROM case_access_tokens t JOIN cases c ON t.case_id = c.case_id WHERE t.token = :tok");
$db->bind(':tok', $token);
$case = $db->single();

if (!$case) {
    setFlashMessage('Invalid access token.', 'danger');
    redirect('index.php');
}

$caseId = (int)$case['case_id'];

// Load related data (read-only)
// Judge and creator names
$db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as created_by_name, 
                   CONCAT(j.first_name, ' ', j.last_name) as judge_name
            FROM cases c 
            LEFT JOIN users u ON c.created_by = u.user_id 
            LEFT JOIN users j ON c.assigned_judge = j.user_id 
            WHERE c.case_id = :case_id");
$db->bind(':case_id', $caseId);
$names = $db->single();

// Parties
$db->query("SELECT party_id, party_name, party_type, contact_info FROM case_parties WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$parties = $db->resultSet();

// Lawyers per party (internal and external where available)
try {
    $db->query("SELECT pl.id as party_lawyer_id, pl.lawyer_id, pl.external_lawyer_id, pl.case_party_id, 
                l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                el.lawyer_name as external_lawyer_name, el.bar_number as external_lawyer_bar, el.law_firm,
                cp.party_name, cp.party_type 
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                LEFT JOIN external_lawyers el ON pl.external_lawyer_id = el.external_lawyer_id
                JOIN case_parties cp ON pl.case_party_id = cp.party_id 
                WHERE cp.case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $lawyers = $db->resultSet();
} catch (PDOException $e) {
    $db->query("SELECT pl.id as party_lawyer_id, pl.lawyer_id, pl.external_lawyer_id, pl.case_party_id, 
                l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                NULL as external_lawyer_name, NULL as external_lawyer_bar, NULL as law_firm,
                cp.party_name, cp.party_type 
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                JOIN case_parties cp ON pl.case_party_id = cp.party_id 
                WHERE cp.case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $lawyers = $db->resultSet();
}

// Hearings
$db->query("SELECT * FROM hearings WHERE case_id = :case_id ORDER BY hearing_date DESC");
$db->bind(':case_id', $caseId);
$hearings = $db->resultSet();

// Documents (metadata only; viewing may still enforce login in document_view.php)
$db->query("SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
            FROM documents d 
            JOIN users u ON d.uploaded_by = u.user_id 
            WHERE d.case_id = :case_id 
            ORDER BY d.created_at DESC");
$db->bind(':case_id', $caseId);
$documents = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Public Case View: <?php echo htmlspecialchars($case['case_number']); ?></h1>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Home</a>
    </div>

    <div class="alert alert-info">This is a read-only public view provided via an access token. Some actions may be unavailable.</div>

    <?php
    // Build timeline events (public, read-only)
    $timelineEvents = [];

    // Filing event
    if (!empty($case['filing_date'])) {
        $timelineEvents[] = [
            'datetime' => $case['filing_date'],
            'title' => 'Case Filed',
            'description' => 'Case was filed with case number ' . htmlspecialchars($case['case_number']),
            'type' => 'filing'
        ];
    }

    // Created event (if different from filing)
    if (!empty($case['created_at']) && $case['created_at'] !== $case['filing_date']) {
        $timelineEvents[] = [
            'datetime' => $case['created_at'],
            'title' => 'Case Record Created',
            'description' => 'Record created in the system',
            'type' => 'system'
        ];
    }

    // Status history (if table exists)
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
        // Table may not exist in some deployments; ignore gracefully
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

    // Sort by datetime ascending
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
                /* Minimal vertical timeline */
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

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Case Information</h6>
            <?php echo getCaseStatusLabel($case['status']); ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><th width="30%">Case Number:</th><td><?php echo htmlspecialchars($case['case_number']); ?></td></tr>
                        <tr><th>Title:</th><td><?php echo htmlspecialchars($case['case_title']); ?></td></tr>
                        <tr><th>Type:</th><td><?php echo ucfirst(htmlspecialchars($case['case_type'])); ?></td></tr>
                        <tr><th>Filing Date:</th><td><?php echo formatDate($case['filing_date']); ?></td></tr>
                        <tr><th>Description:</th><td><?php echo nl2br(htmlspecialchars($case['description'])); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><th width="30%">Assigned Judge:</th><td><?php echo $names['judge_name'] ?: 'Not Assigned'; ?></td></tr>
                        <tr><th>Created By:</th><td><?php echo $names['created_by_name']; ?></td></tr>
                        <tr><th>Created On:</th><td><?php echo formatDateTime($case['created_at']); ?></td></tr>
                        <tr><th>Last Updated:</th><td><?php echo formatDateTime($case['updated_at']); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
                        <?php if (!empty($parties)): foreach ($parties as $party): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($party['party_name']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($party['party_type'])); ?></td>
                            <td><?php echo htmlspecialchars($party['contact_info'] ?: 'N/A'); ?></td>
                            <td>
                                <?php
                                $partyLawyers = array_filter($lawyers, function($lawyer) use ($party) { return $lawyer['case_party_id'] == $party['party_id']; });
                                if (!empty($partyLawyers)) {
                                    foreach ($partyLawyers as $lawyer) {
                                        if (!empty($lawyer['lawyer_name'])) {
                                            echo htmlspecialchars($lawyer['lawyer_name']) . ' (Bar #: ' . htmlspecialchars($lawyer['bar_number']) . ')<br>';
                                        } elseif (!empty($lawyer['external_lawyer_name'])) {
                                            echo htmlspecialchars($lawyer['external_lawyer_name']) . ' (Bar #: ' . htmlspecialchars($lawyer['external_lawyer_bar'] ?? 'N/A') . ')' . (!empty($lawyer['law_firm']) ? ' - ' . htmlspecialchars($lawyer['law_firm']) : '') . '<br>';
                                        }
                                    }
                                } else {
                                    echo 'No lawyer assigned';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center">No parties found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Hearings</h6>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($hearings)): foreach ($hearings as $hearing): ?>
                        <tr>
                            <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                            <td><?php echo htmlspecialchars($hearing['hearing_type']); ?></td>
                            <td><?php echo htmlspecialchars($hearing['location']); ?></td>
                            <td><?php echo getHearingStatusLabel($hearing['status']); ?></td>
                            <td><?php echo htmlspecialchars($hearing['notes'] ?: 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center">No hearings scheduled.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($documents)): foreach ($documents as $document): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($document['document_title']); ?></td>
                            <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                            <td><?php echo htmlspecialchars($document['uploaded_by_name']); ?></td>
                            <td><?php echo formatDateTime($document['created_at']); ?></td>
                            <td><?php echo formatFileSize($document['file_size']); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center">No documents found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
