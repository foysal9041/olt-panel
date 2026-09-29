<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Packages a MAC client may sell, and what they pay us per user:
        // a fixed rate, or the package's list price minus a commission %.
        Schema::create('customer_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('pricing', 12)->default('fixed');      // fixed | commission
            $table->decimal('rate', 12, 2)->nullable();            // per user, when fixed
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->unsignedInteger('quantity')->default(0);       // users billed monthly
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });

        // Goods handed to a customer (ONU, router, SFP...). Chargeable ones
        // are added to their next invoice once.
        Schema::create('customer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->boolean('chargeable')->default(true);
            $table->date('given_on');
            $table->string('note')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_products');
        Schema::dropIfExists('customer_packages');
    }
};
