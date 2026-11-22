<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('company', 'account_name');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('first_name', 'contact_first_name');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('last_name', 'contact_last_name');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('email', 'contact_email');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('phone', 'contact_phone');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->string('account_address')->nullable()->after('account_name');
            $table->string('contact_title')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['account_address', 'contact_title']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('account_name', 'company');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('contact_first_name', 'first_name');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('contact_last_name', 'last_name');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('contact_email', 'email');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('contact_phone', 'phone');
        });
    }
};
