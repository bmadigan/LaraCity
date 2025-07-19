# LaraCity: LangChain RAG Tutorial

**An AI-Powered Municipal Complaint Management System**

> 🎯 **Main Focus**: This project serves as a **beginner's guide to LangChain integration** with real-world civic data. Laravel provides the necessary infrastructure, but the **main educational value** lies in the Python/AI components that will be built in later phases.

---

## Table of Contents

1. [Project Overview & Learning Goals](#1-project-overview--learning-goals)
2. [Phase B: Laravel Foundation](#2-phase-b-laravel-foundation-current)
3. [Phase C: API Setup](#3-phase-c-api-setup-upcoming)
4. [Phase D: PHP-Python Bridge](#4-phase-d-php-python-bridge-upcoming)
5. [Phase E: LangChain Deep Dive](#5-phase-e-langchain-deep-dive-main-focus)
6. [Phase F: Vector Database Integration](#6-phase-f-vector-database-integration-upcoming)
7. [Phase G: Production Considerations](#7-phase-g-production-considerations-upcoming)
8. [Next Steps](#8-next-steps)

---

## 1. Project Overview & Learning Goals

**LaraCity** transforms NYC 311 complaint data into actionable insights through AI analysis. The system demonstrates:

- **Smart Data Import**: CSV-based NYC 311 data ingestion with validation
- **AI Analysis**: Automated complaint categorization, risk scoring, and summarization
- **Natural Language Queries**: "Show me noise complaints in Queens last week"
- **Risk Escalation**: Automated Slack alerts when complaints exceed risk thresholds
- **Audit Trail**: Complete action logging and user question tracking

### 🎓 Primary Learning Objectives

This tutorial teaches **LangChain implementation for beginners** using real civic data:

1. **RAG Implementation**: Using vectorized CSV data for intelligent question answering
2. **PromptTemplates**: Structured prompts for consistent AI responses  
3. **Few-Shot Learning**: Examples-based prompt engineering
4. **LCEL (LangChain Expression Language)**: Beginner-friendly chain composition
5. **OpenAI Integration**: API usage patterns and best practices
6. **Vector Search**: Semantic similarity for complaint discovery

---

## 2. Phase B: Laravel Foundation (COMPLETED)

**Status**: ✅ **COMPLETED**

### What We Built

#### 🗄️ Database Schema Design

We created a comprehensive database schema for managing NYC 311 complaint data:

**1. Complaints Table** - Core 311 records from CSV data
```sql
-- Core complaint identifiers
complaint_number (unique)    -- Maps from NYC 'unique_key'
complaint_type              -- Type of complaint
descriptor                  -- Detailed description

-- Agency information  
agency                      -- Agency code (NYPD, DSNY, etc.)
agency_name                 -- Full agency name

-- Location details
borough                     -- MANHATTAN, BROOKLYN, QUEENS, BRONX, STATEN ISLAND
city                        -- Typically "NEW YORK"
incident_address           -- Full street address
latitude, longitude        -- GPS coordinates for mapping
incident_zip               -- ZIP code

-- Status and priority
status                     -- Open, InProgress, Closed, Escalated
priority                   -- Low, Medium, High, Critical
resolution_description     -- How complaint was resolved

-- Temporal data
submitted_at               -- When complaint was filed
resolved_at                -- When complaint was closed
due_date                   -- Expected resolution date
```

**2. Complaint Analyses Table** - AI-generated insights
```sql
complaint_id               -- Foreign key to complaints
summary                    -- AI-generated summary
risk_score                 -- 0.0-1.0 risk assessment  
category                   -- AI-normalized category
tags                       -- JSON array of extracted tags
```

**3. Actions Table** - Audit trail for all system actions
```sql
type                       -- escalate, summarize, notify, analyze
parameters                 -- JSON context for the action
triggered_by               -- user_id or 'system'
complaint_id              -- Optional reference to related complaint
```

**4. User Questions Table** - Natural language query log + Chat History
```sql
question                   -- Raw user input
parsed_filters            -- Extracted filters JSON
ai_response               -- Generated answer from RAG system
embedding                 -- Question embedding for similarity search
conversation_id           -- For multi-turn chat sessions
```

#### 📊 Database Design Decisions Explained

**Why these specific tables?**

1. **Separation of Concerns**: Core complaint data (CSV import) is separate from AI analysis (generated later)
2. **Audit Trail**: Every action is logged for compliance and debugging
3. **Chat Memory**: User questions are stored with embeddings for improved context in future conversations
4. **Flexible Status System**: Enums allow controlled status transitions
5. **Performance Indexes**: Strategic indexes on frequently queried columns (borough + date, complaint_type + status)

**Real Example**: A noise complaint in Brooklyn gets imported → AI analysis generates risk score → If high risk → Action logged → Slack notification sent → All steps audited

#### 🔧 Laravel Migration Patterns for Civic Data

**Migration Structure** (Created in dependency order):
```php
// 1. Core complaints table first
2025_07_19_172522_create_complaints_table.php

// 2. Analysis table (depends on complaints)
2025_07_19_172632_create_complaint_analyses_table.php  

// 3. Actions table (optional complaint reference)
2025_07_19_172633_create_actions_table.php

// 4. User questions table (independent)
2025_07_19_172633_create_user_questions_table.php
```

**Key Migration Patterns**:
```php
// 1. Proper foreign key constraints with cascade
$table->foreignId('complaint_id')->constrained()->onDelete('cascade');

// 2. Enums for controlled values
$table->enum('status', ['Open', 'InProgress', 'Closed', 'Escalated']);

// 3. JSON columns for flexible data
$table->json('tags')->nullable(); // AI-extracted tags
$table->json('parameters'); // Action context

// 4. Performance indexes on query patterns
$table->index(['complaint_type', 'status']); // Common filter combination
$table->index(['borough', 'submitted_at']); // Geographic + temporal queries
```

#### 🏗️ Eloquent Model Architecture

**Complaint Model** - Main entity with relationships and business logic:
```php
class Complaint extends Model
{
    // Constants for referential integrity
    const STATUS_OPEN = 'Open';
    const BOROUGH_MANHATTAN = 'MANHATTAN';
    
    // Relationships
    public function analysis(): HasOne
    public function actions(): HasMany
    
    // Business logic
    public function isHighRisk(): bool
    {
        return $this->analysis && $this->analysis->risk_score >= 0.7;
    }
    
    // Query scopes for common patterns
    public function scopeByBorough($query, string $borough)
    public function scopeByDateRange($query, $start, $end)
}
```

**Why this model design?**
- **Constants**: Prevent typos and ensure consistency across the application
- **Relationships**: Eloquent handles foreign key relationships automatically
- **Business Logic**: Domain-specific methods like `isHighRisk()` for readable code
- **Query Scopes**: Reusable query patterns for filtering by common criteria

#### 📥 CSV Import System

**CsvImportService** - Robust NYC 311 data processing:

**Key Features**:
1. **Header Detection**: Automatically maps NYC 311 CSV columns to database fields
2. **Data Validation**: Ensures required fields are present and valid
3. **Error Handling**: Graceful handling of malformed rows with detailed logging
4. **Batch Processing**: Processes large files (534MB+) in configurable batches
5. **Upsert Logic**: Prevents duplicates by updating existing complaints

**Real CSV Processing Example**:
```php
// NYC 311 CSV Header Mapping
'Unique Key' => 'complaint_number'
'Created Date' => 'submitted_at'
'Complaint Type' => 'complaint_type'
'Borough' => 'borough'
'Latitude' => 'latitude'

// Data Transformation Example
Input:  "07/13/2025 02:49:10 AM" (NYC format)
Output: Carbon::createFromFormat('m/d/Y h:i:s A', ...) (Laravel datetime)

Input:  "brooklyn" (various cases in CSV)
Output: "BROOKLYN" (normalized uppercase)
```

**Command Usage**:
```bash
# Import with validation
php artisan lacity:import-csv --file=storage/311-data.csv --validate

# Import summary shows:
# ├── Records Processed: 1,234,567
# ├── New Records Imported: 1,200,000  
# ├── Existing Records Updated: 34,567
# ├── Records Skipped: 0
# ├── Errors: 0
# └── Success Rate: 100%
```

#### 🛠️ Error Handling & Validation Strategies

**Multi-Layer Validation**:
```php
// 1. Header validation - ensure required columns exist
$required = ['complaint_number', 'submitted_at', 'complaint_type', 'agency'];

// 2. Row validation - check data integrity
if (empty($data['complaint_number'])) {
    $this->addError($lineNumber, "Missing complaint number");
}

// 3. Date parsing with fallbacks
try {
    return Carbon::createFromFormat('m/d/Y h:i:s A', trim($dateString));
} catch (\Exception) {
    // Try alternative format
    return Carbon::parse(trim($dateString));
}

// 4. Graceful coordinate handling
$latitude = is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
```

**Why this approach?**
- **Fail Fast**: Invalid headers stop import immediately
- **Graceful Degradation**: Bad rows are skipped with logging, import continues
- **Comprehensive Logging**: Every error is logged with line numbers for debugging
- **Progress Tracking**: Large imports show progress every 10k records

---

## 3. Phase C: API Setup (Upcoming)

**Purpose**: Create REST API endpoints for data access and filtering

**Planned Components**:
- Authenticated API routes using Livewire Starter Kit auth
- `GET /api/complaints` with advanced filtering (borough, type, status, date range)
- `GET /api/complaints/summary` for aggregated statistics  
- `POST /api/actions/escalate` for batch operations
- API Resources for consistent JSON transformation

**Learning Focus**: Laravel API design patterns for data-heavy applications

---

## 4. Phase D: PHP-Python Bridge (Upcoming) 

**Purpose**: Establish communication between Laravel and Python AI components

**Planned Components**:
- `AnalyzeComplaintJob` for async AI processing
- `PythonAiBridge` service using Symfony Process
- Risk escalation workflow with Slack notifications
- Observer pattern for automatic analysis triggers

**Learning Focus**: Async job architecture and inter-process communication

---

## 5. Phase E: LangChain Deep Dive (🎯 MAIN FOCUS)

**Purpose**: **Primary learning objective** - Comprehensive LangChain implementation for beginners

### 🎓 Detailed Learning Sections (Coming Soon)

#### OpenAI Setup & Configuration
```python
from langchain_openai import ChatOpenAI

# Why we configure the client this way...
llm = ChatOpenAI(
    api_key=os.getenv("OPENAI_API_KEY"),
    model="gpt-4o-mini",
    temperature=0.1,  # Lower temperature for consistent analysis
    timeout=30        # Prevent hanging requests
)
```

#### PromptTemplates Explained
```python
# Show actual civic data examples
risk_analysis_prompt = PromptTemplate(
    input_variables=["complaint_type", "description", "location"],
    template="""
    Analyze this NYC 311 complaint for risk level:
    Type: {complaint_type}
    Description: {description}
    Location: {location}

    Examples of high-risk complaints:
    - "Gas leak reported at residential building"
    - "Structural damage to apartment building"
    """
)
```

#### LCEL for Beginners
```python
# Beginner-friendly chain explanation
analysis_chain = (
    risk_analysis_prompt
    | llm
    | StrOutputParser()
)
# Each | operator explained step by step
```

**Full Architecture**: Complete Python project structure with real NYC 311 examples

---

## 6. Phase F: Vector Database Integration (Upcoming)

**Purpose**: Enable semantic search and RAG functionality using pgvector

**Planned Components**:
- pgvector PostgreSQL extension setup
- Embedding generation for complaints and questions
- Hybrid search combining vector similarity + metadata filtering
- LangChain vector store integration

**Learning Focus**: Vector databases, embedding strategies, hybrid search patterns

---

## 7. Phase G: Production Considerations (Upcoming)

**Purpose**: Documentation, demos, and production readiness

**Planned Components**:
- Complete installation and setup guides
- Demo scripts showcasing AI capabilities
- Performance benchmarks and optimization
- Portfolio-ready documentation

---

## 8. Next Steps

### Current Status: Phase B Complete ✅

**What's Ready**:
- ✅ Database schema with 4 tables and proper relationships
- ✅ Eloquent models with business logic and query scopes  
- ✅ CSV import system handling 534MB+ files
- ✅ Artisan command with validation and progress tracking
- ✅ Configuration system for LaraCity-specific settings

**Immediate Next Phase**: Phase C - API Controllers
```bash
# Ready to execute:
/phase:c-api-controllers
```

### Key Learnings from Phase B

1. **Database Design**: How to structure civic data for AI analysis
2. **Laravel Patterns**: Migration dependencies, model relationships, service classes
3. **CSV Processing**: Robust import strategies for large government datasets
4. **Error Handling**: Multi-layer validation with graceful degradation
5. **Performance**: Batch processing and strategic database indexing

### Foundation for AI Components

The database schema we built directly supports the upcoming LangChain integration:

- **Embeddings Ready**: `embedding` columns prepared for Phase F vector storage
- **Conversation History**: User questions table ready for chat memory
- **Audit Trail**: Actions table will track all AI-triggered operations  
- **Risk Assessment**: Analysis table structure ready for AI-generated insights

#### 🧪 Factories for Demo Data Generation

**ComplaintAnalysisFactory** - AI-style analysis generation:

**Key Features**:
- **Risk-Based Generation**: Different risk scores based on complaint types
- **Realistic Summaries**: Context-aware AI-style summaries for each complaint type
- **Smart Categorization**: Automatic category assignment (Infrastructure, Quality of Life, etc.)
- **Dynamic Tagging**: Risk and type-based tag generation

**Example Factory Usage**:
```php
// Generate high-risk analysis
ComplaintAnalysis::factory()->highRisk()->create([
    'complaint_id' => $complaint->id
]);

// Risk scores automatically generated based on complaint type:
// Water/Heat complaints: 0.6-1.0 (high risk)
// Noise/Parking complaints: 0.0-0.4 (low risk)
// Other complaints: 0.2-0.7 (medium risk)
```

**ActionFactory** - Audit trail generation:

**Features**:
- **Type-Specific Parameters**: Different JSON parameters for each action type
- **Realistic Workflows**: Escalation → Notification → Analysis chains
- **System vs User Actions**: Proper attribution of automated vs manual actions

```php
// Generate escalation workflow
Action::factory()->escalation()->create(); // High-risk complaint escalated
Action::factory()->notification()->create(); // Slack alert sent
Action::factory()->analysis()->create(); // AI analysis completed
```

#### 🌱 Comprehensive DatabaseSeeder

**Intelligent Seeding Strategy**:

**1. User Management**:
```php
// Creates demo users with role-based names
User::firstOrCreate(['email' => 'admin@laracity.test'], ['name' => 'LaraCity Admin']);
User::firstOrCreate(['email' => 'analyst@laracity.test'], ['name' => 'Data Analyst']);
User::firstOrCreate(['email' => 'field@laracity.test'], ['name' => 'Field Coordinator']);
```

**2. Smart Complaint Analysis**:
- **If no real data**: Creates 130 demo complaints with realistic NYC 311 patterns
- **If real data exists**: Analyzes sample of existing complaints (10% or max 200)
- **Analysis Generation**: Risk scores and summaries based on complaint characteristics

**3. Realistic Data Patterns**:
```php
// High-risk complaints (water, heat)
Complaint::factory(15)->create(['complaint_type' => 'Water System', 'borough' => 'MANHATTAN']);

// Medium-risk complaints (street conditions)  
Complaint::factory(25)->create(['complaint_type' => 'Street Condition', 'borough' => 'QUEENS']);

// Low-risk complaints (noise, parking)
Complaint::factory(50)->create(['complaint_type' => 'Noise - Street/Sidewalk', 'borough' => 'MANHATTAN']);
```

**4. RAG System Preparation**:
```php
// Sample user questions for chat system
$sampleQuestions = [
    'Show me all noise complaints in Manhattan from last week',
    'What are the highest risk complaints currently open?',
    'Find all complaints with risk score above 0.8'
];

// Each question gets parsed filters and sample AI responses
```

#### 📊 Current Database State

**After Phase B completion**:
```
📊 Database Seeding Summary:
├── Users: 3 (Admin, Analyst, Field Coordinator)
├── Complaints: 4,999 (Real NYC 311 data)
├── Complaint Analyses: 394 (AI-generated insights)
├── Actions: 38 (Escalations, notifications, analysis)
└── User Questions: 10 (Sample chat queries)
```

**Analysis Distribution**:
- **High-risk complaints**: 6 (risk_score >= 0.7) 
- **Sample analysis**: Real complaints now have AI summaries, risk scores, categories, and tags
- **Action audit trail**: Escalations and notifications for high-risk complaints

#### 💡 Why This Foundation Matters

**For AI Integration (Phases E-F)**:
1. **Analysis Table Ready**: Structure prepared for real AI-generated insights
2. **Risk Assessment Framework**: Risk scoring system established for escalation thresholds
3. **Action Logging**: Complete audit trail for AI-triggered operations
4. **Chat History**: User questions table ready for RAG conversation memory
5. **Tag System**: JSON tag storage ready for AI-extracted metadata

**For API Development (Phase C)**:
1. **Rich Relationships**: Complaints → Analysis → Actions fully connected
2. **Query Optimization**: Strategic indexes for filtering and aggregation
3. **Demo Data**: Realistic test data for API endpoint development
4. **User System**: Authentication ready for protected API routes

**Real Example Workflow**:
```
1. NYC 311 complaint imported → Complaint record created
2. DatabaseSeeder runs → AI analysis generated with risk score
3. If risk_score >= 0.7 → Action records created for escalation
4. Action audit trail → Complete transparency of system decisions
5. User asks question → UserQuestion record for RAG context
```

**This solid Laravel foundation enables the exciting AI components coming in Phases E-F!**

---

*📚 This tutorial will be significantly expanded during Phase E with comprehensive LangChain examples, code walkthroughs, and beginner explanations of RAG implementation patterns.*