<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('space_styles', 'space_capacities');

        Schema::create('space_styles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('code');
            $table->index(['is_active', 'sort_order']);
        });

        $existing = DB::table('space_capacities')
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name');

        $usedCodes = [];
        $nameToId = [];
        $sort = 0;

        foreach ($existing as $name) {
            $code = $this->uniqueCode((string) $name, $usedCodes);
            $usedCodes[] = $code;
            $id = DB::table('space_styles')->insertGetId([
                'code' => $code,
                'name' => $name,
                'description' => null,
                'sort_order' => $sort++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nameToId[$name] = $id;
        }

        Schema::table('space_capacities', function (Blueprint $table) {
            $table->unsignedBigInteger('space_style_id')->nullable()->after('space_id');
        });

        foreach (DB::table('space_capacities')->get() as $row) {
            DB::table('space_capacities')
                ->where('id', $row->id)
                ->update(['space_style_id' => $nameToId[$row->name] ?? null]);
        }

        DB::statement('ALTER TABLE space_capacities MODIFY space_style_id BIGINT UNSIGNED NOT NULL');

        Schema::table('space_capacities', function (Blueprint $table) {
            $table->foreign('space_style_id')->references('id')->on('space_styles')->cascadeOnDelete();
            $table->dropColumn('name');
            $table->unique(['space_id', 'space_style_id']);
        });
    }

    public function down(): void
    {
        Schema::table('space_capacities', function (Blueprint $table) {
            $table->string('name')->nullable()->after('space_id');
        });

        foreach (DB::table('space_capacities')->get() as $row) {
            $style = DB::table('space_styles')->where('id', $row->space_style_id)->first();
            DB::table('space_capacities')
                ->where('id', $row->id)
                ->update(['name' => $style?->name ?? 'Style']);
        }

        Schema::table('space_capacities', function (Blueprint $table) {
            $table->dropUnique(['space_id', 'space_style_id']);
            $table->dropForeign(['space_style_id']);
            $table->dropColumn('space_style_id');
        });

        DB::statement('ALTER TABLE space_capacities MODIFY name VARCHAR(255) NOT NULL');

        Schema::dropIfExists('space_styles');
        Schema::rename('space_capacities', 'space_styles');
    }

    private function uniqueCode(string $name, array $usedCodes): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = $base !== '' ? substr($base, 0, 48) : 'STYLE';
        $code = $base;
        $suffix = 2;
        while (in_array($code, $usedCodes, true)) {
            $code = substr($base, 0, 44).'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
};
