<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Case Details: <?php echo $case['case_number']; ?></h1>
        <div>
            <a href="litigant_cases.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to My Cases
            </a>
        </div>
    </div>

    <!-- Case Information Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Case Information</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Case Number:</th>
                        <td><?php echo $case['case_number']; ?></td>
                    </tr>
                    <tr>
                        <th>Title:</th>
                        <td><?php echo $case['case_title']; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><?php echo ucfirst($case['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Filing Date:</th>
                        <td><?php echo formatDate($case['filing_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Assigned Judge:</th>
                        <td><?php echo $case['judge_name'] ?: 'Not Assigned'; ?></td>
                    </tr>
                </table>
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
                            <th>Lawyer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parties as $party): ?>
                        <tr>
                            <td><?php echo $party['party_name']; ?></td>
                            <td><?php echo ucfirst($party['party_type']); ?></td>
                            <td>
                                <?php
                                $partyLawyers = array_filter($lawyers, function($lawyer) use ($party) {
                                    return $lawyer['party_id'] == $party['party_id'];
                                });
                                
                                if (count($partyLawyers) > 0) {
                                    foreach ($partyLawyers as $lawyer) {
                                        if (!empty($lawyer['lawyer_name'])) {
                                            echo $lawyer['lawyer_name'] . ' (Bar #: ' . $lawyer['bar_number'] . ')<br>';
                                        } else {
                                            echo $lawyer['external_lawyer_name'] . 
                                                 ' (Bar #: ' . ($lawyer['external_lawyer_bar'] ?? 'N/A') . ')' .
                                                 (!empty($lawyer['law_firm']) ? ' - ' . $lawyer['law_firm'] : '') .
                                                 '<br>';
                                        }
                                    }
                                } else {
                                    echo 'No lawyer assigned';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hearings Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($hearings) > 0): ?>
                            <?php foreach ($hearings as $hearing): ?>
                            <tr>
                                <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                <td><?php echo $hearing['hearing_type']; ?></td>
                                <td><?php echo $hearing['location']; ?></td>
                                <td><?php echo ucfirst($hearing['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No hearings scheduled</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (count($documents) > 0): ?>
    <!-- Documents Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Documents</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?php echo $document['document_name']; ?></td>
                            <td><?php echo $document['document_type']; ?></td>
                            <td><?php echo formatDateTime($document['created_at']); ?></td>
                            <td>
                                <a href="download_document.php?id=<?php echo $document['document_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
