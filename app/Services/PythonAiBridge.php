<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PythonAiBridge
{
    private string $scriptPath;
    private int $timeout;
    private int $maxOutputLength;

    public function __construct()
    {
        $this->scriptPath = config('complaints.python.script_path');
        $this->timeout = config('complaints.python.timeout', 90);
        $this->maxOutputLength = config('complaints.python.max_output_length', 10000);
    }

    /**
     * Analyze a complaint using Python AI bridge
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
            
            // Pass environment variables to Python process
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

            // Try to decode JSON response
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to decode Python bridge JSON response', [
                    'json_error' => json_last_error_msg(),
                    'raw_output' => $output,
                ]);
                
                // Return fallback analysis
                return $this->createFallbackAnalysis($complaintData, $output);
            }

            // Validate required fields
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

            // Return fallback analysis for graceful degradation
            return $this->createFallbackAnalysis($complaintData, 'Process failed: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('Python AI bridge unexpected error', [
                'complaint_id' => $complaintData['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            // Return fallback analysis
            return $this->createFallbackAnalysis($complaintData, 'Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Generate embedding for text using Python AI bridge
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
            
            // Pass environment variables to Python process
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
            
            // Extract JSON from output that may contain log messages
            $lines = explode("\n", $output);
            $jsonContent = '';
            
            // Look for JSON content by finding the opening { and collecting until closing }
            $foundStart = false;
            $braceCount = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Look for JSON start
                if (!$foundStart && str_starts_with($line, '{')) {
                    $foundStart = true;
                    $jsonContent = $line;
                    $braceCount = substr_count($line, '{') - substr_count($line, '}');
                    
                    // If it's a complete JSON object on one line
                    if ($braceCount === 0) {
                        break;
                    }
                } elseif ($foundStart) {
                    // Continue collecting JSON content
                    $jsonContent .= "\n" . $line;
                    $braceCount += substr_count($line, '{') - substr_count($line, '}');
                    
                    // If we've balanced all braces, we have complete JSON
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

            // Handle the actual format returned by Python script
            if (!isset($result['data']['embeddings']) || !is_array($result['data']['embeddings'])) {
                Log::error('Invalid embedding format in Python response', [
                    'result_structure' => array_keys($result ?? []),
                    'data_structure' => isset($result['data']) ? array_keys($result['data']) : 'data key missing',
                    'raw_result' => $result
                ]);
                throw new \RuntimeException('Invalid embedding format returned from Python');
            }

            // Extract the first embedding from the embeddings array
            $firstEmbedding = $result['data']['embeddings'][0] ?? null;
            if (!$firstEmbedding || !is_array($firstEmbedding)) {
                throw new \RuntimeException('No valid embedding found in Python response');
            }

            // Convert from object format {0: value, 1: value, ...} to array [value, value, ...]
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
     * Search documents using vector similarity
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
     * Sync vector store with pgvector database
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
     * Test Python AI bridge connectivity
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
     * Create fallback analysis when Python bridge fails
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
     * Validate and sanitize analysis result from Python
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
     * Rule-based risk score estimation for fallback
     */
    private function estimateRiskScore(array $complaintData): float
    {
        $type = strtolower($complaintData['type'] ?? '');

        // High-risk complaint types
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
     * Rule-based categorization for fallback
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
     * Generate fallback tags based on complaint data
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