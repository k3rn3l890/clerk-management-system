<?php
require_once 'includes/functions.php';

// Require login and role
if (!isLoggedIn() || !hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('Unauthorized.', 'danger');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('Invalid request method.', 'danger');
    redirect('cases.php');
}

$caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
if ($caseId <= 0) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('cases.php');
}

try {
    $db = new Database();

    // Ensure table exists
    ensureCaseAccessTokensTable();

    // Enforce access: admin any; clerk only cases they created
    if (hasRole(['court_clerk'])) {
        $db->query('SELECT COUNT(*) AS cnt FROM cases WHERE case_id = :cid AND created_by = :uid');
        $db->bind(':cid', $caseId);
        $db->bind(':uid', $_SESSION['user_id']);
        $row = $db->single();
        if (empty($row) || (int)$row['cnt'] === 0) {
            setFlashMessage('You do not have permission to manage this case.', 'danger');
            redirect('case_view.php?id=' . $caseId);
        }
    }

    // Generate random 10-char alphanumeric token
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $token = '';
    for ($i = 0; $i < 10; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }

    // Insert token (ensure uniqueness by retrying on duplicate)
    $maxAttempts = 5;
    $attempt = 0;
    $inserted = false;
    while (!$inserted && $attempt < $maxAttempts) {
        try {
            $db->query('INSERT INTO case_access_tokens(case_id, token, created_by) VALUES (:cid, :tok, :uid)');
            $db->bind(':cid', $caseId);
            $db->bind(':tok', $token);
            $db->bind(':uid', $_SESSION['user_id']);
            $db->execute();
            $inserted = true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                // regenerate and retry
                $token = '';
                for ($i = 0; $i < 10; $i++) {
                    $token .= $chars[random_int(0, strlen($chars) - 1)];
                }
                $attempt++;
            } else {
                throw $e;
            }
        }
    }

    if ($inserted) {
        $publicUrl = getBaseUrl() . '/case_public.php?token=' . urlencode($token);
        setFlashMessage('Token generated: ' . htmlspecialchars($token) . ' — Share this link: ' . htmlspecialchars($publicUrl), 'success');
    } else {
        setFlashMessage('Failed to generate a unique token. Please try again.', 'danger');
    }
} catch (Throwable $ex) {
    error_log('Token generation error: ' . $ex->getMessage());
    setFlashMessage('Error: ' . htmlspecialchars($ex->getMessage()), 'danger');
}

redirect('case_view.php?id=' . $caseId);
