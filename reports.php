<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view reports
if (!hasRole(['court_clerk', 'admin', 'judge'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Get report type if provided
$reportType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Initialize report data
$reportData = [];
$reportTitle = '';
$chartData = null;

// Generate report based on type
if (!empty($reportType)) {
    switch ($reportType) {
        case 'case_status':
            $reportTitle = 'Cases by Status';
            
            $db->query("SELECT status, COUNT(*) as count 
                        FROM cases 
                        GROUP BY status 
                        ORDER BY count DESC");
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            $backgroundColor = [
                'pending' => '#ffc107',    // Warning
                'active' => '#0d6efd',     // Primary
                'closed' => '#198754',     // Success
                'appealed' => '#0dcaf0',   // Info
                'archived' => '#6c757d'    // Secondary
            ];
            $colors = [];
            
            foreach ($reportData as $row) {
                $labels[] = ucfirst($row['status']);
                $data[] = $row['count'];
                $colors[] = isset($backgroundColor[$row['status']]) ? $backgroundColor[$row['status']] : '#000000';
            }
            
            $chartData = [
                'type' => 'pie',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'data' => $data,
                            'backgroundColor' => $colors
                        ]
                    ]
                ]
            ];
            break;
            
        case 'case_type':
            $reportTitle = 'Cases by Type';
            
            $db->query("SELECT case_type, COUNT(*) as count 
                        FROM cases 
                        GROUP BY case_type 
                        ORDER BY count DESC");
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            
            foreach ($reportData as $row) {
                $labels[] = ucfirst($row['case_type']);
                $data[] = $row['count'];
            }
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Number of Cases',
                            'data' => $data,
                            'backgroundColor' => '#0d6efd'
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true
                        ]
                    ]
                ]
            ];
            break;
            
        case 'hearings':
            $reportTitle = 'Hearings by Status';
            
            $db->query("SELECT status, COUNT(*) as count 
                        FROM hearings 
                        GROUP BY status 
                        ORDER BY count DESC");
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            $backgroundColor = [
                'scheduled' => '#0d6efd',  // Primary
                'completed' => '#198754',  // Success
                'postponed' => '#ffc107',  // Warning
                'cancelled' => '#dc3545'   // Danger
            ];
            $colors = [];
            
            foreach ($reportData as $row) {
                $labels[] = ucfirst($row['status']);
                $data[] = $row['count'];
                $colors[] = isset($backgroundColor[$row['status']]) ? $backgroundColor[$row['status']] : '#000000';
            }
            
            $chartData = [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'data' => $data,
                            'backgroundColor' => $colors
                        ]
                    ]
                ]
            ];
            break;
            
        case 'hearings_monthly':
            $reportTitle = 'Hearings by Month';
            
            $db->query("SELECT DATE_FORMAT(hearing_date, '%Y-%m') as month, 
                        COUNT(*) as count 
                        FROM hearings 
                        WHERE hearing_date BETWEEN :start_date AND :end_date 
                        GROUP BY month 
                        ORDER BY month");
            $db->bind(':start_date', $startDate);
            $db->bind(':end_date', $endDate);
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            
            foreach ($reportData as $row) {
                $labels[] = date('M Y', strtotime($row['month'] . '-01'));
                $data[] = $row['count'];
            }
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Number of Hearings',
                            'data' => $data,
                            'borderColor' => '#0d6efd',
                            'backgroundColor' => 'rgba(13, 110, 253, 0.1)',
                            'fill' => true
                        ]
                    ]
                ]
            ];
            break;
            
        case 'cases_monthly':
            $reportTitle = 'Cases Filed by Month';
            
            $db->query("SELECT DATE_FORMAT(filing_date, '%Y-%m') as month, 
                        COUNT(*) as count 
                        FROM cases 
                        WHERE filing_date BETWEEN :start_date AND :end_date 
                        GROUP BY month 
                        ORDER BY month");
            $db->bind(':start_date', $startDate);
            $db->bind(':end_date', $endDate);
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            
            foreach ($reportData as $row) {
                $labels[] = date('M Y', strtotime($row['month'] . '-01'));
                $data[] = $row['count'];
            }
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Number of Cases Filed',
                            'data' => $data,
                            'borderColor' => '#198754',
                            'backgroundColor' => 'rgba(25, 135, 84, 0.1)',
                            'fill' => true
                        ]
                    ]
                ]
            ];
            break;
            
        case 'judge_caseload':
            $reportTitle = 'Judge Caseload';
            
            $db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as judge_name, 
                        COUNT(c.case_id) as case_count 
                        FROM users u 
                        LEFT JOIN cases c ON u.user_id = c.assigned_judge 
                        WHERE u.role = 'judge' 
                        GROUP BY u.user_id 
                        ORDER BY case_count DESC");
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            
            foreach ($reportData as $row) {
                $labels[] = $row['judge_name'];
                $data[] = $row['case_count'];
            }
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Number of Cases',
                            'data' => $data,
                            'backgroundColor' => '#0d6efd'
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true
                        ]
                    ]
                ]
            ];
            break;
            
        case 'case_duration':
            $reportTitle = 'Average Case Duration (Days)';
            
            $db->query("SELECT case_type, 
                        AVG(DATEDIFF(IFNULL(updated_at, CURRENT_DATE), filing_date)) as avg_duration 
                        FROM cases 
                        WHERE status = 'closed' 
                        GROUP BY case_type 
                        ORDER BY avg_duration DESC");
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            
            foreach ($reportData as $row) {
                $labels[] = ucfirst($row['case_type']);
                $data[] = round($row['avg_duration'], 1);
            }
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Average Days',
                            'data' => $data,
                            'backgroundColor' => '#dc3545'
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true
                        ]
                    ]
                ]
            ];
            break;
            
        case 'payments':
            $reportTitle = 'Payments by Type';
            
            $db->query("SELECT payment_type, SUM(amount) as total_amount 
                        FROM payments 
                        WHERE payment_date BETWEEN :start_date AND :end_date 
                        GROUP BY payment_type 
                        ORDER BY total_amount DESC");
            $db->bind(':start_date', $startDate);
            $db->bind(':end_date', $endDate);
            $reportData = $db->resultSet();
            
            // Prepare chart data
            $labels = [];
            $data = [];
            $backgroundColor = [
                'filing_fee' => '#0d6efd',  // Primary
                'fine' => '#dc3545',        // Danger
                'other_fee' => '#6c757d'    // Secondary
            ];
            $colors = [];
            
            foreach ($reportData as $row) {
                $labels[] = ucwords(str_replace('_', ' ', $row['payment_type']));
                $data[] = $row['total_amount'];
                $colors[] = isset($backgroundColor[$row['payment_type']]) ? $backgroundColor[$row['payment_type']] : '#000000';
            }
            
            $chartData = [
                'type' => 'pie',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'data' => $data,
                            'backgroundColor' => $colors
                        ]
                    ]
                ]
            ];
            break;
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Reports and Analytics</h1>
    
    <!-- Report Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Generate Report</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="reportType" class="form-label">Report Type</label>
                    <select class="form-select" id="reportType" name="type" required>
                        <option value="">Select Report Type</option>
                        <option value="case_status" <?php echo ($reportType === 'case_status') ? 'selected' : ''; ?>>Cases by Status</option>
                        <option value="case_type" <?php echo ($reportType === 'case_type') ? 'selected' : ''; ?>>Cases by Type</option>
                        <option value="hearings" <?php echo ($reportType === 'hearings') ? 'selected' : ''; ?>>Hearings by Status</option>
                        <option value="hearings_monthly" <?php echo ($reportType === 'hearings_monthly') ? 'selected' : ''; ?>>Hearings by Month</option>
                        <option value="cases_monthly" <?php echo ($reportType === 'cases_monthly') ? 'selected' : ''; ?>>Cases Filed by Month</option>
                        <option value="judge_caseload" <?php echo ($reportType === 'judge_caseload') ? 'selected' : ''; ?>>Judge Caseload</option>
                        <option value="case_duration" <?php echo ($reportType === 'case_duration') ? 'selected' : ''; ?>>Average Case Duration</option>
                        <?php if (hasRole(['admin', 'court_clerk'])): ?>
                        <option value="payments" <?php echo ($reportType === 'payments') ? 'selected' : ''; ?>>Payments by Type</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3 date-range-fields" <?php echo (!in_array($reportType, ['hearings_monthly', 'cases_monthly', 'payments'])) ? 'style="display:none;"' : ''; ?>>
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                
                <div class="col-md-3 date-range-fields" <?php echo (!in_array($reportType, ['hearings_monthly', 'cases_monthly', 'payments'])) ? 'style="display:none;"' : ''; ?>>
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (!empty($reportType) && !empty($reportData)): ?>
    <!-- Report Results -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $reportTitle; ?></h6>
            <div>
                <button class="btn btn-sm btn-info btn-print">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="report_export.php?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <?php
                                    // Generate table headers based on report type
                                    switch ($reportType) {
                                        case 'case_status':
                                            echo '<th>Status</th><th>Number of Cases</th><th>Percentage</th>';
                                            break;
                                        case 'case_type':
                                            echo '<th>Case Type</th><th>Number of Cases</th><th>Percentage</th>';
                                            break;
                                        case 'hearings':
                                            echo '<th>Status</th><th>Number of Hearings</th><th>Percentage</th>';
                                            break;
                                        case 'hearings_monthly':
                                        case 'cases_monthly':
                                            echo '<th>Month</th><th>Count</th>';
                                            break;
                                        case 'judge_caseload':
                                            echo '<th>Judge</th><th>Number of Cases</th>';
                                            break;
                                        case 'case_duration':
                                            echo '<th>Case Type</th><th>Average Duration (Days)</th>';
                                            break;
                                        case 'payments':
                                            echo '<th>Payment Type</th><th>Total Amount (GH₵)</th><th>Percentage</th>';
                                            break;
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Calculate total for percentage
                                $total = 0;
                                if (in_array($reportType, ['case_status', 'case_type', 'hearings', 'payments'])) {
                                    foreach ($reportData as $row) {
                                        $total += $row['count'] ?? $row['total_amount'] ?? 0;
                                    }
                                }
                                
                                // Generate table rows based on report type
                                foreach ($reportData as $row):
                                    switch ($reportType) {
                                        case 'case_status':
                                            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
                                            echo '<tr>';
                                            echo '<td>' . ucfirst($row['status']) . '</td>';
                                            echo '<td>' . $row['count'] . '</td>';
                                            echo '<td>' . $percentage . '%</td>';
                                            echo '</tr>';
                                            break;
                                        case 'case_type':
                                            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
                                            echo '<tr>';
                                            echo '<td>' . ucfirst($row['case_type']) . '</td>';
                                            echo '<td>' . $row['count'] . '</td>';
                                            echo '<td>' . $percentage . '%</td>';
                                            echo '</tr>';
                                            break;
                                        case 'hearings':
                                            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
                                            echo '<tr>';
                                            echo '<td>' . ucfirst($row['status']) . '</td>';
                                            echo '<td>' . $row['count'] . '</td>';
                                            echo '<td>' . $percentage . '%</td>';
                                            echo '</tr>';
                                            break;
                                        case 'hearings_monthly':
                                            echo '<tr>';
                                            echo '<td>' . date('F Y', strtotime($row['month'] . '-01')) . '</td>';
                                            echo '<td>' . $row['count'] . '</td>';
                                            echo '</tr>';
                                            break;
                                        case 'cases_monthly':
                                            echo '<tr>';
                                            echo '<td>' . date('F Y', strtotime($row['month'] . '-01')) . '</td>';
                                            echo '<td>' . $row['count'] . '</td>';
                                            echo '</tr>';
                                            break;
                                        case 'judge_caseload':
                                            echo '<tr>';
                                            echo '<td>' . $row['judge_name'] . '</td>';
                                            echo '<td>' . $row['case_count'] . '</td>';
                                            echo '</tr>';
                                            break;
                                        case 'case_duration':
                                            echo '<tr>';
                                            echo '<td>' . ucfirst($row['case_type']) . '</td>';
                                            echo '<td>' . round($row['avg_duration'], 1) . '</td>';
                                            echo '</tr>';
                                            break;
                                        case 'payments':
                                            $percentage = ($total > 0) ? round(($row['total_amount'] / $total) * 100, 1) : 0;
                                            echo '<tr>';
                                            echo '<td>' . ucwords(str_replace('_', ' ', $row['payment_type'])) . '</td>';
                                            echo '<td>' . number_format($row['total_amount'], 2) . '</td>';
                                            echo '<td>' . $percentage . '%</td>';
                                            echo '</tr>';
                                            break;
                                    }
                                endforeach;
                                
                                // Add total row if applicable
                                if (in_array($reportType, ['case_status', 'case_type', 'hearings', 'payments'])):
                                ?>
                                <tr class="table-primary">
                                    <th>Total</th>
                                    <th><?php echo number_format($total, ($reportType === 'payments' ? 2 : 0)); ?></th>
                                    <th>100%</th>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container" style="position: relative; height:400px;">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show/hide date range fields based on report type
        const reportTypeSelect = document.getElementById('reportType');
        const dateRangeFields = document.querySelectorAll('.date-range-fields');
        
        reportTypeSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            const needsDateRange = ['hearings_monthly', 'cases_monthly', 'payments'].includes(selectedValue);
            
            dateRangeFields.forEach(field => {
                field.style.display = needsDateRange ? 'block' : 'none';
            });
        });
        
        // Initialize chart if data is available
        <?php if ($chartData): ?>
        const ctx = document.getElementById('reportChart').getContext('2d');
        const chartConfig = <?php echo json_encode($chartData); ?>;
        new Chart(ctx, chartConfig);
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/footer.php'; ?>
