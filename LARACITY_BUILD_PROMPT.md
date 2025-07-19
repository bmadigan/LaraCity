# LaraCity Claude Code Build Prompt
## Ultra-Think AI-Powered Municipal Complaint Management System

---

## 📚 Tutorial Documentation Requirements

**CRITICAL: Tutorial-Details.md Creation & Updates**

Every phase MUST update `Tutorial-Details.md` with:

### 📝 **Documentation Standards:**
- **Beginner-Focused**: Target developers new to Python/LangChain
- **Code Examples**: Include actual code snippets from implementation
- **Step-by-Step**: Break complex concepts into digestible steps
- **Why + How**: Explain reasoning behind technical decisions
- **Real Examples**: Use actual NYC 311 data in explanations

### 📖 **Required Tutorial Sections:**

```markdown
# LaraCity: LangChain RAG Tutorial

## Table of Contents
1. Project Overview & Learning Goals
2. Phase B: Laravel Foundation (Brief)
3. Phase C: API Setup (Brief)
4. Phase D: PHP-Python Bridge (Brief)
5. Phase E: LangChain Deep Dive (🎯 MAIN FOCUS)
   - OpenAI Setup & Configuration
   - PromptTemplates Explained
   - Few-Shot Learning with Examples
   - LCEL for Beginners
   - RAG System Implementation
   - Chat Agent Development
6. Phase F: Vector Database Integration
7. Phase G: Production Considerations
8. Troubleshooting Guide
9. Next Steps & Advanced Topics
```

### 🎯 **Phase E Focus Areas (80% of tutorial content):**

**OpenAI Integration:**
```python
# Example with explanation
from langchain_openai import ChatOpenAI

# Why we configure the client this way...
llm = ChatOpenAI(
    api_key=os.getenv("OPENAI_API_KEY"),
    model="gpt-4o-mini",
    temperature=0.1,  # Lower temperature for consistent analysis
    timeout=30        # Prevent hanging requests
)
```

**PromptTemplates with Examples:**
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

**LCEL Chain Building:**
```python
# Beginner-friendly chain explanation
analysis_chain = (
    risk_analysis_prompt
    | llm
    | StrOutputParser()
)
# Each | operator explained step by step
```

---

## 🎯 ULTRA-THINK CONTRACT (MANDATORY START)

> **COPY THIS INTO EVERY CLAUDE CODE SESSION:**
>
> **ULTRA-THINK PROTOCOL FOR LARACITY**
> Before ANY code generation: STOP → THINK DEEPLY → PLAN → VALIDATE ASSUMPTIONS
>
> **Pre-Code Checklist (EVERY TIME):**
> 1. 🔍 **EXPLORE**: Scan complete file tree, detect configuration drift, identify blockers
> 2. 🎯 **ASSESS**: Confirm Laravel version, dependencies, database state, env configuration
> 3. 📋 **PLAN**: Map file paths → operations → validation tests (small, reviewable changesets)
> 4. ⚡ **EXECUTE**: Write code in digestible chunks, request approval between bundles
> 5. ✅ **VALIDATE**: Run migrations/tests, summarize results, propose next logical step
> 6. 🧠 **REFLECT**: "Go Slow to Go Smart" - pause before phase completion
>
> **Use "think", "think hard", "ultrathink" commands when complexity increases**
> **Request user approval before major file modifications or multi-component changes**

---

## 📊 Project Overview: LaraCity

**LaraCity** is an AI-powered municipal complaint management system that transforms civic 311 data into actionable insights through:

- **Smart Data Import**: CSV-based NYC 311 data ingestion with validation
- **AI Analysis**: Automated complaint categorization, risk scoring, and summarization
- **Natural Language Queries**: "Show me noise complaints in Queens last week"
- **Risk Escalation**: Automated Slack alerts when complaints exceed risk thresholds
- **Audit Trail**: Complete action logging and user question tracking

## 🎓 **TUTORIAL FOCUS: Python + LangChain Integration**

**Primary Learning Objective**: This project serves as a **beginner's guide to LangChain integration** with real-world civic data. Laravel provides the necessary infrastructure, but the **main educational value** lies in the Python/AI components:

- **RAG Implementation**: Using vectorized CSV data for intelligent question answering
- **PromptTemplates**: Structured prompts for consistent AI responses
- **Few-Shot Learning**: Examples-based prompt engineering
- **LCEL (LangChain Expression Language)**: Beginner-friendly chain composition
- **OpenAI Integration**: API usage patterns and best practices
- **Vector Search**: Semantic similarity for complaint discovery

