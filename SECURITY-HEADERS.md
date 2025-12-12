# Security Headers Configuration Guide

This guide explains the security headers implemented in `.htaccess` and how to test and configure them.

## Implemented Security Headers

### 1. X-XSS-Protection
```apache
Header always set X-XSS-Protection "1; mode=block"
```

**Purpose:** Protects against Cross-Site Scripting (XSS) attacks
**Action:** Blocks page rendering if XSS attack detected
**Support:** Most modern browsers

### 2. X-Content-Type-Options
```apache
Header always set X-Content-Type-Options "nosniff"
```

**Purpose:** Prevents MIME type sniffing
**Action:** Forces browser to respect declared content type
**Benefit:** Prevents executing malicious files disguised as safe types

### 3. X-Frame-Options
```apache
Header always set X-Frame-Options "SAMEORIGIN"
```

**Purpose:** Prevents clickjacking attacks
**Action:** Allows framing only from same origin
**Benefit:** Prevents your site from being embedded in malicious iframes

**Options:**
- `DENY` - Never allow framing (most secure)
- `SAMEORIGIN` - Allow framing from same domain (recommended)
- `ALLOW-FROM uri` - Allow specific domain (deprecated)

### 4. Referrer-Policy
```apache
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

**Purpose:** Controls referrer information sent to other sites
**Action:** Sends full URL to same origin, only origin to cross-origin HTTPS
**Benefit:** Protects user privacy while maintaining analytics

**Options:**
- `no-referrer` - Never send referrer (most private)
- `strict-origin-when-cross-origin` - Balanced (recommended)
- `same-origin` - Only send to same domain

### 5. Permissions-Policy
```apache
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=()"
```

**Purpose:** Controls which browser features can be used
**Action:** Disables unused features to reduce attack surface
**Benefit:** Prevents malicious scripts from accessing device features

**Current Settings:**
- ❌ Geolocation disabled
- ❌ Microphone disabled
- ❌ Camera disabled
- ❌ Payment API disabled

**To Enable (if needed):**
```apache
# Allow geolocation for your site only
Header always set Permissions-Policy "geolocation=(self), microphone=(), camera=(), payment=()"
```

### 6. Content-Security-Policy (CSP)
```apache
# Currently commented out - enable after testing
# Header always set Content-Security-Policy "default-src 'self'; ..."
```

**Purpose:** Powerful defense against XSS and injection attacks
**Status:** Commented out (can break functionality if misconfigured)

**To Enable:**
1. Uncomment the CSP header line
2. Test thoroughly on development site
3. Adjust policy based on your needs
4. Monitor for violations

**Common Adjustments:**
```apache
# If you use Google Analytics
script-src 'self' 'unsafe-inline' https://www.google-analytics.com;

# If you use external CDNs
script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;

# If you use inline styles
style-src 'self' 'unsafe-inline';
```

### 7. Strict-Transport-Security (HSTS)
```apache
# Currently commented out - enable after HTTPS is confirmed working
# Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

**Purpose:** Forces HTTPS connections
**Status:** Commented out (requires working HTTPS)

**⚠️ Important:** Only enable after confirming:
- [ ] Valid SSL certificate installed
- [ ] HTTPS working on all pages
- [ ] No mixed content warnings
- [ ] All subdomains support HTTPS (if using includeSubDomains)

**To Enable:**
1. Test HTTPS thoroughly
2. Start with shorter max-age: `max-age=300` (5 minutes)
3. Gradually increase: `max-age=86400` (1 day)
4. Finally: `max-age=31536000` (1 year)

### 8. Server Signature Removal
```apache
Header always unset X-Powered-By
Header unset X-Powered-By
ServerSignature Off
```

**Purpose:** Hides server technology information
**Benefit:** Reduces information available to attackers

## Testing Security Headers

### 1. Online Tools

**SecurityHeaders.com** (Recommended)
```
https://securityheaders.com/?q=stunnerboyone.live
```
- Grades your security headers (A+ to F)
- Shows missing headers
- Provides improvement suggestions

**Mozilla Observatory**
```
https://observatory.mozilla.org/analyze/stunnerboyone.live
```
- Comprehensive security analysis
- Checks headers, SSL, and more
- Detailed recommendations

### 2. Browser DevTools

**Chrome/Firefox:**
1. Open DevTools (F12)
2. Go to Network tab
3. Reload page
4. Click on main request
5. View Response Headers

Look for:
```
x-xss-protection: 1; mode=block
x-content-type-options: nosniff
x-frame-options: SAMEORIGIN
referrer-policy: strict-origin-when-cross-origin
```

### 3. Command Line Testing

```bash
# Test all headers
curl -I https://stunnerboyone.live

# Test specific header
curl -I https://stunnerboyone.live | grep -i "x-frame-options"

# Test multiple URLs
for url in / /product/123 /category/456; do
    echo "Testing: $url"
    curl -I "https://stunnerboyone.live$url" | grep -i "x-xss"
done
```

## Security Header Scores

### Current Implementation (Expected)

| Header | Status | Score |
|--------|--------|-------|
| X-XSS-Protection | ✅ Enabled | Good |
| X-Content-Type-Options | ✅ Enabled | Good |
| X-Frame-Options | ✅ Enabled | Good |
| Referrer-Policy | ✅ Enabled | Good |
| Permissions-Policy | ✅ Enabled | Good |
| Content-Security-Policy | ⚠️ Disabled | - |
| Strict-Transport-Security | ⚠️ Disabled | - |

