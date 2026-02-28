# Two-Factor Authentication Setup Instructions

## Step 1: Install the Bundle

Run the following command to install the required bundles:

```bash
composer require scheb/2fa-bundle scheb/2fa-totp scheb/2fa-qr-code
```

## Step 2: Uncomment Interface Implementation

After installing the bundle, you need to uncomment the TotpConfigurationInterface implementation in `src/Entity/Utilisateur.php`:

1. Uncomment these lines (around line 16-17):
```php
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
```

2. Update the class declaration (around line 22):
```php
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface, TotpConfigurationInterface
```

3. Uncomment the `getTotpAuthenticationConfiguration()` method (around line 850):
```php
public function getTotpAuthenticationConfiguration(): TotpConfiguration
{
    return new TotpConfiguration($this->totpSecret ?? '', TotpConfiguration::ALGORITHM_SHA1, 30, 6);
}
```

## Step 3: Run Database Migration

Run the migration to add the 2FA fields:

```bash
php bin/console doctrine:migrations:migrate
```

## Step 4: Clear Cache

Clear the Symfony cache:

```bash
php bin/console cache:clear
```

## Step 5: Test the Implementation

1. Log in to your account
2. Go to your profile page
3. Click "Enable 2FA" in the Two-Factor Authentication section
4. Scan the QR code with an authenticator app (Google Authenticator or Authy)
5. Enter the 6-digit code to verify and enable 2FA
6. Save your backup codes securely
7. Log out and log back in - you should be prompted for the 2FA code

## Features Implemented

✅ Real email validation with DNS MX record checking
✅ Disposable email domain detection
✅ Email typo detection and suggestions
✅ Two-factor authentication with TOTP
✅ QR code generation for easy setup
✅ Backup codes for account recovery
✅ Password confirmation for enabling/disabling 2FA
✅ Professional UI matching existing design

## Notes

- The 2FA form template is located at `templates/security/2fa_form.html.twig`
- The enable page is at `templates/two_factor/enable.html.twig`
- Backup codes are shown only once after enabling 2FA
- Users can regenerate backup codes from their profile
- 2FA is optional - users can enable/disable it from their profile
