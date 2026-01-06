# FASE 1 Testing Guide

## ✅ Deployment Status
- **Project**: test_bedrock
- **MU Plugin**: Copied successfully
- **Files Verified**: All FASE 1 components present

## 🧪 Manual Testing Steps

### 1. Start Containers
```bash
cd /home/achat/code/test_bedrock
docker-compose up -d
```

### 2. Access WordPress Admin
- URL: http://localhost/wp/wp-admin
- Login with admin credentials

### 3. Navigate to Bedrock CLI
- Left menu → **Bedrock CLI**
- Should see new dashboard with 3 cards

### 4. Test System Status
- **Expected**: 
  - System health card shows Docker/DB/Acorn status
  - PHP version badge in header
  - ✅ or ❌ indicators

### 5. Test Language Switcher
- **Steps**:
  1. Check current language displayed
  2. Select different language from dropdown
  3. Click "Cambiar" button
  4. Confirm reload prompt
- **Expected**: Language changes, page reloads

### 6. Test Docker Restart
- **Steps**:
  1. Click "🔄 Reiniciar Servicios"
  2. Confirm dialog
  3. Wait for terminal output
- **Expected**: 
  - Terminal shows docker-compose restart output
  - Containers restart successfully

### 7. Test API Directly (Optional)
```bash
# Get nonce from browser console: bedrockCliSettings.nonce
curl -X GET http://localhost/wp-json/bedrock-cli/v1/system/status \
  -H "X-WP-Nonce: YOUR_NONCE"

# Test Docker control
curl -X POST http://localhost/wp-json/bedrock-cli/v1/docker/control \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"action":"restart"}'

# Test blocked action (should return 403)
curl -X POST http://localhost/wp-json/bedrock-cli/v1/docker/control \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"action":"down"}'
```

## 🐛 Troubleshooting

### Dashboard doesn't load
- Check browser console for JS errors
- Verify assets loaded: Network tab → admin-dashboard.js/css

### API returns 401
- Verify logged in as admin
- Check nonce is being sent in headers

### Docker restart fails
- Check Docker is running: `docker ps`
- Verify project path in ServiceBridge

### Language change fails
- Check WP-CLI is accessible in container
- Verify WpCliService instantiation

## 📊 Success Criteria
- [ ] Dashboard loads without errors
- [ ] System status displays correctly
- [ ] Language switcher works
- [ ] Docker restart executes safely
- [ ] Blocked actions return 403
- [ ] No PHP errors in logs
- [ ] No JS errors in console

## 🚀 Next: FASE 2
Once testing passes, proceed with:
- Plugin management
- Theme management
- Bulk operations
