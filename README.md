# LaraCity: AI-Powered Municipal Complaint Management System

> **🎯 Educational Focus**: A comprehensive tutorial demonstrating **LangChain RAG integration** with real-world civic data, showcasing modern Laravel architecture patterns and production-ready AI implementation.

### The Tutorial Series:
[LaraCity - Municipal AI Complaint System](https://madigan.dev/blog/mastering-ai-engineering-building-a-production-ready-ai-complaint-system-with-laravel-langchain)

---

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![Python](https://img.shields.io/badge/Python-3.8+-blue.svg)](https://python.org)
[![LangChain](https://img.shields.io/badge/LangChain-0.1+-green.svg)](https://langchain.com)
[![pgvector](https://img.shields.io/badge/pgvector-0.7+-purple.svg)](https://github.com/pgvector/pgvector)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-blue.svg)](https://postgresql.org)

## 🌟 What is LaraCity?

**LaraCity** transforms NYC 311 complaint data into actionable insights through AI analysis. Built as an educational resource, it demonstrates **production-ready LangChain integration** with Laravel, featuring:

- **🤖 AI-Powered Analysis**: Automated complaint categorization, risk scoring, and summarization
- **🔍 Semantic Search**: Natural language queries like "Show me noise complaints in Queens last week"
- **⚡ Real-Time Escalation**: Automated Slack alerts when complaints exceed risk thresholds
- **📊 Vector Database**: pgvector-powered similarity search for intelligent complaint discovery
- **🔗 Hybrid Architecture**: Laravel backend + Python LangChain AI processing
- **📈 Complete Audit Trail**: Full action logging and conversation history

### 📚 Primary Learning Objectives

This project serves as a **beginner's guide to LangChain integration** using real civic data:

1. **RAG Implementation**: Vectorized CSV data for intelligent question answering
2. **PromptTemplates**: Structured prompts for consistent AI responses
3. **Few-Shot Learning**: Examples-based prompt engineering with NYC 311 data
4. **LCEL (LangChain Expression Language)**: Chain composition for complex workflows
5. **Vector Search**: Semantic similarity for complaint discovery and analysis
6. **Production Patterns**: Error handling, monitoring, scaling, and deployment

![LaraCity Dashboard Screenshot](https://github.com/bmadigan/LaraCity/blob/main/public/imgs/dashboard-screenshot-dangerous.png?raw=true)


## 🚀 Quick Start

### Prerequisites

- **PHP 8.3+** with Laravel 12.x
- **PostgreSQL 15+** with pgvector extension
- **Python 3.8+** for LangChain components
- **Node.js 18+** (optional, for frontend development)
- **OpenAI API Key** for embedding generation and AI analysis

### 1. Clone and Install

```bash
# Clone the repository
git clone https://github.com/bmadigan/LaraCity.git
cd LaraCity

# Install PHP dependencies
composer install

# Install Python dependencies
pip install -r lacity-ai/requirements.txt

# Create environment file
cp .env.example .env
```

### 2. Configure Environment

Edit `.env` with your settings:

```env
# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laracity
DB_USERNAME=your_postgres_user
DB_PASSWORD=your_postgres_password

# OpenAI Configuration (Required for AI features)
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_ORGANIZATION=your-org-id
OPENAI_MODEL=gpt-4o-mini

# Slack Configuration (Optional - for escalation alerts)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/your/webhook/url

# Application Settings
APP_URL=http://laracity.test
COMPLAINT_ESCALATE_THRESHOLD=0.7
```

### 3. Database Setup

```bash
# Generate application key
php artisan key:generate

# Install pgvector extension (requires PostgreSQL admin access)
psql -U postgres -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Run migrations
php artisan migrate

# Seed with demo data (optional but recommended)
php artisan db:seed
```

### 4. Import NYC 311 Data (Optional)

```bash
# Download sample NYC 311 data (or use your own CSV)
# The system can handle large files (534MB+ tested)

# Import complaints data
php artisan lacity:import-csv --file=storage/311-data.csv --validate

# Expected output:
# ✅ Records Processed: 8,130
# ✅ Success Rate: 100%
```

### 5. Generate AI Analysis & Embeddings

```bash
# Process complaints through AI analysis pipeline
php artisan queue:work --queue=ai-analysis &

# Generate vector embeddings for semantic search
php artisan lacity:generate-embeddings --type=all --batch-size=50

# Monitor progress
php artisan lacity:vector-store stats
```

### 6. Test the System

```bash
# Test Python-Laravel bridge
php artisan tinker
>>> app(App\Services\PythonAiBridge::class)->testConnection()

# Test semantic search
php artisan lacity:vector-store search --query="water leak complaints"

# Test API endpoints (requires authentication)
curl -X GET "http://laracity.test/api/complaints/summary" \
  -H "Authorization: Bearer your-api-token"
```

## 🏗️ Architecture Overview

LaraCity demonstrates a **modern hybrid architecture** combining Laravel's robustness with Python's AI capabilities:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Laravel API   │◄──►│ PostgreSQL +    │◄──►│ Python/LangChain│
│                 │    │ pgvector        │    │                 │
│ • REST endpoints│    │                 │    │ • RAG chains    │
│ • Queue jobs    │    │ • Vector search │    │ • Embeddings    │
│ • Auth/validation│    │ • HNSW indexing │    │ • OpenAI client │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Audit Trail   │    │   External APIs │
│                 │    │                 │    │                 │
│ • Livewire UI   │    │ • Action logs   │    │ • OpenAI API    │
│ • API consumers │    │ • Chat history  │    │ • Slack webhooks│
│ • Dashboards    │    │ • Error tracking│    │ • NYC 311 data  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Key Components

| Component | Purpose | Technology |
|-----------|---------|------------|
| **Complaint Management** | Core data models and CRUD operations | Laravel Eloquent, PostgreSQL |
| **AI Analysis Pipeline** | Automated complaint processing | Laravel Jobs, Python Bridge |
| **Vector Search** | Semantic similarity and RAG | pgvector, LangChain, OpenAI |
| **Risk Escalation** | Automated alerting workflow | Queue Jobs, Slack API |
| **API Layer** | REST endpoints for integration | Laravel Sanctum, API Resources |
| **Admin Interface** | Management dashboard | Laravel Livewire |

## 📖 Feature Showcase

### 🤖 AI-Powered Complaint Analysis

```php
// Automatic analysis when complaints are imported
$complaint = Complaint::create($complaintData);

// AI analysis happens automatically via Observer pattern:
// 1. AnalyzeComplaintJob queued
// 2. Python LangChain processes complaint
// 3. Risk score, category, tags generated
// 4. Vector embedding created for semantic search
// 5. High-risk complaints trigger escalation

$analysis = $complaint->analysis; // AI-generated insights
echo $analysis->risk_score;      // 0.85 (high risk)
echo $analysis->category;        // "Infrastructure"
echo $analysis->summary;         // AI-generated summary
```

### 🔍 Semantic Search & RAG

```php
// Natural language search across complaints
$results = app(HybridSearchService::class)->search(
    "apartment heating not working in winter",
    ['borough' => 'BROOKLYN'],
    ['similarity_threshold' => 0.8]
);

// Returns semantically similar complaints even if exact keywords don't match
foreach ($results['results'] as $result) {
    echo "Similarity: {$result['combined_score']}\n";
    echo "Content: {$result['content']}\n";
    echo "Related Complaint: #{$result['complaint']['complaint_number']}\n";
}
```

### ⚡ Real-Time Risk Escalation

```php
// Automatic escalation for high-risk complaints
if ($analysis->risk_score >= 0.7) {
    // 1. Complaint status → "Escalated"
    // 2. Action logged for audit trail
    // 3. Slack notification sent with AI summary
    // 4. Follow-up jobs scheduled
}

// Slack message example:
// 🚨 ELEVATED Risk Complaint Alert
// Complaint #: NYC311-12345
// Risk Score: 🟡 0.85
// Type: Gas Leak
// Location: MANHATTAN
// AI Summary: Critical gas leak at residential building requires immediate emergency response.
```

### 📊 Advanced API Integration

```bash
# Hybrid semantic + metadata search
curl -X POST "http://laracity.test/api/search/semantic" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "water leak in apartment building",
    "filters": {"borough": "MANHATTAN", "risk_level": "high"},
    "options": {"vector_weight": 0.7, "metadata_weight": 0.3}
  }'

# Response includes:
# - Semantically similar complaints
# - Combined similarity + metadata scores
# - Full complaint details with AI analysis
# - Search performance metrics
```

## 🛠️ Development Workflow

### Running the Development Environment

```bash
# Start Laravel development server
php artisan serve

# Start queue workers for AI processing
php artisan queue:work --queue=ai-analysis,escalation,notification

# Watch for file changes (if using Vite)
npm run dev

# Monitor logs
tail -f storage/logs/laravel.log
```

### Common Development Tasks

```bash
# Generate embeddings for new complaints
php artisan lacity:generate-embeddings --type=complaints --limit=100

# Test vector store functionality
php artisan lacity:vector-store test

# Import new NYC 311 data
php artisan lacity:import-csv --file=new-data.csv --validate

# Clear caches and restart services
php artisan config:clear && php artisan queue:restart
```

### Testing the AI Pipeline

```php
// Test individual components
php artisan tinker

// Test Python bridge connection
>>> $bridge = app(\App\Services\PythonAiBridge::class);
>>> $result = $bridge->testConnection();
>>> print_r($result);

// Test complaint analysis
>>> $complaint = \App\Models\Complaint::first();
>>> \App\Jobs\AnalyzeComplaintJob::dispatch($complaint);

// Test semantic search
>>> $search = app(\App\Services\HybridSearchService::class);
>>> $results = $search->search("noise complaints");
>>> count($results['results']);
```

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| [Tutorial-Details.md](Tutorial-Details.md) | **Complete 6-phase tutorial** with step-by-step implementation guide |
| [API-Documentation.md](docs/API-Documentation.md) | REST API endpoints and examples |
| [Deployment-Guide.md](docs/Deployment-Guide.md) | Production deployment instructions |
| [Architecture-Overview.md](docs/Architecture-Overview.md) | System design and component interaction |
| [Troubleshooting.md](docs/Troubleshooting.md) | Common issues and solutions |

## 🎯 Tutorial Phases

LaraCity is built as a **6-phase educational journey**, each phase building on the previous:

| Phase | Focus | Status | Learning Outcomes |
|-------|-------|--------|-------------------|
| **Phase B** | Laravel Foundation | ✅ Complete | Database design, Eloquent relationships, CSV import |
| **Phase C** | API Development | ✅ Complete | REST APIs, authentication, resource transformation |
| **Phase D** | PHP-Python Bridge | ✅ Complete | Inter-process communication, queue architecture |
| **Phase E** | LangChain Integration | ✅ Complete | RAG systems, prompt engineering, LCEL chains |
| **Phase F** | Vector Database | ✅ Complete | pgvector, semantic search, hybrid queries |
| **Phase G** | Production Ready | ✅ Complete | Documentation, deployment, monitoring |

Each phase includes:
- 📖 **Detailed explanations** of concepts and implementation decisions
- 💻 **Complete working code** with educational comments
- 🔧 **Real-world examples** using NYC 311 complaint data
- 🎯 **Learning exercises** and extension opportunities

## 🚦 API Endpoints

### Complaint Management
- `GET /api/complaints` - List complaints with advanced filtering
- `GET /api/complaints/summary` - Aggregated statistics
- `GET /api/complaints/{id}` - Individual complaint details
- `POST /api/actions/escalate` - Batch escalation operations

### Semantic Search
- `POST /api/search/semantic` - Hybrid vector + metadata search
- `POST /api/search/similar` - Pure vector similarity search
- `POST /api/search/embed` - Generate embeddings via API
- `GET /api/search/stats` - Vector store statistics

### System Management
- `POST /api/user-questions` - Natural language query logging
- `GET /api/health` - System health check

> **Authentication Required**: All endpoints require Laravel Sanctum token authentication.

## 🔧 Command Line Tools

LaraCity includes comprehensive CLI tools for management and operations:

### Data Management
```bash
# Import NYC 311 complaint data
php artisan lacity:import-csv --file=data.csv --validate

# Generate AI analysis for existing complaints
php artisan queue:work --queue=ai-analysis
```

### Vector Operations
```bash
# Generate embeddings for semantic search
php artisan lacity:generate-embeddings --type=all --batch-size=50

# Manage vector store
php artisan lacity:vector-store stats
php artisan lacity:vector-store sync
php artisan lacity:vector-store search --query="noise complaints"
php artisan lacity:vector-store test
php artisan lacity:vector-store cleanup --days=30
```

### Python Integration
```bash
# Direct LangChain operations
python3 lacity-ai/langchain_runner.py analyze_complaint '{"id": 123, ...}'
python3 lacity-ai/langchain_runner.py sync_pgvector '{}'
python3 lacity-ai/langchain_runner.py health_check '{}'
```

## 🎨 Demo Scenarios

### Scenario 1: Emergency Response
```bash
# Simulate high-risk complaint processing
php artisan demo:emergency-complaint
# → Creates gas leak complaint
# → AI analysis detects high risk (0.95)
# → Automatic escalation triggered
# → Slack alert sent to emergency team
```

### Scenario 2: Semantic Search
```bash
# Demonstrate natural language understanding
php artisan demo:semantic-search
# → User asks "apartment heating problems in Brooklyn"
# → Vector search finds related complaints
# → Returns results even without exact keyword matches
```

### Scenario 3: Bulk Processing
```bash
# Show enterprise-scale processing
php artisan demo:bulk-import
# → Imports 1000 sample complaints
# → Processes through AI analysis pipeline
# → Generates embeddings for semantic search
# → Shows performance metrics
```

## 🚀 Production Deployment

### Performance Characteristics

| Metric | Typical Performance |
|--------|-------------------|
| **API Response Time** | 50-200ms for filtered queries |
| **Vector Search** | 10-50ms for 10k embeddings |
| **AI Analysis** | 2-5 seconds per complaint |
| **Bulk Import** | 1000 complaints/minute |
| **Concurrent Users** | 100+ with proper scaling |

### Scaling Recommendations

- **Database**: PostgreSQL with proper indexing and connection pooling
- **Queue Workers**: Multiple workers for AI processing queues
- **Caching**: Redis for API response caching
- **CDN**: Static asset delivery optimization
- **Monitoring**: Application performance monitoring (APM)

### Security Considerations

- **API Authentication**: Laravel Sanctum with proper token management
- **Rate Limiting**: Throttling for API endpoints
- **Input Validation**: Comprehensive request validation
- **SQL Injection**: Eloquent ORM prevents direct SQL vulnerabilities
- **XSS Protection**: Laravel's built-in protection mechanisms

## 🤝 Contributing

LaraCity is designed as an educational resource. Contributions that enhance the learning experience are welcome:

### Areas for Contribution
- **Additional AI Models**: Integration with other LLM providers
- **Frontend Development**: React/Vue.js dashboard implementation
- **Data Sources**: Integration with other civic data APIs
- **Analytics**: Advanced reporting and visualization features
- **Documentation**: Tutorial improvements and examples

### Development Setup
```bash
# Fork and clone the repository
git clone https://github.com/your-username/LaraCity.git

# Create feature branch
git checkout -b feature/your-enhancement

# Install dependencies and configure environment
composer install && pip install -r lacity-ai/requirements.txt

# Run tests
php artisan test
python -m pytest lacity-ai/tests/

# Submit pull request with detailed description
```

## 📊 Project Stats

- **📁 Total Files**: 80+ across Laravel and Python components
- **📄 Lines of Code**: 15,000+ (PHP + Python + Documentation)
- **🗃️ Database Tables**: 5 (complaints, analyses, actions, embeddings, users)
- **🔗 API Endpoints**: 12 REST endpoints with comprehensive functionality
- **🤖 AI Operations**: 8 LangChain chains and Python components
- **📖 Documentation**: 2,500+ lines of educational content

## 🎓 Learning Outcomes

By exploring LaraCity, you'll gain hands-on experience with:

### **Laravel Expertise**
- Advanced Eloquent relationships and query optimization
- Queue architecture and job processing patterns
- API development with Laravel Sanctum authentication
- Observer patterns for event-driven architecture
- Service layer architecture and dependency injection

### **AI/ML Integration**
- LangChain RAG system implementation
- Vector database operations with pgvector
- OpenAI API integration and prompt engineering
- Semantic search and similarity algorithms
- Few-shot learning and prompt template patterns

### **System Architecture**
- Inter-process communication (PHP ↔ Python)
- Hybrid search systems (semantic + metadata)
- Production deployment and scaling patterns
- Monitoring, logging, and error handling
- Real-time notification systems

### **Data Engineering**
- Large CSV file processing and validation
- ETL pipelines for civic data transformation
- Vector embedding generation and storage
- Database schema design for analytics
- Performance optimization for large datasets

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- **NYC Open Data** for providing real 311 complaint datasets
- **LangChain Community** for excellent documentation and examples
- **Laravel Community** for robust web application framework
- **pgvector** for PostgreSQL vector search capabilities
- **OpenAI** for embedding and language model APIs

---

For questions, issues, or contributions, please open a GitHub issue or reach out to the maintainers.
