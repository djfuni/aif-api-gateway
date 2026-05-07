<?php
declare(strict_types=1);

if (!defined('AIF_CACHE_DIR')) define('AIF_CACHE_DIR', __DIR__ . '/../data/cache');

function aif_cache_path(string $key): string {
    if (!is_dir(AIF_CACHE_DIR)) @mkdir(AIF_CACHE_DIR, 0777, true);
    return AIF_CACHE_DIR . '/' . hash('sha256', $key) . '.cache';
}

function aif_cache_get(string $key, int $ttl = 300): mixed {
    $file = aif_cache_path($key);
    if (!is_file($file)) return null;
    if ($ttl > 0 && time() - filemtime($file) > $ttl) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;
    $value = @unserialize($raw, ['allowed_classes' => false]);
    return $value === false && $raw !== serialize(false) ? null : $value;
}

function aif_cache_set(string $key, mixed $value): void {
    $file = aif_cache_path($key);
    @file_put_contents($file, serialize($value), LOCK_EX);
}

function aif_cache_delete(string $key): void {
    $file = aif_cache_path($key);
    if (is_file($file)) @unlink($file);
}

function aif_cache_remember(string $key, int $ttl, callable $loader): mixed {
    $hit = aif_cache_get($key, $ttl);
    if ($hit !== null) return $hit;
    $value = $loader();
    aif_cache_set($key, $value);
    return $value;
}
