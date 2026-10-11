<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('each');
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ar_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('status')->default('draft'); // draft, sent, partial, paid, void
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_email')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->text('notes')->nullable();
            $table->string('portal_token', 64)->nullable()->unique();
            $table->timestamp('portal_token_expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ar_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_invoice_id')->constrained('ar_invoices')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ar_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_invoice_id')->constrained('ar_invoices')->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->string('status')->default('completed'); // pending, completed, failed, refunded
            $table->string('method')->default('card'); // card, check, ach, cash, other
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->string('authorize_net_trans_id')->nullable();
            $table->json('authorize_net_response')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('via_portal')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ar_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period_type')->default('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->decimal('budget_amount', 12, 2);
            $table->decimal('actual_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gl_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->string('status')->default('posted'); // draft, posted, void
            $table->string('memo')->nullable();
            $table->string('source')->nullable(); // manual, ar_payment, ap_payment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gl_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gl_journal_entry_id')->constrained('gl_journal_entries')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained('gl_accounts')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('ap_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->string('vendor_name');
            $table->string('vendor_email')->nullable();
            $table->string('status')->default('open'); // draft, open, partial, paid, void
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ap_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_bill_id')->constrained('ap_bills')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('ap_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_bill_id')->constrained('ap_bills')->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->string('status')->default('completed');
            $table->string('method')->default('check');
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payments');
        Schema::dropIfExists('ap_bill_lines');
        Schema::dropIfExists('ap_bills');
        Schema::dropIfExists('gl_journal_lines');
        Schema::dropIfExists('gl_journal_entries');
        Schema::dropIfExists('ar_budgets');
        Schema::dropIfExists('ar_payments');
        Schema::dropIfExists('ar_invoice_lines');
        Schema::dropIfExists('ar_invoices');
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('inventory_items');
    }
};
