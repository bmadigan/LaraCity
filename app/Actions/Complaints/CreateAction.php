<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Models\Action;
use App\Models\Complaint;

class CreateAction
{
    public static function run(string $type, array $parameters, string $triggeredBy, ?Complaint $complaint = null): Action
    {
        return Action::create([
            'type' => $type,
            'parameters' => $parameters,
            'triggered_by' => $triggeredBy,
            'complaint_id' => $complaint?->id,
        ]);
    }
}