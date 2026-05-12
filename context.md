# Ghana Court Clerk Management System

## Overview

The Ghana Court Clerk Management System is a comprehensive web-based application designed to digitize and streamline court case management processes within the Ghanaian judicial system. This system serves various stakeholders including court clerks, judges, lawyers, litigants, and administrators, providing role-based access to different functionalities.

## System Architecture

### Technology Stack
- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: 
  - HTML5
  - CSS3 (Bootstrap 5)
  - JavaScript
- **Libraries/Frameworks**:
  - jQuery 3.6.0
  - Bootstrap 5.3.0
  - Font Awesome 6.0.0
  - DataTables 1.13.1

### Directory Structure
- `/assets`: Contains CSS, JavaScript, and image files
  - `/css`: Custom stylesheets for the application
  - `/js`: Custom JavaScript files
  - `/images`: System images including the Ghana Coat of Arms
- `/config`: Configuration files
- `/database`: Database schema and SQL files
- `/includes`: Reusable PHP components
  - `/views`: Modular view components
- `/uploads`: Storage for user-uploaded documents
  - `/documents`: Case-related document files
  - `/profile_pictures`: User profile images

## Database Schema

The system uses a relational database named `court_management` with the following key tables:

1. **users**: Stores user account information and credentials
2. **cases**: Contains court case details including case number, title, type, and status
3. **case_parties**: Records information about parties involved in cases
4. **lawyers**: Stores lawyer-specific information linked to user accounts
5. **case_lawyers**: Maps relationships between cases, lawyers, and parties
6. **documents**: Manages document metadata and file paths
7. **hearings**: Records scheduled court hearings and their status
8. **notifications**: System notifications for users
9. **messages**: Internal communication between system users
10. **payments**: Case-related financial transactions
11. **audit_logs**: System activity tracking for accountability
12. **settings**: Application configuration parameters

## User Roles & Permissions

The system implements a comprehensive role-based access control model with the following roles:

1. **Admin**:
   - Full system access
   - User management
   - System configuration
   - Report generation

2. **IT Admin**:
   - Technical system management
   - Database maintenance
   - Access to audit logs

3. **Court Clerk**:
   - Case creation and management
   - Document management
   - Hearing scheduling
   - Payment recording

4. **Judge**:
   - Case review
   - Hearing management
   - Document access
   - Case status updates

5. **Lawyer**:
   - Access to assigned cases
   - Document submission
   - Hearing schedule viewing
   - Client communication

6. **Litigant**:
   - Limited access to their own cases
   - View case status
   - Access to public documents
   - View hearing schedules

## Core Features & Functionalities

### Authentication System
- Secure user login with password hashing (bcrypt)
- Session management
- Password reset functionality
- Account status management (active/inactive/suspended)

### User Management
- User registration and profile management
- Role assignment
- Status toggling (activate/deactivate users)
- Profile picture uploading

### Case Management
- Case creation with unique case numbers
- Case categorization by type (civil, criminal, family, commercial, other)
- Case status tracking (pending, active, closed, appealed, archived)
- Assignment of judges to cases
- Association of parties and lawyers
- Case filtering and searching capabilities

### Party & Lawyer Management
- Registration of case parties (plaintiff, defendant, appellant, respondent)
- Lawyer assignment to parties
- Internal system lawyers and external lawyers tracking
- Bar number and law firm recording

### Document Management
- Secure document uploading with validation
- Document categorization
- Access control (public/private documents)
- Document download functionality
- Document tracking system
- Multiple file format support

### Hearing Management
- Court session scheduling
- Hearing type categorization
- Location recording
- Status tracking (scheduled, completed, postponed, cancelled)
- Notifications for upcoming hearings

### Payment System
- Recording of case-related payments
- Multiple payment types (filing fees, fines, other fees)
- Receipt generation
- Payment method tracking
- Financial reporting

### Communication System
- Internal messaging between system users
- WhatsApp-style chat interface
- Notification system for important events
- Email notifications

### Reporting & Analytics
- Case statistics by type and status
- User activity reporting
- Hearing schedules
- Export functionality (likely PDF/Excel)
- Custom report generation

### Audit & Security
- Comprehensive action logging
- User activity tracking
- IP address recording
- Record of data changes (old/new values)
- Secure access controls

### System Configuration
- Customizable system name
- Court information settings
- Email configuration
- Case numbering settings
- Maintenance mode toggle

