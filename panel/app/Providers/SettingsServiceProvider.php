<?php

namespace Pterodactyl\Providers;

use Psr\Log\LoggerInterface as Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * An array of configuration keys to override with database values
     * if they exist.
     */
    protected array $keys = [
        'app:name',
        'app:locale',
        'pterodactyl:guzzle:timeout',
        'pterodactyl:guzzle:connect_timeout',
        'pterodactyl:console:count',
        'pterodactyl:console:frequency',
        'pterodactyl:auth:2fa_required',
        'pterodactyl:client_features:allocations:enabled',
        'pterodactyl:client_features:allocations:range_start',
        'pterodactyl:client_features:allocations:range_end',
        'pterodactyl:client_features:schedules:per_schedule_task_limit',
        'hydra:branding:accent',
        'hydra:branding:logo',
        'hydra:branding:favicon',
        'hydra:profiles:upload_limit',
        'hydra:backgrounds:login:image',
        'hydra:backgrounds:login:blur',
        'hydra:backgrounds:login:dim',
        'hydra:backgrounds:dashboard:image',
        'hydra:backgrounds:dashboard:blur',
        'hydra:backgrounds:dashboard:dim',
        'hydra:navigation:server',
        'hydra:console:offline_message',
        'hydra:console:prefix',
        'hydra:console:daemon_prefix',
    ];

    /**
     * Keys specific to the mail driver that are only grabbed from the database
     * when using the SMTP driver.
     */
    protected array $emailKeys = [
        'mail:mailers:smtp:host',
        'mail:mailers:smtp:port',
        'mail:mailers:smtp:encryption',
        'mail:mailers:smtp:username',
        'mail:mailers:smtp:password',
        'mail:from:address',
        'mail:from:name',
    ];

    /**
     * Keys that are encrypted and should be decrypted when set in the
     * configuration array.
     */
    protected static array $encrypted = [
        'mail:mailers:smtp:password',
    ];

    /**
     * Boot the service provider.
     */
    public function boot(ConfigRepository $config, Encrypter $encrypter, Log $log, SettingsRepositoryInterface $settings): void
    {
        // Only set the email driver settings from the database if we
        // are configured using SMTP as the driver.
        if ($config->get('mail.default') === 'smtp') {
            $this->keys = array_merge($this->keys, $this->emailKeys);
        }

        // Hydra: the settings of every addon (Design > Addons).
        [$addonKeys, $addonEncrypted] = AddonManager::settingKeys();
        $this->keys = array_merge($this->keys, $addonKeys);
        $encrypted = array_merge(self::$encrypted, $addonEncrypted);

        try {
            $values = $settings->all()->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            })->toArray();
        } catch (QueryException $exception) {
            $log->notice('A query exception was encountered while trying to load settings from the database: ' . $exception->getMessage());

            return;
        }

        foreach ($this->keys as $key) {
            $value = array_get($values, 'settings::' . $key, $config->get(str_replace(':', '.', $key)));
            if (in_array($key, $encrypted)) {
                try {
                    $value = $encrypter->decrypt($value);
                } catch (DecryptException $exception) {
                }
            }

            // Hydra: defaults of addons can be lists (e.g. the eggs of the game library).
            if (is_array($value)) {
                $config->set(str_replace(':', '.', $key), $value);
                continue;
            }

            switch (strtolower((string) $value)) {
                case 'true':
                case '(true)':
                    $value = true;
                    break;
                case 'false':
                case '(false)':
                    $value = false;
                    break;
                case 'empty':
                case '(empty)':
                    $value = '';
                    break;
                case 'null':
                case '(null)':
                    $value = null;
            }

            $config->set(str_replace(':', '.', $key), $value);
        }
    }

    public static function getEncryptedKeys(): array
    {
        return self::$encrypted;
    }
}
