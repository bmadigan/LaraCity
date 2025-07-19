# LaraCity Postman Collection

This directory contains a complete Postman collection for testing and exploring the LaraCity API endpoints.

## 📋 What's Included

### 1. **LaraCity-API.postman_collection.json**
Complete API collection with all endpoints organized by category:

- **Authentication** - API token management
- **Complaints** - Complaint CRUD operations and filtering
- **Semantic Search** - AI-powered search capabilities  
- **Actions** - Escalation and workflow operations
- **User Questions** - Natural language query logging
- **System** - Health checks and monitoring

### 2. **LaraCity-Environment.postman_environment.json**
Pre-configured environment variables for easy testing:

- Base URLs and endpoints
- Authentication credentials
- Sample data for testing
- Common query parameters

## 🚀 Quick Start

### Step 1: Import Collections

1. Open Postman
2. Click **Import** button
3. Select both JSON files from this directory
4. Collections will appear in your workspace

### Step 2: Configure Environment

1. Select "LaraCity API Environment" from the environment dropdown
2. Update these variables with your values:

```
base_url: http://laracity.test/api  (your Laravel server)
user_email: demo@laracity.local     (your demo user)
user_password: demo-password        (your demo password)
```

### Step 3: Authenticate

1. Go to **Authentication > Create API Token**
2. Update the email/password in the request body
3. Send the request
4. Copy the returned `token` value
5. Set it as the `laracity_token` environment variable

### Step 4: Start Testing

All requests are now ready to use! The collection automatically uses your token for authentication.

## 📊 Collection Structure

### Authentication Endpoints
```
POST /sanctum/token - Create API token
```

### Complaint Management
```
GET  /complaints           - List complaints with filtering
GET  /complaints/{id}      - Get specific complaint
GET  /complaints/summary   - Aggregated statistics
```

### Semantic Search
```
POST /search/semantic      - Hybrid semantic search
POST /search/similar       - Pure vector similarity
POST /search/embed         - Generate embeddings
GET  /search/stats         - Vector store statistics
GET  /search/test          - Test search system
```

### Actions & Escalation
```
POST /actions/escalate     - Escalate multiple complaints
```

### User Questions
```
POST /user-questions       - Log natural language queries
```

### System Health
```
GET /health               - Complete system health check
```

## 🔧 Advanced Usage

### Pre-request Scripts

The collection includes automatic token management:
- Checks for stored API token
- Auto-sets from environment variables
- Handles authentication headers

### Test Scripts

Common validations for all requests:
- Response time checks (< 5 seconds)
- Content type validation
- Status code verification
- Response debugging

### Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `base_url` | API base URL | `http://laracity.test/api` |
| `laracity_token` | API authentication token | `1\|abc123...` |
| `user_email` | Demo user email | `demo@laracity.local` |
| `user_password` | Demo user password | `demo-password` |
| `complaint_id` | Sample complaint ID | `1` |
| `search_query` | Test search query | `heating problems` |
| `test_borough` | Test borough filter | `MANHATTAN` |

## 🎯 Testing Scenarios

### Scenario 1: Basic API Exploration

1. **Health Check** - Verify system status
2. **List Complaints** - Explore available data
3. **Get Complaint Details** - See full complaint structure
4. **Summary Statistics** - Understand data distribution

### Scenario 2: Search Capabilities

1. **Semantic Search** - Test natural language queries
2. **Similarity Search** - Test vector-based search
3. **Generate Embeddings** - Understand AI processing
4. **Search Stats** - Monitor system performance

### Scenario 3: Workflow Operations

1. **Filter Complaints** - Find specific complaint types
2. **Escalate High-Risk** - Test escalation workflow
3. **Log Questions** - Test analytics features
4. **Monitor Health** - Verify system stability

### Scenario 4: Performance Testing

1. **Pagination** - Test large dataset handling
2. **Complex Filters** - Test advanced querying
3. **Bulk Operations** - Test batch processing
4. **Concurrent Requests** - Test system load

## 📝 Request Examples

### Complex Filtering

Test advanced complaint filtering:
```json
{
  "borough": "MANHATTAN",
  "status": "Open", 
  "risk_level": "high",
  "date_from": "2024-01-01",
  "date_to": "2024-12-31",
  "sort_by": "risk_score",
  "sort_order": "desc"
}
```

### Semantic Search with Options

Test AI-powered search with custom parameters:
```json
{
  "query": "apartment heating not working winter",
  "filters": {
    "borough": "BROOKLYN",
    "status": "Open"
  },
  "options": {
    "vector_weight": 0.8,
    "metadata_weight": 0.2,
    "similarity_threshold": 0.7,
    "limit": 15
  }
}
```

### Bulk Escalation

Test escalating multiple complaints:
```json
{
  "complaint_ids": [1, 2, 3, 4, 5],
  "reason": "High risk complaints requiring immediate attention",
  "priority": "urgent",
  "notify_slack": true,
  "assign_to": "emergency-team"
}
```

## 🛠️ Customization

### Adding Custom Requests

1. Right-click on any folder
2. Select "Add Request"
3. Configure method, URL, and parameters
4. Use variables for dynamic values: `{{base_url}}/custom-endpoint`

### Environment Setup for Different Stages

Create separate environments for:
- **Development**: Local Laravel server
- **Staging**: Staging environment  
- **Production**: Production API (read-only recommended)

### Custom Test Scripts

Add custom validations to any request:
```javascript
pm.test("High-risk complaints detected", function () {
    const response = pm.response.json();
    const highRiskCount = response.data.filter(
        complaint => complaint.analysis?.risk_score >= 0.7
    ).length;
    
    pm.expect(highRiskCount).to.be.above(0);
    console.log(`Found ${highRiskCount} high-risk complaints`);
});
```

## 🔍 Debugging Tips

### Response Analysis

Use the **Tests** tab to log detailed response data:
```javascript
console.log("Response Status:", pm.response.status);
console.log("Response Time:", pm.response.responseTime + "ms");
console.log("Response Body:", pm.response.json());
```

### Request Inspection

Enable **Postman Console** (View > Show Postman Console) to see:
- Complete request/response details
- Custom console.log outputs
- Error messages and stack traces

### Variable Debugging

Check current variable values:
```javascript
console.log("Current token:", pm.environment.get("laracity_token"));
console.log("Base URL:", pm.environment.get("base_url"));
```

## 📚 Integration Patterns

### CI/CD Pipeline Testing

Run collections via Newman (Postman CLI):
```bash
npm install -g newman
newman run LaraCity-API.postman_collection.json \
  -e LaraCity-Environment.postman_environment.json \
  --reporters html,cli
```

### Automated Monitoring

Use Postman Monitors to run health checks:
1. Select collection
2. Click **Monitor** 
3. Schedule regular runs
4. Get alerts for failures

### Data-Driven Testing

Use CSV/JSON files for dynamic test data:
1. Create data file with test scenarios
2. Use **Runner** to iterate through data
3. Generate comprehensive test reports

## 🎓 Learning Resources

### API Documentation
- [Complete API Reference](../api-examples/curl-examples.md)
- [Python SDK Examples](../api-examples/python-sdk-example.py)
- [JavaScript SDK Examples](../api-examples/javascript-sdk-example.js)

### Postman Learning
- [Postman Learning Center](https://learning.postman.com/)
- [Writing Tests in Postman](https://learning.postman.com/docs/writing-scripts/test-scripts/)
- [Using Variables](https://learning.postman.com/docs/sending-requests/variables/)

---

**Ready to explore?** Import the collection and start with a health check to verify your setup!