# Confirm Dialog Replacement Guide

## Pattern to Replace

### Old Pattern (Negated confirm):
```javascript
if (!confirm('Message')) {
    return;
}
// code here
```

### New Pattern:
```javascript
showConfirmDialog('Title', 'Message', async () => {
    // code here
});
```

### Old Pattern (Direct confirm):
```javascript
if (confirm('Message')) {
    // code here
}
```

### New Pattern:
```javascript
showConfirmDialog('Title', 'Message', () => {
    // code here
});
```

## Files to Update:
- staff/accounts_payable.php ✓ needs closing
- staff/accounts_receivable.php ✓ needs changes
- superadmin/accounts_receivable.php ✓ needs changes
- admin/accounts_receivable.php ✓ needs changes
- superadmin/settings.php (many confirm calls)
- superadmin/workflows.php (confirm calls)
- superadmin/two_factor_auth.php (confirm calls)
- superadmin/translations.php (confirm calls)
- superadmin/search.php
- superadmin/currencies.php
- superadmin/dashboard_customization.php
- superadmin/backups.php (many)
- superadmin/audit.php
- superadmin/api_clients.php
- staff/* (similar files)
- admin/* (remaining files)
