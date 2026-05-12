<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Environment Check</h1>";

// Check PHP version
$phpVersion = phpversion();
echo "<h2>PHP Version: $phpVersion</h2>";

// Check required extensions
$requiredExtensions = [
    'pdo_mysql',
    'gd',
    'mbstring',
    'fileinfo',
    'openssl',
    'json',
    'session'
];

echo "<h2>Required Extensions</h2><ul>";
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    echo sprintf(
        '<li>%s: %s</li>',
        $ext,
        $loaded ? '<span style="color:green">✓ Loaded</span>' : '<span style="color:red">✗ Missing</span>'
    );
}
echo "</ul>";

// Check directory permissions
$dirsToCheck = [
    'uploads/',
    'uploads/watermarked/',
    'includes/',
    'assets/'
];

echo "<h2>Directory Permissions</h2><ul>";
foreach ($dirsToCheck as $dir) {
    $exists = file_exists($dir);
    $writable = is_writable($dir);
    $readable = is_readable($dir);
    
    echo "<li>";
    echo "<strong>$dir</strong>: ";
    echo $exists ? 'Exists' : '<span style="color:red">Does not exist</span>';
    
    if ($exists) {
        echo " | ";
        echo $readable ? 'Readable' : '<span style="color:red">Not readable</span>';
        echo " | ";
        echo $writable ? 'Writable' : '<span style="color:red">Not writable</span>';
    }
    
    echo "</li>";
}
echo "</ul>";

// Check if we can create files
echo "<h2>File Creation Test</h2>";
$testFile = 'test_write.txt';
$testContent = 'Test content ' . date('Y-m-d H:i:s');

if (file_put_contents($testFile, $testContent) !== false) {
    echo "<p style='color:green'>✓ Successfully created test file: $testFile</p>";
    
    // Try to read it back
    $readContent = file_get_contents($testFile);
    if ($readContent === $testContent) {
        echo "<p style='color:green'>✓ Successfully read from test file</p>";
    } else {
        echo "<p style='color:orange'>⚠ Could not verify file contents</p>";
    }
    
    // Clean up
    if (unlink($testFile)) {
        echo "<p style='color:green'>✓ Cleaned up test file</p>";
    } else {
        echo "<p style='color:orange'>⚠ Could not delete test file</p>";
    }
} else {
    echo "<p style='color:red'>✗ Failed to create test file. Check directory permissions.</p>";
}

// Check if we can use exec()
echo "<h2>System Command Execution</h2>";
if (function_exists('exec')) {
    echo "<p>exec() is available</p>";
    
    // Try a simple command
    $output = [];
    $return_var = 0;
    @exec('echo test', $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p style='color:green'>✓ Command execution test passed</p>";
    } else {
        echo "<p style='color:orange'>⚠ Command execution returned non-zero status: $return_var</p>";
    }
} else {
    echo "<p style='color:orange'>⚠ exec() function is disabled</p>";
}

// Check memory limit
echo "<h2>PHP Configuration</h2>";
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<ul>";
echo "<li>Memory Limit: $memoryLimit</li>";
echo "<li>Max Execution Time: $maxExecutionTime seconds</li>";
echo "<li>Upload Max Filesize: $uploadMaxFilesize</li>";
echo "<li>POST Max Size: $postMaxSize</li>";
echo "</ul>";

// Check if we can load TCPDF and FPDI
echo "<h2>PDF Libraries</h2>";

$tcpdfPath = 'vendor/tecnickcom/tcpdf/tcpdf.php';
$fpdiPath = 'vendor/setasign/fpdi/src/autoload.php';

if (file_exists($tcpdfPath) && file_exists($fpdiPath)) {
    echo "<p>TCPDF and FPDI files exist</p>";
    
    try {
        require_once $tcpdfPath;
        require_once $fpdiPath;
        
        if (class_exists('TCPDF') && class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
            echo "<p style='color:green'>✓ TCPDF and FPDI classes loaded successfully</p>";
            
            // Test creating a simple PDF
            try {
                $pdf = new TCPDF();
                $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 10, 'Test PDF', 0, 1);
                
                $testPdfPath = 'test_pdf.pdf';
                $pdf->Output($testPdfPath, 'F');
                
                if (file_exists($testPdfPath)) {
                    echo "<p style='color:green'>✓ Successfully created test PDF: <a href='$testPdfPath' target='_blank'>View PDF</a> (" . filesize($testPdfPath) . " bytes)</p>";
                    // Clean up
                    @unlink($testPdfPath);
                } else {
                    echo "<p style='color:red'>✗ Failed to create test PDF</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>✗ Error creating test PDF: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p style='color:red'>✗ Failed to load TCPDF or FPDI classes</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Error loading PDF libraries: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ TCPDF or FPDI files not found</p>";
    echo "<p>Install them using Composer:</p>";
    echo "<pre>composer require setasign/fpdi
composer require tecnickcom/tcpdf</pre>";
}

// Display PHP info if requested
if (isset($_GET['phpinfo'])) {
    echo "<h2>PHP Info</h2>";
    phpinfo();
} else {
    echo "<p><a href='?phpinfo=1'>Show PHP Info</a></p>";
}
?>
