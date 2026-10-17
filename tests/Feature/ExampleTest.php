<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * دودآزمون: صفحه اصلی فروشگاه بدون دیتا باید ۲۰۰ برگرداند.
     */
    public function test_home_page_renders_successfully(): void
    {
        $this->get('/')->assertStatus(200);
    }

    /**
     * دودآزمون: صفحه طرح‌های اشتراک بدون دیتا باید ۲۰۰ برگرداند.
     */
    public function test_subscriptions_page_renders_successfully(): void
    {
        $this->get('/subscriptions')->assertStatus(200);
    }
}
