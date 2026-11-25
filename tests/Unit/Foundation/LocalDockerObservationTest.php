<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\LocalDocker;

it('normalizes only exact pinned loopback-bound container observations and rejects unsafe variants without daemon I/O', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $record = $fixture->record['containers']['postgres'];
        $id = $record['id'];
        $directory = $fixture->record['directory'];
        $image = [$record['image_id'], 'linux', 'amd64', ['postgres@'.$record['manifest']]];
        $container = [
            $id, $record['image_id'], 'docker.io/library/postgres:17.12-bookworm@'.$record['manifest'],
            $record['labels'],
            ['5432/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => (string) $record['port']]]],
            ['5432/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => '']]],
            false, 'bridge',
            [['Type' => 'volume', 'Name' => $record['volumes'][0], 'Destination' => '/var/lib/postgresql/data', 'RW' => true]],
            true,
        ];
        match ($case) {
            'valid' => null,
            'wrong image' => $image[0] = 'sha256:'.str_repeat('e', 64),
            'wrong digest' => $image[3] = ['postgres@sha256:'.str_repeat('e', 64)],
            'wrong architecture' => $image[2] = 'arm64',
            'wrong reference' => $container[2] = 'postgres:latest',
            'public port' => $container[4]['5432/tcp'][0]['HostIp'] = '0.0.0.0',
            'multiple bindings' => $container[4]['5432/tcp'][] = ['HostIp' => '::', 'HostPort' => '45321'],
            'privileged' => $container[6] = true,
            'host network' => $container[7] = 'host',
            'shared named volume' => $container[8][0]['Name'] = 'synthetic-shared',
            'docker socket mount' => $container[8][] = ['Type' => 'bind', 'Source' => '/var/run/docker.sock', 'Destination' => '/var/run/docker.sock', 'RW' => false],
            'writable config mount' => $container[8][] = ['Type' => 'bind', 'Source' => $directory.'/pg-password', 'Destination' => '/run/f03/pg-password', 'RW' => true],
            'stopped' => $container[9] = false,
        };
        if ($case === 'valid') {
            expect(LocalDocker::normalize('postgres', $id, $directory, $container, $image))->toBe($record);
        } else {
            expect(fn () => LocalDocker::normalize('postgres', $id, $directory, $container, $image))->toThrow(RuntimeException::class);
        }
    } finally {
        $fixture->close();
    }
})->with(['valid', 'wrong image', 'wrong digest', 'wrong architecture', 'wrong reference', 'public port', 'multiple bindings', 'privileged', 'host network', 'shared named volume', 'docker socket mount', 'writable config mount', 'stopped'])->group('testing-foundation');