---

## 🏗️ Architecture Stack

- **Backend**: Laravel 12 + Livewire Starter Kit (auth complete) - *Supporting Infrastructure*
- **Database**: PostgreSQL with **pgvector embeddings** - *Required for RAG*
- **AI Core**: **Python + LangChain + OpenAI** - *Primary Focus*
  - **RAG System**: Vectorized CSV data for intelligent Q&A
  - **PromptTemplates**: Structured, reusable prompts
  - **Few-Shot Learning**: Example-driven prompt engineering
  - **LCEL**: Beginner-friendly chain composition
  - **Vector Search**: Semantic similarity using pgvector
- **Bridge Layer**: Laravel → Python Process communication
- **Queue System**: Laravel Jobs for async AI processing
- **Notifications**: Slack webhooks for escalations
- **Data Source**: NYC 311 CSV files (uploaded to storage)

---

## 📁 Data Model Schema + Vectorization Strategy

```
complaints            # Core 311 records from CSV
├── complaint_number  # Unique identifier from NYC data (unique_key)
├── complaint_type    # Mapped from CSV complaint_type
├── descriptor        # Detailed complaint description from CSV
├── agency           # Responsible city agency (mapped from agency)
├── agency_name      # Full agency name for display
├── borough          # Normalized borough name (MANHATTAN, BROOKLYN, etc.)
├── city             # City name (typically "NEW YORK")
├── incident_address # Full street address where incident occurred
├── street_name      # Parsed street name for filtering
├── cross_street_1   # First cross street (if available)
├── cross_street_2   # Second cross street (if available)
├── incident_zip     # ZIP code of incident location
├── address_type     # Location type (Residential, Commercial, etc.)
├── latitude         # GPS latitude coordinate
├── longitude        # GPS longitude coordinate
├── location_type    # How location was determined (address, intersection, etc.)
├── status           # Open, InProgress, Closed, Escalated
├── resolution_description # How the complaint was resolved
├── community_board  # NYC community board number
├── council_district # NYC council district number
├── police_precinct  # NYPD precinct number
├── school_district  # School district number
├── priority         # Low, Medium, High, Critical (derived)
├── submitted_at     # Parsed from CSV created_date
├── resolved_at      # Parsed from CSV closed_date
├── due_date         # Expected resolution date
├── facility_type    # Type of facility (if applicable)
├── park_facility_name # Park name (for Parks Dept complaints)
├── vehicle_type     # Vehicle type (for transportation complaints)
└── embedding        # pgvector column for RAG/semantic search

complaint_analyses    # AI-generated insights
├── complaint_id     # FK to complaints
├── summary          # AI-generated summary
├── risk_score       # 0.0-1.0 risk assessment
├── category         # AI-normalized category
└── tags             # JSON array of extracted tags

actions              # Audit trail
├── type            # escalate, summarize, notify
├── parameters      # JSON of action context
└── triggered_by    # user_id or 'system'

user_questions       # Natural language query log + Chat History
├── question        # Raw user input
├── parsed_filters  # Extracted filters JSON
├── ai_response     # Generated answer from RAG system
├── embedding       # Question embedding for similarity search
└── conversation_id # For multi-turn chat sessions
```

## 🔍 **Vectorization Strategy for Chat Agent**

**YES - We vectorize data for human interaction questions:**

1. **Complaint Embeddings**: Each complaint gets vectorized (description + type + location)
2. **Question Embeddings**: User questions are embedded for similarity matching
3. **RAG Pipeline**: Question → Find similar complaints → Generate contextual answer
4. **Chat Memory**: Conversation history with embeddings for context continuity
5. **Hybrid Search**: Vector similarity + metadata filtering (borough, date, type)

---

## 📂 CSV Data Mapping (NYC 311 OpenData Export)

**Expected CSV Columns:**
- `unique_key` → `complaint_number`
- `created_date` → `submitted_at` (timezone-aware parsing)
- `closed_date` → `resolved_at` (nullable)
- `complaint_type` → `complaint_type`
- `descriptor` → `description`
- `incident_address` → `address`
- `borough` → `borough` (normalized to uppercase)
- `latitude` → `latitude` (decimal)
- `longitude` → `longitude` (decimal)
- `status` → `status` (mapped to enum)

