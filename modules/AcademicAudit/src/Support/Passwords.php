<?php

namespace Mmmut\AcademicAudit\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Passwords for accounts the portal creates. There is no shared default password any more:
 * new accounts get a random password nobody knows (people sign in through Q-Sphere, or set a
 * password with "Forgot password"), and an IQAC reset gives a random one-time password.
 */
class Passwords
{
    /** The old shared default password; only used by audit:default-password-check. */
    public const LEGACY_DEFAULT = 'Mmmut@123';

    /** A random temporary password (letters and digits, easy to read out). */
    public static function temporary(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        do {
            $p = '';
            for ($i = 0; $i < $length; $i++) {
                $p .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (! preg_match('/[A-Za-z]/', $p) || ! preg_match('/\d/', $p));

        return $p;
    }

    /** Give a new (unsaved) user a password nobody knows, and force a change if they ever log in locally. */
    public static function setUnknown(object $user): object
    {
        $user->forceFill(['password' => Hash::make(Str::random(48))]);
        return self::mustChange($user);
    }

    /** Set a known temporary password that must be changed at the next local login. */
    public static function setTemporary(object $user, string $password): object
    {
        $user->forceFill(['password' => Hash::make($password)]);
        return self::mustChange($user);
    }

    private static function mustChange(object $user): object
    {
        if (Schema::hasColumn($user->getTable(), 'must_change_password')) {
            $user->forceFill(['must_change_password' => true]);
        }
        return $user;
    }
}
