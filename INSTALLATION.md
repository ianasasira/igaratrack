# Installation Guide

## Quick Start

### Step 1: Database Setup

1. Open phpMyAdmin or MySQL command line
2. Create a new database named `teacher_attendance`
3. Import the schema file:
   ```sql
   source database/schema.sql
   ```
   Or use phpMyAdmin's import feature to import `database/schema.sql`

### Step 2: Configure Database Connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'teacher_attendance');
```

### Step 3: Configure Application

Edit `config/config.php`:

```php
// Change this to your actual domain/URL
define('BASE_URL', 'http://localhost/igaratrack2.0');

// For production, change to your actual domain
define('RP_ID', 'localhost');  // e.g., 'yourdomain.com'
```

### Step 4: Set Up Web Server

#### For XAMPP (Windows):
1. Place project in `C:\xampp\htdocs\igaratrack2.0`
2. Access via: `http://localhost/igaratrack2.0`

#### For Apache:
1. Place project in your web root
2. Ensure mod_rewrite is enabled
3. Access via your configured domain

#### For Nginx:
Add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Step 5: Set Permissions

Ensure PHP can write to session directory:
```bash
chmod 755 /path/to/project
```

### Step 6: Test Installation

1. Open browser: `http://localhost/igaratrack2.0`
2. Click "Admin Login"
3. Login with:
   - Username: `admin`
   - Password: `admin123`

### Step 7: Change Default Password

After first login, change the admin password:

```sql
UPDATE admins 
SET password_hash = '$2y$10$YourNewHashHere' 
WHERE username = 'admin';
```

Generate hash using PHP:
```php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
```

## HTTPS Setup (Required for WebAuthn)

### Using XAMPP with Self-Signed Certificate:

1. Generate SSL certificate (for development only):
   ```bash
   # Windows (using OpenSSL)
   openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout localhost.key -out localhost.crt
   ```

2. Configure Apache in `httpd-ssl.conf`:
   ```apache
   <VirtualHost _default_:443>
       DocumentRoot "C:/xampp/htdocs/igaratrack2.0"
       ServerName localhost
       SSLEngine on
       SSLCertificateFile "conf/ssl.crt/localhost.crt"
       SSLCertificateKeyFile "conf/ssl.key/localhost.key"
   </VirtualHost>
   ```

3. Access via: `https://localhost/igaratrack2.0`

### Production HTTPS:

Use Let's Encrypt or your hosting provider's SSL certificate.

## Cron Job Setup (Optional)

### Windows (Task Scheduler):

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 12:00 AM
4. Action: Start a program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `C:\xampp\htdocs\igaratrack2.0\cron\generate_attendance_logs.php`

### Linux (Crontab):

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at midnight)
0 0 * * * /usr/bin/php /path/to/project/cron/generate_attendance_logs.php
```

## Troubleshooting

### "Database connection failed"
- Check MySQL service is running
- Verify database credentials in `config/database.php`
- Ensure database exists

### "WebAuthn not supported"
- Ensure HTTPS is enabled
- Check browser compatibility (Chrome 67+, Firefox 60+, Safari 13+)
- Verify device has fingerprint scanner

### "Session not working"
- Check PHP session directory is writable
- Verify session configuration in `php.ini`
- Clear browser cookies

### "404 Not Found"
- Check `.htaccess` file exists
- Verify mod_rewrite is enabled (Apache)
- Check file permissions

## Next Steps

1. Add teachers via Admin → Teachers
2. Register biometrics for each teacher
3. Set up lesson timetables
4. Add public holidays
5. Test clock-in/clock-out functionality

## Support

For issues, check:
- PHP error logs
- Apache/Nginx error logs
- Browser console (F12)
- Database connection status

