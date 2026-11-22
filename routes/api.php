<?php

use App\Http\Controllers\Admin\ChecklistController;
use App\Http\Controllers\Admin\EventTypeController as AdminEventTypeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SpaceAttributeController;
use App\Http\Controllers\Admin\SpaceController;
use App\Http\Controllers\Admin\SpaceStyleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\PlatformAuthController;
use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\Booking\AccountController;
use App\Http\Controllers\Booking\AccountNoteController;
use App\Http\Controllers\Booking\BookingSearchController;
use App\Http\Controllers\Booking\ContactController;
use App\Http\Controllers\Booking\ContractController;
use App\Http\Controllers\Booking\LeadController;
use App\Http\Controllers\Booking\ProposalController;
use App\Http\Controllers\Booking\SalesGoalController;
use App\Http\Controllers\Events\CalendarActivityController;
use App\Http\Controllers\Events\EventChangeController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventDignitaryController;
use App\Http\Controllers\Events\EventFunctionController;
use App\Http\Controllers\Events\EventNoteController;
use App\Http\Controllers\Events\EventTypeController;
use App\Http\Controllers\Events\ServiceOrderController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\ApBillController;
use App\Http\Controllers\Accounting\ApPaymentController;
use App\Http\Controllers\Accounting\ArBudgetController;
use App\Http\Controllers\Accounting\ArInvoiceController;
use App\Http\Controllers\Accounting\ArPaymentController;
use App\Http\Controllers\Accounting\GlAccountController;
use App\Http\Controllers\Accounting\GlJournalEntryController;
use App\Http\Controllers\Accounting\InventoryItemController;
use App\Http\Controllers\Accounting\PaymentPortalController;
use App\Http\Controllers\Reporting\AiReportController;
use App\Http\Controllers\Reporting\SavedReportController;
use App\Http\Controllers\Reporting\StandardReportController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Operations\ChecklistInstanceController;
use App\Http\Controllers\Operations\WorkOrderController;
use App\Http\Controllers\Intake\AddressSuggestionController;
use App\Http\Controllers\Intake\LeadIntakeController;
use App\Http\Controllers\Platform\ModuleController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\InitializeTenancyBySlug;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Platform (central) auth + admin
    Route::prefix('platform')->group(function () {
        Route::post('auth/login', [PlatformAuthController::class, 'login']);

        Route::middleware('auth:platform')->group(function () {
            Route::post('auth/logout', [PlatformAuthController::class, 'logout']);
            Route::get('auth/me', [PlatformAuthController::class, 'me']);
            Route::put('auth/profile', [PlatformAuthController::class, 'updateProfile']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
            Route::put('notifications/preferences', [NotificationController::class, 'updatePreferences']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

            Route::apiResource('tenants', TenantController::class);
            Route::put('tenants/{tenant}/modules', [TenantController::class, 'syncModules']);
            Route::get('modules', [ModuleController::class, 'index']);
            Route::put('modules/{module}', [ModuleController::class, 'update']);
        });
    });

    // Tenant-scoped routes
    Route::middleware([InitializeTenancyBySlug::class])->group(function () {
        Route::post('auth/login', [TenantAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('auth/logout', [TenantAuthController::class, 'logout']);
            Route::get('auth/me', [TenantAuthController::class, 'me']);
            Route::put('auth/profile', [TenantAuthController::class, 'updateProfile']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
            Route::put('notifications/preferences', [NotificationController::class, 'updatePreferences']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

            Route::prefix('admin')->group(function () {
                Route::get('users', [UserController::class, 'index']);
                Route::post('users', [UserController::class, 'store']);
                Route::put('users/{user}', [UserController::class, 'update']);
                Route::delete('users/{user}', [UserController::class, 'destroy']);
                Route::get('roles', [UserController::class, 'roles']);
                Route::get('settings', [SettingController::class, 'show']);
                Route::put('settings', [SettingController::class, 'update']);
                Route::get('checklists', [ChecklistController::class, 'index']);
                Route::post('checklists', [ChecklistController::class, 'store']);
                Route::get('checklists/{checklist}', [ChecklistController::class, 'show']);
                Route::put('checklists/{checklist}', [ChecklistController::class, 'update']);
                Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy']);

                Route::get('space-attributes', [SpaceAttributeController::class, 'index']);
                Route::post('space-attributes', [SpaceAttributeController::class, 'store']);
                Route::put('space-attributes/{spaceAttribute}', [SpaceAttributeController::class, 'update']);
                Route::delete('space-attributes/{spaceAttribute}', [SpaceAttributeController::class, 'destroy']);
                Route::get('space-styles', [SpaceStyleController::class, 'index']);
                Route::post('space-styles', [SpaceStyleController::class, 'store']);
                Route::put('space-styles/{spaceStyle}', [SpaceStyleController::class, 'update']);
                Route::delete('space-styles/{spaceStyle}', [SpaceStyleController::class, 'destroy']);
                Route::apiResource('spaces', SpaceController::class);
                Route::get('event-types', [AdminEventTypeController::class, 'index']);
                Route::post('event-types', [AdminEventTypeController::class, 'store']);
                Route::put('event-types/{eventType}', [AdminEventTypeController::class, 'update']);
                Route::delete('event-types/{eventType}', [AdminEventTypeController::class, 'destroy']);
            });

            Route::prefix('checklists')->group(function () {
                Route::get('available', [ChecklistInstanceController::class, 'available']);
                Route::get('instances', [ChecklistInstanceController::class, 'index']);
                Route::post('instances', [ChecklistInstanceController::class, 'store']);
                Route::get('instances/{instance}', [ChecklistInstanceController::class, 'show']);
                Route::delete('instances/{instance}', [ChecklistInstanceController::class, 'destroy']);
                Route::post('instances/{instance}/submit', [ChecklistInstanceController::class, 'submit']);
                Route::post('instances/{instance}/steps/{step}/complete', [ChecklistInstanceController::class, 'completeStep']);
                Route::post('instances/{instance}/steps/{step}/uncomplete', [ChecklistInstanceController::class, 'uncompleteStep']);
            });

            Route::prefix('booking')->middleware([EnsureModuleEnabled::class.':booking'])->group(function () {
                Route::get('search', BookingSearchController::class);

                Route::apiResource('leads', LeadController::class);
                Route::get('leads/{lead}/matches', [LeadController::class, 'matches']);
                Route::post('leads/{lead}/attach', [LeadController::class, 'attach']);
                Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);

                Route::apiResource('accounts', AccountController::class);
                Route::get('accounts/{account}/notes', [AccountNoteController::class, 'index']);
                Route::post('accounts/{account}/notes', [AccountNoteController::class, 'store']);
                Route::put('accounts/{account}/notes/{note}', [AccountNoteController::class, 'update']);
                Route::delete('accounts/{account}/notes/{note}', [AccountNoteController::class, 'destroy']);
                Route::apiResource('contacts', ContactController::class);

                Route::apiResource('proposals', ProposalController::class);
                Route::post('proposals/{proposal}/contracts', [ProposalController::class, 'createContract']);

                Route::apiResource('contracts', ContractController::class)->except(['store']);
                Route::apiResource('sales-goals', SalesGoalController::class);
            });

            Route::prefix('events')->middleware([EnsureModuleEnabled::class.':events'])->group(function () {
                Route::get('calendar', [EventController::class, 'calendar']);
                Route::get('calendar/availability', [EventController::class, 'availability']);
                Route::get('calendar/space-options', [EventController::class, 'spaceOptions']);
                Route::get('event-types', [EventTypeController::class, 'index']);
                Route::apiResource('calendar-activities', CalendarActivityController::class);
                Route::apiResource('events', EventController::class);
                Route::post('events/{event}/dignitaries', [EventDignitaryController::class, 'store']);
                Route::put('events/{event}/dignitaries/{dignitary}', [EventDignitaryController::class, 'update']);
                Route::delete('events/{event}/dignitaries/{dignitary}', [EventDignitaryController::class, 'destroy']);
                Route::get('events/{event}/notes', [EventNoteController::class, 'index']);
                Route::post('events/{event}/notes', [EventNoteController::class, 'store']);
                Route::put('events/{event}/notes/{note}', [EventNoteController::class, 'update']);
                Route::delete('events/{event}/notes/{note}', [EventNoteController::class, 'destroy']);
                Route::apiResource('functions', EventFunctionController::class);
                Route::apiResource('service-orders', ServiceOrderController::class);
                Route::get('changes', [EventChangeController::class, 'index']);
            });

            Route::prefix('operations')->middleware([EnsureModuleEnabled::class.':operations'])->group(function () {
                Route::apiResource('work-orders', WorkOrderController::class);
            });

            Route::prefix('accounting')->middleware([EnsureModuleEnabled::class.':accounting'])->group(function () {
                Route::get('reports/summary', [AccountingReportController::class, 'summary']);
                Route::get('reports/ar-aging', [AccountingReportController::class, 'arAging']);
                Route::get('reports/ap-aging', [AccountingReportController::class, 'apAging']);
                Route::get('reports/trial-balance', [AccountingReportController::class, 'trialBalance']);
                Route::get('reports/budget-vs-actual', [AccountingReportController::class, 'budgetVsActual']);
                Route::apiResource('inventory-items', InventoryItemController::class);
                Route::post('inventory-items/{inventoryItem}/adjust', [InventoryItemController::class, 'adjust']);
                Route::apiResource('ar-invoices', ArInvoiceController::class);
                Route::post('ar-invoices/{arInvoice}/portal-link', [ArInvoiceController::class, 'issuePortalLink']);
                Route::get('ar-payments', [ArPaymentController::class, 'index']);
                Route::post('ar-payments', [ArPaymentController::class, 'store']);
                Route::apiResource('ar-budgets', ArBudgetController::class);
                Route::get('gl-accounts', [GlAccountController::class, 'index']);
                Route::post('gl-accounts', [GlAccountController::class, 'store']);
                Route::get('gl-journal-entries', [GlJournalEntryController::class, 'index']);
                Route::post('gl-journal-entries', [GlJournalEntryController::class, 'store']);
                Route::apiResource('ap-bills', ApBillController::class);
                Route::get('ap-payments', [ApPaymentController::class, 'index']);
                Route::post('ap-payments', [ApPaymentController::class, 'store']);
            });

            Route::prefix('reporting')->middleware([EnsureModuleEnabled::class.':reporting'])->group(function () {
                Route::get('overview', [StandardReportController::class, 'overview']);
                Route::get('catalog', [StandardReportController::class, 'catalog']);
                Route::post('run', [SavedReportController::class, 'run']);
                Route::post('ai/suggest', [AiReportController::class, 'suggest']);
                Route::get('saved-reports', [SavedReportController::class, 'index']);
                Route::post('saved-reports', [SavedReportController::class, 'store']);
                Route::get('saved-reports/{savedReport}', [SavedReportController::class, 'show']);
                Route::put('saved-reports/{savedReport}', [SavedReportController::class, 'update']);
                Route::delete('saved-reports/{savedReport}', [SavedReportController::class, 'destroy']);
                Route::post('saved-reports/{savedReport}/run', [SavedReportController::class, 'runSaved']);
            });
        });

        Route::prefix('public')->middleware([EnsureModuleEnabled::class.':booking'])->group(function () {
            Route::get('intake', [LeadIntakeController::class, 'show']);
            Route::post('leads', [LeadIntakeController::class, 'store'])->middleware('throttle:8,1');
            Route::get('address-suggestions', [AddressSuggestionController::class, 'index'])->middleware('throttle:30,1');
        });

        // Client payment portal (token auth, module must be enabled)
        Route::prefix('portal')->middleware([EnsureModuleEnabled::class.':accounting'])->group(function () {
            Route::get('invoices/{token}', [PaymentPortalController::class, 'show']);
            Route::post('invoices/{token}/pay', [PaymentPortalController::class, 'pay']);
        });
    });
});
