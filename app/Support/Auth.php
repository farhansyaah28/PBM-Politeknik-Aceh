<?php
declare(strict_types=1);

namespace App\Support;

final class Auth
{
    /** @return array{user_id:string,participant_id:?string,role:string}|null */
    public static function user(): ?array
    {
        $user = $_SESSION['auth'] ?? null;
        if (!is_array($user) || !isset($user['user_id'], $user['role']) || !array_key_exists('participant_id', $user)) return null;
        return $user;
    }

    /** @param array{user_id:string,participant_id:?string,role:string} $user */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** @return array{user_id:string,participant_id:string,role:string} */
    public static function requireParticipant(): array
    {
        $user = self::user();
        if ($user === null) { header('Location: /login', true, 303); exit; }
        if ($user['role'] !== 'PARTICIPANT' || $user['participant_id'] === null) { http_response_code(403); exit('Akses tidak diizinkan.'); }
        return $user;
    }

    /** @return array{user_id:string,participant_id:?string,role:string} */
    public static function requireInternal(): array
    {
        $user = self::user();
        if ($user === null) { header('Location: /login', true, 303); exit; }
        if (!in_array($user['role'], ['COMMITTEE', 'ADMIN'], true)) { http_response_code(403); exit('Akses tidak diizinkan.'); }
        return $user;
    }

    /** @return array{user_id:string,participant_id:?string,role:string} */
    public static function requireAdmin(): array
    {
        $user = self::user();
        if ($user === null) { header('Location: /login', true, 303); exit; }
        if ($user['role'] !== 'ADMIN') { http_response_code(403); exit('Akses Admin diperlukan.'); }
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
        }
        session_destroy();
    }
}
