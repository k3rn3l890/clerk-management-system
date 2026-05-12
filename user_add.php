<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to add users
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $phoneNumber = sanitize($_POST['phone_number']);
    
    // Additional fields for lawyers
    $barNumber = isset($_POST['bar_number']) ? sanitize($_POST['bar_number']) : '';
    $lawFirm = isset($_POST['law_firm']) ? sanitize($_POST['law_firm']) : '';
    $specialization = isset($_POST['specialization']) ? sanitize($_POST['specialization']) : '';
    
    // Validate form data
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $errors[] = 'Username can only contain letters and numbers';
    } else {
        // Check if username already exists
        $db->query("SELECT COUNT(*) as count FROM users WHERE username = :username");
        $db->bind(':username', $username);
        $result = $db->single();
        
        if ($result['count'] > 0) {
            $errors[] = 'Username already exists';
        }
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email already exists
        $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email");
        $db->bind(':email', $email);
        $result = $db->single();
        
        if ($result['count'] > 0) {
            $errors[] = 'Email already exists';
        }
    }
    
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
        $errors[] = 'First name can only contain letters and spaces';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
        $errors[] = 'Last name can only contain letters and spaces';
    }
    
    if (empty($role)) {
        $errors[] = 'Role is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    // Validate phone number if provided
    if (!empty($phoneNumber)) {
        // Remove any non-digit characters for validation
        $cleanPhoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (strlen($cleanPhoneNumber) !== 10) {
            $errors[] = 'Phone number must contain exactly 10 digits';
        } elseif (!preg_match('/^[0-9]{10}$/', $cleanPhoneNumber)) {
            $errors[] = 'Phone number can only contain numbers';
        } else {
            // Update phone number to cleaned version
            $phoneNumber = $cleanPhoneNumber;
        }
    }
    
    // Validate lawyer-specific fields
    if ($role === 'lawyer') {
        if (empty($barNumber)) {
            $errors[] = 'Bar number is required for lawyers';
        } else {
            // Check if bar number already exists
            $db->query("SELECT COUNT(*) as count FROM lawyers WHERE bar_number = :bar_number");
            $db->bind(':bar_number', $barNumber);
            $result = $db->single();
            
            if ($result['count'] > 0) {
                $errors[] = 'Bar number already exists';
            }
        }
    }
    
    // If no errors, proceed with saving the user
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $db->query("INSERT INTO users (username, password, email, first_name, last_name, role, status, phone_number) 
                        VALUES (:username, :password, :email, :first_name, :last_name, :role, :status, :phone_number)");
            
            $db->bind(':username', $username);
            $db->bind(':password', $hashedPassword);
            $db->bind(':email', $email);
            $db->bind(':first_name', $firstName);
            $db->bind(':last_name', $lastName);
            $db->bind(':role', $role);
            $db->bind(':status', 'active');
            $db->bind(':phone_number', $phoneNumber);
            
            $db->execute();
            
            // Get the last inserted user ID
            $userId = $db->lastInsertId();
            
            // If role is lawyer, insert lawyer record
            if ($role === 'lawyer') {
                $db->query("INSERT INTO lawyers (user_id, bar_number, law_firm, specialization) 
                            VALUES (:user_id, :bar_number, :law_firm, :specialization)");
                
                $db->bind(':user_id', $userId);
                $db->bind(':bar_number', $barNumber);
                $db->bind(':law_firm', $lawFirm);
                $db->bind(':specialization', $specialization);
                
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Log action
            logAction('User created', 'users', $userId);
            
            // Send welcome email to the new user
            $emailSent = sendWelcomeEmail($email, $firstName, $lastName, $username, $role);
            
            // Set success message
            if ($emailSent) {
                setFlashMessage('User created successfully and welcome email sent.', 'success');
            } else {
                setFlashMessage('User created successfully, but welcome email could not be sent.', 'warning');
            }
            
            // Redirect to users page
            redirect('users.php');
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            $errors[] = 'An error occurred while creating the user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Add New User</h1>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error!</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="userForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label required-field">Username</label>
                            <input type="text" class="form-control" id="username" name="username" pattern="[a-zA-Z0-9]+" title="Username can only contain letters and numbers" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="firstName" class="form-label required-field">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" pattern="[a-zA-Z\s]+" title="First name can only contain letters and spaces" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="lastName" class="form-label required-field">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" pattern="[a-zA-Z\s]+" title="Last name can only contain letters and spaces" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label required-field">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" toggle="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label required-field">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" toggle="#confirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label required-field">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="court_clerk">Court Clerk</option>
                                <option value="judge">Judge</option>
                                <option value="admin">Administrator</option>
                                <option value="it_admin">IT Administrator</option>
                                <option value="lawyer">Lawyer</option>
                                <option value="litigant">Litigant</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phoneNumber" name="phone_number" pattern="[0-9]{10}" title="Phone number must contain exactly 10 digits" maxlength="10">
                            <div class="form-text">Enter exactly 10 digits (e.g., 1234567890)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Lawyer-specific fields -->
                <div id="lawyerFields" style="display: none;">
                    <hr class="my-4">
                    <h5 class="mb-3">Lawyer Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="barNumber" class="form-label required-field">Bar Number</label>
                                <input type="text" class="form-control" id="barNumber" name="bar_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="lawFirm" class="form-label">Law Firm</label>
                                <input type="text" class="form-control" id="lawFirm" name="law_firm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specialization" class="form-label">Specialization</label>
                        <input type="text" class="form-control" id="specialization" name="specialization">
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-between">
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Show/hide lawyer fields based on role selection
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        const lawyerFields = document.getElementById('lawyerFields');
        
        roleSelect.addEventListener('change', function() {
            if (this.value === 'lawyer') {
                lawyerFields.style.display = 'block';
                document.getElementById('barNumber').setAttribute('required', 'required');
            } else {
                lawyerFields.style.display = 'none';
                document.getElementById('barNumber').removeAttribute('required');
            }
        });
        
        // Validate Username field to only allow alphanumeric characters
        const usernameField = document.getElementById('username');
        
        function validateUsernameField(field) {
            const value = field.value.trim();
            const alphanumericPattern = /^[a-zA-Z0-9]+$/;
            
            if (value && !alphanumericPattern.test(value)) {
                field.setCustomValidity('Username can only contain letters and numbers');
                field.reportValidity();
                return false;
            } else {
                field.setCustomValidity('');
                return true;
            }
        }
        
        // Validate Phone Number field to only allow exactly 10 digits
        const phoneNumberField = document.getElementById('phoneNumber');
        
        function validatePhoneNumberField(field) {
            const value = field.value.trim();
            const digitsOnly = value.replace(/[^0-9]/g, '');
            const phonePattern = /^[0-9]{10}$/;
            
            if (value && digitsOnly.length !== 10) {
                field.setCustomValidity('Phone number must contain exactly 10 digits');
                field.reportValidity();
                return false;
            } else if (value && !phonePattern.test(digitsOnly)) {
                field.setCustomValidity('Phone number can only contain numbers');
                field.reportValidity();
                return false;
            } else {
                field.setCustomValidity('');
                return true;
            }
        }
        
        // Validate First Name and Last Name fields to only allow alphabetic characters
        const firstNameField = document.getElementById('firstName');
        const lastNameField = document.getElementById('lastName');
        
        function validateNameField(field, fieldName) {
            const value = field.value.trim();
            const alphabeticPattern = /^[a-zA-Z\s]+$/;
            
            if (value && !alphabeticPattern.test(value)) {
                field.setCustomValidity(fieldName + ' can only contain letters and spaces');
                field.reportValidity();
                return false;
            } else {
                field.setCustomValidity('');
                return true;
            }
        }
        
        // Add event listeners for real-time validation
        usernameField.addEventListener('input', function() {
            validateUsernameField(this);
        });
        
        phoneNumberField.addEventListener('input', function() {
            validatePhoneNumberField(this);
        });
        
        firstNameField.addEventListener('input', function() {
            validateNameField(this, 'First name');
        });
        
        lastNameField.addEventListener('input', function() {
            validateNameField(this, 'Last name');
        });
        
        // Validate on form submission
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const usernameValid = validateUsernameField(usernameField);
            const phoneNumberValid = validatePhoneNumberField(phoneNumberField);
            const firstNameValid = validateNameField(firstNameField, 'First name');
            const lastNameValid = validateNameField(lastNameField, 'Last name');
            
            if (!usernameValid || !phoneNumberValid || !firstNameValid || !lastNameValid) {
                e.preventDefault();
                return false;
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
