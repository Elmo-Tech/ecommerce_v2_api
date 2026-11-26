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
            'job' => 'native-integration',
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
                'image_id' => 'sha256:3185a9b85c3c84d67acfef7ee6e583ffbd92e87d45de676f181a7a26c026bef7',
                'manifest' => 'sha256:3f9531ee43e9099389008518aab5b3a967a127ef573138c952645848d259f3ec',
                'labels' => $labels,
                'host' => '127.0.0.1',
                'port' => 45321,
                'volumes' => [str_repeat('c', 64)],
            ],
            'redis' => [
                'id' => str_repeat('b', 64),
                'image_id' => 'sha256:e6cdb4e394c6058305c6c73d4a09e97554aa28edb875212e33f32c597e09a94a',
                'manifest' => 'sha256:0b642fe44cd4a510d07ee35c48f803b0fd51dbdfc1920fe6027d35a4c269beed',
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
