<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('applies_to', 32);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['applies_to', 'is_active']);
        });

        Schema::create('checklist_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->index(['checklist_id', 'sort_order']);
        });

        Schema::create('checklist_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->restrictOnDelete();
            $table->string('checklistable_type', 32);
            $table->unsignedBigInteger('checklistable_id');
            $table->string('status', 32)->default('open');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['checklistable_type', 'checklistable_id'], 'checklist_instances_morph_index');
            $table->index(['checklist_id', 'status']);
        });

        Schema::create('checklist_instance_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_instance_id')->constrained('checklist_instances')->cascadeOnDelete();
            $table->foreignId('checklist_step_id')->nullable()->constrained('checklist_steps')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['checklist_instance_id', 'sort_order'], 'checklist_instance_steps_order_index');
        });

        Schema::create('checklist_step_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_instance_step_id')->constrained('checklist_instance_steps')->cascadeOnDelete();
            $table->string('action', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['checklist_instance_step_id', 'occurred_at'], 'checklist_step_events_step_time_index');
        });

        if (Schema::hasTable('permissions')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $now = now();
            $permissionId = DB::table('permissions')->where('name', 'admin.checklists.manage')->where('guard_name', 'web')->value('id');
            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => 'admin.checklists.manage',
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $adminRoleId = DB::table('roles')->where('name', 'tenant_admin')->where('guard_name', 'web')->value('id');
            if ($adminRoleId && $permissionId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $adminRoleId)
                    ->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $adminRoleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_step_events');
        Schema::dropIfExists('checklist_instance_steps');
        Schema::dropIfExists('checklist_instances');
        Schema::dropIfExists('checklist_steps');
        Schema::dropIfExists('checklists');
    }
};
