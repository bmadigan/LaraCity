<?php

use App\Console\Commands\ImportCsvCommand;
use App\Models\Complaint;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('import csv command requires file argument', function () {
    $this->artisan('import:csv')
        ->expectsOutput('The file argument is required.')
        ->assertExitCode(1);
});

test('import csv command handles non-existent file', function () {
    $this->artisan('import:csv', ['file' => 'nonexistent.csv'])
        ->expectsOutput('File not found: nonexistent.csv')
        ->assertExitCode(1);
});

test('import csv command imports valid complaint data', function () {
    // Create a test CSV file
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough,incident_address,status,submitted_at\n";
    $csvContent .= "12345,Noise - Residential,Loud music,NYPD,MANHATTAN,123 Main St,Open,2024-01-15 10:30:00\n";
    $csvContent .= "12346,Water System,Broken pipe,DEP,BROOKLYN,456 Oak Ave,Closed,2024-01-16 14:20:00\n";
    
    Storage::put('test_complaints.csv', $csvContent);
    $filePath = Storage::path('test_complaints.csv');
    
    expect(Complaint::count())->toBe(0);
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Starting CSV import from: ' . $filePath)
        ->expectsOutput('Successfully imported 2 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(2);
    
    $complaint1 = Complaint::where('complaint_number', '12345')->first();
    expect($complaint1)->not->toBeNull()
        ->and($complaint1->complaint_type)->toBe('Noise - Residential')
        ->and($complaint1->descriptor)->toBe('Loud music')
        ->and($complaint1->agency)->toBe('NYPD')
        ->and($complaint1->borough)->toBe('MANHATTAN');
    
    $complaint2 = Complaint::where('complaint_number', '12346')->first();
    expect($complaint2)->not->toBeNull()
        ->and($complaint2->complaint_type)->toBe('Water System')
        ->and($complaint2->status)->toBe('Closed');
});

test('import csv command handles malformed csv gracefully', function () {
    // Create malformed CSV (missing columns)
    $csvContent = "complaint_number,complaint_type\n";
    $csvContent .= "12345,Noise - Residential\n";
    $csvContent .= "12346\n"; // Missing data
    
    Storage::put('malformed.csv', $csvContent);
    $filePath = Storage::path('malformed.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Starting CSV import from: ' . $filePath)
        ->expectsOutput('Encountered 1 errors during import')
        ->assertExitCode(0);
    
    // Should import the valid row
    expect(Complaint::count())->toBe(1);
    expect(Complaint::first()->complaint_number)->toBe('12345');
});

test('import csv command skips duplicate complaint numbers', function () {
    // Create existing complaint
    Complaint::factory()->create(['complaint_number' => '12345']);
    
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    $csvContent .= "12345,Noise - Residential,Loud music,NYPD,MANHATTAN\n";
    $csvContent .= "12346,Water System,Broken pipe,DEP,BROOKLYN\n";
    
    Storage::put('duplicates.csv', $csvContent);
    $filePath = Storage::path('duplicates.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Skipped 1 duplicate complaint numbers')
        ->expectsOutput('Successfully imported 1 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(2); // 1 existing + 1 new
    expect(Complaint::where('complaint_number', '12346')->exists())->toBeTrue();
});

test('import csv command handles empty file', function () {
    Storage::put('empty.csv', '');
    $filePath = Storage::path('empty.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('CSV file is empty or has no data rows')
        ->assertExitCode(1);
});

test('import csv command handles file with only headers', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    
    Storage::put('headers_only.csv', $csvContent);
    $filePath = Storage::path('headers_only.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('CSV file is empty or has no data rows')
        ->assertExitCode(1);
});

test('import csv command respects batch size option', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    for ($i = 1; $i <= 10; $i++) {
        $csvContent .= "1234{$i},Test Type,Test Description,TEST,MANHATTAN\n";
    }
    
    Storage::put('batch_test.csv', $csvContent);
    $filePath = Storage::path('batch_test.csv');
    
    $this->artisan('import:csv', ['file' => $filePath, '--batch-size' => 3])
        ->expectsOutput('Starting CSV import from: ' . $filePath)
        ->expectsOutput('Batch size: 3')
        ->expectsOutput('Successfully imported 10 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(10);
});

test('import csv command shows progress for large files', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    for ($i = 1; $i <= 25; $i++) {
        $csvContent .= "1234{$i},Test Type,Test Description,TEST,MANHATTAN\n";
    }
    
    Storage::put('progress_test.csv', $csvContent);
    $filePath = Storage::path('progress_test.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Processed 20 rows...')
        ->expectsOutput('Successfully imported 25 complaints')
        ->assertExitCode(0);
});

test('import csv command handles invalid date formats', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough,submitted_at\n";
    $csvContent .= "12345,Test Type,Test Description,TEST,MANHATTAN,invalid-date\n";
    $csvContent .= "12346,Test Type,Test Description,TEST,MANHATTAN,2024-01-15 10:30:00\n";
    
    Storage::put('invalid_dates.csv', $csvContent);
    $filePath = Storage::path('invalid_dates.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Encountered 1 errors during import')
        ->expectsOutput('Successfully imported 1 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(1);
    expect(Complaint::first()->complaint_number)->toBe('12346');
});

test('import csv command handles missing required fields', function () {
    $csvContent = "complaint_number,complaint_type,descriptor\n";
    $csvContent .= ",Test Type,Test Description\n"; // Missing complaint_number
    $csvContent .= "12346,Test Type,Test Description\n";
    
    Storage::put('missing_required.csv', $csvContent);
    $filePath = Storage::path('missing_required.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Encountered 1 errors during import')
        ->expectsOutput('Successfully imported 1 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(1);
});

test('import csv command provides detailed error reporting', function () {
    $csvContent = "complaint_number,complaint_type,descriptor\n";
    $csvContent .= "12345,Test Type,Test Description\n";
    $csvContent .= ",Invalid Row,Missing Number\n"; // Error on row 3 (including header)
    $csvContent .= "12347,Valid Type,Valid Description\n";
    
    Storage::put('error_reporting.csv', $csvContent);
    $filePath = Storage::path('error_reporting.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Encountered 1 errors during import')
        ->expectsOutput('Successfully imported 2 complaints')
        ->assertExitCode(0);
});

test('import csv command validates file extension', function () {
    // Create a non-CSV file
    Storage::put('test_file.txt', 'This is not a CSV file');
    $filePath = Storage::path('test_file.txt');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('File must have .csv extension')
        ->assertExitCode(1);
});

test('import csv command handles very large complaint numbers', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    $csvContent .= "999999999999999,Test Type,Test Description,TEST,MANHATTAN\n";
    
    Storage::put('large_numbers.csv', $csvContent);
    $filePath = Storage::path('large_numbers.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Successfully imported 1 complaints')
        ->assertExitCode(0);
    
    $complaint = Complaint::first();
    expect($complaint->complaint_number)->toBe('999999999999999');
});

test('import csv command handles special characters in data', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    $csvContent .= "12345,\"Noise - Résidentiel\",\"Müsic très loud\",NYPD,MANHATTAN\n";
    
    Storage::put('special_chars.csv', $csvContent);
    $filePath = Storage::path('special_chars.csv');
    
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Successfully imported 1 complaints')
        ->assertExitCode(0);
    
    $complaint = Complaint::first();
    expect($complaint->complaint_type)->toBe('Noise - Résidentiel')
        ->and($complaint->descriptor)->toBe('Müsic très loud');
});

test('import csv command can be interrupted and resumed', function () {
    $csvContent = "complaint_number,complaint_type,descriptor,agency,borough\n";
    for ($i = 1; $i <= 5; $i++) {
        $csvContent .= "1234{$i},Test Type,Test Description,TEST,MANHATTAN\n";
    }
    
    Storage::put('resume_test.csv', $csvContent);
    $filePath = Storage::path('resume_test.csv');
    
    // First run - import some data
    Complaint::factory()->create(['complaint_number' => '12341']);
    Complaint::factory()->create(['complaint_number' => '12342']);
    
    // Resume import (should skip existing)
    $this->artisan('import:csv', ['file' => $filePath])
        ->expectsOutput('Skipped 2 duplicate complaint numbers')
        ->expectsOutput('Successfully imported 3 complaints')
        ->assertExitCode(0);
    
    expect(Complaint::count())->toBe(5);
});