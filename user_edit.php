<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to edit users
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid user ID.', 'danger');
    echo "<script>window.location.href = 'users.php';</script>";
    exit;
}

$userId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get user details
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$user = $db->single();

if (!$user) {
    setFlashMessage('User not found.', 'danger');
    echo "<script>window.location.href = 'users.php';</script>";
    exit;
}

// Get role-specific details
$roleSpecificDetails = [];
// Initialize role specific details as empty array
$roleSpecificDetails = [];

// Check if tables exist before querying
try {
    if ($user['role'] === 'lawyer') {
        // Check if lawyers table exists
        $db->query("SHOW TABLES LIKE 'lawyers'");
        $tableExists = $db->single();
        
        if ($tableExists) {
            $db->query("SELECT * FROM lawyers WHERE user_id = :user_id");
            $db->bind(':user_id', $userId);
            $roleSpecificDetails = $db->single() ?: [];
        }
    } elseif ($user['role'] === 'judge') {
        // Check if judges table exists
        $db->query("SHOW TABLES LIKE 'judges'");
        $tableExists = $db->single();
        
        if ($tableExists) {
            $db->query("SELECT * FROM judges WHERE user_id = :user_id");
            $db->bind(':user_id', $userId);
            $roleSpecificDetails = $db->single() ?: [];
        }
    }
} catch (Exception $e) {
    // If any error occurs, set empty array
    $roleSpecificDetails = [];
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    
    // Validate form data
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email exists for another user
        $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email AND user_id != :user_id");
        $db->bind(':email', $email);
        $db->bind(':user_id', $userId);
        $result = $db->single();
        
        if ($result['count'] > 0) {
            $errors[] = 'Email already exists for another user';
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Get current user data for logging
            $oldUserData = $user;
            
            // Check if phone column exists in users table
            $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
            $phoneColumnExists = $db->single();
            
            // Update user - with or without phone field based on column existence
            if ($phoneColumnExists) {
                $db->query("UPDATE users SET 
                            first_name = :first_name, 
                            last_name = :last_name, 
                            email = :email, 
                            phone = :phone, 
                            role = :role, 
                            updated_at = NOW() 
                            WHERE user_id = :user_id");
                
                $db->bind(':first_name', $firstName);
                $db->bind(':last_name', $lastName);
                $db->bind(':email', $email);
                $db->bind(':phone', $phone);
                $db->bind(':role', $role);
                $db->bind(':user_id', $userId);
            } else {
                // If phone column doesn't exist, update without it
                $db->query("UPDATE users SET 
                            first_name = :first_name, 
                            last_name = :last_name, 
                            email = :email, 
                            role = :role, 
                            updated_at = NOW() 
                            WHERE user_id = :user_id");
                
                $db->bind(':first_name', $firstName);
                $db->bind(':last_name', $lastName);
                $db->bind(':email', $email);
                $db->bind(':role', $role);
                $db->bind(':user_id', $userId);
                
                // Add a note to the errors array (but don't prevent saving)
                $errors[] = 'Phone number could not be saved because the phone column does not exist in the database. Please run the check_phone_column.php script to add this column.';
            }
            
            $db->execute();
            
            // Handle role-specific details
            if ($role === 'lawyer') {
                // Check if lawyers table exists before trying to update it
                $db->query("SHOW TABLES LIKE 'lawyers'");
                $lawyersTableExists = $db->single();
                
                if ($lawyersTableExists) {
                    $barNumber = sanitize($_POST['bar_number'] ?? '');
                    $specialization = sanitize($_POST['specialization'] ?? '');
                    $lawFirm = sanitize($_POST['law_firm'] ?? '');
                    
                    try {
                        // Check if lawyer record exists
                        $db->query("SELECT COUNT(*) as count FROM lawyers WHERE user_id = :user_id");
                        $db->bind(':user_id', $userId);
                        $result = $db->single();
                        
                        if ($result['count'] > 0) {
                            // Update existing lawyer record
                            $db->query("UPDATE lawyers SET 
                                        bar_number = :bar_number, 
                                        specialization = :specialization, 
                                        law_firm = :law_firm 
                                        WHERE user_id = :user_id");
                        } else {
                            // Insert new lawyer record
                            $db->query("INSERT INTO lawyers (user_id, bar_number, specialization, law_firm) 
                                        VALUES (:user_id, :bar_number, :specialization, :law_firm)");
                        }
                        
                        $db->bind(':user_id', $userId);
                        $db->bind(':bar_number', $barNumber);
                        $db->bind(':specialization', $specialization);
                        $db->bind(':law_firm', $lawFirm);
                        $db->execute();
                    } catch (Exception $e) {
                        // Log the error but continue with user update
                        error_log("Error updating lawyer details: " . $e->getMessage());
                    }
                } else {
                    // Add a note to inform admin that lawyer-specific details couldn't be saved
                    $errors[] = 'Lawyer-specific details could not be saved because the lawyers table does not exist. Please run the create_lawyers_table.php script to create this table.';
                }
            } elseif ($role === 'judge') {
                // Check if judges table exists before trying to update it
                $db->query("SHOW TABLES LIKE 'judges'");
                $judgesTableExists = $db->single();
                
                if ($judgesTableExists) {
                    $courtDivision = sanitize($_POST['court_division'] ?? '');
                    $appointmentDate = sanitize($_POST['appointment_date'] ?? '');
                    
                    try {
                        // Check if judge record exists
                        $db->query("SELECT COUNT(*) as count FROM judges WHERE user_id = :user_id");
                        $db->bind(':user_id', $userId);
                        $result = $db->single();
                        
                        if ($result['count'] > 0) {
                            // Update existing judge record
                            $db->query("UPDATE judges SET 
                                        court_division = :court_division, 
                                        appointment_date = :appointment_date 
                                        WHERE user_id = :user_id");
                        } else {
                            // Insert new judge record
                            $db->query("INSERT INTO judges (user_id, court_division, appointment_date) 
                                        VALUES (:user_id, :court_division, :appointment_date)");
                        }
                        
                        $db->bind(':user_id', $userId);
                        $db->bind(':court_division', $courtDivision);
                        $db->bind(':appointment_date', $appointmentDate);
                        $db->execute();
                    } catch (Exception $e) {
                        // Log the error but continue with user update
                        error_log("Error updating judge details: " . $e->getMessage());
                    }
                } else {
                    // Add a note to inform admin that judge-specific details couldn't be saved
                    $errors[] = 'Judge-specific details could not be saved because the judges table does not exist. Please run the create_judges_table.php script to create this table.';
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Log the action
            logAction('User updated', 'users', $userId, json_encode($oldUserData), json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'role' => $role
            ]));
            
            // Update user variable to reflect changes
            $user['first_name'] = $firstName;
            $user['last_name'] = $lastName;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['role'] = $role;
            
            $success = true;
            setFlashMessage('User updated successfully.', 'success');
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            $errors[] = 'An error occurred while updating the user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit User</h1>
        <div>
            <a href="users.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="user_view.php?id=<?php echo $userId; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View User
            </a>
            <?php if ($userId != $_SESSION['user_id']): ?>
            <a href="user_status.php?id=<?php echo $userId; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-user-cog"></i> Change Status
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        User updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $userId); ?>">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="firstName" class="form-label required-field">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="lastName" class="form-label required-field">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                            <small class="form-text text-muted">Username cannot be changed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label required-field">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="it_admin" <?php echo ($user['role'] === 'it_admin') ? 'selected' : ''; ?>>IT Admin</option>
                                <option value="court_clerk" <?php echo ($user['role'] === 'court_clerk') ? 'selected' : ''; ?>>Court Clerk</option>
                                <option value="judge" <?php echo ($user['role'] === 'judge') ? 'selected' : ''; ?>>Judge</option>
                                <option value="lawyer" <?php echo ($user['role'] === 'lawyer') ? 'selected' : ''; ?>>Lawyer</option>
                                <option value="litigant" <?php echo ($user['role'] === 'litigant') ? 'selected' : ''; ?>>Litigant</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Role-specific fields -->
                <div id="lawyerFields" class="role-fields <?php echo ($user['role'] === 'lawyer') ? '' : 'd-none'; ?>">
                    <hr>
                    <h5>Lawyer Details</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="barNumber" class="form-label">Bar Number</label>
                                <input type="text" class="form-control" id="barNumber" name="bar_number" value="<?php echo htmlspecialchars($roleSpecificDetails['bar_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($roleSpecificDetails['specialization'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="lawFirm" class="form-label">Law Firm</label>
                                <input type="text" class="form-control" id="lawFirm" name="law_firm" value="<?php echo htmlspecialchars($roleSpecificDetails['law_firm'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="judgeFields" class="role-fields <?php echo ($user['role'] === 'judge') ? '' : 'd-none'; ?>">
                    <hr>
                    <h5>Judge Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="courtDivision" class="form-label">Court Division</label>
                                <input type="text" class="form-control" id="courtDivision" name="court_division" value="<?php echo htmlspecialchars($roleSpecificDetails['court_division'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointmentDate" class="form-label">Appointment Date</label>
                                <input type="date" class="form-control" id="appointmentDate" name="appointment_date" value="<?php echo htmlspecialchars($roleSpecificDetails['appointment_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle role change to show/hide role-specific fields
    const roleSelect = document.getElementById('role');
    const lawyerFields = document.getElementById('lawyerFields');
    const judgeFields = document.getElementById('judgeFields');
    
    roleSelect.addEventListener('change', function() {
        // Hide all role-specific fields
        document.querySelectorAll('.role-fields').forEach(function(el) {
            el.classList.add('d-none');
        });
        
        // Show fields based on selected role
        if (this.value === 'lawyer') {
            lawyerFields.classList.remove('d-none');
        } else if (this.value === 'judge') {
            judgeFields.classList.remove('d-none');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
