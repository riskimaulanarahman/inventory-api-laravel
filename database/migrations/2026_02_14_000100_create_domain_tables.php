<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('display_name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('must_reset_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('email');
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->enum('type', ['company', 'individual']);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('profile_id');
            $table->enum('role', ['tenant_owner', 'tenant_admin', 'staff']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->unique(['tenant_id', 'profile_id']);
            $table->index('profile_id');
            $table->index(['tenant_id', 'role']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
        });

        Schema::create('membership_branch_access', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('membership_id');
            $table->uuid('branch_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('membership_id')->references('id')->on('memberships')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->unique(['membership_id', 'branch_id']);
            $table->index('branch_id');
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id');
            $table->uuid('unit_id');
            $table->string('name');
            $table->string('sku');
            $table->integer('central_stock')->default(0);
            $table->integer('minimum_low_stock')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('inventory_categories')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('inventory_units')->restrictOnDelete();
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('inventory_branch_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('product_id');
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->unique(['branch_id', 'product_id']);
            $table->index('tenant_id');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('branch_id')->nullable();
            $table->enum('type', ['in', 'out', 'opname']);
            $table->integer('qty');
            $table->text('note');
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('location_kind');
            $table->string('location_id');
            $table->string('location_label');
            $table->integer('counted_stock')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('source_branch_id')->nullable();
            $table->string('source_kind');
            $table->string('source_label');
            $table->integer('total_qty');
            $table->text('note');
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->foreign('source_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('inventory_transfer_dests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transfer_id');
            $table->uuid('branch_id');
            $table->integer('qty');

            $table->foreign('transfer_id')->references('id')->on('inventory_transfers')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->index('branch_id');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 18, 2);
            $table->decimal('yearly_price', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index('is_active');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id')->nullable();
            $table->enum('status', ['trialing', 'active', 'past_due', 'canceled'])->default('trialing');
            $table->enum('current_cycle', ['monthly', 'yearly'])->nullable();
            $table->timestamp('trial_start_at')->nullable();
            $table->timestamp('trial_end_at')->nullable();
            $table->timestamp('period_start_at')->nullable();
            $table->timestamp('period_end_at')->nullable();
            $table->boolean('read_only_mode')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');
            $table->uuid('plan_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->string('payment_code');
            $table->enum('cycle', ['monthly', 'yearly']);
            $table->decimal('amount', 18, 2);
            $table->timestamp('due_at');
            $table->enum('status', ['pending', 'paid', 'rejected', 'expired'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->index(['tenant_id', 'status', 'due_at']);
            $table->unique(['tenant_id', 'payment_code']);
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->string('proof_path');
            $table->decimal('transfer_amount', 18, 2);
            $table->string('bank_name');
            $table->string('sender_bank')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['invoice_id', 'created_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('usage_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->date('day');
            $table->integer('login_count')->default(0);
            $table->integer('movement_count')->default(0);
            $table->integer('transfer_count')->default(0);
            $table->integer('active_user_count')->default(0);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'day']);
            $table->index('day');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('usage_daily');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('inventory_transfer_dests');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_branch_stocks');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('membership_branch_access');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('profiles');
    }
};