**CSV Processing Features:**
- Robust header detection with whitespace trimming
- Duplicate prevention via `complaint_number` upserts
- Data validation and error reporting
- Batch processing for large files
- Progress tracking with user feedback

---

## 🚀 Build Phases (Copy-Paste Slash Commands)

### Phase B: Database Foundation + CSV Importer

```
/phase:b-csv-foundation
ULTRATHINK: Build LaraCity database schema + CSV importer for NYC 311 data.

REQUIREMENTS:
- 4 migrations: complaints, complaint_analyses, actions, user_questions
- Eloquent models with relationships and constants
- CSV importer service using Laravel's file handling
- Factories for demo data generation
- Artisan command: lacity:import-csv --file=storage/311-data.csv --validate
- DatabaseSeeder with user creation and sample data
- CREATE Tutorial-Details.md with Phase B documentation

VALIDATION POINTS:
- complaint_number unique constraint
- Timezone-safe date parsing (America/Toronto default)
- Borough normalization (uppercase)
- Graceful handling of malformed CSV rows
- Upsert logic to prevent duplicates on re-import

TUTORIAL DOCUMENTATION (Tutorial-Details.md):
- Explain Laravel migration patterns for civic data
- Document CSV parsing strategies and error handling
- Show database design decisions for 311 data structure
- Include actual migration code examples with explanations

Plan first, await approval, then execute in small batches.
```

### Phase C: REST API + Filtering System

```
/phase:c-api-controllers
ULTRATHINK: Implement LaraCity REST API with advanced filtering.

REQUIREMENTS:
- Authenticated API routes (Livewire Starter Kit auth)
- GET /api/complaints (filters: borough, type, status, date range)
- GET /api/complaints/summary (aggregated stats using same filter base)
- GET /api/complaints/{id} (with analysis relationship)
- POST /api/actions/escalate (batch escalation with filters)
- POST /api/user-questions (capture natural language queries)
- API Resources for consistent JSON structure
- Controller tests covering filter combinations
- UPDATE Tutorial-Details.md with Phase C documentation

CRITICAL: Summary endpoint must reuse filtered query builder to ensure consistent totals.

TUTORIAL DOCUMENTATION (Tutorial-Details.md):
- Document API design patterns for data-heavy applications
- Explain filtering strategies for large datasets
- Show Laravel Resource transformation examples
- Brief explanation of preparing data endpoints for Python consumption

Plan endpoint structure, then implement with validation.
```

### Phase D: AI Analysis Pipeline + Risk Escalation

```
/phase:d-ai-workflow
ULTRATHINK: Build AI analysis jobs + risk-based escalation system.

REQUIREMENTS:
- AnalyzeComplaintJob: processes complaints via PrismPHP or Python bridge
- ComplaintObserver: auto-trigger analysis on new complaints
- Risk escalation cascade: FlagComplaintJob → SendSlackAlertJob → LogComplaintEscalationJob
- Config: complaints.escalate_threshold (env: COMPLAINT_ESCALATE_THRESHOLD)
- PythonAiBridge service using Symfony Process (prepare for LangChain integration)
- Slack notification with AI-condensed summaries (<200 chars)
- Action audit logging for all escalations
- Git commits
- UPDATE Tutorial-Details.md with Phase D documentation

WORKFLOW:
1. New complaint → Observer → AnalyzeComplaintJob
2. AI analysis → Store ComplaintAnalysis
3. If risk_score ≥ threshold → Escalation cascade
4. Log all actions with parameters

TUTORIAL DOCUMENTATION (Tutorial-Details.md):
- Explain Laravel Job architecture for AI processing
- Document PHP → Python process communication setup
- Show async workflow patterns for AI applications
- Prepare foundation for LangChain integration (next phase)

Plan job dependencies, then implement queue workflow.
```

### Phase E: LangChain Integration (🎯 PRIMARY FOCUS)

