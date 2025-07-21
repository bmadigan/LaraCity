<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Bridge service connecting Laravel to Python AI processing capabilities.
 *
 * This class solves the fundamental challenge of integrating PHP with Python's
 * rich AI ecosystem. Rather than reimplementing complex libraries like OpenAI
 * and LangChain in PHP, we delegate AI operations to a Python subprocess.
 * 
 * The design emphasizes reliability over performance - we prioritize graceful
 * degradation and robust error handling since AI operations are inherently
 * unpredictable and external dependencies can fail.
 */
class PythonAiBridge
{
    private string $scriptPath;
    private int $timeout;
    private int $maxOutputLength;

    /**
     * Initialize the bridge with configuration-driven settings.
     *
     * We separate configuration from code to make deployment flexible
     * across different environments where Python paths and timeouts vary.
     */
    public function __construct()
    {
        $this->scriptPath = config('complaints.python.script_path');
        $this->timeout = config('complaints.python.timeout', 90);
        $this->maxOutputLength = config('complaints.python.max_output_length', 10000);
    }

    /**
     * Delegate complaint analysis to Python's AI capabilities.
     *
     * This method showcases the core pattern: serialize data to JSON,
     * spawn a Python subprocess, and parse the response. The environment
     * variable forwarding ensures the Python script has access to API keys
     * while keeping secrets out of command-line arguments.
     */
    public function analyzeComplaint(array $complaintData): array
    {
        Log::info('Calling Python AI bridge for complaint analysis', [
            'complaint_id' => $complaintData['id'] ?? null,
            'script_path' => $this->scriptPath,
        ]);

        $command = [
            'python3',
            $this->scriptPath,
            'analyze_complaint',
            json_encode($complaintData)
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeout);
            
            // Forward API credentials securely through environment variables
            // rather than exposing them in command arguments
            $process->setEnv([
                'OPENAI_API_KEY' => config('services.openai.api_key') ?: env('OPENAI_API_KEY'),
                'OPENAI_ORGANIZATION' => config('services.openai.organization') ?: env('OPENAI_ORGANIZATION'),
                'PATH' => env('PATH', '/usr/local/bin:/usr/bin:/bin'),
                'PYTHONPATH' => dirname($this->scriptPath),
            ]);
            
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            
            // Truncate output if too long
            if (strlen($output) > $this->maxOutputLength) {
                Log::warning('Python bridge output truncated', [
                    'original_length' => strlen($output),
                    'max_length' => $this->maxOutputLength,
                ]);
                $output = substr($output, 0, $this->maxOutputLength);
            }

            Log::info('Python AI bridge output received', [
                'output_length' => strlen($output),
                'complaint_id' => $complaintData['id'] ?? null,
            ]);

            // Parse the AI response, handling malformed JSON gracefully
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to decode Python bridge JSON response', [
                    'json_error' => json_last_error_msg(),
                    'raw_output' => $output,
                ]);
                
                // Graceful degradation: provide rule-based analysis instead of failing
                return $this->createFallbackAnalysis($complaintData, $output);
            }

            // Sanitize and validate the AI output for database storage
            $validated = $this->validateAnalysisResult($result, $complaintData);
            
            Log::info('Python AI analysis completed successfully', [
                'complaint_id' => $complaintData['id'] ?? null,
                'risk_score' => $validated['risk_score'],
                'category' => $validated['category'],
            ]);

            return $validated;

        } catch (ProcessFailedException $e) {
            Log::error('Python AI bridge process failed', [
                'complaint_id' => $complaintData['id'] ?? null,
                'command' => implode(' ', $command),
                'error_output' => $e->getProcess()->getErrorOutput(),
                'exit_code' => $e->getProcess()->getExitCode(),
            ]);

            // Never let AI failures break the application - always provide fallback
            return $this->createFallbackAnalysis($complaintData, 'Process failed: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('Python AI bridge unexpected error', [
                'complaint_id' => $complaintData['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            // Robust error handling ensures the system remains functional
            return $this->createFallbackAnalysis($complaintData, 'Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Generate vector embeddings through OpenAI's text-embedding model.
     *
     * This is the foundation of our semantic search capability. Rather than
     * implementing OpenAI's API client in PHP, we leverage Python's mature
     * ecosystem. The complex JSON parsing handles Python's serialization quirks
     * when converting numpy arrays to JSON.
     */
    public function generateEmbedding(string $text): array
    {
        Log::info('Generating embedding via Python AI bridge', [
            'text_length' => strlen($text),
        ]);

        $command = [
            'python3',
            $this->scriptPath,
            'create_embeddings',
            json_encode(['texts' => [$text]])
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeout);
            
            // Environment variables keep API credentials secure
            $process->setEnv([
                'OPENAI_API_KEY' => config('services.openai.api_key') ?: env('OPENAI_API_KEY'),
                'OPENAI_ORGANIZATION' => config('services.openai.organization') ?: env('OPENAI_ORGANIZATION'),
                'PATH' => env('PATH', '/usr/local/bin:/usr/bin:/bin'),
                'PYTHONPATH' => dirname($this->scriptPath),
            ]);
            
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            
            // Parse JSON from mixed output containing both logs and data
            // This complexity arises because Python scripts often mix print statements with JSON output
            $lines = explode("\n", $output);
            $jsonContent = '';
            
            // Track brace balance to extract complete JSON from multi-line output
            $foundStart = false;
            $braceCount = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Identify JSON start by looking for opening brace
                if (!$foundStart && str_starts_with($line, '{')) {
                    $foundStart = true;
                    $jsonContent = $line;
                    $braceCount = substr_count($line, '{') - substr_count($line, '}');
                    
                    // Handle single-line JSON objects
                    if ($braceCount === 0) {
                        break;
                    }
                } elseif ($foundStart) {
                    // Continue collecting until we have balanced braces
                    $jsonContent .= "\n" . $line;
                    $braceCount += substr_count($line, '{') - substr_count($line, '}');
                    
                    // Complete JSON object found
                    if ($braceCount === 0) {
                        break;
                    }
                }
            }
            
            $jsonLine = $jsonContent;
            
            $result = json_decode($jsonLine, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Python embedding response parsing failed', [
                    'json_error' => json_last_error_msg(),
                    'raw_output' => $output,
                    'json_line' => $jsonLine,
                    'error_output' => $process->getErrorOutput(),
                ]);
                throw new \RuntimeException('Invalid JSON response from Python embeddings: ' . json_last_error_msg() . '. JSON line: ' . substr($jsonLine, 0, 200));
            }

            // Navigate Python's serialization format for numpy arrays
            // Python often serializes arrays as objects with string indices: {"0": 0.1, "1": 0.2}
            if (!isset($result['data']['embeddings']) || !is_array($result['data']['embeddings'])) {
                Log::error('Invalid embedding format in Python response', [
                    'result_structure' => array_keys($result ?? []),
                    'data_structure' => isset($result['data']) ? array_keys($result['data']) : 'data key missing',
                    'raw_result' => $result
                ]);
                throw new \RuntimeException('Invalid embedding format returned from Python');
            }

            // Extract the first embedding vector from the response
            $firstEmbedding = $result['data']['embeddings'][0] ?? null;
            if (!$firstEmbedding || !is_array($firstEmbedding)) {
                throw new \RuntimeException('No valid embedding found in Python response');
            }

            // Transform Python's object-like array format to a proper PHP array
            // This handles the {"0": value, "1": value} format from numpy serialization
            $embeddingArray = [];
            for ($i = 0; $i < count($firstEmbedding); $i++) {
                if (isset($firstEmbedding[(string)$i])) {
                    $embeddingArray[] = $firstEmbedding[(string)$i];
                }
            }

            if (empty($embeddingArray)) {
                throw new \RuntimeException('Failed to convert embedding format');
            }

            Log::info('Embedding generated successfully', [
                'embedding_dimension' => count($embeddingArray),
                'model' => $result['data']['model'] ?? 'unknown'
            ]);

            return [
                'embedding' => $embeddingArray,
                'model' => $result['data']['model'] ?? 'text-embedding-3-small',
                'dimension' => count($embeddingArray)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate embedding', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Perform semantic search using vector similarity.
     *
     * This delegates to Python's more sophisticated vector search libraries
     * rather than implementing cosine similarity calculations in PHP.
     */
    public function vectorSearch(string $query, array $options = []): array
    {
        Log::info('Performing vector search via Python AI bridge', [
            'query_length' => strlen($query),
            'options' => $options
        ]);

        $command = [
            'python3',
            $this->scriptPath,
            'search_documents',
            json_encode([
                'query' => $query,
                'options' => $options
            ])
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Python vector search: ' . json_last_error_msg());
            }

            Log::info('Vector search completed', [
                'results_count' => count($result['data']['results'] ?? [])
            ]);

            return $result['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('Vector search failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Synchronize the vector database with current complaint data.
     *
     * This operation can be expensive, so we delegate it to Python which has
     * better tooling for batch processing large datasets efficiently.
     */
    public function syncPgVectorStore(): array
    {
        Log::info('Syncing vector store with pgvector');

        $command = [
            'python3',
            $this->scriptPath,
            'sync_pgvector',
            json_encode([])
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeout * 2); // Longer timeout for sync operations
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Python vector sync: ' . json_last_error_msg());
            }

            Log::info('Vector store sync completed', $result['data'] ?? []);

            return $result['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('Vector store sync failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify that the Python AI environment is properly configured.
     *
     * This health check is crucial for debugging deployment issues where
     * Python dependencies or API keys might be misconfigured.
     */
    public function testConnection(): array
    {
        Log::info('Testing Python AI bridge connection');

        $command = [
            'python3',
            $this->scriptPath,
            'health_check'
        ];

        try {
            $process = new Process($command);
            $process->setTimeout(30); // Shorter timeout for health check
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid JSON response',
                    'raw_output' => $output,
                ];
            }

            Log::info('Python AI bridge health check completed', $result);
            return $result;

        } catch (\Exception $e) {
            Log::error('Python AI bridge health check failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate rule-based analysis when AI processing fails.
     *
     * This fallback system ensures our application remains functional even when
     * external AI services are unavailable. It uses simple heuristics based on
     * complaint type keywords to provide basic risk assessment.
     */
    private function createFallbackAnalysis(array $complaintData, string $errorContext): array
    {
        $riskScore = $this->estimateRiskScore($complaintData);
        $category = $this->categorizeComplaint($complaintData);

        Log::info('Creating fallback analysis', [
            'complaint_id' => $complaintData['id'] ?? null,
            'fallback_risk_score' => $riskScore,
            'fallback_category' => $category,
        ]);

        return [
            'summary' => sprintf(
                'Fallback analysis for %s complaint in %s. %s',
                $complaintData['type'] ?? 'Unknown',
                $complaintData['borough'] ?? 'Unknown location',
                'AI analysis unavailable - using rule-based assessment.'
            ),
            'risk_score' => $riskScore,
            'category' => $category,
            'tags' => $this->generateFallbackTags($complaintData),
            'fallback' => true,
            'error_context' => $errorContext,
        ];
    }

    /**
     * Sanitize AI output for safe database storage.
     *
     * AI models can return unexpected data types or out-of-range values.
     * This validation ensures we store clean, consistent data regardless
     * of what the AI returns.
     */
    private function validateAnalysisResult(array $result, array $complaintData): array
    {
        return [
            'summary' => $result['summary'] ?? 'AI analysis completed',
            'risk_score' => max(0.0, min(1.0, (float) ($result['risk_score'] ?? 0.0))),
            'category' => $result['category'] ?? $this->categorizeComplaint($complaintData),
            'tags' => is_array($result['tags'] ?? null) ? $result['tags'] : [],
            'fallback' => false,
        ];
    }

    /**
     * Estimate risk using keyword-based heuristics.
     *
     * While less sophisticated than AI analysis, these rules provide
     * reasonable risk assessments based on domain expertise about
     * NYC complaint types and their typical severity levels.
     */
    private function estimateRiskScore(array $complaintData): float
    {
        $type = strtolower($complaintData['type'] ?? '');

        // Emergency situations that pose immediate public safety risks
        if (str_contains($type, 'gas leak') || 
            str_contains($type, 'structural') || 
            str_contains($type, 'emergency') ||
            str_contains($type, 'water main') ||
            str_contains($type, 'electrical hazard')) {
            return 0.85;
        }

        // Medium-high risk
        if (str_contains($type, 'water') || 
            str_contains($type, 'heat') || 
            str_contains($type, 'plumbing') ||
            str_contains($type, 'unsanitary')) {
            return 0.65;
        }

        // Medium risk
        if (str_contains($type, 'street') || 
            str_contains($type, 'sidewalk') || 
            str_contains($type, 'traffic')) {
            return 0.45;
        }

        // Low risk (noise, parking, etc.)
        return 0.25;
    }

    /**
     * Classify complaints into operational categories.
     *
     * These categories align with how NYC agencies typically organize
     * complaint types for routing and resource allocation.
     */
    private function categorizeComplaint(array $complaintData): string
    {
        $type = strtolower($complaintData['type'] ?? '');

        if (str_contains($type, 'noise')) return 'Quality of Life';
        if (str_contains($type, 'parking')) return 'Transportation';
        if (str_contains($type, 'water') || str_contains($type, 'heat')) return 'Infrastructure';
        if (str_contains($type, 'street') || str_contains($type, 'sidewalk')) return 'Infrastructure';
        if (str_contains($type, 'sanitation')) return 'Public Health';
        if (str_contains($type, 'animal')) return 'Public Health';

        return 'General';
    }

    /**
     * Create searchable tags from structured complaint data.
     *
     * These tags enable basic search functionality even when sophisticated
     * vector search is unavailable, providing a simple indexing system.
     */
    private function generateFallbackTags(array $complaintData): array
    {
        $tags = [];

        // Add borough tag
        if (!empty($complaintData['borough'])) {
            $tags[] = strtolower($complaintData['borough']);
        }

        // Add agency tag
        if (!empty($complaintData['agency'])) {
            $tags[] = strtolower($complaintData['agency']);
        }

        // Add type-based tags
        $type = strtolower($complaintData['type'] ?? '');
        if (str_contains($type, 'noise')) $tags[] = 'noise';
        if (str_contains($type, 'water')) $tags[] = 'water';
        if (str_contains($type, 'heat')) $tags[] = 'heating';
        if (str_contains($type, 'street')) $tags[] = 'street';

        return array_unique($tags);
    }
}