<?php

namespace App\Providers;

use App\Models\Documents\Document;
use App\Models\Notifications\SystemNotification;
use App\Services\Checklistables;
use App\Services\ChecklistStarter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Relation::morphMap(array_merge(Checklistables::MAP, Document::DOCUMENTABLE_MAP));
        $this->registerRequiredChecklistHooks();

        Route::bind('notification', function (string $value) {
            return SystemNotification::query()->findOrFail($value);
        });
    }

    protected function registerRequiredChecklistHooks(): void
    {
        foreach (Checklistables::MAP as $type => $class) {
            /** @var class-string<Model> $class */
            $class::created(function (Model $model) use ($type) {
                ChecklistStarter::attachRequired($type, $model);
            });
        }
    }
}

