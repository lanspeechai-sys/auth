# SchoolLink Africa

A comprehensive alumni connection platform designed specifically for African high schools. This platform enables schools to maintain lifelong relationships with their graduates while providing tools for community building, networking, and engagement.

## Features

### Core Functionality
- **Multi-role Authentication System** (Super Admin, School Admin, Student/Alumni)
- **School Registration & Verification** with approval workflow
- **Join Request Management** with AJAX-powered approval system
- **Alumni Directory** with search and filtering capabilities
- **School Feed** for announcements, events, and opportunities
- **Profile Management** with photo uploads
- **Real-time Notifications** and status updates

### User Roles

#### Platform Super Admin
- Verify and approve new school registrations
- Manage and suspend schools
- Platform-wide oversight and analytics
- User management across all schools

#### School Administrator
- Register school on the platform
- Manage join requests from students/alumni
- Create and manage school posts and events
- Oversee school community and member directory
- School-specific analytics and reporting

#### Student/Alumni Users
- Search and join their school community
- Connect with classmates and alumni
- Access school updates and announcements
- Participate in events and opportunities
- Maintain updated profiles with achievements

## Technology Stack

- **Backend**: PHP 7.4+ with PDO for database operations
- **Database**: MySQL 5.7+ with UTF-8 support
- **Frontend**: HTML5, CSS3, Bootstrap 5.3
- **JavaScript**: Vanilla JS with AJAX for dynamic interactions
- **Icons**: Bootstrap Icons
- **Security**: Password hashing, CSRF protection, session management

## Installation & Setup

### Prerequisites
- Web server (Apache/Nginx) with PHP 7.4+
- MySQL 5.7+ database server
- PHP extensions: PDO, PDO_MySQL, GD (for image handling)

### Quick Setup (Shared Hosting)

1. **Download and Upload**
   ```bash
   # Upload all files to your hosting public_html directory
   # Ensure directory structure is maintained
   ```

2. **Database Setup**
   ```sql
   # Import the database schema
   mysql -u username -p database_name < database/schoollink_africa.sql
   ```

3. **Configuration**
   ```php
   # Edit config/database.php with your database credentials
   private $host = 'localhost';
   private $db_name = 'your_database_name';
   private $username = 'your_db_username';
   private $password = 'your_db_password';
   ```

4. **File Permissions**
   ```bash
   # Set proper permissions for upload directories
   chmod 755 uploads/
   chmod 755 uploads/logos/
   chmod 755 uploads/profiles/
   ```

### Local Development Setup

1. **Clone or Download Project**
   ```bash
   git clone https://github.com/your-repo/schoollink-africa.git
   cd schoollink-africa
   ```

2. **Start Local Server**
   ```bash
   # Using PHP built-in server
   php -S localhost:8000
   
   # Or use XAMPP/WAMP/MAMP
   # Place files in htdocs/www directory
   ```

3. **Database Setup**
   ```bash
   # Create database and import schema
   mysql -u root -p
   CREATE DATABASE schoollink_africa;
   USE schoollink_africa;
   SOURCE database/schoollink_africa.sql;
   ```

## Default Login Credentials

### Super Administrator
- **Email**: admin@schoollink.africa
- **Password**: admin123
- **Note**: Change this password immediately after first login

### Sample Schools
The database includes sample schools for testing:
- Lagos State Model College
- Government Secondary School Maitama  
- Loyola Jesuit College

## Directory Structure

```
schoollink-africa/
├── admin/                  # Super admin dashboard and management
│   └── dashboard.php      # Main admin interface
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css     # Custom styling
│   ├── js/
│   │   └── main.js       # JavaScript functionality
│   └── images/           # Static images
├── config/
│   └── database.php      # Database connection configuration
├── database/
│   └── schoollink_africa.sql  # Database schema and sample data
├── includes/
│   ├── auth.php          # Authentication functions
│   └── functions.php     # Utility functions
├── school-admin/         # School administrator interface
│   ├── dashboard.php     # School admin dashboard
│   └── join-requests.php # AJAX-powered request management
├── uploads/              # File upload directories
│   ├── logos/            # School logos
│   └── profiles/         # User profile photos
├── user/                 # Student/Alumni interface
│   └── dashboard.php     # User dashboard
├── index.php             # Homepage
├── login.php             # Multi-role login
├── register.php          # Student/Alumni registration
├── register-school.php   # School registration
├── logout.php            # Logout handler
└── pending-approval.php  # Approval waiting page
```

## Configuration

### Database Configuration
Edit `config/database.php`:
```php
private $host = 'localhost';        # Database host
private $db_name = 'schoollink_africa';  # Database name
private $username = 'root';         # Database username  
private $password = '';             # Database password
```

### File Upload Settings
Maximum file sizes and allowed types are configured in `includes/functions.php`:
```php
$max_size = 5242880;  // 5MB limit
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
```

### Security Settings
- Sessions are secured with `session_regenerate_id()`
- CSRF protection is implemented for sensitive operations
- Password hashing uses PHP's `password_hash()` function
- Input sanitization prevents XSS attacks

## Deployment

### Shared Hosting (Hostinger, cPanel, etc.)

1. **Upload Files**
   - Compress the entire project directory
   - Upload via File Manager or FTP
   - Extract in public_html

2. **Database Setup**
   - Create MySQL database via hosting control panel
   - Import `database/schoollink_africa.sql`
   - Update `config/database.php` with new credentials

3. **SSL Certificate**
   - Enable SSL through hosting provider
   - Update any hardcoded HTTP links to HTTPS

4. **Email Configuration** (Optional)
   - Configure SMTP settings for email notifications
   - Update email templates in includes/functions.php

### VPS/Dedicated Server

1. **Web Server Setup**
   ```bash
   # Apache configuration
   sudo apt install apache2 php7.4 php7.4-mysql php7.4-gd
   
   # Enable required modules
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Database Setup**
   ```bash
   sudo apt install mysql-server
   sudo mysql_secure_installation
   ```

3. **Virtual Host**
   ```apache
   <VirtualHost *:80>
       ServerName schoollink.yourdomain.com
       DocumentRoot /var/www/schoollink-africa
       <Directory /var/www/schoollink-africa>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

## Customization

### Branding
- Update logo and colors in `assets/css/style.css`
- Modify homepage content in `index.php`
- Customize email templates in notification functions

### Features
- Add new post types by modifying the posts table enum
- Extend user profiles with additional fields
- Implement messaging system between alumni
- Add event RSVP functionality

## Security Considerations

### Production Checklist
- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Enable HTTPS/SSL
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Remove or secure phpMyAdmin access
- [ ] Enable error logging (disable display_errors)
- [ ] Implement regular database backups
- [ ] Set up monitoring and alerting

### Security Features
- Password strength requirements
- Session timeout management
- SQL injection prevention via prepared statements
- XSS protection through input sanitization
- CSRF token validation
- File upload restrictions and validation

## Support & Maintenance

### Regular Maintenance
- **Database Backups**: Schedule automatic daily backups
- **Log Monitoring**: Review error logs regularly
- **Security Updates**: Keep PHP and MySQL updated
- **Performance**: Monitor server resources and optimize queries

### Troubleshooting
- Check PHP error logs in hosting control panel
- Verify database connection settings
- Ensure upload directories have write permissions
- Confirm PHP version compatibility (7.4+)

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Contact

For support or questions about SchoolLink Africa:
- Email: support@schoollink.africa
- Documentation: [Project Wiki]
- Issues: [GitHub Issues]

---

**Note**: This platform is designed specifically for African educational institutions. All sample data, school names, and locations reflect this focus. The system supports multiple African languages and regional educational systems.