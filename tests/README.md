# LaraCity Test Suite

## 🚨 CRITICAL DATABASE SAFETY

**This test suite has been configured with multiple safety mechanisms to prevent accidental production database deletion:**

### Safety Mechanisms

1. **phpunit.xml Configuration**: Forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`
2. **TestCase Safety Check**: Verifies in-memory database on every test setUp
3. **Environment Isolation**: Uses `.env.testing` file with safe defaults

### Running Tests Safely

```bash
# ✅ SAFE: Uses phpunit.xml configuration
php artisan test

# ✅ SAFE: Explicitly uses testing environment  
php artisan test --env=testing

# ✅ SAFE: Run specific test files
php artisan test tests/Unit/Models/ComplaintTest.php

# ✅ SAFE: Run with filters
php artisan test --filter="complaint has fillable attributes"
```

### Safety Verification

Every test automatically verifies:
- `DB_CONNECTION` is `sqlite`
- `DB_DATABASE` is `:memory:`
- `APP_ENV` is `testing`

If any safety check fails, tests will immediately abort with an error message.

## Test Structure

### Unit Tests
- **Models**: `tests/Unit/Models/`
  - ComplaintTest.php (18 tests)
  - ComplaintAnalysisTest.php (15 tests) 
  - DocumentEmbeddingTest.php (12 tests)

- **Services**: `tests/Unit/Services/`
  - PythonAiBridgeTest.php (10 tests)
  - VectorEmbeddingServiceTest.php (8 tests)
  - HybridSearchServiceTest.php (12 tests)

- **Jobs**: `tests/Unit/Jobs/`
  - AnalyzeComplaintJobTest.php (14 tests)

### Feature Tests
- **API Endpoints**: `tests/Feature/Api/`
  - ComplaintControllerTest.php (15 tests)
  - SemanticSearchControllerTest.php (20 tests)

- **Console Commands**: `tests/Feature/Console/`
  - ImportCsvCommandTest.php (8 tests)
  - GenerateEmbeddingsCommandTest.php (6 tests)

- **Livewire Components**: `tests/Feature/Livewire/`
  - ChatAgentTest.php (10 tests)

## Test Coverage

- ✅ **Models**: Complete coverage of relationships, scopes, and business logic
- ✅ **Services**: Mocked external dependencies, isolated testing
- ✅ **Jobs**: Queue behavior, error handling, escalation logic
- ✅ **API**: Authentication, validation, response formatting
- ✅ **Commands**: Console input/output, batch processing
- ✅ **Components**: Livewire interaction and state management

## Important Notes

### SQLite Compatibility
- Tests use SQLite in-memory database for speed and isolation
- Decimal fields are cast to float for compatibility
- PostgreSQL-specific features are conditionally applied

### Mocking Strategy
- External services (OpenAI, Slack) are mocked
- Python AI Bridge is mocked to prevent external API calls
- Queue connections use `sync` driver for immediate execution

### Database Seeding
- Each test creates its own data using factories
- `RefreshDatabase` trait ensures clean state between tests
- No shared test data to prevent coupling

## Troubleshooting

### Slow Tests
If tests run slowly (>10 seconds each), this is normal for the current SQLite setup. Tests prioritize safety over speed.

### Failed Assertions
- Check decimal casting issues: use `(float) $value` for comparisons
- Verify mocking setup for external services
- Ensure factory relationships are properly configured

## Adding New Tests

1. **Unit Tests**: Test individual classes in isolation
2. **Feature Tests**: Test full request/response cycles
3. **Always Mock**: External APIs, file systems, network calls
4. **Use Factories**: Generate test data programmatically
5. **Test Safety**: Verify error conditions and edge cases