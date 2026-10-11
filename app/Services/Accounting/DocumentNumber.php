<?php

namespace App\Services\Accounting;

use Illuminate\Support\Str;

class DocumentNumber
{
    public static function next(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
