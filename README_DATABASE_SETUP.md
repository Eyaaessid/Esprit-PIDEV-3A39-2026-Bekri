# Database Setup Instructions

## Quick Start Guide

### Step 1: Access phpMyAdmin
1. Open your browser and go to phpMyAdmin (usually `http://localhost/phpmyadmin`)
2. Login with your MySQL credentials

### Step 2: Create Database
1. Click on "Databases" tab
2. Enter database name: `bekri_wellbeing`
3. Select collation: `utf8mb4_unicode_ci`
4. Click "Create"

### Step 3: Import SQL Script
1. Click on the `bekri_wellbeing` database in the left sidebar
2. Click on the "SQL" tab at the top
3. Open the file `create_database.sql` in a text editor
4. Copy ALL the content
5. Paste it into the SQL query box in phpMyAdmin
6. Click "Go" button at the bottom

### Step 4: Generate Password Hashes (Optional - for test users)
If you want to create test users with the sample data:

1. Open terminal/command prompt
2. Navigate to your project folder
3. Run: `php generate_test_passwords.php`
4. Copy the generated password hashes
5. Replace the placeholder hashes in the SQL file with the real ones
6. Re-run the INSERT statements in phpMyAdmin

### Step 5: Update .env File
Update your `.env` file with the database credentials:

```env
DATABASE_URL="mysql://username:password@127.0.0.1:3306/bekri_wellbeing?serverVersion=8.0&charset=utf8mb4"
```

Replace:
- `username` with your MySQL username (usually `root`)
- `password` with your MySQL password
- `127.0.0.1` with your MySQL host if different
- `3306` with your MySQL port if different
- `8.0` with your MySQL version

### Step 6: Verify Installation
Run these queries in phpMyAdmin SQL tab to verify:

```sql
-- Check all tables exist
SHOW TABLES;

-- Check evenement table has the 'lieu' column
DESCRIBE evenement;

-- Check participation_evenement has the 'commentaire' column
DESCRIBE participation_evenement;

-- Check if sample users were created
SELECT id, nom, prenom, email, role FROM utilisateur;
```

## What Was Fixed

The SQL script includes all the fixes from the diagnostic:

✅ **Added missing `lieu` column** to `evenement` table
✅ **Added missing `commentaire` column** to `participation_evenement` table  
✅ **Added missing `image` column** to `evenement` table
✅ **Proper foreign key constraints** with CASCADE delete
✅ **All tables use utf8mb4** for full Unicode support
✅ **Proper indexes** on foreign keys for performance

## Database Schema Overview

### Main Tables:
- `utilisateur` - Users (admin, coach, regular users)
- `evenement` - Events created by coaches
- `participation_evenement` - User registrations for events
- `post` - Community posts
- `commentaire` - Comments on posts
- `like` - Likes on posts
- `post_notification` - Notifications for likes/comments
- `objectif_bien_etre` - User wellness goals
- `suivi_quotidien` - Daily tracking
- `test_mental` - Mental health tests
- `question` - Test questions
- `resultat_test` - Test results

## Sample Data Included

The script includes 3 test users:
1. **Admin** - `admin@bekri.local` (password: `admin123`)
2. **Coach** - `coach@bekri.local` (password: `coach123`)
3. **User** - `user@bekri.local` (password: `user123`)

And 2 sample events:
1. Meditation workshop
2. 30-day health challenge

## Troubleshooting

### Error: "Table already exists"
- The script uses `IF NOT EXISTS` so it's safe to run multiple times
- If you want to start fresh, drop all tables first:
  ```sql
  DROP DATABASE bekri_wellbeing;
  CREATE DATABASE bekri_wellbeing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

### Error: "Cannot add foreign key constraint"
- Make sure tables are created in the correct order
- The script creates tables in dependency order (parent tables first)

### Error: "Access denied"
- Check your MySQL user has CREATE and INSERT privileges
- Grant privileges: `GRANT ALL PRIVILEGES ON bekri_wellbeing.* TO 'username'@'localhost';`

### Sample users not working
- Generate real password hashes using `generate_test_passwords.php`
- Replace the placeholder hashes in the SQL file
- Re-run the INSERT statements

## Next Steps

After database setup:

1. **Run Symfony migrations** to sync Doctrine metadata:
   ```bash
   php bin/console doctrine:migrations:sync-metadata-storage
   php bin/console doctrine:migrations:version --add --all
   ```

2. **Validate schema**:
   ```bash
   php bin/console doctrine:schema:validate
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Test the application**:
   - Try logging in with test users
   - Create a new event as coach
   - Register for an event as user

## Security Notes

⚠️ **IMPORTANT**: 
- Change all default passwords before deploying to production
- Remove or disable test users in production
- Use strong passwords (minimum 12 characters)
- Enable 2FA for admin and coach accounts
- Regularly backup your database

## Support

If you encounter issues:
1. Check the diagnostic report: `DIAGNOSTIC_EVENEMENT_PARTICIPANT.md`
2. Verify your MySQL version is 5.7+ or MariaDB 10.2+
3. Check PHP version is 8.1+
4. Ensure all Symfony dependencies are installed: `composer install`
