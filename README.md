# Ghana Court Clerk Management System

A comprehensive court management system for Ghanaian courts built with HTML, CSS, JavaScript, Bootstrap, PHP, and MySQL.

## Features

### For Internal Court Users

1. **Court Clerks**
   - Case management (create, update, view)
   - Document management
   - Hearing scheduling
   - Electronic notices
   - Fee/fine recording
   - Report generation
   - Internal messaging

2. **Judges**
   - Case file access
   - Court calendar management
   - Secure communication with clerks

3. **Judicial Secretary & Court Administrators**
   - Performance dashboards
   - Report access
   - Notice broadcasting

4. **IT Administrators**
   - System monitoring
   - User account management
   - Security monitoring

### For External Users

1. **Lawyers**
   - E-filing documents
   - Case tracking
   - Notification reception
   - Calendar access
   - Secure communication

2. **Litigants / Public Users**
   - Case status checking
   - Hearing date viewing

## Project Progress

### Completed Features

- **Automated Installation System**: All-in-one installer (`http://localhost/clerk_mgmt1/automated_installer.php`) that handles:
  - Environment checks and requirements validation
  - Composer dependency installation (PDF libraries: `tecnickcom/tcpdf`, `setasign/fpdi`, `setasign/fpdi-tcpdf`)
  - Database schema creation from `court_db.sql`
  - Execution of all migration and setup scripts
  - Admin user creation and password management
  - Comprehensive installation reporting

- **Database Management**: 
  - Fixed database import issues with proper SQL structure
  - Integrated admin password reset functionality
  - Automated database creation and user management

- **Document Management System**:
  - Secure browser-based PDF viewer with PDF.js integration
  - Document version control and audit trails
  - Advanced annotation system (comments, highlights)
  - Secure download with user watermarking
  - Geolocation tracking for document access
  - Access logging and reporting with Leaflet.js map visualization

- **User Authentication & Security**:
  - Integrated password reset system
  - Secure session management
  - Role-based access control
  - Admin credential management

- **System Architecture**:
  - Modular PHP structure with proper separation of concerns
  - Bootstrap 5 responsive design
  - MySQL database with comprehensive schema
  - RESTful API endpoints for key functionalities

### Technical Improvements

- **Enhanced Installer**: Integrated admin password management into automated installer
- **Database Fixes**: Resolved "No database selected" import errors
- **Code Organization**: Removed redundant files, streamlined codebase
- **Documentation**: Comprehensive README with installation and troubleshooting guides
- **Security**: Implemented secure password hashing and access controls

## Quick Start (5 Minutes)

**For experienced users who want to get running quickly:**

1. **Clone and Install**:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/k3rn3l890/clerk-management-system.git clerk_mgmt1
   cd clerk_mgmt1
   php automated_installer.php
   ```

2. **Login**:
   - URL: `http://localhost/clerk_mgmt1/login.php`
   - Username: `admin`
   - Password: `admin123`

That's it! The automated installer handles everything else.

---

## Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- PHP 8.0 or higher
- Composer (included with XAMPP)
- Git

### Step-by-Step Installation

1. **Install XAMPP**
   - Download and install XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
   - Ensure Apache and MySQL services are running

2. **Clone Repository**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/k3rn3l890/clerk-management-system.git clerk_mgmt1
   ```

3. **Database Setup (Optional)**
   - If you prefer manual database setup:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Import the database file: `database/court_db.sql`
   - **Note**: The automated installer handles this automatically

4. **Run Automated Installer**
   ```bash
   cd C:\xampp\htdocs\clerk_mgmt1
   php automated_installer.php
   ```
   
   **The automated installer will:**
   - Check system environment and requirements
   - Install Composer dependencies (PDF libraries, etc.)
   - Create database schema from `court_db.sql`
   - Execute all migration scripts
   - Run setup scripts for additional features
   - Create and configure admin user
   - Reset admin password to ensure access
   - Generate comprehensive installation report

5. **Access the System**
   - **Login URL**: `http://localhost/clerk_mgmt1/login.php`
   - **Default Admin Credentials**:
     - Username: `admin`
     - Password: `admin123`

### Alternative Installation Methods

#### Web-Based Installation
```
http://localhost/clerk_mgmt1/automated_installer.php
```

#### Manual Database Import (if automated installer fails)
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create database: `court_management`
3. Import: `database/court_db.sql`
4. Run installer for remaining setup

### Password Reset Options

If you need to reset admin password after installation:

1. **Re-run Automated Installer** (Recommended):
   ```bash
   php automated_installer.php
   ```

2. **Browser Access**:
   ```
   http://localhost/clerk_mgmt1/automated_installer.php
   ```

Both methods will reset admin credentials to:
- Username: `admin`
- Password: `admin123`

### Manual Admin User Creation

If the automated installer fails or admin login doesn't work, you can manually create an admin user:

#### Method 1: Create Admin Script

1. **Create `create_admin.php`** in your root directory with this content:
```php
<?php
require_once 'config/database.php';

// Database connection
$db = new Database();
$connection = $db->getConnection();

// Delete any existing admin and create new one
$connection->exec("DELETE FROM users WHERE username = 'admin'");

$hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $connection->prepare("INSERT INTO users (username, password, email, first_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute(['admin', $hashedPassword, 'admin@court.gov.gh', 'System', 'Administrator', 'admin', 'active']);

echo "New admin user created successfully!<br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";
echo "<a href='login.php'>Click here to login</a>";
?>
```

2. **Access the script**: `https://yourdomain.com/create_admin.php`

