# ATIERA External API

This document explains how to set up and use the external API for ATIERA Financial Management System, allowing third-party applications to integrate with your financial data.

## 🚀 Quick Start

### 1. Database Setup
Run the database migration to create required tables:

```bash
php create_api_tables.php
```

This will create:
- `api_clients` - API client management
- `api_requests` - Request logging and rate limiting
- `webhooks` - Webhook configurations
- `webhook_deliveries` - Webhook delivery tracking
- Additional columns in existing tables for API tracking

### 2. Create API Client
1. Log into your ATIERA admin panel
2. Go to **Admin > API Clients**
3. Click **"Create New API Client"**
4. Enter a name and description
5. Save to generate your API key

### 3. Test the API
Use the test endpoint to verify everything works:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-domain.com/api/v1/test
```

## 📡 API Endpoints

### Base URL
```
https://your-domain.com/api/v1/
```

### Authentication
All requests require an API key in the header:
```
Authorization: Bearer your_api_key_here
```
or
```
X-API-Key: your_api_key_here
```

### Available Endpoints

#### Invoices
- `GET /api/v1/invoices` - List invoices with filters
- `GET /api/v1/invoices?id=123` - Get specific invoice
- `POST /api/v1/invoices` - Create new invoice
- `PUT /api/v1/invoices?id=123` - Update invoice

#### Customers
- `GET /api/v1/customers` - List customers
- `GET /api/v1/customers?id=123` - Get specific customer

#### Vendors
- `GET /api/v1/vendors` - List vendors
- `GET /api/v1/vendors?id=123` - Get specific vendor

#### Test
- `GET /api/v1/test` - API connectivity test

## 💻 Code Examples

### JavaScript/Node.js
```javascript
const axios = require('axios');

const apiClient = axios.create({
  baseURL: 'https://your-domain.com/api/v1/',
  headers: {
    'Authorization': 'Bearer your_api_key',
    'Content-Type': 'application/json'
  }
});

// Get invoices
const invoices = await apiClient.get('invoices');
console.log(invoices.data);

// Create invoice
const newInvoice = await apiClient.post('invoices', {
  customer_id: 123,
  invoice_date: '2025-01-15',
  due_date: '2025-02-14',
  items: [{
    description: 'Web Development Services',
    quantity: 40,
    unit_price: 125.00
  }]
});
```

### PHP
```php
<?php
$apiKey = 'your_api_key';
$url = 'https://your-domain.com/api/v1/invoices';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);
```

### Python
```python
import requests

api_key = 'your_api_key'
base_url = 'https://your-domain.com/api/v1/'

headers = {
    'Authorization': f'Bearer {api_key}',
    'Content-Type': 'application/json'
}

# Get invoices
response = requests.get(f'{base_url}invoices', headers=headers)
invoices = response.json()

# Create invoice
invoice_data = {
    'customer_id': 123,
    'invoice_date': '2025-01-15',
    'due_date': '2025-02-14',
    'items': [{
        'description': 'Web Development Services',
        'quantity': 40,
        'unit_price': 125.00
    }]
}

response = requests.post(f'{base_url}invoices', json=invoice_data, headers=headers)
result = response.json()
```

## 🔐 Security Features

- **API Key Authentication**: Secure token-based authentication
- **Rate Limiting**: 100 requests per hour per API key
- **Request Logging**: All API requests are logged for audit purposes
- **CORS Support**: Configured for cross-origin requests
- **Input Validation**: All inputs are validated and sanitized

## 📊 Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "pagination": {
    "total": 150,
    "limit": 50,
    "offset": 0,
    "has_more": true
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error message",
  "timestamp": "2025-01-15T10:30:00Z"
}
```

## ⚠️ Error Codes

- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized (invalid API key)
- `403` - Forbidden
- `404` - Not Found
- `429` - Rate limit exceeded
- `500` - Internal server error

## 🪝 Webhooks (Future Feature)

Webhooks will allow real-time notifications for events like:
- `invoice.created`
- `invoice.updated`
- `payment.received`
- `bill.created`
- `bill.paid`

## 📋 API Management

### Admin Interface
- **API Clients**: `/admin/api_clients.php` - Manage API keys
- **Documentation**: `/admin/api_docs.php` - Complete API documentation
- **Logs**: Monitor API usage and requests

### Best Practices
1. **Secure API Keys**: Never expose keys in client-side code
2. **Rate Limiting**: Implement retry logic with exponential backoff
3. **Error Handling**: Always check the `success` field
4. **Pagination**: Use limit/offset for large datasets
5. **Testing**: Use the `/api/v1/test` endpoint for connectivity checks

## 🆘 Troubleshooting

### Common Issues

**401 Unauthorized**
- Check your API key is correct and active
- Ensure proper header format: `Authorization: Bearer your_key`

**429 Rate Limited**
- You've exceeded 100 requests per hour
- Wait or contact admin to increase limits

**500 Internal Error**
- Check server logs for detailed error information
- Verify your request format matches the documentation

### Getting Help
1. Check the API documentation at `/admin/api_docs.php`
2. Review server logs for detailed error messages
3. Contact system administrator for API key issues

## 📝 Changelog

### Version 1.0.0
- Initial release
- Invoice CRUD operations
- Customer/Vendor read operations
- API key authentication
- Rate limiting
- Request logging
- Basic documentation

---

**Note**: This API is designed for server-to-server integrations. Keep your API keys secure and never expose them in client-side applications.
