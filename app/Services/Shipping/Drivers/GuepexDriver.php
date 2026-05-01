<?php

namespace App\Services\Shipping\Drivers;

class GuepexDriver extends AbstractYalidineLikeDriver
{
    protected function carrierKey(): string
    {
        return 'guepex';
    }

    protected function carrierName(): string
    {
        return 'Guepex';
    }

    protected function baseUrl(): string
    {
        return 'https://api.guepex.app/v1';
    }
}
