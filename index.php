<?php
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Get system settings
$systemName = getSetting('system_name') ?: 'Ghana Court Clerk Management System';
$courtName = getSetting('court_name') ?: 'High Court of Ghana';
$courtAddress = getSetting('court_address') ?: 'Accra, Ghana';
$courtContact = getSetting('court_contact') ?: '+233 XX XXX XXXX';
$courtEmail = getSetting('court_email') ?: 'info@court.gov.gh';
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
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/background.css" rel="stylesheet">
    <link href="assets/css/navbar-logo.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <style>
        /* Landing page specific styles */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/Court-Accra-Law-Complex.jpg');
            background-size: cover;
            background-position: center 30%; /* Moved down from center to 30% from the top */
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
    </style>
</head>
<body class="ghana-background">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span class="navbar-logo"></span><?php echo $systemName; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#tokenAccessModal">
                            Access Case via Token
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Token Access Modal -->
    <div class="modal fade" id="tokenAccessModal" tabindex="-1" aria-labelledby="tokenAccessModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tokenAccessModalLabel"><i class="fas fa-key me-2"></i>Access Case Details via Token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="get" action="case_public.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="publicTokenInput" class="form-label">Enter 10-character token</label>
                            <input type="text" class="form-control" id="publicTokenInput" name="token" placeholder="e.g. A1b2C3d4E5" maxlength="64" pattern="[A-Za-z0-9]{10}" required>
                            <small class="form-text text-muted">Paste the token you received to view case details in read-only mode.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> View Case</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4"><?php echo $courtName; ?></h1>
            <p class="lead mb-5">A comprehensive digital solution for efficient court case management in Ghana</p>
            <a href="login.php" class="btn btn-primary btn-lg px-5 py-3">Access the System</a>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Key Features</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h4>Case Management</h4>
                            <p>Efficiently manage the entire lifecycle of court cases from filing to resolution.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4>Hearing Scheduling</h4>
                            <p>Schedule, track, and manage court hearings with automated notifications.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h4>Document Management</h4>
                            <p>Securely store, organize, and retrieve court documents and case files.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mx-auto text-center">
                    <h2 class="mb-4">Contact Us</h2>
                    <address class="mb-4">
                        <p><i class="fas fa-map-marker-alt me-2"></i><?php echo $courtAddress; ?></p>
                        <p><i class="fas fa-phone me-2"></i><?php echo $courtContact; ?></p>
                        <p><i class="fas fa-envelope me-2"></i><?php echo $courtEmail; ?></p>
                    </address>
                    <a href="login.php" class="btn btn-primary">Login to the System</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-light py-3 mt-5 border-top">
        <div class="container text-center">
            <div class="mb-2">
                <span class="navbar-logo" style="width: 25px; height: 25px; display: inline-block;"></span>
            </div>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo $courtName; ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