```
/phase:e-langchain-rag
ULTRATHINK: Create comprehensive LangChain RAG system for beginners.

🎓 LEARNING OBJECTIVES (Beginner Python Developer Focus):
- OpenAI API integration with error handling
- PromptTemplates for consistent, reusable prompts
- Few-Shot learning with real civic data examples
- LCEL (LangChain Expression Language) chains for beginners
- RAG implementation using vectorized CSV data
- Chat agent with conversation memory

REQUIREMENTS:
├── lacity-ai/
│   ├── requirements.txt (langchain, langchain-openai, faiss, python-dotenv)
│   ├── config.py (centralized settings management)
│   ├── models/
│   │   ├── openai_client.py (API client with retry logic)
│   │   └── embeddings.py (OpenAI embedding generation)
│   ├── prompts/
│   │   ├── templates.py (PromptTemplate definitions)
│   │   ├── few_shot_examples.py (NYC 311 examples)
│   │   └── system_prompts.py (role definitions)
│   ├── chains/
│   │   ├── analysis_chain.py (LCEL: complaint analysis)
│   │   ├── rag_chain.py (LCEL: question answering)
│   │   └── chat_chain.py (conversational interface)
│   ├── rag/
│   │   ├── vector_store.py (FAISS/pgvector setup)
│   │   ├── document_loader.py (CSV → Documents)
│   │   └── retriever.py (similarity search)
│   ├── tools/
│   │   ├── laravel_api.py (HTTP client for Laravel endpoints)
│   │   └── date_parser.py (natural language date parsing)
│   ├── agents/
│   │   └── civic_assistant.py (main chat agent)
│   ├── langchain_runner.py (Laravel bridge entry point)
│   └── README.md (complete beginner setup guide)
|   Git commits
|   Update Tutorial

LANGCHAIN COMPONENTS TO IMPLEMENT:
1. **OpenAI Integration**: Client setup, API key management, error handling
2. **PromptTemplates**: Risk analysis, summarization, question answering
3. **Few-Shot Examples**: Real NYC 311 complaints with expected outputs
4. **LCEL Chains**: Beginner-friendly chain composition with | operator
5. **RAG System**: Document loading, vectorization, retrieval, generation
6. **Chat Agent**: Memory management, conversation flow, tool usage

TUTORIAL DOCUMENTATION (Tutorial-Details.md - MAJOR UPDATE):
📚 Complete beginner's guide sections:
- "Setting up LangChain with OpenAI" (API keys, environment)
- "Understanding PromptTemplates" (with NYC 311 examples)
- "Few-Shot Learning Explained" (show examples vs training)
- "LCEL for Beginners" (chain composition step-by-step)
- "Building RAG Systems" (vectorize → store → retrieve → generate)
- "Creating Chat Agents" (memory, tools, conversation flow)
- All code examples with line-by-line explanations
- Common troubleshooting and error handling patterns

Plan comprehensive Python architecture, then implement incrementally with extensive documentation.
```

### Phase F: pgvector Integration (Required for RAG)

```
/phase:f-pgvector-rag
ULTRATHINK: Implement pgvector database integration for RAG system.

NOTE: This is REQUIRED (not optional) for the RAG functionality.

REQUIREMENTS:
- Enable pgvector PostgreSQL extension
- Add embedding columns to complaints and user_questions tables
- Migrate existing complaints to include embeddings
- Python scripts for batch embedding generation
- Hybrid search: vector similarity + metadata filtering
- Integration with LangChain vector store
- Performance optimization for similarity queries
- Git commits
- UPDATE Tutorial-Details.md with Phase F documentation

DELIVERABLES:
- Migration: ADD embedding column (vector type)
- Python: batch_embeddings.py (process all complaints)
- Laravel: EmbeddingService for PHP → Python embedding calls
- LangChain: PgVectorStore integration
- Performance: proper indexes for vector operations

TUTORIAL DOCUMENTATION (Tutorial-Details.md):
- "Understanding Vector Databases" (beginner explanation)
- "pgvector Setup and Configuration" (PostgreSQL extension)
- "Embedding Generation Strategies" (when to embed, batch processing)
- "Hybrid Search Patterns" (vector + metadata combination)
- "Performance Considerations" (indexing, query optimization)

Plan vector database integration, then implement with performance focus.
```

### Phase G: Documentation + Demo Polish

