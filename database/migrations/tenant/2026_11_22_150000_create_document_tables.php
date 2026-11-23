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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('applies_to', 32);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('kind', 32)->default('docx_merge');
            $table->string('original_filename');
            $table->string('disk_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['applies_to', 'is_active']);
            $table->index(['applies_to', 'is_default']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('documentable_type', 32);
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('disk_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 32)->default('generated');
            $table->string('emailed_to')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->json('merge_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['documentable_type', 'documentable_id'], 'documents_morph_index');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('document_template_id')->nullable()->after('event_id')->constrained('document_templates')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->after('document_template_id')->constrained('documents')->nullOnDelete();
        });

        if (Schema::hasTable('permissions')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $now = now();
            $permissions = [
                'admin.documents.manage',
                'booking.documents.view',
                'booking.documents.manage',
            ];

            $ids = [];
            foreach ($permissions as $name) {
                $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
                if (! $id) {
                    $id = DB::table('permissions')->insertGetId([
                        'name' => $name,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $ids[$name] = $id;
            }

            $grant = [
                'tenant_admin' => $permissions,
                'sales' => ['booking.documents.view', 'booking.documents.manage'],
                'read_only' => ['booking.documents.view'],
            ];

            foreach ($grant as $roleName => $names) {
                $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
                if (! $roleId) {
                    continue;
                }
                foreach ($names as $name) {
                    $permissionId = $ids[$name];
                    $exists = DB::table('role_has_permissions')
                        ->where('permission_id', $permissionId)
                        ->where('role_id', $roleId)
                        ->exists();
                    if (! $exists) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
            $table->dropConstrainedForeignId('document_template_id');
        });
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_templates');
    }
};
