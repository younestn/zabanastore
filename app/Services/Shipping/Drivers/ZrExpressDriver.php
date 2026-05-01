<?php

namespace App\Services\Shipping\Drivers;

class ZrExpressDriver extends AbstractProcolisLikeDriver
{
    protected function carrierKey(): string
    {
        return 'zr_express';
    }

    protected function carrierName(): string
    {
        return 'ZR Express';
    }

    protected function baseUrl(): string
    {
        return 'https://procolis.com/api_v1';
    }
}
