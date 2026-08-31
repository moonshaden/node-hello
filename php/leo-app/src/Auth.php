<?php
declare(strict_types=1);

namespace Leo;

/**
 * Admin session handling.
 *
 * One shared admin password and a signed, HttpOnly cookie — no user table, no
 * password reset flow, no session files to clean up. That is the right size for
 * a foundation whose staff is a handful of people.
 */
final class Auth
{
    private const COOKIE = 'leo_admin';
    private const MAX_AGE = 60 * 60 * 12;

    public function __construct(private string $password, private string $secret)
    {
    }

    public function isConfigured(): bool
    {
        return $this->password !== '';
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }

    public function issue(): string
    {
        $expires = (string) (time() + self::MAX_AGE);
        return $expires . '.' . $this->sign($expires);
    }

    public function verify(?string $token): bool
    {
        if ($token === null || !str_contains($token, '.')) {
            return false;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if ($payload === '' || $signature === '') {
            return false;
        }
        if (!hash_equals($this->sign($payload), $signature)) {
            return false;
        }
        return (int) $payload > time();
    }

    /** Constant-time comparison, so a wrong guess leaks nothing by timing. */
    public function passwordMatches(?string $candidate): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        return hash_equals($this->password, (string) $candidate);
    }

    public function isAdmin(): bool
    {
        return $this->isConfigured() && $this->verify($_COOKIE[self::COOKIE] ?? null);
    }

    public function login(): void
    {
        setcookie(self::COOKIE, $this->issue(), [
            'expires' => time() + self::MAX_AGE,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);
    }

    public function logout(): void
    {
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return true;
        }
        // cPanel sits behind a proxy that terminates TLS and forwards this header.
        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Reject cross-site form posts. Enough for a cookie-auth admin. */
    public static function sameOrigin(): bool
    {
        $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($source === '') {
            return true;
        }
        $host = parse_url($source, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        $expected = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0];
        return $host === $expected;
    }
}
