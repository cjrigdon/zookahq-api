<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('account_address_2')->nullable()->after('account_address');
            $table->string('account_city')->nullable()->after('account_address_2');
            $table->string('account_state')->nullable()->after('account_city');
            $table->string('account_postal_code')->nullable()->after('account_state');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('address_2')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'account_address_2',
                'account_city',
                'account_state',
                'account_postal_code',
            ]);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('address_2');
        });
    }
};
