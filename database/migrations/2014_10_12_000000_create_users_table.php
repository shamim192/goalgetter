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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->string('stripe_customer_id')->nullable(); // New: To store Stripe Customer ID
            $table->string('payment_method_id')->nullable(); // New: To store Stripe Payment Method ID
            $table->string('stripe_account_id')->nullable();
            $table->string('avatar')->nullable();
            $table->date('birthday')->nullable();
            $table->text('about_us')->nullable();
            $table->string('provider')->nullable(); // Field to store social provider name (e.g., 'google', 'facebook')
            $table->string('provider_id')->nullable(); // Field to store the unique ID from the social provider
            $table->boolean('is_verified')->default(false);
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
