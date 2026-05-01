<?php

namespace App\Services\Shipping\Drivers;

class YalidineDriver extends AbstractYalidineLikeDriver
{
    protected function carrierKey(): string
    {
        return 'yalidine';
    }

    protected function carrierName(): string
    {
        return 'Yalidine';
    }

    protected function baseUrl(): string
    {
        return 'https://api.yalidine.app/v1';
    }
}
