<?php

namespace App\Services;

use App\Models\Complaint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CsvImportService
{
    private $batchSize;
    private $timezone;
    private $errors = [];
    private $processed = 0;
    private $imported = 0;
    private $updated = 0;
    private $skipped = 0;

    public function __construct()
    {
        $this->batchSize = config('laracity.csv_batch_size', 1000);
        $this->timezone = config('laracity.timezone', 'America/Toronto');
    }

    /**
     * Import CSV file with progress tracking and error handling
     */
    public function import(string $filePath, bool $validate = true): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("CSV file not found: {$filePath}");
        }

        $this->resetCounters();
        
        Log::info("Starting CSV import from: {$filePath}");
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Unable to open CSV file: {$filePath}");
        }

        try {
            // Read and process header
            $headers = $this->processHeader($handle);
            $headerMapping = $this->createHeaderMapping($headers);
            
            if ($validate) {
                $this->validateHeaders($headerMapping);
            }

            // Process data in batches
            $batch = [];
            $lineNumber = 1; // Start from 1 (header already read)
            
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                if (count($row) !== count($headers)) {
                    $this->addError($lineNumber, "Column count mismatch. Expected " . count($headers) . ", got " . count($row));
                    $this->skipped++;
                    continue;
                }
                
                try {
                    $mappedData = $this->mapRowData($row, $headerMapping);
                    $complaintData = $this->transformComplaintData($mappedData);
                    
                    if ($this->validateComplaintData($complaintData, $lineNumber)) {
                        $batch[] = $complaintData;
                        
                        if (count($batch) >= $this->batchSize) {
                            $this->processBatch($batch);
                            $batch = [];
                        }
                    } else {
                        $this->skipped++;
                    }
                } catch (\Exception $e) {
                    $this->addError($lineNumber, "Processing error: " . $e->getMessage());
                    $this->skipped++;
                }
                
                $this->processed++;
                
                // Progress reporting every 10k records
                if ($this->processed % 10000 === 0) {
                    Log::info("CSV Import Progress: {$this->processed} records processed");
                }
            }
            
            // Process remaining batch
            if (!empty($batch)) {
                $this->processBatch($batch);
            }
            
        } finally {
            fclose($handle);
        }

        $this->logImportSummary();
        
        return $this->getImportSummary();
    }

    /**
     * Process header row and normalize column names
     */
    private function processHeader($handle): array
    {
        $header = fgetcsv($handle);
        if (!$header) {
            throw new \RuntimeException("Unable to read CSV header");
        }
        
        // Normalize headers: trim whitespace and standardize
        return array_map(function($col) {
            return trim($col);
        }, $header);
    }

    /**
     * Create mapping from CSV columns to database fields
     */
    private function createHeaderMapping(array $headers): array
    {
        $mapping = [
            'Unique Key' => 'complaint_number',
            'Created Date' => 'submitted_at',
            'Closed Date' => 'resolved_at',
            'Complaint Type' => 'complaint_type',
            'Descriptor' => 'descriptor',
            'Agency' => 'agency',
            'Agency Name' => 'agency_name',
            'Borough' => 'borough',
            'City' => 'city',
            'Incident Address' => 'incident_address',
            'Street Name' => 'street_name',
            'Cross Street 1' => 'cross_street_1',
            'Cross Street 2' => 'cross_street_2',
            'Incident Zip' => 'incident_zip',
            'Address Type' => 'address_type',
            'Latitude' => 'latitude',
            'Longitude' => 'longitude',
            'Location Type' => 'location_type',
            'Status' => 'status',
            'Resolution Description' => 'resolution_description',
            'Community Board' => 'community_board',
            'Council District' => 'council_district',
            'Police Precinct' => 'police_precinct',
            'School District' => 'school_district',
            'Due Date' => 'due_date',
            'Facility Type' => 'facility_type',
            'Park Facility Name' => 'park_facility_name',
            'Vehicle Type' => 'vehicle_type',
        ];
        
        $result = [];
        foreach ($headers as $index => $header) {
            if (isset($mapping[$header])) {
                $result[$index] = $mapping[$header];
            }
        }
        
        return $result;
    }

    /**
     * Validate that required headers are present
     */
    private function validateHeaders(array $headerMapping): void
    {
        $required = ['complaint_number', 'submitted_at', 'complaint_type', 'agency'];
        
        $mapped = array_values($headerMapping);
        $missing = array_diff($required, $mapped);
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException("Missing required columns: " . implode(', ', $missing));
        }
    }

    /**
     * Map CSV row data to database field names
     */
    private function mapRowData(array $row, array $headerMapping): array
    {
        $mapped = [];
        foreach ($headerMapping as $csvIndex => $dbField) {
            $mapped[$dbField] = $row[$csvIndex] ?? null;
        }
        return $mapped;
    }

    /**
     * Transform and normalize complaint data
     */
    private function transformComplaintData(array $data): array
    {
        $transformed = $data;
        
        // Parse dates with timezone handling
        if (!empty($data['submitted_at'])) {
            $transformed['submitted_at'] = $this->parseDate($data['submitted_at']);
        }
        
        if (!empty($data['resolved_at'])) {
            $transformed['resolved_at'] = $this->parseDate($data['resolved_at']);
        }
        
        if (!empty($data['due_date'])) {
            $transformed['due_date'] = $this->parseDate($data['due_date']);
        }
        
        // Normalize borough
        if (!empty($data['borough']) && trim($data['borough']) !== 'Unspecified') {
            $transformed['borough'] = strtoupper(trim($data['borough']));
        } else {
            $transformed['borough'] = null; // Set to null if empty or "Unspecified"
        }
        
        // Handle coordinates
        if (!empty($data['latitude'])) {
            $transformed['latitude'] = is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
        }
        
        if (!empty($data['longitude'])) {
            $transformed['longitude'] = is_numeric($data['longitude']) ? (float) $data['longitude'] : null;
        }
        
        // Handle required fields with defaults for empty values
        if (empty($data['complaint_type'])) {
            $transformed['complaint_type'] = 'Unknown';
        }
        
        if (empty($data['agency'])) {
            $transformed['agency'] = 'UNKNOWN';
        }
        
        if (empty($data['agency_name'])) {
            $transformed['agency_name'] = 'Unknown Agency';
        }
        
        // Normalize status to match database enum
        if (!empty($data['status'])) {
            $status = trim($data['status']);
            switch ($status) {
                case 'In Progress':
                    $transformed['status'] = Complaint::STATUS_IN_PROGRESS;
                    break;
                case 'Open':
                    $transformed['status'] = Complaint::STATUS_OPEN;
                    break;
                case 'Closed':
                    $transformed['status'] = Complaint::STATUS_CLOSED;
                    break;
                case 'Escalated':
                    $transformed['status'] = Complaint::STATUS_ESCALATED;
                    break;
                default:
                    $transformed['status'] = Complaint::STATUS_OPEN;
            }
        } else {
            $transformed['status'] = Complaint::STATUS_OPEN;
        }
        
        // Clean up empty strings to null for nullable fields only
        $nullableFields = [
            'descriptor', 'borough', 'incident_address', 'street_name', 
            'cross_street_1', 'cross_street_2', 'incident_zip', 'address_type',
            'latitude', 'longitude', 'location_type', 'resolution_description',
            'community_board', 'council_district', 'police_precinct', 
            'school_district', 'resolved_at', 'due_date', 'facility_type',
            'park_facility_name', 'vehicle_type', 'embedding'
        ];
        
        foreach ($transformed as $key => $value) {
            if ($value === '' && in_array($key, $nullableFields)) {
                $transformed[$key] = null;
            }
        }
        
        return $transformed;
    }

    /**
     * Parse date string with timezone awareness
     */
    private function parseDate(string $dateString): ?Carbon
    {
        if (empty(trim($dateString))) {
            return null;
        }
        
        try {
            // NYC 311 dates are typically in "MM/dd/yyyy HH:mm:ss AM/PM" format
            return Carbon::createFromFormat('m/d/Y h:i:s A', trim($dateString), $this->timezone);
        } catch (\Exception) {
            // Try alternative formats
            try {
                return Carbon::parse(trim($dateString), $this->timezone);
            } catch (\Exception) {
                Log::warning("Unable to parse date: " . $dateString);
                return null;
            }
        }
    }

    /**
     * Validate complaint data before database insertion
     */
    private function validateComplaintData(array $data, int $lineNumber): bool
    {
        $isValid = true;
        
        // Required fields validation
        if (empty($data['complaint_number'])) {
            $this->addError($lineNumber, "Missing complaint number");
            $isValid = false;
        }
        
        if (empty($data['submitted_at'])) {
            $this->addError($lineNumber, "Missing or invalid submitted date");
            $isValid = false;
        }
        
        if (empty($data['complaint_type'])) {
            $this->addError($lineNumber, "Missing complaint type");
            $isValid = false;
        }
        
        if (empty($data['agency'])) {
            $this->addError($lineNumber, "Missing agency");
            $isValid = false;
        }
        
        return $isValid;
    }

    /**
     * Process batch of complaint records using upsert
     */
    private function processBatch(array $batch): void
    {
        try {
            foreach ($batch as $complaintData) {
                $existing = Complaint::where('complaint_number', $complaintData['complaint_number'])->first();
                
                if ($existing) {
                    $existing->update($complaintData);
                    $this->updated++;
                } else {
                    Complaint::create($complaintData);
                    $this->imported++;
                }
            }
        } catch (\Exception $e) {
            Log::error("Batch processing error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add error to error log
     */
    private function addError(int $lineNumber, string $message): void
    {
        $this->errors[] = "Line {$lineNumber}: {$message}";
        Log::warning("CSV Import Error - Line {$lineNumber}: {$message}");
    }

    /**
     * Reset import counters
     */
    private function resetCounters(): void
    {
        $this->errors = [];
        $this->processed = 0;
        $this->imported = 0;
        $this->updated = 0;
        $this->skipped = 0;
    }

    /**
     * Get import summary
     */
    public function getImportSummary(): array
    {
        return [
            'processed' => $this->processed,
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'error_count' => count($this->errors),
            'success_rate' => $this->processed > 0 ? 
                round((($this->imported + $this->updated) / $this->processed) * 100, 2) : 0
        ];
    }

    /**
     * Log import summary
     */
    private function logImportSummary(): void
    {
        $summary = $this->getImportSummary();
        
        Log::info("CSV Import Complete", $summary);
        
        if ($summary['error_count'] > 0) {
            Log::warning("CSV Import had {$summary['error_count']} errors", [
                'errors' => array_slice($this->errors, 0, 10) // Log first 10 errors
            ]);
        }
    }
}