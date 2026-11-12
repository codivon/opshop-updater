<?php

namespace App\Services\Payments;

use Shetabit\Multipay\Drivers\Sepordeh\Sepordeh as SepordehDriver;
use Shetabit\Multipay\Receipt;

/**
 * درگاه نوین‌پی (novinopay.com)
 *
 * API فاکتوری نوین‌پی هم‌ساختار درگاه سپرده است (merchant/invoices/add → pay → verify)
 * پس درایور سپرده را با تغییر نام رسید استفاده می‌کنیم؛ آدرس‌ها از config/payment.php
 * (کلید novinopay) و مقادیر مرچنت از جدول gateway_configs تزریق می‌شوند.
 */
class Novinopay extends SepordehDriver
{
    /**
     * ساخت رسید با نام درایور نوین‌پی (به‌جای sepordeh)
     *
     * @param  mixed  $referenceId
     */
    protected function createReceipt($referenceId, $detail = []): Receipt
    {
        $receipt = new Receipt('novinopay', $referenceId);
        $receipt->detail($detail);

        return $receipt;
    }
}
