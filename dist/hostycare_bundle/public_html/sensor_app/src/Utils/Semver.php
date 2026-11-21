<?php
declare(strict_types=1);

namespace App\Utils;

use InvalidArgumentException;

class Semver
{
    private const SEMVER_REGEX = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    public static function isValid(string $version): bool
    {
        return preg_match(self::SEMVER_REGEX, trim($version)) === 1;
    }

    /**
     * Compares two semver strings.
     * Returns:
     *  -1 if $v1 < $v2
     *   0 if $v1 == $v2
     *   1 if $v1 > $v2
     */
    public static function compare(string $v1, string $v2): int
    {
        $v1 = trim($v1);
        $v2 = trim($v2);

        if (!self::isValid($v1) || !self::isValid($v2)) {
            throw new InvalidArgumentException("Invalid semver format: '{$v1}' or '{$v2}'");
        }

        return version_compare($v1, $v2);
    }
}
