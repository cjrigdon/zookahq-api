<?php

namespace App\Services;

use App\Models\Events\EventChange;

class EventChangeLogger
{
    public static function log(
        int $eventId,
        string $summary,
        string $entityType,
        ?int $entityId = null,
        string $changeType = 'update',
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
    ): EventChange {
        return EventChange::create([
            'event_id' => $eventId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field' => $field,
            'old_value' => is_scalar($oldValue) || $oldValue === null ? $oldValue : json_encode($oldValue),
            'new_value' => is_scalar($newValue) || $newValue === null ? $newValue : json_encode($newValue),
            'change_type' => $changeType,
            'summary' => $summary,
            'changed_by' => auth()->id(),
        ]);
    }
}