#### Method 2: Direct Database Access

1. **Access phpMyAdmin** in your hosting control panel
2. **Select your database**
3. **Run this SQL query**:
```sql
DELETE FROM users WHERE username = 'admin';
INSERT INTO users (username, password, email, first_name, last_name, role, status) 
VALUES ('admin', '$2y$10$8zf0SXIrLBRUQ5dUJwdw8.XZNm5zSJU0Vd.lOZJj.fRXIXVxjUMmK', 'admin@court.gov.gh', 'System', 'Administrator', 'admin', 'active');
```

**After creating admin user, you can login with:**
- Username: `admin`
- Password: `admin123`

### Standalone Installer (Recommended)

The **standalone_installer.php** is the recommended method for complete system setup. It provides a single-file solution that handles all installation and configuration tasks.

#### Features

- **Single File Installation**: No need to run multiple scripts
- **Professional Interface**: Clean, business-appropriate design
- **Comprehensive Setup**: Handles all database tables, admin user, and system configuration
- **Error Handling**: Smart detection of existing tables and configurations
- **Self-Contained**: No external dependencies that might cause redirects

#### Installation Steps

1. **Access the Installer**:
   ```
   http://localhost/clerk_mgmt1/standalone_installer.php
   ```

2. **Follow the On-Screen Instructions**:
   - Step 1: Environment Check
   - Step 2: Database Configuration
   - Step 3: Core Database Schema
   - Step 4: Document Tracking Tables
   - Step 5: Admin User Management
   - Step 6: System Configuration
   - Step 7: Directory Permissions

3. **Login After Installation**:
   - URL: `http://localhost/clerk_mgmt1/login.php`
   - Username: `admin`
   - Password: `admin123`

#### What the Installer Does

- **Database Setup**: Creates all required tables with proper relationships
- **Document Tracking**: Sets up document versioning, annotations, and access logging
- **Admin User**: Creates or resets admin account with secure password
- **System Settings**: Configures default court system settings
- **Directory Structure**: Creates necessary upload directories
- **Error Recovery**: Handles existing installations gracefully

#### For Production Deployment

1. **Upload** `standalone_installer.php` to your hosting root directory
2. **Access** via your domain: `https://yourdomain.com/standalone_installer.php`
3. **Delete** the installer file after successful installation for security

#### Status Indicators

The installer uses professional status indicators:
- `[OK]` - Successful operation
- `[ERROR]` - Critical error (installation stops)
- `[WARNING]` - Non-critical issue (installation continues)
- `[INFO]` - Informational message

#### Troubleshooting

**Common Issues:**
- **"No database selected" error**: Run standalone installer (it fixes database creation)
- **Login fails**: Re-run standalone installer to reset admin password
- **SQL syntax errors**: Installer handles these automatically by cleaning schema file
- **"Table already exists" warnings**: Normal for re-installations

**Database Connection Issues:**
- Verify MySQL service is running in XAMPP Control Panel
- Check database credentials in `config/config.php`
- Default settings: Host: `localhost`, User: `root`, Password: (empty), Database: `court_management`

**File Permissions:**
- Ensure `uploads/` directory is writable
- Check `vendor/` directory exists after Composer install



## Technology Stack

- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- PHP 8
- MySQL

## Document Viewing System

The document viewing system provides a secure, browser-based solution for viewing, annotating, and controlling access to court documents.

### Features

1. **Browser-Based PDF Viewer**
   - View PDF documents directly in the browser without downloading
   - Built with PDF.js for cross-browser compatibility
   - Zoom controls and page navigation
   - Responsive design for desktop and mobile devices

2. **Document Version Control**
   - Track document versions and changes
   - View document history and previous versions
   - Maintain audit trail of document modifications

3. **Document Annotations**
   - Add, edit, and delete comments on documents
   - Highlight important sections
   - Share annotations with other users

4. **Secure Download with Watermarking**
   - Watermark documents with user information and timestamp
   - Control download permissions
   - Set expiration for download links

5. **Document Access Tracking**
   - Track who viewed documents and when
   - Capture geolocation data for enhanced security
   - Generate access reports for auditing

### Implementation Files

- `document_view_browser.php` - Main document viewing interface
- `pdf_viewer.php` - PDF.js integration for browser-based viewing
- `secure_download.php` - Controlled document downloads with watermarking
- `save_annotation.php` - Save document annotations
- `delete_annotation.php` - Delete document annotations
- `document_upload_version.php` - Upload new document versions
- `get_document_access_history.php` - View document access logs
- `track_document_access.php` - Track document access with geolocation
- `document_version_integration.php` - Configure document control settings
- `document_browser_integration.php` - Update existing document links

### Database Tables

- `document_versions` - Stores document version history
- `document_annotations` - Stores document annotations
- `document_access_logs` - Tracks document access
- `secure_downloads` - Manages secure download links

### Setup Instructions

1. Run the database table creation script:
   ```
   create_document_version_control.php
   ```

2. Configure document control settings:
   ```
   document_version_integration.php
   ```

3. Update existing document links to use the browser-based viewer:
   ```
   document_browser_integration.php
   ```

4. Test the document viewing system:
   ```
   test_document_system.php
   ```

### Security Features

- Watermarking with user information and timestamp
- Geolocation tracking for document access
- Access control based on user roles
- Secure download links with expiration
- Document version control and audit trail

### Configuration Options

- Enable/disable document versioning
- Enable/disable document annotations
- Set default download permissions
- Configure watermark template
- Set download link expiration time

