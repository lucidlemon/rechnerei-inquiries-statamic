<?php

namespace Rechnerei\Inquiries;

class Settings
{
    protected function path(): string
    {
        return storage_path('app/rechnerei-inquiries/settings.json');
    }

    public function all(): array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return $this->defaults();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_merge($this->defaults(), $decoded) : $this->defaults();
    }

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'endpoint' => '',
            'token' => '',
            'ignore_keywords' => implode("\n", $this->defaultIgnoreKeywords()),
        ];
    }

    public function defaultIgnoreKeywords(): array
    {
        return [
            'password reset',
            'passwort zurücksetzen',
            'reset your password',
            'verify your email',
            'bestätigen sie ihre e-mail',
            'e-mail-adresse bestätigen',
            'two-factor',
            'zwei-faktor',
            'backup successful',
            'backup failed',
            'backup erfolgreich',
            'backup fehlgeschlagen',
            'scheduled task failed',
            'failed job',
            'queue failure',
            'health check failed',
            'application error',
            'exception occurred',
            'new user registration',
            'neuer benutzer',
            'license key',
            'lizenzschlüssel',
        ];
    }

    public function save(array $values): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $merged = array_merge($this->defaults(), $this->all(), $values);

        file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function isEnabled(): bool
    {
        $s = $this->all();
        return !empty($s['enabled']) && $s['endpoint'] !== '' && $s['token'] !== '';
    }

    /**
     * @return string[]
     */
    public function ignoreRules(): array
    {
        $s = $this->all();
        $lines = preg_split('/\r\n|\r|\n/', (string) $s['ignore_keywords']);

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }
}
