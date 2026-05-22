<?php

namespace Studio;

class AuthGuard
{
    private array $session;
    private $clock;

    public function __construct(array &$session, ?callable $clock = null)
    {
        $this->session = &$session;
        $this->clock = $clock ?? fn() => time();
    }

    public function isAuthenticated(): bool
    {
        if (!isset($this->session['studio_auth'])) return false;
        if (($this->clock)() - $this->session['studio_auth']['login_time'] > STUDIO_SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        return true;
    }

    public function login(string $password): bool
    {
        if ($password !== STUDIO_PASSWORD) return false;
        $this->session['studio_auth'] = ['login_time' => ($this->clock)()];
        return true;
    }

    public function logout(): void
    {
        unset($this->session['studio_auth']);
    }
}
