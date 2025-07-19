<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Complaints\CreateComplaintAnalysis;
use App\Actions\Complaints\CreateAction;
use App\Models\Action;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use App\Models\User;
use App\Models\UserQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting LaraCity database seeding...');
        
        // Create users
        $this->seedUsers();
        
        // Only seed demo data if no real complaints exist
        if (Complaint::count() === 0) {
            $this->command->info('📊 No real complaint data found. Creating demo data...');
            $this->seedDemoComplaints();
        } else {
            $this->command->info('📊 Real complaint data found. Creating analysis for existing complaints...');
            $this->seedAnalysisForExistingComplaints();
        }
        
        // Create sample user questions for chat/RAG system
        $this->seedUserQuestions();
        
        $this->command->info('✅ Database seeding completed!');
        $this->displaySeedingSummary();
    }
    
    /**
     * Create demo users for testing
     */
    private function seedUsers(): void
    {
        $this->command->info('👥 Creating demo users...');
        
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@laracity.test'],
            [
                'name' => 'LaraCity Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        
        // Analyst user
        User::firstOrCreate(
            ['email' => 'analyst@laracity.test'],
            [
                'name' => 'Data Analyst', 
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        
        // Field worker user
        User::firstOrCreate(
            ['email' => 'field@laracity.test'],
            [
                'name' => 'Field Coordinator',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        
        $this->command->info('   ✓ Ensured 3 demo users exist');
    }
    
    /**
     * Create demo complaints with realistic NYC 311 data patterns
     */
    private function seedDemoComplaints(): void
    {
        $this->command->info('🏙️ Creating demo NYC 311 complaints...');
        
        // High-risk complaints (water, heat, traffic safety)
        $highRiskComplaints = Complaint::factory(15)->create([
            'complaint_type' => 'Water System',
            'agency' => 'DEP',
            'agency_name' => 'Department of Environmental Protection',
            'borough' => 'MANHATTAN',
            'priority' => Complaint::PRIORITY_HIGH,
        ]);
        
        Complaint::factory(10)->create([
            'complaint_type' => 'Heat/Hot Water',
            'agency' => 'HPD',
            'agency_name' => 'Housing Preservation and Development',
            'borough' => 'BROOKLYN',
            'priority' => Complaint::PRIORITY_HIGH,
        ]);
        
        // Medium-risk complaints (street conditions, sanitation)
        Complaint::factory(25)->create([
            'complaint_type' => 'Street Condition',
            'agency' => 'DOT',
            'agency_name' => 'Department of Transportation',
            'borough' => 'QUEENS',
            'priority' => Complaint::PRIORITY_MEDIUM,
        ]);
        
        // Low-risk complaints (noise, parking)
        Complaint::factory(50)->create([
            'complaint_type' => 'Noise - Street/Sidewalk',
            'agency' => 'NYPD',
            'agency_name' => 'New York City Police Department',
            'borough' => 'MANHATTAN',
            'priority' => Complaint::PRIORITY_LOW,
        ]);
        
        Complaint::factory(30)->create([
            'complaint_type' => 'Illegal Parking',
            'agency' => 'NYPD',
            'agency_name' => 'New York City Police Department',
            'borough' => 'BRONX',
            'priority' => Complaint::PRIORITY_LOW,
        ]);
        
        $this->command->info('   ✓ Created 130 demo complaints across all boroughs');
        
        // Create AI analyses for all demo complaints
        $this->createAnalysisForComplaints(Complaint::all());
        
        // Create sample actions (escalations, notifications)
        $this->seedActions();
    }
    
    /**
     * Create AI analysis for existing real complaints (sampled)
     */
    private function seedAnalysisForExistingComplaints(): void
    {
        $totalComplaints = Complaint::count();
        
        if ($totalComplaints > 1000) {
            // For large datasets, only analyze a sample
            $sampleSize = min(200, intval($totalComplaints * 0.1)); // 10% or max 200
            $complaints = Complaint::inRandomOrder()->limit($sampleSize)->get();
            $this->command->info("   📊 Analyzing sample of {$sampleSize} complaints from {$totalComplaints} total...");
        } else {
            // For smaller datasets, analyze all
            $complaints = Complaint::all();
            $this->command->info("   📊 Analyzing all {$totalComplaints} complaints...");
        }
        
        $this->createAnalysisForComplaints($complaints);
        
        // Create sample actions
        $this->seedActions();
    }
    
    /**
     * Create AI analysis records for given complaints
     */
    private function createAnalysisForComplaints($complaints): void
    {
        $this->command->info('🤖 Generating AI analysis data...');
        
        $analysisCount = 0;
        
        foreach ($complaints as $complaint) {
            // Skip if analysis already exists
            if ($complaint->analysis) {
                continue;
            }
            
            // Generate risk-appropriate analysis based on complaint type
            $analysis = $this->generateAnalysisForComplaint($complaint);
            
            CreateComplaintAnalysis::run($complaint, $analysis);
            
            $analysisCount++;
        }
        
        $this->command->info("   ✓ Created {$analysisCount} AI analysis records");
    }
    
    /**
     * Generate realistic analysis based on complaint characteristics
     */
    private function generateAnalysisForComplaint(Complaint $complaint): array
    {
        $type = $complaint->complaint_type;
        
        // Risk scoring based on complaint type
        $highRiskTypes = ['Water System', 'Heat/Hot Water', 'Gas', 'Elevator', 'Traffic Signal'];
        $mediumRiskTypes = ['Street Condition', 'Sanitation', 'Animal Abuse', 'Graffiti'];
        
        if (str_contains($type, 'Water') || str_contains($type, 'Heat') || str_contains($type, 'Gas')) {
            $riskScore = fake()->randomFloat(2, 0.6, 1.0);
            $category = 'Infrastructure';
            $summary = 'Critical infrastructure issue requiring immediate attention and potential escalation.';
            $tags = ['high-priority', 'infrastructure', 'urgent', 'escalate'];
        } elseif (str_contains($type, 'Noise') || str_contains($type, 'Parking')) {
            $riskScore = fake()->randomFloat(2, 0.0, 0.4);
            $category = str_contains($type, 'Noise') ? 'Quality of Life' : 'Traffic & Transportation';
            $summary = 'Routine community issue requiring standard processing and resolution.';
            $tags = ['routine', 'community', 'standard-process'];
        } else {
            $riskScore = fake()->randomFloat(2, 0.2, 0.7);
            $category = 'General Services';
            $summary = 'Municipal service request requiring timely response and appropriate resolution.';
            $tags = ['standard', 'municipal-services'];
        }
        
        return [
            'summary' => $summary,
            'risk_score' => $riskScore,
            'category' => $category,
            'tags' => $tags,
        ];
    }
    
    /**
     * Create sample actions for audit trail
     */
    private function seedActions(): void
    {
        $this->command->info('📋 Creating sample actions for audit trail...');
        
        // Get high-risk complaints for escalation actions
        $highRiskComplaints = Complaint::whereHas('analysis', function ($query) {
            $query->where('risk_score', '>=', 0.7);
        })->limit(10)->get();
        
        foreach ($highRiskComplaints as $complaint) {
            // Create escalation action
            Action::factory()->escalation()->create([
                'complaint_id' => $complaint->id,
            ]);
            
            // Create notification action
            Action::factory()->notification()->create([
                'complaint_id' => $complaint->id,
            ]);
            
            // Create analysis action
            Action::factory()->analysis()->create([
                'complaint_id' => $complaint->id,
            ]);
        }
        
        // Create some general system actions
        Action::factory(15)->systemTriggered()->create();
        Action::factory(5)->userTriggered()->create();
        
        $this->command->info('   ✓ Created sample actions and audit trail');
    }
    
    /**
     * Create sample user questions for RAG/chat system
     */
    private function seedUserQuestions(): void
    {
        $this->command->info('💬 Creating sample user questions for chat system...');
        
        $users = User::all();
        
        $sampleQuestions = [
            'Show me all noise complaints in Manhattan from last week',
            'What are the highest risk complaints currently open?',
            'How many water system complaints were resolved this month?',
            'Which borough has the most traffic-related complaints?',
            'Find all escalated complaints from the NYPD',
            'What is the average resolution time for heat complaints?',
            'Show me complaints near Central Park',
            'Which agency handles the most high-priority complaints?',
            'Find all complaints with risk score above 0.8',
            'What are the trending complaint types in Brooklyn?'
        ];
        
        foreach ($sampleQuestions as $question) {
            UserQuestion::create([
                'question' => $question,
                'user_id' => $users->random()->id,
                'conversation_id' => fake()->uuid(),
                'parsed_filters' => $this->generateParsedFilters($question),
                'ai_response' => 'This is a sample AI response that would be generated by the RAG system in Phase E.',
            ]);
        }
        
        $this->command->info('   ✓ Created 10 sample user questions');
    }
    
    /**
     * Generate realistic parsed filters for sample questions
     */
    private function generateParsedFilters(string $question): array
    {
        $filters = [];
        
        if (str_contains(strtolower($question), 'manhattan')) {
            $filters['borough'] = 'MANHATTAN';
        }
        if (str_contains(strtolower($question), 'brooklyn')) {
            $filters['borough'] = 'BROOKLYN';
        }
        if (str_contains(strtolower($question), 'noise')) {
            $filters['complaint_type'] = 'Noise - Street/Sidewalk';
        }
        if (str_contains(strtolower($question), 'water')) {
            $filters['complaint_type'] = 'Water System';
        }
        if (str_contains(strtolower($question), 'nypd')) {
            $filters['agency'] = 'NYPD';
        }
        if (str_contains(strtolower($question), 'last week')) {
            $filters['date_range'] = [
                'start' => now()->subWeek()->format('Y-m-d'),
                'end' => now()->format('Y-m-d')
            ];
        }
        
        return $filters;
    }
    
    /**
     * Display seeding summary
     */
    private function displaySeedingSummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 Database Seeding Summary:');
        $this->command->table(
            ['Table', 'Records'],
            [
                ['Users', User::count()],
                ['Complaints', Complaint::count()],
                ['Complaint Analyses', ComplaintAnalysis::count()],
                ['Actions', Action::count()],
                ['User Questions', UserQuestion::count()],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('🎯 Ready for Phase C: API Controllers');
        $this->command->info('   Next: php artisan /phase:c-api-controllers');
    }
}
