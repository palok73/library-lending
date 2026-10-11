<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

final class Uuid
{
    /**
     * Generates UUID v7 (time-ordered, index-friendly).
     */
    public static function generate(): string
    {
        $millis = (int) (microtime(true) * 1000);
        $bytes = substr(pack('J', $millis), 2) . random_bytes(10);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
