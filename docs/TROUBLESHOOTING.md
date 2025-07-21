# LaraCity AI Troubleshooting Guide

## Table of Contents
1. [Common Issues](#common-issues)
2. [AI Service Issues](#ai-service-issues)
3. [Search Problems](#search-problems)
4. [Queue Processing](#queue-processing)
5. [Database Issues](#database-issues)
6. [Performance Problems](#performance-problems)
7. [Development Environment](#development-environment)
8. [Debugging Tips](#debugging-tips)

## Common Issues

### 1. Application Won't Start

#### Symptoms
- White screen or 500 error
- "Class not found" errors
- Configuration errors

#### Solutions

1. **Clear all caches:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

2. **Check environment variables:**
```bash
# Ensure .env file exists
cp .env.example .env

# Generate application key
php artisan key:generate

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

3. **Install dependencies:**
```bash
composer install
npm install && npm run build
```

### 2. Login Issues

#### Symptoms
- Can't log in with seeded credentials
- "Invalid credentials" error
- Session expiring immediately

#### Solutions

1. **Check user exists:**
```bash
php artisan tinker
>>> User::where('email', 'admin@example.com')->first();
```

2. **Reset password:**
```bash
php artisan tinker
>>> $user = User::where('email', 'admin@example.com')->first();
>>> $user->password = Hash::make('password');
>>> $user->save();
```

3. **Check session configuration:**
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => env('SESSION_LIFETIME', 120),
```

## AI Service Issues

### 1. Python AI Bridge Failures

#### Symptoms
- "Failed to generate embedding" errors
- "Python AI bridge process failed" in logs
- Timeouts during analysis

#### Solutions

1. **Check Python installation:**
```bash
python3 --version  # Should be 3.9+
which python3      # Verify path

# Test Python script directly
cd storage/app/python
python3 ai_analysis.py health_check
```

2. **Install Python dependencies:**
```bash
cd storage/app/python
pip3 install -r requirements.txt
```

3. **Verify OpenAI API key:**
```bash
# Check if set in .env
grep OPENAI_API_KEY .env

# Test API key
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

4. **Common Python errors:**
```python
# Missing modules
pip3 install openai langchain numpy pandas

# Path issues - Update config/complaints.php
'python' => [
    'script_path' => storage_path('app/python/ai_analysis.py'),
]
```

### 2. Embedding Generation Failures

#### Symptoms
- Vector search returns no results
- "Invalid embedding format" errors
- JSON parsing errors

#### Solutions

1. **Check embedding data format:**
```bash
php artisan tinker
>>> $embedding = DocumentEmbedding::latest()->first();
>>> json_decode($embedding->embedding);  // Should return array
```

2. **Regenerate embeddings:**
```bash
# For specific complaint
php artisan embeddings:generate --complaint-id=123

# For all complaints missing embeddings
php artisan embeddings:generate --missing
```

3. **Debug JSON parsing:**
```php
// Add to PythonAiBridge.php temporarily
Log::debug('Raw Python output', ['output' => $output]);
Log::debug('JSON parse attempt', ['json' => $jsonContent]);
```

### 3. AI Analysis Not Working

#### Symptoms
- Complaints stuck in "pending analysis"
- No risk scores appearing
- Analysis queue not processing

#### Solutions

1. **Check queue is running:**
```bash
# For Laravel Herd
php artisan queue:work --queue=ai-analysis

# Check for failed jobs
php artisan queue:failed
```

2. **Retry failed jobs:**
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry 5
```

3. **Manual analysis trigger:**
```bash
php artisan tinker
>>> $complaint = Complaint::find(1);
>>> AnalyzeComplaintJob::dispatch($complaint);
```

## Search Problems

### 1. Chat Agent Returns "No Results"

#### Symptoms
- Statistical queries not working
- "I couldn't find any complaints" for valid queries
- Search returning empty results

#### Solutions

1. **Check if data exists:**
```bash
php artisan tinker
>>> Complaint::count();  // Should return > 0
>>> ComplaintAnalysis::count();  // Should return > 0
```

2. **Test query routing:**
```php
// Add to ChatAgent.php temporarily
Log::debug('Query classification', [
    'message' => $message,
    'is_statistical' => $this->isStatisticalQuery($message),
    'is_complaint' => $this->isComplaintQuery($message),
]);
```

3. **Verify database queries:**
```sql
-- Check complaint types
SELECT complaint_type, COUNT(*) 
FROM complaints 
GROUP BY complaint_type 
ORDER BY COUNT(*) DESC;

-- Check risk distribution
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN risk_score >= 0.7 THEN 1 ELSE 0 END) as high_risk
FROM complaint_analysis;
```

### 2. Vector Search Not Finding Similar Complaints

#### Symptoms
- Semantic search returns no results
- "No embeddings found" errors
- Search only returning exact matches

#### Solutions

1. **Check embeddings exist:**
```bash
php artisan tinker
>>> DocumentEmbedding::count();  // Should be > 0
>>> DocumentEmbedding::where('document_type', 'complaint')->count();
```

2. **Verify pgvector extension:**
```sql
-- Connect to PostgreSQL
\dx  -- List extensions

-- If pgvector not listed
CREATE EXTENSION IF NOT EXISTS vector;
```

3. **Test similarity search:**
```bash
php artisan tinker
>>> $service = app(VectorEmbeddingService::class);
>>> $results = $service->searchSimilar('noise complaint', 'complaint', 0.5);
>>> count($results);  // Should return results
```

### 3. Hybrid Search Failures

#### Symptoms
- Search falling back to metadata only
- Inconsistent search results
- Performance issues

#### Solutions

1. **Check search weights:**
```php
// In HybridSearchService
'vector_weight' => 0.7,      // Increase for more semantic
'metadata_weight' => 0.3,     // Increase for more literal
'similarity_threshold' => 0.6, // Lower for more results
```

2. **Debug search components:**
```bash
php artisan tinker
>>> $search = app(HybridSearchService::class);
>>> $results = $search->search('water leak', [], ['limit' => 10]);
>>> $results['metadata'];  // Check what ran
```

## Queue Processing

### 1. Jobs Not Processing

#### Symptoms
- Jobs stuck in queue
- High job count in jobs table
- No activity in queue worker

#### Solutions

1. **Start queue worker:**
```bash
# Basic worker
php artisan queue:work

# With specific options
php artisan queue:work --queue=ai-analysis,escalation,default --tries=3
```

2. **Monitor queue:**
```bash
# Watch queue in real-time
php artisan queue:listen

# Check job count
php artisan tinker
>>> DB::table('jobs')->count();
```

3. **Clear stuck jobs:**
```bash
# Clear all jobs (careful!)
php artisan queue:clear

# Clear specific queue
php artisan queue:clear --queue=ai-analysis
```

### 2. Jobs Failing

#### Symptoms
- Jobs in failed_jobs table
- Timeout errors
- Memory exhaustion

#### Solutions

1. **Increase timeout:**
```php
// In job class
public $timeout = 300;  // 5 minutes

// In config/queue.php
'connections' => [
    'database' => [
        'retry_after' => 90,
    ],
],
```

2. **Fix memory issues:**
```bash
# Increase memory limit
php -d memory_limit=512M artisan queue:work

# Or in php.ini
memory_limit = 512M
```

## Database Issues

### 1. Migration Failures

#### Symptoms
- "Column already exists" errors
- Foreign key constraint failures
- pgvector extension errors

#### Solutions

1. **Reset migrations (development only):**
```bash
php artisan migrate:fresh --seed
```

2. **Fix pgvector issues:**
```sql
-- As superuser
CREATE EXTENSION IF NOT EXISTS vector;
GRANT ALL ON SCHEMA public TO your_user;
```

3. **Fix foreign key issues:**
```bash
# Check constraint names
php artisan tinker
>>> Schema::getConnection()->getDoctrineSchemaManager()->listTableForeignKeys('complaints');
```

### 2. Connection Issues

#### Symptoms
- "Connection refused" errors
- "Database does not exist"
- Authentication failures

#### Solutions

1. **Verify PostgreSQL is running:**
```bash
# macOS with Homebrew
brew services list | grep postgresql

# Start if needed
brew services start postgresql@15
```

2. **Check connection settings:**
```bash
# Test connection
PGPASSWORD=yourpassword psql -h 127.0.0.1 -U root -d laracity

# Verify .env settings
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laracity
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

## Performance Problems

### 1. Slow Page Load

#### Symptoms
- Dashboard takes long to load
- Timeout errors
- High memory usage

#### Solutions

1. **Enable query debugging:**
```php
// Add to AppServiceProvider boot()
if (config('app.debug')) {
    DB::listen(function ($query) {
        Log::info($query->sql, $query->bindings);
    });
}
```

2. **Add missing indexes:**
```sql
-- Check existing indexes
\d complaints

-- Add performance indexes
CREATE INDEX idx_complaints_borough ON complaints(borough);
CREATE INDEX idx_complaints_type ON complaints(complaint_type);
CREATE INDEX idx_complaints_submitted ON complaints(submitted_at);
```

3. **Optimize queries:**
```php
// Use eager loading
Complaint::with(['analysis', 'embeddings'])->paginate(50);

// Add query caching
Cache::remember('complaint_stats', 900, function () {
    return Complaint::getStatistics();
});
```

### 2. Slow AI Processing

#### Symptoms
- Embeddings take minutes to generate
- Analysis jobs timing out
- Queue backing up

#### Solutions

1. **Batch processing:**
```bash
# Process in smaller batches
php artisan embeddings:generate --batch-size=10

# Run multiple workers
php artisan queue:work --queue=ai-analysis &
php artisan queue:work --queue=ai-analysis &
```

2. **Rate limiting:**
```php
// Add to job
public function middleware()
{
    return [new RateLimited('openai')];
}

// In AppServiceProvider
RateLimiter::for('openai', function ($job) {
    return Limit::perMinute(50);
});
```

## Development Environment

### 1. Laravel Herd Issues

#### Symptoms
- Site not loading at .test domain
- SSL certificate errors
- PHP version conflicts

#### Solutions

1. **Check Herd configuration:**
```bash
# Verify site is linked
herd link laracity

# Check PHP version
herd use php@8.2

# Restart services
herd restart
```

2. **Fix SSL issues:**
```bash
# Trust Herd certificates
herd trust

# Check site is secured
herd secure laracity
```

### 2. Asset Compilation

#### Symptoms
- Styles not updating
- JavaScript errors
- Missing compiled assets

#### Solutions

1. **Rebuild assets:**
```bash
npm run build

# For development with hot reload
npm run dev
```

2. **Clear asset cache:**
```bash
rm -rf public/build
npm run build
```

## Debugging Tips

### 1. Enable Debug Mode

```php
// .env
APP_DEBUG=true
APP_ENV=local
LOG_LEVEL=debug
```

### 2. Useful Debug Commands

```bash
# Tail application logs
tail -f storage/logs/laravel.log

# Interactive debugging
php artisan tinker

# Database queries
php artisan db:show
php artisan db:table complaints

# Route debugging
php artisan route:list --path=api

# Config debugging
php artisan config:show complaints
```

### 3. Debug Toolbar

Install Laravel Debugbar for development:
```bash
composer require barryvdh/laravel-debugbar --dev
```

### 4. Common Log Locations

- **Laravel logs**: `storage/logs/laravel.log`
- **Queue logs**: Look for "queue" entries in Laravel log
- **Python logs**: Check Python script output in Laravel log
- **Database logs**: PostgreSQL logs location varies by system

### 5. Testing Specific Components

```bash
# Test AI bridge
php artisan tinker
>>> app(PythonAiBridge::class)->testConnection();

# Test embeddings
>>> app(VectorEmbeddingService::class)->generateEmbedding(Complaint::first());

# Test search
>>> app(HybridSearchService::class)->search('test query');
```

## Getting Help

1. **Check logs first** - Most issues leave traces in logs
2. **Isolate the problem** - Test individual components
3. **Review configuration** - Many issues are config-related
4. **Check the Tutorial** - Tutorial-Details.md has implementation details
5. **Create minimal test case** - Simplify to identify root cause

## Emergency Fixes

### Reset Everything (Development Only!)
```bash
# Complete reset
php artisan migrate:fresh --seed
php artisan cache:clear
php artisan queue:clear
php artisan embeddings:generate --all
```

### Disable AI Features Temporarily
```php
// In .env
AI_ENABLED=false

// In config/complaints.php
'ai_enabled' => env('AI_ENABLED', true),
```