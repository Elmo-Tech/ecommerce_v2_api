<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use RuntimeException;
use SensitiveParameter;
use Tests\Support\IntegrationContext;
use Tests\Support\OwnedTestRun;

/** Exclusive creation and expected-record replacement in the current job. */
final class OwnedSetupFiles
{
    public static function create(string $directory, string $name, #[SensitiveParameter] string $content): void
    {
        $allowed = ['ownership.json', 'ownership.next.json', 'setup.json', 'pg-password', 'redis.conf', 'users.acl', 'runtime.json', 'redis-evidence.json'];
        if (!in_array($name, $allowed, true) || !is_dir($directory) || is_link($directory)
            || realpath($directory) !== $directory || !preg_match('/^f03-[0-9a-f]{16}$/D', basename($directory))
            || (fileperms($directory) & 0777) !== 0700
            || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())
            || $content === '' || strlen($content) > 65536) {
            self::reject();
        }
        $path = $directory.'/'.$name;
        if (file_exists($path) || is_link($path)) {
            self::reject();
        }
        $previous = umask(0077);
        try {
            $handle = fopen($path, 'xb');
        } finally {
            umask($previous);
        }
        if ($handle === false) {
            self::reject();
        }
        try {
            $stat = fstat($handle);
            if ($stat === false || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1
                || fwrite($handle, $content) !== strlen($content) || !fflush($handle) || !fsync($handle)) {
                self::reject();
            }
        } finally {
            fclose($handle);
        }
    }

    public static function json(string $directory, string $name, #[SensitiveParameter] array $value): void
    {
        self::create($directory, $name, json_encode($value, JSON_THROW_ON_ERROR)."\n");
    }

    /** Append exactly one newly returned ID; existing captures never change. */
    public static function appendCapture(array $context, array $expected, string $service, string $id): array
    {
        $dir = $context['directory'];
        if (!in_array($service, ['postgres', 'redis'], true) || !preg_match('/^[0-9a-f]{64}$/D', $id)
            || ($expected['token'] ?? null) !== $context['token'] || ($expected['directory'] ?? null) !== $dir
            || array_key_exists($service, $expected['containers'])
            || IntegrationContext::read($dir, 'ownership.json') !== $expected) {
            self::reject();
        }
        OwnedTestRun::validateCapturedRecord($context['job'], $expected, $context['temp'], true);
        $next = $expected;
        $pin = OwnedTestRun::image($service);
        $next['containers'][$service] = [
            'id' => $id, 'image_id' => $pin['image_id'], 'manifest' => $pin['manifest'],
            'labels' => OwnedServicePlan::labels($context['job'], $context['token']),
            'host' => '127.0.0.1', 'port' => null, 'volumes' => [],
        ];
        OwnedTestRun::validateCapturedRecord($context['job'], $next, $context['temp'], true);
        self::json($dir, 'ownership.next.json', $next);
        if (IntegrationContext::read($dir, 'ownership.json') !== $expected
            || is_link($dir.'/ownership.next.json') || !rename($dir.'/ownership.next.json', $dir.'/ownership.json')
            || IntegrationContext::read($dir, 'ownership.json') !== $next) {
            self::reject();
        }
        return $next;
    }

    private static function reject(): never
    {
        throw new RuntimeException('Owned setup file operation rejected.');
    }
}
