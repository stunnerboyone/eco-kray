# Sitemap Configuration Guide

This guide covers setting up and maintaining the XML sitemap for your OpenCart store.

## What is a Sitemap?

A sitemap helps search engines (Google, Bing, Yandex) discover and index all pages on your website, improving SEO and search visibility.

## Quick Setup

### Method 1: Generate Static Sitemap (Recommended)

Generate a static `sitemap.xml` file:

```bash
cd /path/to/stunnerboyone.live
php generate-sitemap.php
```

This creates `sitemap.xml` in your web root with all:
- Products (with images)
- Categories
- Manufacturers
- Information pages
- Homepage

### Method 2: Dynamic Sitemap (OpenCart Built-in)

Enable the built-in dynamic sitemap:

1. **Login to Admin Panel** (overlord/)
2. **Navigate to:** Extensions → Feeds
3. **Find:** Google Sitemap
4. **Click:** Edit
5. **Set Status:** Enabled
6. **Set Data Feed URL:** `sitemap.xml`
7. **Save**

Access at: `https://stunnerboyone.live/index.php?route=extension/feed/google_sitemap`

## Automated Generation (Cron Job)

### Daily Regeneration

Add to crontab for automatic updates:

```bash
crontab -e
```

Add this line (runs daily at 3 AM):
```bash
0 3 * * * cd /var/www/ekokrai/data/www/stunnerboyone.live && php generate-sitemap.php >> /var/log/sitemap-gen.log 2>&1
```

### After Product Updates

Generate after adding/updating products:
```bash
# Manual trigger
php generate-sitemap.php

# Or create a hook in admin after product save
```

## Verify Sitemap

### 1. Check File Exists
```bash
ls -lh sitemap.xml
curl -I https://stunnerboyone.live/sitemap.xml
```

### 2. Validate XML
Visit: https://www.xml-sitemaps.com/validate-xml-sitemap.html

Paste: `https://stunnerboyone.live/sitemap.xml`

### 3. Check robots.txt
Verify the sitemap is referenced:
```bash
curl https://stunnerboyone.live/robots.txt | grep -i sitemap
```

Should show:
```
Sitemap: https://stunnerboyone.live/sitemap.xml
```

## Submit to Search Engines

### Google Search Console

1. Visit: https://search.google.com/search-console
2. Add property: `stunnerboyone.live`
3. Verify ownership (DNS, HTML file, or meta tag)
4. Go to: **Sitemaps** (left menu)
5. Enter: `sitemap.xml`
6. Click: **Submit**

Status will show:
- ✓ Success - URLs discovered
- ⚠️ Warnings - Some issues (check details)
- ✗ Error - Sitemap not accessible

### Bing Webmaster Tools

1. Visit: https://www.bing.com/webmasters
2. Add site: `stunnerboyone.live`
3. Verify ownership
4. **Sitemaps** → Submit Sitemap
5. Enter: `https://stunnerboyone.live/sitemap.xml`

### Yandex Webmaster

1. Visit: https://webmaster.yandex.com/
2. Add site: `https://stunnerboyone.live`
3. Verify ownership
4. **Indexing** → Sitemap files
5. Add: `https://stunnerboyone.live/sitemap.xml`

## Sitemap Structure

### URL Priority

The generated sitemap uses these priorities:

| Page Type | Priority | Change Frequency |
|-----------|----------|------------------|
| Homepage | 1.0 | daily |
| Products | 0.9 | weekly |
| Categories | 0.8 | weekly |
| Manufacturers | 0.6 | monthly |
| Info Pages | 0.5 | monthly |

### Image Sitemap

Product images are included using Google's image sitemap extension:

```xml
<url>
  <loc>https://stunnerboyone.live/product/juice</loc>
  <image:image>
    <image:loc>https://stunnerboyone.live/image/products/juice.jpg</image:loc>
    <image:caption>Яблучний сік</image:caption>
    <image:title>Яблучний сік</image:title>
  </image:image>
</url>
```

## Monitoring & Maintenance

### Check Indexing Status

**Google Search Console:**
- Coverage report shows indexed/excluded URLs
- Check for errors or warnings
- Monitor click-through rates

**Manual check:**
```
site:stunnerboyone.live
```

### Common Issues

#### 1. Sitemap Too Large
**Issue:** Sitemap over 50MB or 50,000 URLs
**Solution:** Split into multiple sitemaps:
```bash
# Create sitemap index
php generate-sitemap.php --split
```

#### 2. URLs Not Indexed
**Issue:** Submitted but not appearing in search
**Solution:**
- Check robots.txt not blocking
- Verify URLs are accessible (not 404)
- Check for noindex meta tags
- Wait (can take 1-4 weeks)

#### 3. 404 Errors in Sitemap
**Issue:** Deleted products still in sitemap
**Solution:** Regenerate sitemap:
```bash
php generate-sitemap.php
```

#### 4. Permission Denied
**Issue:** Cannot write sitemap.xml
**Solution:**
```bash
chmod 755 /path/to/stunnerboyone.live
chown www-data:www-data sitemap.xml  # or your web user
```

## SEO Best Practices

### 1. Keep Sitemap Updated
- Regenerate after major catalog changes
- Set up automatic daily generation
- Remove discontinued products

### 2. Submit New Sitemap
After major changes, resubmit to search engines:
```bash
# Google Search Console API
curl -X GET "https://www.google.com/ping?sitemap=https://stunnerboyone.live/sitemap.xml"
```

### 3. Monitor Coverage
- Check Google Search Console weekly
- Fix errors promptly
- Track indexation trends

### 4. Optimize for Ukrainian Market
Since your site is in Ukrainian:
- Submit to Yandex (popular in Ukraine)
- Use hreflang tags if multiple languages
- Target Ukrainian search engines

## Advanced Configuration

### Custom Priorities

Edit `generate-sitemap.php` to customize priorities:

```php
// Higher priority for specific categories
if ($category['category_id'] == 123) {
    $priority = '1.0';  // Featured category
}
```

### Exclude Pages

Skip certain products/categories:

```php
// Skip out-of-stock products
if ($product['quantity'] <= 0) {
    continue;
}
```

### Multiple Language Support

If you add other languages:

```php
$output .= "  <xhtml:link rel='alternate' hreflang='uk' href='{$url}' />\n";
$output .= "  <xhtml:link rel='alternate' hreflang='en' href='{$url_en}' />\n";
```

## Checklist

After setup, verify:

- [ ] sitemap.xml exists and is accessible
- [ ] robots.txt references sitemap
- [ ] Submitted to Google Search Console
- [ ] Submitted to Bing Webmaster
- [ ] Submitted to Yandex Webmaster (if targeting Ukraine/Russia)
- [ ] Cron job configured for auto-generation
- [ ] No errors in Search Console
- [ ] URLs being indexed (check after 1 week)

## Next Steps

1. ✅ Generate initial sitemap: `php generate-sitemap.php`
2. ✅ Verify accessible: https://stunnerboyone.live/sitemap.xml
3. ✅ Submit to search engines
4. ✅ Set up daily cron job
5. ⬜ Monitor indexing in Search Console
6. ⬜ Track organic traffic improvements

## Resources

- [Google Sitemap Guidelines](https://developers.google.com/search/docs/advanced/sitemaps/overview)
- [Bing Sitemap Guidelines](https://www.bing.com/webmasters/help/sitemaps-3b5cf6ed)
- [Yandex Sitemap Guidelines](https://yandex.com/support/webmaster/controlling-robot/sitemap.html)
