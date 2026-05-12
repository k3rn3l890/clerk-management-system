<?php
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to export reports
if (!hasRole(['court_clerk', 'admin', 'judge'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if report type is provided
if (!isset($_GET['type']) || empty($_GET['type'])) {
    setFlashMessage('Invalid report type.', 'danger');
    redirect('reports.php');
}

$reportType = sanitize($_GET['type']);
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Initialize database
$db = new Database();

// Initialize report data
$reportData = [];
$reportTitle = '';
$headers = [];

// Generate report based on type
switch ($reportType) {
    case 'case_status':
        $reportTitle = 'Cases by Status';
        $headers = ['Status', 'Number of Cases', 'Percentage'];
        
        $db->query("SELECT status, COUNT(*) as count 
                    FROM cases 
                    GROUP BY status 
                    ORDER BY count DESC");
        $reportData = $db->resultSet();
        
        // Calculate total for percentage
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['count'];
        }
        
        // Add percentage to each row
        foreach ($reportData as &$row) {
            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
            $row['percentage'] = $percentage . '%';
            $row['status'] = ucfirst($row['status']);
        }
        
        // Add total row
        $reportData[] = [
            'status' => 'Total',
            'count' => $total,
            'percentage' => '100%'
        ];
        break;
        
    case 'case_type':
        $reportTitle = 'Cases by Type';
        $headers = ['Case Type', 'Number of Cases', 'Percentage'];
        
        $db->query("SELECT case_type, COUNT(*) as count 
                    FROM cases 
                    GROUP BY case_type 
                    ORDER BY count DESC");
        $reportData = $db->resultSet();
        
        // Calculate total for percentage
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['count'];
        }
        
        // Add percentage to each row
        foreach ($reportData as &$row) {
            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
            $row['percentage'] = $percentage . '%';
            $row['case_type'] = ucfirst($row['case_type']);
        }
        
        // Add total row
        $reportData[] = [
            'case_type' => 'Total',
            'count' => $total,
            'percentage' => '100%'
        ];
        break;
        
    case 'hearings':
        $reportTitle = 'Hearings by Status';
        $headers = ['Status', 'Number of Hearings', 'Percentage'];
        
        $db->query("SELECT status, COUNT(*) as count 
                    FROM hearings 
                    GROUP BY status 
                    ORDER BY count DESC");
        $reportData = $db->resultSet();
        
        // Calculate total for percentage
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['count'];
        }
        
        // Add percentage to each row
        foreach ($reportData as &$row) {
            $percentage = ($total > 0) ? round(($row['count'] / $total) * 100, 1) : 0;
            $row['percentage'] = $percentage . '%';
            $row['status'] = ucfirst($row['status']);
        }
        
        // Add total row
        $reportData[] = [
            'status' => 'Total',
            'count' => $total,
            'percentage' => '100%'
        ];
        break;
        
    case 'hearings_monthly':
        $reportTitle = 'Hearings by Month (' . $startDate . ' to ' . $endDate . ')';
        $headers = ['Month', 'Number of Hearings'];
        
        $db->query("SELECT DATE_FORMAT(hearing_date, '%Y-%m') as month, 
                    COUNT(*) as count 
                    FROM hearings 
                    WHERE hearing_date BETWEEN :start_date AND :end_date 
                    GROUP BY month 
                    ORDER BY month");
        $db->bind(':start_date', $startDate);
        $db->bind(':end_date', $endDate);
        $reportData = $db->resultSet();
        
        // Format month names
        foreach ($reportData as &$row) {
            $row['month'] = date('F Y', strtotime($row['month'] . '-01'));
        }
        
        // Calculate total
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['count'];
        }
        
        // Add total row
        $reportData[] = [
            'month' => 'Total',
            'count' => $total
        ];
        break;
        
    case 'cases_monthly':
        $reportTitle = 'Cases Filed by Month (' . $startDate . ' to ' . $endDate . ')';
        $headers = ['Month', 'Number of Cases Filed'];
        
        $db->query("SELECT DATE_FORMAT(filing_date, '%Y-%m') as month, 
                    COUNT(*) as count 
                    FROM cases 
                    WHERE filing_date BETWEEN :start_date AND :end_date 
                    GROUP BY month 
                    ORDER BY month");
        $db->bind(':start_date', $startDate);
        $db->bind(':end_date', $endDate);
        $reportData = $db->resultSet();
        
        // Format month names
        foreach ($reportData as &$row) {
            $row['month'] = date('F Y', strtotime($row['month'] . '-01'));
        }
        
        // Calculate total
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['count'];
        }
        
        // Add total row
        $reportData[] = [
            'month' => 'Total',
            'count' => $total
        ];
        break;
        
    case 'judge_caseload':
        $reportTitle = 'Judge Caseload';
        $headers = ['Judge Name', 'Number of Cases'];
        
        $db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as judge_name, 
                    COUNT(c.case_id) as case_count 
                    FROM users u 
                    LEFT JOIN cases c ON u.user_id = c.assigned_judge 
                    WHERE u.role = 'judge' 
                    GROUP BY u.user_id 
                    ORDER BY case_count DESC");
        $reportData = $db->resultSet();
        
        // Calculate total
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['case_count'];
        }
        
        // Add total row
        $reportData[] = [
            'judge_name' => 'Total',
            'case_count' => $total
        ];
        break;
        
    case 'case_duration':
        $reportTitle = 'Average Case Duration (Days)';
        $headers = ['Case Type', 'Average Duration (Days)'];
        
        $db->query("SELECT case_type, 
                    AVG(DATEDIFF(IFNULL(updated_at, CURRENT_DATE), filing_date)) as avg_duration 
                    FROM cases 
                    WHERE status = 'closed' 
                    GROUP BY case_type 
                    ORDER BY avg_duration DESC");
        $reportData = $db->resultSet();
        
        // Format case types and round average duration
        foreach ($reportData as &$row) {
            $row['case_type'] = ucfirst($row['case_type']);
            $row['avg_duration'] = round($row['avg_duration'], 1);
        }
        
        // Calculate overall average
        $db->query("SELECT AVG(DATEDIFF(IFNULL(updated_at, CURRENT_DATE), filing_date)) as overall_avg 
                    FROM cases 
                    WHERE status = 'closed'");
        $overall = $db->single();
        
        // Add overall average row
        $reportData[] = [
            'case_type' => 'Overall Average',
            'avg_duration' => round($overall['overall_avg'], 1)
        ];
        break;
        
    case 'payments':
        $reportTitle = 'Payments by Type (' . $startDate . ' to ' . $endDate . ')';
        $headers = ['Payment Type', 'Total Amount (GH₵)', 'Percentage'];
        
        $db->query("SELECT payment_type, SUM(amount) as total_amount 
                    FROM payments 
                    WHERE payment_date BETWEEN :start_date AND :end_date 
                    GROUP BY payment_type 
                    ORDER BY total_amount DESC");
        $db->bind(':start_date', $startDate);
        $db->bind(':end_date', $endDate);
        $reportData = $db->resultSet();
        
        // Calculate total for percentage
        $total = 0;
        foreach ($reportData as $row) {
            $total += $row['total_amount'];
        }
        
        // Format payment types and add percentage to each row
        foreach ($reportData as &$row) {
            $percentage = ($total > 0) ? round(($row['total_amount'] / $total) * 100, 1) : 0;
            $row['percentage'] = $percentage . '%';
            $row['payment_type'] = ucwords(str_replace('_', ' ', $row['payment_type']));
            $row['total_amount'] = number_format($row['total_amount'], 2);
        }
        
        // Add total row
        $reportData[] = [
            'payment_type' => 'Total',
            'total_amount' => number_format($total, 2),
            'percentage' => '100%'
        ];
        break;
        
    default:
        setFlashMessage('Invalid report type.', 'danger');
        redirect('reports.php');
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $reportTitle . ' - ' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering
ob_start();

// Create Excel file
echo '<table border="1">';
echo '<tr><th colspan="' . count($headers) . '">' . $reportTitle . '</th></tr>';
echo '<tr>';
foreach ($headers as $header) {
    echo '<th>' . $header . '</th>';
}
echo '</tr>';

foreach ($reportData as $row) {
    echo '<tr>';
    foreach ($row as $key => $value) {
        echo '<td>' . $value . '</td>';
    }
    echo '</tr>';
}

echo '</table>';

// End output buffering and send to browser
ob_end_flush();
exit;
?>