**Estimated Grade:** B+ to A-

### With All Headers Enabled

| Header | Status | Score |
|--------|--------|-------|
| All Above | ✅ Enabled | Excellent |
| Content-Security-Policy | ✅ Enabled | Excellent |
| Strict-Transport-Security | ✅ Enabled | Excellent |

**Estimated Grade:** A to A+

## Common Issues and Solutions

### Issue 1: mod_headers Not Enabled

**Error:** Headers not appearing in responses

**Solution:**
```bash
# Enable mod_headers in Apache
sudo a2enmod headers
sudo systemctl restart apache2

# Or in CentOS/RHEL
sudo systemctl restart httpd
```

**Verify:**
```bash
apache2ctl -M | grep headers
# Should show: headers_module (shared)
```

### Issue 2: CSP Breaks Functionality

**Symptoms:**
- Inline scripts not working
- External resources blocked
- Console shows CSP violations

**Solution:**
1. Open browser console (F12)
2. Check CSP violation reports
3. Adjust policy to allow needed resources
4. Use `Content-Security-Policy-Report-Only` for testing

**Example Fix:**
```apache
# If jQuery from CDN is blocked
script-src 'self' https://code.jquery.com;

# If inline styles are blocked
style-src 'self' 'unsafe-inline';
```

### Issue 3: X-Frame-Options Prevents Embedding

**Symptoms:**
- Cannot embed site in iframe
- Payment gateways fail

**Solution:**
```apache
# Allow specific domain to frame your site
Header always set X-Frame-Options "ALLOW-FROM https://payment-gateway.com"

# Or use CSP instead
Header always set Content-Security-Policy "frame-ancestors 'self' https://payment-gateway.com"
```

### Issue 4: HSTS Causes Issues

**Symptoms:**
- Cannot access site after enabling HSTS
- Browser forces HTTPS even when it shouldn't

**Solution:**
1. Clear HSTS cache in browser:
   - Chrome: `chrome://net-internals/#hsts`
   - Firefox: Clear entire cache
2. Reduce max-age temporarily
3. Remove `includeSubDomains` if subdomains don't have SSL

## Advanced Configuration

### Conditional Headers

Apply different headers based on conditions:

```apache
# Stricter CSP for admin panel
<If "%{REQUEST_URI} =~ m#^/overlord/#">
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none';"
</If>

# Allow iframe embedding for specific pages
<If "%{REQUEST_URI} == '/embed/product'">
    Header always set X-Frame-Options "ALLOW-FROM https://partner-site.com"
</If>
```

### Report-Only Mode

Test CSP without breaking functionality:

```apache
# CSP in report-only mode
Header always set Content-Security-Policy-Report-Only "default-src 'self'; report-uri /csp-report"
```

Create `/csp-report` endpoint to collect violations.

## Monitoring

### 1. Regular Testing

Schedule weekly security header checks:

```bash
#!/bin/bash
# check-security-headers.sh

DOMAIN="https://stunnerboyone.live"
HEADERS=("X-XSS-Protection" "X-Content-Type-Options" "X-Frame-Options" "Referrer-Policy")

for header in "${HEADERS[@]}"; do
    result=$(curl -s -I "$DOMAIN" | grep -i "$header")
    if [ -z "$result" ]; then
        echo "❌ Missing: $header"
    else
        echo "✅ Present: $result"
    fi
done
```

### 2. Log Analysis

Monitor for:
- CSP violations (if enabled)
- Blocked requests
- Security-related errors

### 3. External Monitoring

Set up monitoring with:
- **SecurityHeaders.com** API
- **Mozilla Observatory** API
- Custom monitoring scripts

## Compliance

These security headers help with:

- ✅ **GDPR** - Privacy protection (Referrer-Policy)
- ✅ **PCI DSS** - Payment security (CSP, HSTS)
- ✅ **OWASP Top 10** - Common vulnerabilities
- ✅ **SOC 2** - Security controls

## Rollout Plan

### Phase 1: Basic Headers (✅ Completed)
- [x] X-XSS-Protection
- [x] X-Content-Type-Options
- [x] X-Frame-Options
- [x] Referrer-Policy
- [x] Permissions-Policy

### Phase 2: HTTPS Enforcement (Pending)
1. Verify SSL certificate is valid
2. Test HTTPS on all pages
3. Enable HSTS with short max-age
4. Monitor for 1 week
5. Increase max-age gradually

### Phase 3: CSP Implementation (Pending)
1. Enable CSP in report-only mode
2. Collect violations for 1 week
3. Adjust policy based on reports
4. Enable CSP in enforcement mode
5. Continue monitoring

## Checklist

- [x] Security headers added to .htaccess
- [ ] Test with SecurityHeaders.com
- [ ] Test with Mozilla Observatory
- [ ] Verify headers in browser DevTools
- [ ] Enable mod_headers in Apache (if needed)
- [ ] Test site functionality after headers
- [ ] Document any issues or adjustments
- [ ] Plan HSTS rollout (after HTTPS verification)
- [ ] Plan CSP rollout (advanced)

## Resources

- [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [MDN Web Docs - HTTP Headers](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers)
- [Content Security Policy Reference](https://content-security-policy.com/)
- [Scott Helme's Security Headers Guide](https://scotthelme.co.uk/hardening-your-http-response-headers/)
