# LaraCity API cURL Examples

This document provides practical cURL examples for all LaraCity API endpoints. These examples can be used for testing, integration, and automation.

## 🔐 Authentication

### Obtain API Token

First, you need to get an API token for authentication:

```bash
# Create API token (replace with your credentials)
curl -X POST "http://laracity.test/sanctum/token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "demo@laracity.local",
    "password": "demo-password", 
    "device_name": "api-testing"
  }'
```

**Response:**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Demo User",
    "email": "demo@laracity.local"
  }
}
```

**Save the token** for use in subsequent requests:
```bash
export API_TOKEN="1|abc123..."
```

---

## 📋 Complaint Management

### List Complaints with Filtering

```bash
# Basic complaint list
curl -X GET "http://laracity.test/api/complaints" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Advanced filtering
curl -X GET "http://laracity.test/api/complaints?borough=MANHATTAN&status=Open&per_page=5&sort_by=created_date&sort_order=desc" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Filter by risk level and date range
curl -X GET "http://laracity.test/api/complaints?risk_level=high&date_from=2024-01-01&date_to=2024-12-31" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Filter by complaint type
curl -X GET "http://laracity.test/api/complaints?complaint_type=Heating&borough=BROOKLYN" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

### Get Specific Complaint Details

```bash
# Get complaint with AI analysis data
curl -X GET "http://laracity.test/api/complaints/1" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

**Response includes:**
- Complaint details
- AI analysis (risk score, category, sentiment)
- Related actions and history
- Vector embedding metadata

### Complaints Summary & Statistics

```bash
# Overall summary
curl -X GET "http://laracity.test/api/complaints/summary" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Borough-specific summary
curl -X GET "http://laracity.test/api/complaints/summary?borough=MANHATTAN" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Time-range summary (last 7 days)
curl -X GET "http://laracity.test/api/complaints/summary?date_range=7" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

---

## 🔍 Semantic Search

### Hybrid Semantic Search

Combines vector similarity with metadata filtering:

```bash
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "query": "apartment heating not working in winter",
    "filters": {
      "borough": "BROOKLYN",
      "status": "Open"
    },
    "options": {
      "vector_weight": 0.7,
      "metadata_weight": 0.3,
      "similarity_threshold": 0.6,
      "limit": 10
    }
  }'
```

### Pure Vector Similarity Search

Based solely on semantic meaning:

```bash
curl -X POST "http://laracity.test/api/search/similar" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "query": "water leak ceiling damage apartment below",
    "limit": 5,
    "similarity_threshold": 0.7
  }'
```

### Advanced Search Examples

```bash
# Search for noise complaints
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "loud music late night disturbing neighbors",
    "options": {
      "similarity_threshold": 0.75
    }
  }'

# Search for safety issues
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "dangerous building conditions safety hazards",
    "filters": {
      "borough": "QUEENS"
    },
    "options": {
      "vector_weight": 0.8,
      "limit": 15
    }
  }'

# Infrastructure problems search
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "street repair pothole water main break",
    "options": {
      "similarity_threshold": 0.6,
      "limit": 20
    }
  }'
```

### Generate Embeddings

```bash
# Create embeddings for custom text
curl -X POST "http://laracity.test/api/search/embed" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "garbage collection missed multiple weeks attracting rats",
    "include_metadata": true
  }'
```

### Search System Health

```bash
# Get vector store statistics
curl -X GET "http://laracity.test/api/search/stats" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"

# Test search system health
curl -X GET "http://laracity.test/api/search/test" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

---

## ⚡ Actions & Escalation

### Escalate Complaints

```bash
# Escalate single complaint
curl -X POST "http://laracity.test/api/actions/escalate" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "complaint_ids": [1],
    "reason": "High risk complaint requiring immediate attention",
    "priority": "urgent",
    "notify_slack": true
  }'

# Bulk escalation
curl -X POST "http://laracity.test/api/actions/escalate" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "complaint_ids": [1, 2, 3, 4, 5],
    "reason": "Emergency response required for multiple complaints",
    "priority": "high",
    "assign_to": "emergency-team",
    "notify_slack": true
  }'
```

---

## ❓ User Questions

### Log Natural Language Queries

```bash
# Basic question logging
curl -X POST "http://laracity.test/api/user-questions" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "How many noise complaints were filed in Brooklyn last month?",
    "context": "Dashboard analytics query",
    "user_session": "session-12345"
  }'

# Detailed question with metadata
curl -X POST "http://laracity.test/api/user-questions" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "What are the most common types of complaints in Manhattan?",
    "context": "Strategic planning analysis",
    "user_session": "analyst-session-789",
    "metadata": {
      "source": "web-dashboard",
      "user_role": "analyst",
      "department": "planning"
    }
  }'
