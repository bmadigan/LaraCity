# LaraCity Demo Scripts

This directory contains comprehensive demonstration scripts that showcase LaraCity's AI-powered complaint management capabilities.

## 🚀 Available Demos

### 1. Emergency Complaint Processing (`demo-emergency-complaint.php`)

Demonstrates the complete emergency response workflow:

```bash
# Run specific emergency scenarios
php scripts/demo-emergency-complaint.php gas_leak
php scripts/demo-emergency-complaint.php structural  
php scripts/demo-emergency-complaint.php water_main
```

**What it demonstrates:**
- Automated risk assessment using AI analysis
- Emergency escalation patterns and thresholds
- Real-time Slack notifications for critical issues
- Complete complaint lifecycle from submission to resolution

**Learning outcomes:**
- Emergency response workflow design
- AI-powered risk scoring algorithms
- Automated notification systems
- Production escalation patterns

---

### 2. Semantic Search Capabilities (`demo-semantic-search.php`)

Shows advanced search using vector embeddings and natural language:

```bash
# Run all search scenarios
php scripts/demo-semantic-search.php all

# Run specific scenario (1-4)
php scripts/demo-semantic-search.php 1

# Interactive search mode
php scripts/demo-semantic-search.php interactive
```

**What it demonstrates:**
- Vector similarity search with pgvector
- Natural language query processing
- Hybrid search combining semantic + metadata filtering
- Real-time search performance metrics

**Learning outcomes:**
- Vector database implementation
- Semantic search algorithms
- Query optimization strategies
- Search result ranking and relevance

---

### 3. Bulk Processing Pipeline (`demo-bulk-processing.php`)

Demonstrates enterprise-scale data processing capabilities:

```bash
# Process different batch sizes
php scripts/demo-bulk-processing.php 50   # Process 50 complaints
php scripts/demo-bulk-processing.php 200  # Process 200 complaints
php scripts/demo-bulk-processing.php 500  # Process 500 complaints
```

**What it demonstrates:**
- Large-scale complaint import and processing
- Queue-based AI analysis pipeline
- Performance monitoring and optimization
- Batch processing patterns and metrics

**Learning outcomes:**
- Enterprise data processing patterns
- Queue architecture and job management
- Performance optimization techniques
- Scalability considerations

---

### 4. API Integration Showcase (`demo-api-showcase.php`)

Comprehensive demonstration of all REST API endpoints:

```bash
# Run complete API demonstration
php scripts/demo-api-showcase.php
```

**What it demonstrates:**
- All complaint management endpoints
- Advanced filtering and pagination
- Semantic search API integration
- Authentication with Laravel Sanctum
- Error handling and response formats

**Learning outcomes:**
- REST API design patterns
- Authentication and authorization
- API performance optimization
- Integration testing strategies

## 📋 Prerequisites

Before running demos, ensure you have:

1. **Laravel Environment Setup:**
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate
   ```

2. **Python Dependencies:**
   ```bash
   pip install -r lacity-ai/requirements.txt
   ```

3. **Database with Sample Data:**
   ```bash
   php artisan db:seed
   # OR import real NYC 311 data:
   php artisan lacity:import-csv --file=storage/311-data.csv
   ```

4. **Vector Embeddings (for search demos):**
   ```bash
   php artisan lacity:generate-embeddings --type=all
   ```

5. **Laravel Server Running (for API demo):**
   ```bash
   php artisan serve
   ```

## 🎯 Demo Scenarios by Use Case

### For Developers Learning AI Integration
1. Start with **Emergency Complaint** demo to understand AI analysis workflow
2. Try **Semantic Search** to see vector embeddings in action
3. Explore **API Showcase** for integration patterns

### For Performance Testing
1. Run **Bulk Processing** with increasing batch sizes
2. Monitor system resources and processing times
3. Test queue workers and scaling patterns

### For Product Demonstrations
1. **Emergency Complaint** - Show real-time risk assessment
2. **Semantic Search Interactive** - Let audience try natural language queries  
3. **API Showcase** - Demonstrate integration capabilities

### For System Administration
1. **Bulk Processing** - Test system limits and performance
2. **API Showcase** - Verify all endpoints are working
3. Review logs and error handling

## 🔧 Configuration

Demos use your existing `.env` configuration. Key settings:

```env
# Required for AI features
OPENAI_API_KEY=your-openai-key
OPENAI_MODEL=gpt-4o-mini

# Optional for Slack notifications
SLACK_WEBHOOK_URL=your-slack-webhook

# Database connection
DB_CONNECTION=pgsql
DB_DATABASE=laracity
```

## 📊 Output and Metrics

All demos provide:
- **Real-time progress indicators**
- **Performance metrics and timing**
- **Success/failure rates**
- **Detailed error reporting**
- **System resource usage**

## 🐛 Troubleshooting

### Common Issues:

**"No embeddings found"**
```bash
php artisan lacity:generate-embeddings --type=all --batch-size=50
```

**"No complaints found"**
```bash
php artisan db:seed
# OR
php artisan lacity:import-csv --file=your-data.csv
```

**"OpenAI API quota exceeded"**
- Demos will show this as expected behavior
- System continues to work without AI features
- Check your OpenAI billing dashboard

**"Database connection failed"**
- Verify PostgreSQL is running
- Check `.env` database configuration
- Ensure pgvector extension is installed

### Performance Tips:

1. **For large datasets:** Use smaller batch sizes initially
2. **For faster demos:** Pre-generate embeddings before running search demos
3. **For API demo:** Ensure Laravel server is running and accessible

## 📚 Educational Value

Each demo script includes:
- **Extensive code comments** explaining implementation decisions
- **Educational focus sections** highlighting learning objectives
- **Real-world patterns** you can apply in production
- **Performance considerations** and optimization techniques

## 🔗 Related Documentation

- [Tutorial-Details.md](../Tutorial-Details.md) - Complete implementation guide
- [README.md](../README.md) - Project overview and setup
- [API Documentation](../docs/API-Documentation.md) - Detailed API reference

---

**Ready to explore?** Start with the Emergency Complaint demo to see LaraCity's AI capabilities in action!