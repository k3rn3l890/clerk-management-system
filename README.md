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

- Implemented an automated installation script (`automated_installer.php`) that handles environment checks, Composer dependency installation (including PDF watermarking libraries like `tecnickcom/tcpdf`, `setasign/fpdi`, and `setasign/fpdi-tcpdf`), database schema creation from `court_db.sql`, execution of various migration and setup scripts (e.g., table creations, data migrations, fixes), and automatic admin user creation.
- Enhanced document tracking feature with geolocation tracking, access logging, and map visualization using Leaflet.js.
- Added secure document viewing with browser-based PDF viewer, annotations, version control, and watermarking.
- Verified the automated installer for syntax and logic to ensure error-free setup on new systems.

## Installation

1. Install XAMPP and ensure PHP is configured properly.
2. Clone this repository to your htdocs folder.
3. Run the automated installer for efficient setup:
   ```
   php automated_installer.php
   ```
   This script will:
   - Check environment
   - Install Composer dependencies
   - Create database schema
   - Run migrations and setup scripts
   - Create admin user if needed
4. Access the system at `http://localhost/clerk_mgmt1`

## Login Credentials

Default admin credentials:
- Username: admin
- Password: admin123

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

----------------------------------------------
Users
admin	
admin123
----------------------------------------------
Judge
cyrus9090
cyrus9090!
----------------------------------------------
<!-- It Admin	
eben123
eben123! -->
----------------------------------------------
<!-- Judge
eddison123
eddison123! -->
----------------------------------------------
Litigant
isaac123
isaac123!
----------------------------------------------
Lawyer
jibril123
jibril123!
----------------------------------------------
Court Clerk	
monica123
monica123!
----------------------------------------------
Court Clerk	
peter123
peter123!
----------------------------------------------
Court Clerk	
sara123
sara123!
----------------------------------------------
LITIGANT
nuamah123
nuamah123!



----------------------------------------------
Admin	
salam123
salam123!
----------------------------------------------