<?php

/*
 * Hydra translation check.
 *
 *   php resources/lang/check.php
 *
 * 1. Every key in an English file must exist in every other language.
 * 2. Every key used in the React sources (t('…'), i18nKey, "ns:key" strings) must
 *    exist in the English files.
 *
 * Exits with 1 when something is missing, so it can run in CI or before a release.
 */

$langDir = __DIR__;
$scriptsDir = dirname(__DIR__) . '/scripts';
$errors = [];

function flatten(array $data, string $prefix = ''): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        if (is_array($value) && !array_is_list($value)) {
            $keys = array_merge($keys, flatten($value, "$prefix$key."));
        } else {
            $keys[] = "$prefix$key";
        }
    }

    return $keys;
}

function load(string $langDir, string $locale): array
{
    $result = [];
    $base = "$langDir/$locale";
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $namespace = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1, -4));
        $result[$namespace] = flatten(require $file->getPathname());
    }

    return $result;
}

$english = load($langDir, 'en');
$locales = array_filter(scandir($langDir), fn ($d) => $d[0] !== '.' && is_dir("$langDir/$d") && $d !== 'en');

// 1. Every English key exists in every other language.
foreach ($locales as $locale) {
    $other = load($langDir, $locale);
    foreach ($english as $namespace => $keys) {
        $missing = array_diff($keys, $other[$namespace] ?? []);
        foreach ($missing as $key) {
            $errors[] = "[$locale] missing $namespace:$key";
        }
    }
}

// 2. Every key used by the frontend exists in English.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scriptsDir, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!preg_match('/\.tsx?$/', $file->getFilename())) {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    $relative = substr($file->getPathname(), strlen($scriptsDir) + 1);
    $defaultNs = preg_match("/useTranslation\\('([^']+)'\\)/", $source, $m) ? $m[1] : 'client/common';

    $used = [];
    preg_match_all("/\\bt\\(\\s*'([^']+)'/", $source, $m);
    $used = array_merge($used, $m[1]);
    preg_match_all("/i18nKey=\\{'([^']+)'\\}/", $source, $m);
    $used = array_merge($used, $m[1]);
    preg_match_all("/'((?:client|server)\\/[\\w\\/-]+:[\\w.-]+)'/", $source, $m);
    $used = array_merge($used, $m[1]);
    if (str_ends_with($relative, 'routes.ts')) {
        preg_match_all("/name: '(routes\\.[\\w.]+)'/", $source, $m);
        $used = array_merge($used, $m[1]);
    }

    foreach (array_unique($used) as $key) {
        // Dynamic keys such as t(`status.${state}`) or t(title) are not checked.
        [$namespace, $path] = str_contains($key, ':') ? explode(':', $key, 2) : [$defaultNs, $key];
        if (!in_array($path, $english[$namespace] ?? [], true)) {
            $errors[] = "[code] $relative uses unknown key $namespace:$path";
        }
    }
}

// 3. Every key used by Blade templates and PHP code (__('…'), trans('…'), @lang('…')) exists in English.
$phpRoots = [dirname(__DIR__) . '/views', dirname(__DIR__, 2) . '/app'];
foreach ($phpRoots as $root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        // Keys that are concatenated at runtime ('admin/api.resources.' . $x) end with a dot and are skipped.
        preg_match_all("/(?:__|trans|@lang)\\(\\s*'([a-z][\\w\\/-]*\\.[\\w.-]*\\w)'/", $source, $m);
        foreach (array_unique($m[1]) as $key) {
            // "admin/servers.tabs.about" → namespace "admin/servers", path "tabs.about"
            [$namespace, $path] = explode('.', $key, 2);
            if (!isset($english[$namespace])) {
                continue; // Vendor or framework namespaces (validation attributes etc.) are not ours.
            }
            $isGroup = (bool) array_filter($english[$namespace], fn ($k) => str_starts_with($k, "$path."));
            if (!in_array($path, $english[$namespace], true) && !$isGroup) {
                $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1);
                $errors[] = "[php] $relative uses unknown key $key";
            }
        }
    }
}

if ($errors) {
    echo implode(PHP_EOL, $errors) . PHP_EOL;
    exit(1);
}

echo 'All translations complete.' . PHP_EOL;
