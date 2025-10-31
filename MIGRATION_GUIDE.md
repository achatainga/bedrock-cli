# Migration Guide

Complete guide for migrating existing WordPress databases to Bedrock projects.

## Quick Start

```bash
# 1. Create new Bedrock project
bedrock new my-site --with-docker
cd my-site

# 2. Start Docker
docker-compose up -d

# 3. Migrate database
bedrock migrate \
  --sql-file=/path/to/dump.sql \
  --old-prefix=wp_ \
  --old-url=https://old-site.com \
  --default-theme
```

## What `migrate` Does

The `migrate` command automates the entire database migration process:

1. ✅ **Validates** SQL file exists and Docker is running
2. ✅ **Confirms** before overwriting database
3. ✅ **Imports** SQL dump (supports large files, 10min timeout)
4. ✅ **Cleans** database (renames prefixes, removes multisite tables)
5. ✅ **Replaces** URLs throughout database
6. ✅ **Configures** Acorn (storage + configs)
7. ✅ **Activates** default theme (optional)

## Common Scenarios

### Scenario 1: Different Table Prefix

Migrating from a site with custom prefix (e.g., `hp2f_`) to standard `wp_`:

```bash
bedrock migrate \
  --sql-file=dump.sql \
  --old-prefix=hp2f_ \
  --new-prefix=wp_ \
  --old-url=https://example.com
```

**What happens:**
- All tables renamed: `hp2f_posts` → `wp_posts`
- Multisite tables removed
- Usermeta updated
- Options cleaned

### Scenario 2: Same Prefix, Different URL

Migrating from production to local development:

```bash
bedrock migrate \
  --sql-file=production.sql \
  --old-url=https://mysite.com \
  --new-url=http://localhost:8080
```

**What happens:**
- Table prefix unchanged
- All URLs replaced in database
- Serialized data handled correctly

### Scenario 3: Large Database

For databases over 100MB:

```bash
bedrock migrate \
  --sql-file=large-dump.sql \
  --old-url=https://bigsite.com
```

**Features:**
- 10-minute timeout for import
- Progress indicators
- Error handling for timeouts

### Scenario 4: Without Acorn

If you don't use Roots Acorn:

```bash
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://site.com \
  --skip-acorn
```

### Scenario 5: Custom Port

When using non-standard ports:

```bash
# Create project with custom port
bedrock new my-site --with-docker --http-port=8080

# Migrate (auto-detects from .env)
bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://site.com
```

## Step-by-Step Manual Process

If you need more control, here's the manual equivalent:

```bash
# 1. Import SQL
docker-compose exec -T mysql mysql -uroot -pmysql dbname < dump.sql

# 2. Clean database (if changing prefix)
bedrock db:clean --old-prefix=hp2f_ --new-prefix=wp_

# 3. Replace URLs
docker-compose exec web wp search-replace \
  "https://old-site.com" \
  "http://localhost:8080" \
  --all-tables

# 4. Configure Acorn
docker-compose exec web wp acorn acorn:init storage
docker-compose exec web wp acorn vendor:publish --tag=acorn

# 5. Activate theme
docker-compose exec web wp theme activate twentytwentyfive
```

## Troubleshooting

### Error: "Docker not running"

**Solution:**
```bash
docker-compose up -d
```

### Error: "SQL file not found"

**Solution:** Use absolute path:
```bash
bedrock migrate --sql-file="C:\Users\user\Downloads\dump.sql" ...
```

### Error: "Cannot connect to database"

**Solution:** Check `.env` credentials:
```env
DB_NAME='my_database'
DB_USER='root'
DB_PASSWORD='mysql'
DB_HOST='mysql'
```

### Warning: "Table already exists"

This happens if WordPress was installed before importing SQL.

