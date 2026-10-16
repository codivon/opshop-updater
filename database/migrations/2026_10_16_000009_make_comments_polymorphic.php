<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تبدیل دیدگاه‌ها به چندریختی (polymorphic) —
 * هم محصولات (پروژه‌های قابل‌فروش) و هم پکیج‌ها دیدگاه دارند.
 *
 * up:   ساخت جدول جدید comments با ستون‌های commentable_type/commentable_id،
 *       انتقال داده‌های product_comments و سپس drop جدول قدیمی.
 * down: ساخت مجدد product_comments با انتقال برگشتی فقط دیدگاه‌های محصول
 *       (دیدگاه‌های پکیج حذف می‌شوند) و drop جدول comments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('commentable_type');           // App\Models\Product | App\Models\Package
            $table->unsignedBigInteger('commentable_id');
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->text('body');
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'status']);
        });

        // انتقال داده‌های موجود دیدگاه‌های محصول
        if (Schema::hasTable('product_comments')) {
            $rows = DB::table('product_comments')->get();

            foreach ($rows as $row) {
                DB::table('comments')->insert([
                    'commentable_type' => 'product',
                    'commentable_id'   => $row->product_id,
                    'name'             => $row->name,
                    'email'            => $row->email,
                    'rating'           => $row->rating,
                    'body'             => $row->body,
                    'status'           => $row->status,
                    'admin_note'       => $row->admin_note,
                    'approved_at'      => $row->approved_at,
                    'ip'               => $row->ip,
                    'created_at'       => $row->created_at,
                    'updated_at'       => $row->updated_at,
                ]);
            }

            Schema::drop('product_comments');
        }
    }

    public function down(): void
    {
        // برگرداندن دیدگاه‌های محصول به جدول قدیمی
        if (Schema::hasTable('comments')) {
            Schema::create('product_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('email')->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('body');
                $table->string('status', 20)->default('pending')->index();
                $table->string('admin_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['product_id', 'status']);
            });

            $rows = DB::table('comments')->where('commentable_type', 'product')->get();

            foreach ($rows as $row) {
                DB::table('product_comments')->insert([
                    'product_id'  => $row->commentable_id,
                    'name'        => $row->name,
                    'email'       => $row->email,
                    'rating'      => $row->rating,
                    'body'        => $row->body,
                    'status'      => $row->status,
                    'admin_note'  => $row->admin_note,
                    'approved_at' => $row->approved_at,
                    'ip'          => $row->ip,
                    'created_at'  => $row->created_at,
                    'updated_at'  => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('comments');
    }
};
