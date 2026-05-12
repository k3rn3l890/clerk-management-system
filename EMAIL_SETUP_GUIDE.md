# Email Setup Guide

This guide will help you configure the email functionality for the Ghana Court Clerk Management System.

## Configuration Steps

### 1. Update Email Configuration

Edit the `config/config.php` file and update the following email settings:

```php
// Email configuration
define('MAIL_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('MAIL_PORT', 587); // Change to your SMTP port
define('MAIL_USERNAME', ''); // Change to your email
define('MAIL_PASSWORD', ''); // Change to your email password/app password
define('MAIL_FROM_EMAIL', ''); // Change to your email
define('MAIL_FROM_NAME', 'Ghana Court Clerk Management System');
define('MAIL_ENCRYPTION', 'tls'); // or 'ssl'
define('MAIL_ENABLED', true); // Set to false to disable email functionality
```

### 2. Gmail Setup (Recommended)

If using Gmail, follow these steps:

1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate an App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a new app password for "Mail"
   - Use this app password in `MAIL_PASSWORD`

3. **Update Configuration**:
   ```php
   define('MAIL_HOST', 'smtp.gmail.com');
   define('MAIL_PORT', 587);
   define('MAIL_USERNAME', 'your-gmail@gmail.com');
   define('MAIL_PASSWORD', 'your-16-character-app-password');
   define('MAIL_FROM_EMAIL', 'your-gmail@gmail.com');
   define('MAIL_ENCRYPTION', 'tls');
   ```

### 3. Other Email Providers

#### Outlook/Hotmail
```php
define('MAIL_HOST', 'smtp-mail.outlook.com');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls');
```

#### Yahoo Mail
```php
define('MAIL_HOST', 'smtp.mail.yahoo.com');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls');
```

#### Custom SMTP Server
```php
define('MAIL_HOST', 'your-smtp-server.com');
define('MAIL_PORT', 587); // or 465 for SSL
define('MAIL_ENCRYPTION', 'tls'); // or 'ssl'
```

### 4. Testing Email Functionality

1. **Enable Email**: Ensure `MAIL_ENABLED` is set to `true`
2. **Test User Creation**: Create a new user through the system
3. **Check Email**: The new user should receive a welcome email
4. **Check Logs**: Check server error logs for any email-related errors

### 5. Troubleshooting

#### Common Issues:

1. **Authentication Failed**:
   - Verify username and password are correct
   - For Gmail, ensure you're using an App Password, not your regular password
   - Check if 2FA is enabled

2. **Connection Timeout**:
   - Verify SMTP host and port are correct
   - Check firewall settings
   - Ensure your hosting provider allows SMTP connections

3. **Emails Not Sending**:
   - Check `MAIL_ENABLED` is set to `true`
   - Verify email addresses are valid
   - Check server error logs
   - Test with a simple email first

#### Debug Mode:

To enable email debugging, add this to your `config/config.php`:

```php
// Email debugging
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/error.log');
```

### 6. Email Templates

The system includes three email templates:

1. **Welcome Email**: Sent when a new user is created
2. **Password Reset Email**: Sent when a user requests password reset
3. **Notification Email**: Sent for system notifications

All templates are HTML-formatted and include:
- Professional styling
- System branding
- Security notices
- Responsive design

### 7. Security Considerations

1. **Never commit email credentials** to version control
2. **Use environment variables** for production
3. **Enable SSL/TLS** for email transmission
4. **Regularly rotate** email passwords
5. **Monitor email logs** for suspicious activity

### 8. Production Deployment

For production environments:

1. **Use environment variables**:
   ```php
   define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
   define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);
   ```

2. **Use a dedicated email service** like SendGrid, Mailgun, or Amazon SES

3. **Implement email queuing** for high-volume systems

4. **Set up email monitoring** and alerts

## Support

If you encounter issues with email setup:

1. Check server error logs
2. Verify SMTP settings with your email provider
3. Test with a simple PHP mail script
4. Contact your hosting provider for SMTP support






