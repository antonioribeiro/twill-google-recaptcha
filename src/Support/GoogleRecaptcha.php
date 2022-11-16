<?php

namespace App\Twill\Capsules\GoogleRecaptchas\Support;

use Illuminate\Support\Arr;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use App\Twill\Capsules\GoogleRecaptchas\Models\GoogleRecaptcha as GoogleRecaptchaModel;

class GoogleRecaptcha
{
    protected array|null $config = null;

    protected bool|null $isConfigured = null;

    protected bool|null $enabled = null;

    protected Response|null $googleResponse = null;

    public function __construct()
    {
        $this->setConfigured();

        $this->setEnabled();

        $this->configureViews();
    }

    public function config(string|null $key = null): mixed
    {
        $this->config ??= filled($this->config) ? $this->config : (array) config('google-recaptcha');

        if (blank($key)) {
            return $this->config;
        }

        return Arr::get((array) $this->config, $key);
    }

    public function enabled(): bool
    {
        return $this->enabled ??
            $this->isConfigured() &&
                ($this->hasConfig() ? $this->config('enabled') ?? false : $this->readFromDatabase('published'));
    }

    public function privateKey(bool $force = false): string|null
    {
        return $this->get('keys.private', 'private_key', $force);
    }

    public function siteKey(bool $force = false): string|null
    {
        return $this->get('keys.site', 'site_key', $force);
    }

    public function get(string $configKey, string $databaseColumn, bool $force = false): string|null
    {
        if (!$force && (!$this->isConfigured() || !$this->enabled())) {
            return null;
        }

        return $this->hasConfig() ? $this->config($configKey) : $this->readFromDatabase($databaseColumn);
    }

    private function readFromDatabase(string $string): string|bool|null
    {
        $googleRecaptcha = GoogleRecaptchaModel::first();

        if (empty($googleRecaptcha)) {
            return null;
        }

        return $googleRecaptcha->getAttributes()[$string] ?? null;
    }

    public function hasConfig(): bool
    {
        return filled($this->config('keys.site') ?? null) || filled($this->config('keys.private') ?? null);
    }

    public function asset(): string|null
    {
        if (!$this->enabled()) {
            return null;
        }

        return 'https://www.google.com/recaptcha/api.js?render=' . $this->siteKey();
    }

    private function isConfigured(): bool
    {
        return $this->isConfigured ?? filled($this->siteKey(true)) && filled($this->privateKey(true));
    }

    private function setConfigured(): void
    {
        $this->isConfigured = $this->isConfigured();
    }

    private function setEnabled(): void
    {
        $this->enabled = $this->enabled();
    }

    protected function configureViews(): void
    {
        View::addNamespace('google-recaptcha', __DIR__ . '/../resources/views');
    }

    public function passes(string|null $responseToken = null): bool
    {
        if (!$this->enabled()) {
            return true; // TODO: Should this be false?
        }

        $response = $this->verify($responseToken)?->json();

        if (blank($response)) {
            return false;
        }

        return $response['success'] ?? false;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function verify(string|null $responseToken): Response|null
    {
        if (blank($responseToken = $this->responseToken($responseToken))) {
            return null;
        }

        return $this->googleResponse ??= Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $this->privateKey(),
            'response' => $responseToken,
        ]);
    }

    public function responseToken(string|null $responseToken = null): string|null
    {
        return $responseToken ?? request()->input('g-recaptcha-response');
    }

    public function failedMessage(): string|null
    {
        $message = __($key = $this->config('validation.lang_key'));

        if ($message !== $key) {
            return $message;
        }

        return $this->config('validation.failed');
    }
}
