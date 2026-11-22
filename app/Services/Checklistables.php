<?php

namespace App\Services;

use App\Models\Booking\Account;
use App\Models\Booking\Lead;
use App\Models\Events\Event;
use App\Models\Events\EventFunction;
use App\Models\Events\ServiceOrder;
use App\Models\Operations\Checklist;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Checklistables
{
    /** @var array<string, class-string<Model>> */
    public const MAP = [
        'lead' => Lead::class,
        'account' => Account::class,
        'event' => Event::class,
        'function' => EventFunction::class,
        'service_order' => ServiceOrder::class,
    ];

    /** @var array<string, string> */
    public const VIEW_PERMISSIONS = [
        'lead' => 'booking.leads.view',
        'account' => 'booking.accounts.view',
        'event' => 'events.events.view',
        'function' => 'events.functions.view',
        'service_order' => 'events.service_orders.view',
    ];

    /** @var array<string, string> */
    public const MANAGE_PERMISSIONS = [
        'lead' => 'booking.leads.manage',
        'account' => 'booking.accounts.manage',
        'event' => 'events.events.manage',
        'function' => 'events.functions.manage',
        'service_order' => 'events.service_orders.manage',
    ];

    public static function assertAppliesTo(string $type): string
    {
        if (! in_array($type, Checklist::APPLIES_TO, true)) {
            throw new InvalidArgumentException("Invalid checklist applies_to: {$type}");
        }

        return $type;
    }

    public static function classFor(string $type): string
    {
        self::assertAppliesTo($type);

        return self::MAP[$type];
    }

    public static function findOrFail(string $type, int $id): Model
    {
        $class = self::classFor($type);

        return $class::query()->findOrFail($id);
    }

    public static function viewPermission(string $type): string
    {
        return self::VIEW_PERMISSIONS[self::assertAppliesTo($type)];
    }

    public static function managePermission(string $type): string
    {
        return self::MANAGE_PERMISSIONS[self::assertAppliesTo($type)];
    }
}