```

---

## 🏥 System Health

### Health Check

```bash
# Complete system health check
curl -X GET "http://laracity.test/api/health" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

**Response includes:**
- Database connectivity
- AI services status (OpenAI, LangChain)
- Vector store health
- Queue system status
- Performance metrics

---

## 📊 Batch Operations & Automation

### Automated Report Generation

```bash
#!/bin/bash
# Daily report script

API_TOKEN="your-api-token"
DATE=$(date +%Y-%m-%d)

echo "LaraCity Daily Report - $DATE"
echo "================================"

# Get summary statistics
echo "📊 Summary Statistics:"
curl -s -X GET "http://laracity.test/api/complaints/summary?date_range=1" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json" | jq '.'

# Get high-risk complaints
echo -e "\n🚨 High-Risk Complaints:"
curl -s -X GET "http://laracity.test/api/complaints?risk_level=high&per_page=5" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json" | jq '.data[]'

# Search for urgent keywords
echo -e "\n🔍 Emergency Keywords Search:"
curl -s -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "emergency fire gas leak structural damage",
    "options": {"limit": 3}
  }' | jq '.results[]'
```

### Bulk Data Processing

```bash
#!/bin/bash
# Process multiple complaints for escalation

API_TOKEN="your-api-token"

# Get high-risk complaints
HIGH_RISK_IDS=$(curl -s -X GET "http://laracity.test/api/complaints?risk_level=high&per_page=50" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json" | jq -r '.data[].id' | tr '\n' ',' | sed 's/,$//')

if [ ! -z "$HIGH_RISK_IDS" ]; then
  # Escalate all high-risk complaints
  curl -X POST "http://laracity.test/api/actions/escalate" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"complaint_ids\": [$HIGH_RISK_IDS],
      \"reason\": \"Automated escalation - high risk threshold exceeded\",
      \"priority\": \"high\",
      \"notify_slack\": true
    }"
  
  echo "Escalated $(echo $HIGH_RISK_IDS | tr ',' '\n' | wc -l) high-risk complaints"
fi
```

---

## 🔧 Common Integration Patterns

### Webhook Integration

```bash
# Example: Process webhook data and search for similar complaints
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "'$WEBHOOK_COMPLAINT_TEXT'",
    "options": {
      "similarity_threshold": 0.8,
      "limit": 5
    }
  }'
```

### Monitoring & Alerting

```bash
# Health check for monitoring systems
HEALTH_STATUS=$(curl -s -X GET "http://laracity.test/api/health" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json" | jq -r '.status')

if [ "$HEALTH_STATUS" != "healthy" ]; then
  echo "ALERT: LaraCity system health status: $HEALTH_STATUS"
  # Send alert to monitoring system
fi
```

---

## 📝 Response Format Examples

### Typical Success Response

```json
{
  "data": {
    "id": 1,
    "complaint_number": "NYC311-12345",
    "complaint_type": "Heating",
    "status": "Open",
    "analysis": {
      "risk_score": 0.75,
      "category": "Infrastructure",
      "sentiment": "negative"
    }
  },
  "meta": {
    "total": 150,
    "per_page": 10,
    "current_page": 1
  }
}
```

### Error Response

```json
{
  "message": "Validation failed",
  "errors": {
    "query": ["The query field is required."]
  }
}
```

---

## 🛠️ Testing & Debugging

### Debug Mode

Add `?debug=1` to any endpoint for detailed debugging information:

```bash
curl -X GET "http://laracity.test/api/search/stats?debug=1" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

### Verbose Output

Use `-v` flag with cURL for detailed request/response information:

```bash
curl -v -X GET "http://laracity.test/api/complaints" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Accept: application/json"
```

---

## 🔗 Integration with Other Tools

### Python Integration

```python
import requests

API_BASE = "http://laracity.test/api"
API_TOKEN = "your-api-token"

headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}

# Search for complaints
response = requests.post(f"{API_BASE}/search/semantic", 
                        headers=headers,
                        json={
                            "query": "heating problems winter",
                            "options": {"limit": 10}
                        })

results = response.json()
```

### JavaScript/Node.js Integration

```javascript
const API_BASE = 'http://laracity.test/api';
const API_TOKEN = 'your-api-token';

const headers = {
    'Authorization': `Bearer ${API_TOKEN}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
};

// Get complaints
fetch(`${API_BASE}/complaints?borough=MANHATTAN`, { headers })
    .then(response => response.json())
    .then(data => console.log(data));
```

---

Ready to integrate? Start with the health check endpoint to verify your setup, then explore the complaint and search endpoints for your specific use case!