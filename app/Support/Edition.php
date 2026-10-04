<?php

namespace App\Support;

/** Which edition is running (see config/edition.php). */
class Edition
{
    public const SELF_HOSTED = 'self_hosted';

    public const SAAS = 'saas';

    public static function name(): string
    {
        return config('edition.name') === self::SAAS ? self::SAAS : self::SELF_HOSTED;
    }

    public static function isSaas(): bool
    {
        return self::name() === self::SAAS;
    }

    public static function isSelfHosted(): bool
    {
        return self::name() === self::SELF_HOSTED;
    }
}
