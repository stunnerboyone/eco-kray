# Environment Variables Setup Guide

This project now supports environment-based configuration to keep sensitive credentials secure.

## Quick Setup

### 1. Create .env file
```bash
cd /path/to/stunnerboyone.live
cp .env.example .env
```

### 2. Edit .env with your values
```bash
nano .env  # or use your preferred editor
```

Update these critical values:
- `ENVIRONMENT` - Set to `production` or `development`
- `HTTP_SERVER` and `HTTPS_SERVER` - Your domain URLs
- `DIR_*` - Absolute paths to directories
- `DB_*` - Database credentials

### 3. Update config files (Optional - for new deployments)

If setting up a new environment, you can use the env-based config files:

**For Frontend:**
```bash
cp config.php.env-example config.php
```

**For Admin:**
```bash
cp overlord/config.php.env-example overlord/config.php
```

## How It Works

1. **env-loader.php** - Lightweight .env file parser (no dependencies)
2. **.env** - Stores all sensitive configuration (NEVER commit this)
3. **.env.example** - Template for .env (safe to commit)
4. **config.php** - Uses `env()` helper to load values

## Security Benefits

✅ Database credentials are not in version control
✅ Easy to use different configs per environment
✅ Production/development mode switching
✅ No hardcoded sensitive data

## Current Setup (Existing Sites)

For the existing production site with current config.php files:

**To enable environment-based error handling only:**

Add this line at the top of your existing `config.php`:
```php
define('ENVIRONMENT', 'production');
```

This will disable error display in production while keeping your current database config as-is.

**To fully migrate to .env:**

1. Create `.env` file from `.env.example`
2. Copy your current database credentials to `.env`
3. Replace `config.php` with `config.php.env-example` (rename to config.php)
4. Test thoroughly before deploying

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|---------|
| ENVIRONMENT | Environment mode | production or development |
| HTTP_SERVER | HTTP URL | http://stunnerboyone.live/ |
| HTTPS_SERVER | HTTPS URL | https://stunnerboyone.live/ |
| DB_HOSTNAME | Database host | localhost |
| DB_USERNAME | Database user | your_db_user |
| DB_PASSWORD | Database password | your_secure_password |
| DB_DATABASE | Database name | production |
| DB_PORT | Database port | 3306 |
| DB_PREFIX | Table prefix | oc_ |

## Troubleshooting

**Issue:** Site shows blank page after update
**Solution:** Check file permissions on env-loader.php and .env (should be readable by web server)

**Issue:** Database connection fails
**Solution:** Verify .env values match your database credentials exactly

**Issue:** Changes to .env not taking effect
**Solution:** Clear cache: `rm -rf storage/cache/*`

## Migration Checklist

- [ ] Copy .env.example to .env
- [ ] Update .env with actual credentials
- [ ] Test on staging/development first
- [ ] Verify database connection works
- [ ] Check error logging is working
- [ ] Confirm .env is in .gitignore
- [ ] Never commit .env to git
