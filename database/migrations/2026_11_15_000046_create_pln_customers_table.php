<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pln_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_no')->unique()->index();
            $table->string('customer_name');
            $table->string('meter_no')->nullable();
            $table->string('subscriber_id')->nullable();
            $table->string('segment_power')->nullable();
            $table->string('buyer_sku_code')->default('pln');
            $table->unsignedInteger('admin')->default(2500);
            $table->unsignedInteger('price')->default(10000);
            $table->unsignedInteger('selling_price')->default(11000);
            $table->json('desc')->nullable();
            $table->string('behavior')->default('success_1_bill');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pln_customers');
    }
};
