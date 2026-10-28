<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیام‌های «تماس با ما» صفحهٔ اصلی فروشگاه (Task 56).
 * بازدیدکننده درخواست «قالب اختصاصی / پروژه اختصاصی / استارتاپ اختصاصی / مشاوره» ثبت می‌کند؛
 * مدیر در پنل (admin/messages) صندوق پیام‌ها را می‌بیند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('email', 190)->nullable();
            $table->string('request_type', 20)->index(); // template | project | startup | consult
            $table->text('message');
            $table->string('status', 10)->default('new')->index(); // new | read
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