## Technical Implementation Details

### Authentication & Session Management
The system implements session-based authentication managed through PHP sessions, with login credentials verified against the database. Password security is ensured through bcrypt hashing. Session variables store user information for persistent authentication across page requests.

### Database Interaction
Database operations are abstracted through a custom PDO wrapper class (`Database` in `config/database.php`) that provides:
- Prepared statements for security
- Transaction support
- Error handling
- Query execution methods
- Result set retrieval

### Security Measures
- Input sanitization via the `sanitize()` function
- Prepared statements to prevent SQL injection
- Password hashing for stored credentials
- Role-based access control for protected resources
- XSS protection through `htmlspecialchars()`
- CSRF protection (likely through tokens)
- Authorization checks before performing actions

### Helper Functions
The system includes numerous utility functions in `includes/functions.php`:
- Date formatting
- User information retrieval
- Notification handling
- File operations
- Audit logging
- Navigation and URL handling

### User Interface
- Responsive Bootstrap-based layout
- DataTables for interactive data display
- Modal dialogs for confirmations
- Form validation (both client and server-side)
- Flash messages for user feedback
- Navigation sidebar with role-based menu items

### Document Handling
Documents are securely stored in the file system with:
- Unique filename generation
- File type validation
- Size restrictions
- Metadata storage in the database
- Access control based on user roles and case relationships

## Key Files and Their Functionalities

### Configuration Files
- `config/config.php`: Defines system constants including database credentials
- `config/database.php`: Database connection and query execution class

### Core Functionality Files
- `includes/functions.php`: Core utility functions used throughout the application
- `includes/header.php`: Common header with navigation menu
- `includes/footer.php`: Common footer with JavaScript includes

### Authentication Files
- `login.php`: User authentication interface
- `logout.php`: Session destruction and logout handling
- `forgot_password.php`: Password recovery initiation
- `reset_password.php`: Password reset functionality

### Dashboard and Main Pages
- `index.php`: Landing/welcome page
- `dashboard.php`: User-specific dashboard with relevant statistics and quick access
- `profile.php`: User profile management

### Case Management
- `cases.php`: Case listing and filtering
- `case_add.php`: Case creation interface
- `case_edit.php`: Case information updating
- `case_view.php`: Detailed case information display
- `case_delete.php`: Case removal functionality
- `case_status.php`: Status update interface
- `case_track.php`: Case tracking functionality

### Hearing Management
- `hearings.php`: Hearing listing
- `hearing_add.php`: Hearing scheduling interface
- `hearing_edit.php`: Hearing information updating
- `hearing_view.php`: Detailed hearing information

### Document Management
- `documents.php`: Document listing
- `document_upload.php`: Document submission interface
- `document_view.php`: Document viewing
- `document_download.php`: Secure document retrieval
- `document_delete.php`: Document removal functionality
- `document_tracking.php`: Document access tracking
- `document_track.php`: Individual document tracking

### User Management
- `users.php`: User listing and management
- `user_add.php`: User creation interface
- `user_edit.php`: User information updating
- `user_view.php`: Detailed user information
- `user_status.php`: User status toggling

### Party and Lawyer Management
- `external_lawyers.php`: External lawyer listing
- `external_lawyer_add.php`: External lawyer registration
- `external_lawyer_edit.php`: External lawyer information updating
- `party_lawyer_assign.php`: Association of lawyers with parties

### Communication
- `messages.php`: Message listing and management
- `message_view.php`: Message display interface
- `chat.php`: Interactive messaging interface
- `notifications.php`: Notification center

### System Administration
- `settings.php`: System configuration interface
- `audit_logs.php`: Activity log viewer
- `reports.php`: Report generation interface
- `report_export.php`: Report export functionality

### Database Setup and Maintenance
- `database/court_db.sql`: Complete database schema creation script
- Various files for checking and adding database columns/tables

## Conclusion

The Ghana Court Clerk Management System is a comprehensive and robust application designed to digitize court processes, improve efficiency, and enhance access to justice. It implements a secure, role-based approach to case management with features spanning document handling, scheduling, communication, and reporting. The system architecture follows standard PHP practices with a focus on security, usability, and maintainability.

The modular design allows for future expansion, and the comprehensive audit logging ensures accountability throughout all system operations. The implementation of various user roles reflects the hierarchical structure of the court system while providing appropriate access controls to sensitive case information. 