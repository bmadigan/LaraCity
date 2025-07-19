#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Demo Script: Emergency High-Risk Complaint Processing
 * 
 * Educational Focus:
 * - Automated risk assessment workflow
 * - Emergency escalation patterns  
 * - Real-time AI analysis pipeline
 * - Slack integration for critical alerts
 * 
 * This script demonstrates the complete emergency complaint workflow
 * from initial submission to automated escalation and notification.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Jobs\AnalyzeComplaintJob;
use App\Services\SlackNotificationService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class EmergencyComplaintDemo
{
    private array $emergencyScenarios = [
        [
            'type' => 'Gas Leak',
            'description' => 'Strong gas smell reported in apartment building basement. Multiple tenants evacuated. Possible underground gas line rupture affecting entire block.',
            'location' => 'MANHATTAN',
            'borough' => 'MANHATTAN',
            'zip_code' => '10001',
            'priority' => 'URGENT',
            'expected_risk' => 0.95
        ],
        [
            'type' => 'Structural Emergency',
            'description' => 'Large cracks appeared in building facade overnight. Concrete chunks falling onto sidewalk. Building inspector needed immediately.',
            'location' => 'BROOKLYN', 
            'borough' => 'BROOKLYN',
            'zip_code' => '11201',
            'priority' => 'URGENT',
            'expected_risk' => 0.88
        ],
        [
            'type' => 'Water Main Break',
            'description' => 'Major water main burst flooding entire street. Water service cut to 50+ buildings. Emergency repair crew and traffic control needed.',
            'location' => 'QUEENS',
            'borough' => 'QUEENS', 
            'zip_code' => '11101',
            'priority' => 'URGENT',
            'expected_risk' => 0.82
        ]
    ];

    public function runDemo(string $scenario = 'gas_leak'): void
    {
        $this->printHeader();
        
        $scenarios = [
            'gas_leak' => $this->emergencyScenarios[0],
            'structural' => $this->emergencyScenarios[1], 
            'water_main' => $this->emergencyScenarios[2]
        ];
        
        if (!isset($scenarios[$scenario])) {
            $this->printError("Unknown scenario: {$scenario}. Available: " . implode(', ', array_keys($scenarios)));
            return;
        }
        
        $emergencyData = $scenarios[$scenario];
        
        $this->printStep("🚨 EMERGENCY COMPLAINT SIMULATION", "Scenario: {$emergencyData['type']}");
        
        // Step 1: Create emergency complaint
        $complaint = $this->createEmergencyComplaint($emergencyData);
        $this->printResult("Emergency complaint created", "ID: {$complaint->id}");
        
        // Step 2: Trigger AI analysis
        $this->printStep("🤖 AI ANALYSIS PIPELINE", "Processing complaint through LangChain");
        $analysis = $this->triggerAiAnalysis($complaint);
        
        if ($analysis) {
            $this->printResult("AI analysis completed", "Risk Score: {$analysis->risk_score}");
            $this->displayAnalysisDetails($analysis);
            
            // Step 3: Check escalation trigger
            if ($analysis->risk_score >= config('laracity.escalation_threshold', 0.7)) {
                $this->printStep("⚡ ESCALATION TRIGGERED", "Risk threshold exceeded");
                $this->triggerEscalation($complaint, $analysis);
            }
        }
        
        // Step 4: Show final status
        $this->printFinalStatus($complaint->fresh());
        
        $this->printFooter();
    }
    
    private function createEmergencyComplaint(array $data): Complaint
    {
        $complaintData = [
            'complaint_number' => 'DEMO-' . strtoupper(uniqid()),
            'complaint_type' => $data['type'],
            'descriptor' => $data['type'] . ' - Emergency',
            'complaint_description' => $data['description'],
            'borough' => $data['borough'],
            'incident_zip' => $data['zip_code'],
            'location_type' => 'Residential Building',
            'status' => 'Open',
            'priority' => 'High',
            'created_date' => now()->format('Y-m-d H:i:s'),
            'closed_date' => null,
            'due_date' => now()->addHours(2)->format('Y-m-d H:i:s'), // 2 hour SLA
            'resolution_description' => null,
            'agency' => 'FDNY',
            'agency_name' => 'Fire Department'
        ];
        
        return Complaint::create($complaintData);
    }
    
    private function triggerAiAnalysis(Complaint $complaint): ?ComplaintAnalysis
    {
        try {
            // Dispatch analysis job synchronously for demo
            AnalyzeComplaintJob::dispatchSync($complaint);
            
            // Wait for analysis to complete
            sleep(2);
            
            return $complaint->fresh()->analysis;
            
        } catch (\Exception $e) {
            $this->printError("AI Analysis failed: " . $e->getMessage());
            Log::error('Demo AI analysis failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    private function triggerEscalation(Complaint $complaint, ComplaintAnalysis $analysis): void
    {
        try {
            // Update complaint status
            $complaint->update(['status' => 'Escalated']);
            
            // Send Slack notification if configured
            $slackService = app(SlackNotificationService::class);
            $slackService->sendEscalationAlert($complaint, $analysis);
            
            $this->printResult("Escalation completed", "Status: Escalated, Notifications sent");
            
        } catch (\Exception $e) {
            $this->printError("Escalation failed: " . $e->getMessage());
        }
    }
    
    private function displayAnalysisDetails(ComplaintAnalysis $analysis): void
    {
        echo "📊 AI Analysis Results:\n";
        echo "   • Risk Score: " . number_format($analysis->risk_score, 2) . "/1.00\n";
        echo "   • Category: {$analysis->category}\n";
        echo "   • Sentiment: {$analysis->sentiment}\n";
        echo "   • Priority: {$analysis->priority}\n";
        echo "   • Tags: " . implode(', ', $analysis->tags ?? []) . "\n";
        echo "   • Summary: " . substr($analysis->summary ?? 'N/A', 0, 100) . "...\n\n";
    }
    
    private function printFinalStatus(Complaint $complaint): void
    {
        $this->printStep("📋 FINAL STATUS", "Complaint processing complete");
        
        echo "Complaint ID: {$complaint->id}\n";
        echo "Status: {$complaint->status}\n";
        echo "Processing Time: " . $complaint->created_at->diffForHumans() . "\n";
        
        if ($complaint->analysis) {
            echo "Risk Level: " . ($complaint->analysis->risk_score >= 0.8 ? '🔴 Critical' : 
                              ($complaint->analysis->risk_score >= 0.6 ? '🟡 High' : '🟢 Normal')) . "\n";
        }
        
        echo "\n";
    }
    
    private function printHeader(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🚨 LaraCity Emergency Complaint Processing Demo\n";
        echo "Demonstrating AI-powered risk assessment and escalation workflow\n";
        echo str_repeat("=", 80) . "\n\n";
    }
    
    private function printStep(string $title, string $description): void
    {
        echo "\n{$title}\n";
        echo str_repeat("-", strlen($title)) . "\n";
        echo "{$description}\n\n";
    }
    
    private function printResult(string $action, string $result): void
    {
        echo "✅ {$action}: {$result}\n";
    }
    
    private function printError(string $message): void
    {
        echo "❌ ERROR: {$message}\n";
    }
    
    private function printFooter(): void
    {
        echo str_repeat("=", 80) . "\n";
        echo "Demo completed! Check your Slack channel for escalation alerts.\n";
        echo "📚 Learn more: See Tutorial-Details.md for implementation details\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Main execution
if ($argc < 2) {
    echo "Usage: php demo-emergency-complaint.php [scenario]\n";
    echo "Scenarios: gas_leak, structural, water_main\n";
    exit(1);
}

$scenario = $argv[1] ?? 'gas_leak';
$demo = new EmergencyComplaintDemo();
$demo->runDemo($scenario);