**Solution:** Drop existing tables first:
```bash
docker-compose exec mysql mysql -uroot -pmysql dbname \
  -e "DROP TABLE IF EXISTS wp_posts, wp_postmeta, wp_users, wp_usermeta, wp_options, wp_terms, wp_term_taxonomy, wp_term_relationships, wp_comments, wp_commentmeta, wp_links;"
```

Or use `migrate` which handles this automatically.

### URLs not replaced correctly

**Solution:** Check serialized data:
```bash
docker-compose exec web wp search-replace \
  "https://old-site.com" \
  "http://localhost:8080" \
  --all-tables \
  --report-changed-only
```

## Best Practices

### 1. Always Create Snapshot Before Migration

```bash
# Before migration
bedrock snapshot --create --name="before-migration"

# After migration (if successful)
bedrock snapshot --create --name="after-migration"
```

### 2. Test Locally First

Never migrate directly to production. Test the process locally:

```bash
# Local test
bedrock new test-migration --with-docker
cd test-migration
docker-compose up -d
bedrock migrate --sql-file=production.sql --old-url=https://prod.com
```

### 3. Verify After Migration

```bash
# Check plugins
docker-compose exec web wp plugin list

# Check theme
docker-compose exec web wp theme list

# Check URLs
docker-compose exec web wp option get home
docker-compose exec web wp option get siteurl

# Test site
curl -I http://localhost:8080
```

### 4. Clean Up Transients

After migration, clear transients:

```bash
docker-compose exec web wp transient delete --all
```

### 5. Regenerate Permalinks

```bash
docker-compose exec web wp rewrite flush
```

## Complete Example: Production to Local

```bash
# 1. Export production database
ssh user@production "wp db export - | gzip" > production.sql.gz
gunzip production.sql.gz

# 2. Create local project
bedrock new local-site --with-docker
cd local-site

# 3. Start Docker
docker-compose up -d

# 4. Migrate
bedrock migrate \
  --sql-file=../production.sql \
  --old-prefix=prod_ \
  --old-url=https://mysite.com \
  --default-theme

# 5. Verify
curl -I http://localhost:8080
docker-compose exec web wp user list

# 6. Create snapshot
bedrock snapshot --create --name="production-migrated"
```

## Performance Tips

### Large Databases (>500MB)

1. **Increase timeouts** in `docker-compose.yml`:
```yaml
services:
  mysql:
    command: --max_allowed_packet=256M
```

2. **Split import** if needed:
```bash
# Import structure first
docker-compose exec -T mysql mysql -uroot -pmysql dbname < structure.sql

# Then data
docker-compose exec -T mysql mysql -uroot -pmysql dbname < data.sql
```

### Many Tables (>200)

The `migrate` command handles this automatically, but for manual imports:

```bash
# Disable foreign key checks
docker-compose exec mysql mysql -uroot -pmysql dbname \
  -e "SET FOREIGN_KEY_CHECKS=0; SOURCE /path/to/dump.sql; SET FOREIGN_KEY_CHECKS=1;"
```

## Security Considerations

### 1. Never Commit SQL Dumps

Add to `.gitignore`:
```
*.sql
*.sql.gz
database/snapshots/*.sql
```

### 2. Sanitize Production Data

Before migrating production to local:

```bash
# Anonymize user data
docker-compose exec web wp user list --format=ids | xargs -I % \
  docker-compose exec web wp user update % \
    --user_email=user%@example.com \
    --display_name="User %"
```

### 3. Change Admin Password

```bash
docker-compose exec web wp user update admin --user_pass=newpassword
```

## Next Steps

After successful migration:

1. **Configure plugins**: `bedrock plugins:order`
2. **Set up cron**: Configure WP-Cron or system cron
3. **Configure cache**: Redis/Memcached setup
4. **Test functionality**: Forms, payments, integrations
5. **Create golden image**: `bedrock snapshot --create --name="golden"`

## Support

For issues or questions:
- GitHub Issues: https://github.com/achatainga/bedrock-cli/issues
- Documentation: https://github.com/achatainga/bedrock-cli
