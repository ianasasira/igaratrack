# Teacher Attendance & Performance Tracking System

A secure, responsive web application for tracking teacher attendance using PHP, MySQL, HTML, CSS, JavaScript, and WebAuthn biometric authentication.

## Features

### 🔐 Biometric Authentication (WebAuthn)
- Fingerprint authentication using WebAuthn (FIDO2)
- Secure credential storage (no raw fingerprint data)
- Platform authenticator support (smartphone fingerprint scanners)

### 👨‍🏫 Teacher Features
- Clock-in/Clock-out using fingerprint scanner
- Real-time attendance status
- View today's attendance records
- Mobile-first responsive design

### 👨‍💼 Admin Features
- Teacher management (CRUD operations)
- Biometric credential registration
- Lesson timetable management
- Public holiday management
- Comprehensive analytics dashboard
- Performance metrics and reports
- Data visualizations (Chart.js)

### 📊 Attendance Rules
- **Clock-In Status:**
  - Early: ≥ 1 hour before lesson start
  - On Time: At lesson start time
  - Late: ≤ 30 minutes after lesson start
  - Very Late: > 30 minutes after lesson start
  - Absent: No clock-in

- **Clock-Out Status:**
  - On Time: At lesson end time
  - Late: After lesson end time
  - Absent: No clock-out

- **Lesson Attendance:**
  - Present: Both clock-in and clock-out exist
  - Absent/Missed: Missing clock-in or clock-out

### 📅 Holiday Management
- Public holidays exclude lessons from attendance evaluation
- Support for date ranges
- Recurring yearly holidays

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- HTTPS (mandatory for WebAuthn in production)
- Modern web browser with WebAuthn support

## Installation

### 1. Database Setup

```bash
# Import the database schema
mysql -u root -p < database/schema.sql
```

Or manually:
1. Create a database named `teacher_attendance`
2. Import `database/schema.sql`

### 2. Configuration

Edit `config/config.php` and `config/database.php`:

```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teacher_attendance');

// config/config.php
define('BASE_URL', 'http://localhost/igaratrack2.0');
define('RP_ID', 'localhost'); // Change to your domain in production
```

### 3. Web Server Setup

#### Apache (.htaccess)
Ensure mod_rewrite is enabled and create `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
```

#### Nginx
Configure your server block to point to the project directory.

### 4. HTTPS Setup (Required for Production)

WebAuthn requires HTTPS. Set up SSL certificate:
- Use Let's Encrypt for free SSL
- Or configure your hosting provider's SSL

### 5. Default Admin Credentials

**Username:** `admin`  
**Password:** `admin123`

**⚠️ IMPORTANT:** Change the default password immediately after first login!

To change password:
```sql
UPDATE admins SET password_hash = PASSWORD('your_new_password') WHERE username = 'admin';
```

Or use PHP:
```php
password_hash('your_new_password', PASSWORD_DEFAULT)
```

### 6. Cron Job Setup (Optional)

Set up a daily cron job to pre-generate attendance logs:

```bash
# Add to crontab (runs daily at 12:00 AM)
0 0 * * * /usr/bin/php /path/to/project/cron/generate_attendance_logs.php
```

## Usage

### Admin Access

1. Navigate to `/admin/login.php`
2. Login with admin credentials
3. Manage teachers, timetables, and holidays
4. Register teacher biometrics
5. View analytics and reports

### Teacher Attendance

1. Navigate to `/attendance.php`
2. Select your name from the list
3. Click "Clock In" or "Clock Out"
4. Use your fingerprint scanner when prompted
5. View today's attendance records

## Project Structure

```
igaratrack2.0/
├── admin/              # Admin interface
│   ├── login.php
│   ├── dashboard.php
│   ├── teachers.php
│   ├── timetables.php
│   ├── holidays.php
│   └── analytics.php
├── api/                # API endpoints
│   ├── webauthn_*.php
│   ├── clock_in.php
│   ├── clock_out.php
│   └── get_today_attendance.php
├── assets/
│   └── css/
│       └── style.css
├── config/             # Configuration files
│   ├── config.php
│   └── database.php
├── cron/               # Scheduled tasks
│   └── generate_attendance_logs.php
├── database/           # Database schema
│   └── schema.sql
├── includes/           # Shared PHP files
│   ├── functions.php
│   ├── webauthn.php
│   └── admin_header.php
├── attendance.php      # Teacher attendance page
├── index.php          # Home page
└── README.md
```

## Security Features

- ✅ Password hashing (bcrypt)
- ✅ Prepared statements (SQL injection prevention)
- ✅ Session security
- ✅ Input sanitization
- ✅ WebAuthn (no raw biometric data storage)
- ✅ Audit logging
- ✅ Role-based access control

## Timezone

System timezone is set to **Africa/Kampala** (can be changed to Africa/Nairobi in `config/config.php`).

## Browser Compatibility

WebAuthn is supported in:
- Chrome 67+
- Firefox 60+
- Safari 13+
- Edge 18+

Mobile browsers with fingerprint support:
- iOS Safari 13+
- Chrome Android 67+
- Samsung Internet 7.2+

## Troubleshooting

### WebAuthn Not Working
- Ensure HTTPS is enabled (required for WebAuthn)
- Check browser compatibility
- Verify device has fingerprint scanner
- Check browser console for errors

### Database Connection Issues
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database exists and schema is imported

### Session Issues
- Check PHP session configuration
- Verify session directory is writable
- Clear browser cookies

## Development Notes

### WebAuthn Implementation

The current implementation uses a simplified WebAuthn verification. For production, consider:
- Using a proper WebAuthn library (e.g., `web-auth/webauthn-lib`)
- Implementing proper CBOR/COSE parsing
- Adding credential backup/export functionality

### Future Enhancements

- Email notifications
- SMS reminders
- Advanced reporting
- Export to PDF/Excel
- Mobile app
- Multi-language support

## License

This project is provided as-is for educational and commercial use.

## Support

For issues or questions, please refer to the project documentation or contact the development team.

---

**Note:** This system requires HTTPS for WebAuthn to function properly. Always use HTTPS in production environments.

# igaratrack