```
/phase:g-documentation
ULTRATHINK: Create comprehensive docs + portfolio-ready demos.

REQUIREMENTS:
- README.md: Installation, quickstart, demo scenarios
- Demo scripts: CSV import, API calls, natural language queries
- Architecture documentation for technical review
- "What This Demonstrates" section for recruiters
- Claude Code usage guide with slash commands
- Environment variable documentation
- Git commits
- FINALIZE Tutorial-Details.md with complete learning path

PORTFOLIO HIGHLIGHTS:
- LangChain RAG implementation from scratch
- OpenAI integration with best practices
- Vector database usage in production
- PHP ↔ Python bridge architecture
- Civic data processing at scale
- Clean API design patterns

TUTORIAL DOCUMENTATION (Tutorial-Details.md - FINAL):
📖 Complete tutorial sections:
- "Project Overview and Learning Objectives"
- "Phase-by-Phase Implementation Guide"
- "LangChain Concepts Explained" (comprehensive beginner guide)
- "Common Patterns and Best Practices"
- "Troubleshooting Guide"
- "Next Steps and Advanced Topics"
- "Additional Resources for LangChain Learning"

Plan documentation structure, then create comprehensive guides.
```

---

## 🔧 Environment Configuration

**Copy to `.env.example`:**
```bash
# === LaraCity Configuration ===
APP_NAME="LaraCity"
APP_TIMEZONE=America/Toronto

# Database (PostgreSQL with pgvector)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laracity
DB_USERNAME=postgres
DB_PASSWORD=postgres

# AI & LangChain
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
COMPLAINT_ESCALATE_THRESHOLD=0.7

# Python/LangChain Configuration
LANGCHAIN_TRACING_V2=false
LANGCHAIN_API_KEY=optional-for-debugging
EMBEDDING_DIMENSION=1536
VECTOR_SEARCH_K=5

# CSV Import Settings
LARACITY_CSV_BATCH_SIZE=1000
LARACITY_TIMEZONE=America/Toronto

# Notifications
SLACK_WEBHOOK_URL=https://hooks.slack.com/your-webhook

# RAG Configuration
RAG_CHUNK_SIZE=1000
RAG_CHUNK_OVERLAP=200
SIMILARITY_THRESHOLD=0.8
```

---

## 📋 Claude Code Slash Commands Reference

```bash
# Essential Commands (save to CLAUDE_CMDS.md)
/explore                    # Scan repo state, detect missing files
/phase:b-csv-foundation     # Database + CSV importer + START Tutorial-Details.md
/phase:c-api-controllers    # REST API + filtering + UPDATE tutorial
/phase:d-ai-workflow        # AI jobs + escalation + UPDATE tutorial
/phase:e-langchain-rag      # 🎯 LangChain RAG system + MAJOR tutorial update
/phase:f-pgvector-rag       # Vector database integration + UPDATE tutorial
/phase:g-documentation      # Docs + demo polish + FINALIZE tutorial
/reflect                    # Summarize progress + next steps
/ultrathink                 # Deep planning mode for complex changes
```

---

## 🎬 Kickoff Template

**Paste this to start your Claude Code session:**

```
ULTRATHINK START: LARACITY BUILD
Project: AI-Powered Municipal Complaint Management
Root: /path/to/your/laracity

CONFIRMED:
✅ Laravel 12 + Livewire Starter Kit installed
✅ PostgreSQL configured in .env
✅ Ready to upload NYC 311 CSV data
✅ PrismPHP available for AI integration

STATUS: No domain tables exist yet

EXECUTE: /phase:b-csv-foundation

Claude: Please explore the current repo state, then plan Phase B implementation.
```

---

## 🎯 Success Criteria

**Phase Completion Checklist:**
- [ ] CSV import processes 10k+ records without memory issues
- [ ] API filtering returns consistent totals across endpoints
- [ ] AI analysis generates meaningful risk scores (0.0-1.0)
- [ ] High-risk complaints trigger Slack notifications
- [ ] Natural language queries return accurate results
- [ ] Python bridge executes without process timeouts
- [ ] All database queries use proper indexes
- [ ] Test coverage includes edge cases and error scenarios

**Portfolio Readiness:**
- [ ] Clean, documented codebase with consistent patterns
- [ ] Demo script showcasing AI capabilities
- [ ] README explains technical decisions and architecture
- [ ] Performance benchmarks for large dataset handling

---

## 💡 Pro Tips for Claude Code Sessions

1. **Always start with `/explore`** to understand current state
2. **Request approval before major changes** - prevents rework
3. **Use small, testable increments** - easier to debug and validate
4. **Test each phase before advancing** - run migrations, check API responses
5. **Leverage Ultra-Think** for complex multi-file operations
6. **Save working states** - commit after each successful phase

---
