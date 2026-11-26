<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\LocalDocker;

it('normalizes only exact pinned loopback-bound container observations and rejects unsafe variants without daemon I/O', function (string $case): void {
    /** @var 'valid'|'wrong image'|'wrong digest'|'wrong repository'|'wrong architecture'|'wrong reference'|'public port'|'multiple bindings'|'privileged'|'host network'|'shared named volume'|'docker socket mount'|'writable config mount'|'stopped' $case */

    $fixture = new FoundationOwnedRunFixture();
    try {
        $record = $fixture->record['containers']['postgres'];
        $id = $record['id'];
        $directory = $fixture->record['directory'];
        $image = [$record['image_id'], 'linux', 'amd64', ['ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@'.$record['manifest']]];
        $container = [
            $id, $record['image_id'], 'ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@'.$record['manifest'],
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
            'wrong digest' => $image[3] = ['ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@sha256:'.str_repeat('e', 64)],
            'wrong repository' => $image[3] = ['docker.io/library/postgres@'.$record['manifest']],
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
})->with(['valid', 'wrong image', 'wrong digest', 'wrong repository', 'wrong architecture', 'wrong reference', 'public port', 'multiple bindings', 'privileged', 'host network', 'shared named volume', 'docker socket mount', 'writable config mount', 'stopped'])->group('testing-foundation');


it('permits a stopped owned container with no active mapping only for cleanup observation', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $record = $fixture->record['containers']['postgres'];
        $id = $record['id'];
        $directory = $fixture->record['directory'];
        $image = [$record['image_id'], 'linux', 'amd64', ['ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@'.$record['manifest']]];
        $container = [
            $id, $record['image_id'], 'ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@'.$record['manifest'],
            $record['labels'], [],
            ['5432/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => '']]],
            false, 'bridge',
            [['Type' => 'volume', 'Name' => $record['volumes'][0], 'Destination' => '/var/lib/postgresql/data', 'RW' => true]],
            false,
        ];
        expect(fn () => LocalDocker::normalize('postgres', $id, $directory, $container, $image))->toThrow(RuntimeException::class);
        $record['port'] = null;
        expect(LocalDocker::normalize('postgres', $id, $directory, $container, $image, true))->toBe($record);
        $container[5]['5432/tcp'][0]['HostIp'] = '0.0.0.0';
        expect(fn () => LocalDocker::normalize('postgres', $id, $directory, $container, $image, true))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');


it('classifies only fixed startup diagnostics without exposing raw secret or path text', function (): void {
    expect(LocalDocker::startupCategory('/run/f03/pg-password: Permission denied'))->toBe('password-file-permission');
    expect(LocalDocker::startupCategory('failed to bind host port: address already in use'))->toBe('port-allocation');
    expect(LocalDocker::startupCategory('unrecognized configuration parameter "synthetic"'))->toBe('invalid-server-option');
    expect(LocalDocker::startupCategory('synthetic-secret-value'))->toBe('unclassified');
})->group('testing-foundation');


it('normalizes running and cleanup observations in a child before Composer autoload', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $record = $fixture->record['containers']['postgres'];
        $directory = $fixture->record['directory'];
        $reference = 'ghcr.io/elmo-tech/ecommerce-v2-ci-postgres@'.$record['manifest'];
        $image = [$record['image_id'], 'linux', 'amd64', [$reference]];
        $container = [
            $record['id'], $record['image_id'], $reference, $record['labels'],
            ['5432/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => (string) $record['port']]]],
            ['5432/tcp' => [['HostIp' => '127.0.0.1', 'HostPort' => '']]],
            false, 'bridge',
            [['Type' => 'volume', 'Name' => $record['volumes'][0], 'Destination' => '/var/lib/postgresql/data', 'RW' => true]],
            true,
        ];
        $fixture->checkout->write('observation.json', json_encode([$record, $directory, $container, $image], JSON_THROW_ON_ERROR));
        $fixture->checkout->write('observation-probe.php', <<<'PHP'
<?php

require_once __DIR__.'/tests/Support/OwnedTestRun.php';
require_once __DIR__.'/tests/Support/LocalDocker.php';

[$record, $directory, $container, $image] = json_decode(file_get_contents(__DIR__.'/observation.json'), true, flags: JSON_THROW_ON_ERROR);
if (\Tests\Support\LocalDocker::normalize('postgres', $record['id'], $directory, $container, $image) !== $record) {
    exit(1);
}
$container[4] = [];
$container[9] = false;
$record['port'] = null;
if (\Tests\Support\LocalDocker::normalize('postgres', $record['id'], $directory, $container, $image, true) !== $record
    || class_exists(\Tests\Support\ci\OwnedServicePlan::class, false)) {
    exit(2);
}
PHP);
        $process = $fixture->checkout->run(['observation-probe.php']);
        expect($process->getExitCode())->toBe(0);
        expect($fixture->checkout->reachedAutoload())->toBeFalse();
        expect($fixture->checkout->reachedPest())->toBeFalse();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');
