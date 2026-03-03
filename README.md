# LabTrack Pro — Laboratory Inventory Management System

## Setup Instructions (XAMPP/WAMP)

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.4+
- Apache with mod_rewrite enabled

### Installation Steps

1. **Copy files to web root**
   - XAMPP: Copy `labinventory/` folder to `C:/xampp/htdocs/`
   - WAMP: Copy to `C:/wamp64/www/`

2. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create new database named `lab_inventory`
   - Import `sql/schema.sql`
   - This creates all tables + seed data

3. **Configure Database Connection**
   - Edit `config/config.php`
   - Update DB_HOST, DB_NAME, DB_USER, DB_PASS if needed
   - Default: localhost / lab_inventory / root / (empty)

4. **Access the Application**
   - Open: http://localhost/labinventory
   - Default login credentials (password: `password`):
     - Admin:      admin / password
     - Manager:    labmanager / password
     - Staff:      staff1 / password

5. **MSDS File Uploads**
   - Ensure `assets/uploads/msds/` directory is writable
   - chmod 755 assets/uploads/ (Linux/Mac)

### Folder Structure
```
labinventory/
├── ajax/               # AJAX endpoints
├── assets/
│   ├── css/app.css    # Main stylesheet
│   ├── js/app.js      # Main JavaScript
│   ├── images/        # Icons & images
│   └── uploads/msds/  # Uploaded MSDS files
├── config/
│   ├── config.php     # App configuration
│   ├── database.php   # PDO database class
│   └── session.php    # Session manager
├── includes/
│   ├── functions.php  # Utility functions
│   ├── header.php     # Page header & nav
│   └── footer.php     # Page footer
├── modules/           # Reserved for future modules
├── sql/
│   └── schema.sql     # Database schema + seed data
├── views/
│   └── item_modal.php # Add/edit item modal
├── index.php          # Redirects to dashboard
├── login.php          # Authentication
├── logout.php         # Session destroy
├── dashboard.php      # Main dashboard
├── inventory.php      # Inventory management
├── movements.php      # Stock movements
├── alerts.php         # Alert notifications
├── reports.php        # Reports & exports
├── users.php          # User management (admin)
└── audit.php          # Audit log (admin)
```

### Default User Roles
- **Admin**: Full access — inventory, users, audit log, reports
- **Lab Manager**: Add/edit/delete items, view reports
- **Staff**: View-only access to inventory and alerts

### Features
- ✅ Dashboard with stats, charts, low stock & expiry warnings
- ✅ Full inventory CRUD with search, filter, pagination
- ✅ Stock movement tracking (in/out/adjustment/disposal)
- ✅ Automated alert system (low stock, expiry, expired)
- ✅ CSV export with date/category filters
- ✅ Role-based access control
- ✅ CSRF protection on all forms
- ✅ Audit log for all actions
- ✅ QR code generation for items
- ✅ MSDS/SDS file upload
- ✅ Dark mode toggle
- ✅ Responsive design

### Security Notes
- All queries use PDO prepared statements
- Passwords hashed with bcrypt
- CSRF tokens on all state-changing forms
- File uploads restricted by extension and size
- Uploaded files cannot be executed (via .htaccess)
- Session cookie is HttpOnly + SameSite=Strict

### Customization
- Edit `config/config.php` to change:
  - Low stock alert threshold
  - Expiry warning days (default: 30)
  - File upload limits
  - Items per page
