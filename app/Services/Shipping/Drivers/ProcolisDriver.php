<?php

namespace App\Services\Shipping\Drivers;

class ProcolisDriver extends AbstractProcolisLikeDriver
{
    protected function carrierKey(): string
    {
        return 'procolis';
    }

    protected function carrierName(): string
    {
        return 'Procolis';
    }

    protected function baseUrl(): string
    {
        return 'https://procolis.com/api_v1';
    }
}
