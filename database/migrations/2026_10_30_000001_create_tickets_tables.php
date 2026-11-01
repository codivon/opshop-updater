<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سیستم تیکت پشتیبانی (فقط مشتریانِ دارای اشتراک فعال می‌توانند تیکت ثبت کنند):
 *  - tickets: موضوع + وضعیت (open/answered/closed) + اشتراکِ لحظهٔ ثبت (برای گزارش)
 *  - ticket_replies: گفتگوی تیکت — پاسخ مشتری یا مدیر
 *
 * ⚠️ نام محدودیت‌ها صریح و کوتاه است (سقف ۶۴ کاراکتری MySQL — درس Task 63).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('subject', 190);
            $table->string('status', 20)->default('open')->index('tickets_status_idx');
            $table->timestamp('last_reply_at')->nullable()->index('tickets_last_reply_idx');
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            // FK صریح پایین تعریف می‌شود (constrained اینجا FK دوباره می‌سازد)
            $table->unsignedBigInteger('ticket_id');
            // customer = پاسخ مشتری | admin = پاسخ مدیر
            $table->string('author_type', 20)->default('customer');
            $table->text('body');
            $table->timestamps();

            // نام کوتاه صریح (جلوگیری از خطای 1059 MySQL هاست)
            $table->foreign('ticket_id', 'ticket_replies_ticket_fk')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
    }
};
