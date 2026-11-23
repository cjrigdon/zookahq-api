<?php

namespace App\Services\Documents;

use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Documents\Document;
use App\Models\Events\Event;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Documentables
{
    /** @var array<string, class-string<Model>> */
    public const MAP = Document::DOCUMENTABLE_MAP;

    public static function assertType(string $type): string
    {
        if (! array_key_exists($type, self::MAP)) {
            throw new InvalidArgumentException("Invalid document type: {$type}");
        }

        return $type;
    }

    public static function findOrFail(string $type, int $id): Model
    {
        $class = self::MAP[self::assertType($type)];

        return $class::query()->findOrFail($id);
    }

    public static function typeFor(Model $model): string
    {
        foreach (self::MAP as $type => $class) {
            if ($model instanceof $class) {
                return $type;
            }
        }

        throw new InvalidArgumentException('Unsupported documentable '. $model::class);
    }

    public static function loadForMerge(string $type, int $id): Model
    {
        $model = self::findOrFail($type, $id);

        if ($model instanceof Event) {
            $model->loadMissing(['account.contacts', 'contact', 'eventType']);
        } elseif ($model instanceof Account) {
            $model->loadMissing(['contacts']);
        } elseif ($model instanceof Contact) {
            $model->loadMissing(['account']);
        }

        return $model;
    }
}
