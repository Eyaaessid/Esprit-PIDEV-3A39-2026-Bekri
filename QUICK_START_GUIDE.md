# Quick Start Guide - Running the Project

## Current Status
✅ PHP 8.2.12 installed
✅ Composer installed
✅ Dependencies installed
✅ Database `bekri_db` created
❌ Database is empty - needs tables

## Option 1: Use SQL Script (Recommended - Fastest)

### Step 1: Import Database via phpMyAdmin
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select database `bekri_db` from left sidebar
3. Click "SQL" tab
4. Open file: `create_database.sql`
5. Copy ALL content
6. Paste into SQL query box
7. Click "Go"
8. ✅ Done! All tables created with fixes

### Step 2: Mark Migrations as Executed
```bash
cd bekri-wellbeing-platform-integration
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:migrations:version --add --all --no-interaction
```

### Step 3: Start the Server
```bash
php -S localhost:8000 -t public
```

### Step 4: Open Browser
http://localhost:8000

---

## Option 2: Use Doctrine Migrations (Slower - Has Issues)

### Step 1: Drop and Recreate Database
```bash
cd bekri-wellbeing-platform-integration
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
```

### Step 2: Run Migrations
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

⚠️ **Warning**: This will create tables WITHOUT the fixes (missing lieu, commentaire columns)

### Step 3: Apply Fixes Manually
You'll need to run these SQL commands after migrations:
```sql
ALTER TABLE evenement ADD lieu VARCHAR(255) DEFAULT NULL;
ALTER TABLE evenement ADD image VARCHAR(255) DEFAULT NULL;
ALTER TABLE participation_evenement ADD commentaire LONGTEXT DEFAULT NULL;
```

### Step 4: Start Server
```bash
php -S localhost:8000 -t public
```

---

## Option 3: Fresh Start with Fixed Database

### Step 1: Drop Existing Database
```bash
php bin/console doctrine:database:drop --force
```

### Step 2: Create via phpMyAdmin
1. Open phpMyAdmin
2. Create new database: `bekri_db`
3. Collation: `utf8mb4_unicode_ci`
4. Import `create_database.sql` (see Option 1)

### Step 3: Sync Migrations
```bash
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:migrations:version --add --all --no-interaction
```

### Step 4: Start Server
```bash
php -S localhost:8000 -t public
```

---

## Verify Installation

### Check Database Tables
```bash
php bin/console doctrine:schema:validate
```

Should show:
```
[Mapping]  OK - The mapping files are correct.
[Database] OK - The database schema is in sync with the mapping files.
```

### Check Routes
```bash
php bin/console debug:router
```

Should show all routes including:
- evenement_liste
- evenement_detail
- evenement_participer
- evenement_mes_participations
- etc.

---

## Test Users (if you imported sample data)

### Admin
- Email: `admin@bekri.local`
- Password: `admin123`

### Coach
- Email: `coach@bekri.local`
- Password: `coach123`

### Regular User
- Email: `user@bekri.local`
- Password: `user123`

⚠️ **Note**: You need to generate real password hashes first!

Run: `php generate_test_passwords.php`

---

## Common Issues

### Issue: "Table doesn't exist"
**Solution**: Import the SQL script via phpMyAdmin (Option 1)

### Issue: "Migrations already executed"
**Solution**: 
```bash
php bin/console doctrine:migrations:sync-metadata-storage
```

### Issue: "Port 8000 already in use"
**Solution**: Use different port
```bash
php -S localhost:8001 -t public
```

### Issue: "Database connection failed"
**Solution**: Check `.env` file
```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/bekri_db?serverVersion=8.0&charset=utf8mb4"
```
- Change `root` to your MySQL username
- Add password after `:` if needed
- Change `bekri_db` to your database name

### Issue: "Missing lieu or commentaire columns"
**Solution**: You used migrations instead of SQL script. Run:
```sql
ALTER TABLE evenement ADD lieu VARCHAR(255) DEFAULT NULL;
ALTER TABLE participation_evenement ADD commentaire LONGTEXT DEFAULT NULL;
```

---

## Access Points

### Public Pages
- Home: http://localhost:8000/
- Events List: http://localhost:8000/evenements
- Event Detail: http://localhost:8000/evenements/{id}
- My Participations: http://localhost:8000/evenements/mes-participations

### Coach Pages
- Dashboard: http://localhost:8000/evenements/coach/dashboard
- Create Event: http://localhost:8000/evenements/coach/new
- Manage Events: http://localhost:8000/evenements/coach

### Admin Pages
- Supervision: http://localhost:8000/evenements/admin/supervision

---

## Next Steps After Running

1. ✅ Test event listing page
2. ✅ Test event registration flow
3. ✅ Test participation cancellation
4. ✅ Test coach dashboard
5. ✅ Check for errors in browser console
6. ✅ Review UX issues in `UX_FLOW_ANALYSIS.md`
7. ✅ Review technical issues in `DIAGNOSTIC_EVENEMENT_PARTICIPANT.md`

---

## Development Commands

### Clear Cache
```bash
php bin/console cache:clear
```

### Create New Migration
```bash
php bin/console make:migration
```

### Create New Entity
```bash
php bin/console make:entity
```

### Create New Controller
```bash
php bin/console make:controller
```

### Run Tests
```bash
php bin/phpunit
```

---

## Recommended: Use Symfony CLI

Install Symfony CLI for better development experience:
https://symfony.com/download

Then use:
```bash
symfony server:start
```

Benefits:
- Automatic HTTPS
- Better error handling
- PHP version management
- Background process management

---

## Production Deployment

Before deploying to production:

1. ✅ Fix all critical issues from diagnostic
2. ✅ Implement proper authentication
3. ✅ Add email notifications
4. ✅ Set up proper environment variables
5. ✅ Enable production mode: `APP_ENV=prod`
6. ✅ Clear and warm up cache
7. ✅ Run security checks
8. ✅ Set up SSL certificate
9. ✅ Configure proper database backups
10. ✅ Set up monitoring and logging

---

## Support

If you encounter issues:
1. Check error logs: `var/log/dev.log`
2. Check web server error log
3. Review diagnostic documents
4. Check Symfony documentation: https://symfony.com/doc
