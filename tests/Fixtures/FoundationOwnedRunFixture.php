<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Pure synthetic ownership inputs, not a resource provisioner. Never pass
 * these records to Docker, PDO, Redis, or a real cleanup command.
 */
final class FoundationOwnedRunFixture
{
    public readonly FoundationTestCheckout $checkout;
    /** @var array<array-key, mixed> Mutable synthetic input, including deliberately invalid records. */
    public array $job;
    /** @var array<array-key, mixed> Mutable synthetic input, including deliberately invalid records. */
    public array $record;
    /** @var array<array-key, mixed> Mutable synthetic input, including deliberately invalid records. */
    public array $descriptor;
    /** @var array<array-key, mixed> Mutable synthetic input, including deliberately invalid records. */
    public array $observed;

    public function __construct(string $token = '0123456789abcdef', string $runId = '123456', string $attempt = '1')
    {
        $this->checkout = new FoundationTestCheckout();
        $directory = $this->checkout->root.'/f03-'.$token;
        mkdir($directory, 0700);
        $this->job = [
            'repository' => 'Elmo-Tech/ecommerce_v2_api',
            'run_id' => $runId,
            'run_attempt' => $attempt,
            'job' => 'f0-03-integration',
            'runner_environment' => 'github-hosted',
            'runner_os' => 'Linux',
            'runner_arch' => 'X64',
            'docker_endpoint' => 'unix:///var/run/docker.sock',
        ];
        $labels = [
            'f03.token' => $token,
            'f03.repository' => $this->job['repository'],
            'f03.run_id' => $runId,
            'f03.run_attempt' => $attempt,
            'f03.job' => $this->job['job'],
        ];
        $containers = [
            'postgres' => [
                'id' => str_repeat('a', 64),
                'image_id' => 'sha256:850a2f036953dbe09006295666ee46737e4a15f9ede370bf3bf5718ec0a9e087',
                'manifest' => 'sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03',
                'labels' => $labels,
                'host' => '127.0.0.1',
                'port' => 45321,
                'volumes' => [str_repeat('c', 64)],
            ],
            'redis' => [
                'id' => str_repeat('b', 64),
                'image_id' => 'sha256:443f407d5d5a3ab81be78ebb136791c5d90f4e469231bc922dd187f55660fac6',
                'manifest' => 'sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87',
                'labels' => $labels,
                'host' => '127.0.0.1',
                'port' => 46379,
                'volumes' => [str_repeat('d', 64)],
            ],
        ];
        $this->record = [
            'job' => $this->job,
            'token' => $token,
            'directory' => $directory,
            'containers' => $containers,
        ];
        // In real execution these observations MUST come from fresh, bounded
        // local Docker inspections, not from the descriptor or copied record.
        $this->observed = $containers;
        $this->descriptor = [
            'job' => $this->job,
            'token' => $token,
            'directory' => $directory,
            'postgres' => ['host' => '127.0.0.1', 'port' => 45321, 'slots' => []],
            'redis' => ['host' => '127.0.0.1', 'port' => 46379, 'slots' => []],
        ];
        foreach (['central', 'a', 'b'] as $index => $slot) {
            $name = 'f03_'.$token.'_'.$slot;
            $this->descriptor['postgres']['slots'][$slot] = [
                'database' => $name,
                'role' => $name.'_rt',
                'password' => str_repeat((string) ($index + 1), 64),
                'marker' => 'f03:'.$token.':'.$slot,
            ];
        }
        foreach (['a', 'b'] as $index => $slot) {
            $this->descriptor['redis']['slots'][$slot] = [
                'role' => 'f03_'.$token.'_'.$slot.'_rt',
                'password' => str_repeat((string) ($index + 4), 64),
                'prefix' => 'f03:'.$token.':'.$slot.':',
            ];
        }
    }

    public function close(): void
    {
        $this->checkout->close();
    }
}
