# LaraCity AI Architecture Documentation

## System Overview

LaraCity AI is an intelligent complaint management system that combines Laravel's robust framework with advanced AI capabilities to analyze, categorize, and prioritize NYC 311 complaints.

## Architecture Diagram

```mermaid
graph TB
    subgraph "Frontend Layer"
        UI[Livewire Dashboard]
        Chat[AI Chat Agent]
    end

    subgraph "Application Layer"
        Laravel[Laravel Application]
        Queue[Queue Workers]
    end

    subgraph "AI Services"
        Bridge[Python AI Bridge]
        Embeddings[Vector Embedding Service]
        Search[Hybrid Search Service]
    end

    subgraph "Python Layer"
        Python[Python AI Script]
        OpenAI[OpenAI API]
        LangChain[LangChain]
    end

    subgraph "Data Layer"
        Postgres[(PostgreSQL + pgvector)]
        Cache[(Cache Layer)]
    end

    UI --> Laravel
    Chat --> Laravel
    Laravel --> Queue
    Queue --> Bridge
    Laravel --> Search
    Search --> Embeddings
    Search --> Postgres
    Bridge --> Python
    Python --> OpenAI
    Python --> LangChain
    Embeddings --> Bridge
    Bridge --> Postgres
    Laravel --> Postgres
    Laravel --> Cache
```

## Component Architecture

### 1. Frontend Layer

```mermaid
graph LR
    subgraph "Livewire Components"
        Dashboard[Dashboard Component]
        ChatAgent[Chat Agent Component]
        ComplaintTable[Complaint Table]
        Analytics[Analytics Widgets]
    end

    subgraph "UI Features"
        RealTime[Real-time Updates]
        Search[Semantic Search]
        Filter[Smart Filters]
        Export[Data Export]
    end

    Dashboard --> ComplaintTable
    Dashboard --> Analytics
    ChatAgent --> Search
    ComplaintTable --> Filter
    ComplaintTable --> Export
```

### 2. AI Processing Pipeline

```mermaid
sequenceDiagram
    participant User
    participant Laravel
    participant Queue
    participant PythonBridge
    participant OpenAI
    participant Database

    User->>Laravel: Submit Complaint
    Laravel->>Database: Store Raw Complaint
    Laravel->>Queue: Dispatch AnalyzeComplaintJob
    Queue->>PythonBridge: Process Complaint
    PythonBridge->>OpenAI: Analyze with GPT-4
    OpenAI-->>PythonBridge: Return Analysis
    PythonBridge->>OpenAI: Generate Embedding
    OpenAI-->>PythonBridge: Return Vector
    PythonBridge-->>Queue: Analysis Complete
    Queue->>Database: Store Analysis & Embedding
    Queue->>Laravel: Check Risk Score
    alt High Risk Score
        Laravel->>Queue: Dispatch EscalationJob
    end
```

### 3. Search Architecture

```mermaid
graph TD
    subgraph "Search Query Flow"
        Query[User Query]
        Router{Query Router}
        Statistical[Statistical Query]
        Semantic[Semantic Search]
        Metadata[Metadata Search]
    end

    subgraph "Processing"
        Aggregation[DB Aggregation]
        VectorSearch[Vector Similarity]
        SQLSearch[SQL Pattern Match]
        Combiner[Result Combiner]
    end

    Query --> Router
    Router -->|"count, stats"| Statistical
    Router -->|"find similar"| Semantic
    Router -->|"exact match"| Metadata
    
    Statistical --> Aggregation
    Semantic --> VectorSearch
    Metadata --> SQLSearch
    
    VectorSearch --> Combiner
    SQLSearch --> Combiner
    Combiner --> Results[Weighted Results]
```

## Data Flow Architecture

### 1. Complaint Processing Flow

```mermaid
graph LR
    subgraph "Data Input"
        CSV[NYC 311 CSV]
        API[Future API]
        Manual[Manual Entry]
    end

    subgraph "Processing"
        Import[Import Service]
        Validation[Data Validation]
        Enrichment[AI Enrichment]
        Indexing[Vector Indexing]
    end

    subgraph "Storage"
        Complaints[(Complaints Table)]
        Analysis[(Analysis Table)]
        Embeddings[(Embeddings Table)]
    end

    CSV --> Import
    API --> Import
    Manual --> Import
    Import --> Validation
    Validation --> Enrichment
    Enrichment --> Indexing
    Enrichment --> Analysis
    Indexing --> Embeddings
    Validation --> Complaints
```

### 2. AI Service Integration

