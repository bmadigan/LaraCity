<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Services\CsvImportService;

class ImportCsvData
{
    public static function run(string $filePath, bool $validate = true): array
    {
        $importService = new CsvImportService();
        
        return $importService->import($filePath, $validate);
    }
}