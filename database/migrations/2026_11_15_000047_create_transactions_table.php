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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ref_id')->index();
            $table->string('buyer_sku_code')->default('pln');
            $table->string('customer_no')->index();
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('admin')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('selling_price')->default(0);
            $table->unsignedBigInteger('buyer_last_saldo')->default(0);
            $table->string('sn')->nullable();
            $table->string('status'); // Sukses, Pending, Gagal
            $table->string('rc', 10); // 00, 03, 40, 41, etc.
            $table->string('message');
            $table->json('desc')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('callback_status')->default('none');
            $table->json('callback_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ref_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