```mermaid
graph TB
    subgraph "Laravel Services"
        Controller[Controllers]
        Jobs[Queue Jobs]
        Services[Service Layer]
    end

    subgraph "Bridge Layer"
        PythonBridge[Python AI Bridge]
        ProcessManager[Process Manager]
        JSONParser[JSON Parser]
    end

    subgraph "Python Environment"
        Script[ai_analysis.py]
        Deps[Dependencies]
        Models[AI Models]
    end

    Controller --> Services
    Jobs --> Services
    Services --> PythonBridge
    PythonBridge --> ProcessManager
    ProcessManager --> Script
    Script --> Models
    Script --> Deps
    ProcessManager --> JSONParser
    JSONParser --> Services
```

## Database Schema

```mermaid
erDiagram
    complaints ||--o| complaint_analysis : has
    complaints ||--o{ actions : has
    complaints ||--o{ document_embeddings : has
    complaint_analysis ||--o{ document_embeddings : has

    complaints {
        bigint id PK
        string complaint_number UK
        string complaint_type
        text descriptor
        string borough
        string status
        timestamp submitted_at
        decimal latitude
        decimal longitude
    }

    complaint_analysis {
        bigint id PK
        bigint complaint_id FK
        text summary
        float risk_score
        string category
        json tags
    }

    document_embeddings {
        bigint id PK
        string document_type
        bigint document_id
        text content
        json metadata
        vector embedding
        string content_hash
    }

    actions {
        bigint id PK
        bigint complaint_id FK
        string action_type
        text description
        bigint user_id FK
    }
```

## Technology Stack

### Core Framework
- **Laravel 11.x** - Modern PHP framework
- **Livewire 3.x** - Reactive UI components
- **PostgreSQL 15+** - Primary database with pgvector extension

### AI/ML Stack
- **OpenAI GPT-4** - Natural language processing
- **text-embedding-3-small** - Vector embeddings
- **LangChain** - AI orchestration
- **Python 3.9+** - AI processing runtime

### Infrastructure
- **Laravel Herd** - Local development environment
- **Queue Workers** - Asynchronous processing
- **Laravel Horizon** (optional) - Queue monitoring

## Security Architecture

```mermaid
graph TD
    subgraph "Security Layers"
        Auth[Authentication]
        AuthZ[Authorization]
        Validation[Input Validation]
        Sanitization[Output Sanitization]
        Encryption[Data Encryption]
    end

    subgraph "API Security"
        RateLimit[Rate Limiting]
        CORS[CORS Policy]
        CSP[Content Security Policy]
    end

    subgraph "Secrets Management"
        ENV[Environment Variables]
        Config[Laravel Config]
        Vault[Secret Storage]
    end

    Auth --> AuthZ
    AuthZ --> Validation
    Validation --> Sanitization
    
    ENV --> Config
    Config --> Vault
```

## Deployment Architecture

```mermaid
graph TB
    subgraph "Development"
        LocalDev[Local Development]
        Herd[Laravel Herd]
        LocalDB[(Local PostgreSQL)]
    end

    subgraph "Production"
        WebServer[Web Server]
        AppServer[Application Server]
        QueueServer[Queue Workers]
        ProdDB[(Production PostgreSQL)]
        CDN[CDN/Static Assets]
    end

    subgraph "Monitoring"
        Logs[Application Logs]
        Metrics[Performance Metrics]
        Alerts[Alert System]
    end

    LocalDev --> WebServer
    WebServer --> AppServer
    AppServer --> QueueServer
    AppServer --> ProdDB
    WebServer --> CDN
    
    AppServer --> Logs
    QueueServer --> Logs
    Logs --> Metrics
    Metrics --> Alerts
```

## Performance Optimizations

### Caching Strategy
- **Query Results**: 15-minute cache for expensive aggregations
- **Vector Embeddings**: Content-hash based deduplication
- **API Responses**: Response caching for static data

### Queue Optimization
- **Priority Queues**: High-risk complaints processed first
- **Batch Processing**: Bulk embedding generation
- **Rate Limiting**: Respect API quotas

### Database Optimization
- **Indexes**: Strategic indexing on search fields
- **Partitioning**: Time-based partitioning for complaints
- **Connection Pooling**: Efficient database connections

## Scalability Considerations

1. **Horizontal Scaling**
   - Queue workers can be scaled independently
   - Read replicas for search operations
   - Load balancing for web traffic

2. **Vertical Scaling**
   - AI processing requires adequate memory
   - Vector operations benefit from CPU optimization
   - Database needs sufficient storage for embeddings

3. **Service Separation**
   - AI services can be extracted to microservices
   - Search can be moved to dedicated infrastructure
   - Static assets served from CDN

## Future Architecture Enhancements

1. **Real-time Features**
   - WebSocket integration for live updates
   - Streaming AI responses
   - Real-time collaboration

2. **Advanced AI Features**
   - Multi-modal analysis (images, audio)
   - Predictive analytics
   - Automated response generation

3. **Integration Possibilities**
   - Direct NYC 311 API integration
   - Third-party notification services
   - External analytics platforms