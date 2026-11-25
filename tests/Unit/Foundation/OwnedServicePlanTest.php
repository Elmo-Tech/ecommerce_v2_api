<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\ci\OwnedServicePlan;
use Tests\Support\ci\OwnedSetupFiles;
use Tests\Support\IntegrationContext;

function f03SetupContext(FoundationOwnedRunFixture $fixture): array
{
    return [
        'job' => $fixture->job, 'token' => $fixture->record['token'],
        'directory' => $fixture->record['directory'], 'temp' => $fixture->checkout->root,
    ];
}

it('generates seven distinct protected credential values and keeps setup credentials outside runtime slots', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $credentials = OwnedServicePlan::credentials($fixture->record['token']);
        $passwords = OwnedServicePlan::passwords($credentials);
        expect($passwords)->toHaveCount(7);
        expect(array_unique($passwords))->toHaveCount(7);
        $runtime = OwnedServicePlan::runtime($fixture->record, $credentials);
        $raw = json_encode($runtime, JSON_THROW_ON_ERROR);
        expect(str_contains($raw, $credentials['postgres_admin']))->toBeFalse();
        expect(str_contains($raw, $credentials['redis_admin']))->toBeFalse();
        expect(\Tests\Support\OwnedTestRun::validate($fixture->job, $fixture->record, $runtime, $fixture->observed, $fixture->checkout->root))->toBeInstanceOf(\Tests\Support\OwnedTestRun::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('constructs only pinned owned creation arguments with loopback dynamic ports and no secret value', function (string $service): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $args = OwnedServicePlan::createArguments(f03SetupContext($fixture), $service, 1001, 1001);
        $expectedPort = $service === 'postgres' ? 5432 : 6379;
        expect($args)->toContain('container', 'create', '--pull=never', '--platform=linux/amd64', '--network=bridge', '--publish=127.0.0.1::'.$expectedPort, OwnedServicePlan::imageRef($service));
        expect(implode(' ', $args))->not->toContain('--privileged', '--volume-from', '/var/run/docker.sock', '--network=host');
        expect(implode(' ', $args))->toContain('--label=f03.token='.$fixture->record['token']);
        if ($service === 'redis') {
            expect($args)->toContain('--user=1001:1001');
        }
    } finally {
        $fixture->close();
    }
})->with(['postgres', 'redis'])->group('testing-foundation');

it('rejects unsafe creation paths and unknown service types', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $context = f03SetupContext($fixture);
        expect(fn () => OwnedServicePlan::createArguments($context, 'external', 1001, 1001))->toThrow(RuntimeException::class);
        expect(fn () => OwnedServicePlan::createArguments($context, 'redis', 0, 1001))->toThrow(RuntimeException::class);
        $context['directory'] = '/tmp/other,src=/var/run/docker.sock';
        expect(fn () => OwnedServicePlan::createArguments($context, 'redis', 1001, 1001))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('generates a minimal Redis ACL with hashed credentials and exact independent key patterns', function (): void {
    $credentials = OwnedServicePlan::credentials('0123456789abcdef');
    $acl = OwnedServicePlan::redisAcl($credentials);
    foreach (OwnedServicePlan::passwords($credentials) as $password) {
        expect(str_contains($acl, $password))->toBeFalse();
    }
    $lines = explode("\n", trim($acl));
    expect($lines[0])->toBe('user default reset off');
    expect($lines[2])->toContain('~f03:0123456789abcdef:a:* +ping +get +set +del +acl|whoami');
    expect($lines[3])->toContain('~f03:0123456789abcdef:b:* +ping +get +set +del +acl|whoami');
    foreach ([$lines[2], $lines[3]] as $line) {
        expect($line)->not->toContain('~*', '+@all', '+flush', '+config', '+eval', '+select');
    }
    expect(OwnedServicePlan::redisConfig())->toContain('save ""', 'appendonly no', 'aclfile /run/f03/users.acl');
})->group('testing-foundation');

it('creates files privately and captures a new exact ID before any start without overwriting prior captures', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $context = f03SetupContext($fixture);
        $record = $fixture->record;
        $record['containers'] = [];
        OwnedSetupFiles::json($context['directory'], 'ownership.json', $record);
        $path = $context['directory'].'/ownership.json';
        expect(fileperms($path) & 0777)->toBe(0600);
        $captured = OwnedSetupFiles::appendCapture($context, $record, 'postgres', str_repeat('a', 64));
        expect(IntegrationContext::read($context['directory'], 'ownership.json'))->toBe($captured);
        expect($captured['containers']['postgres']['port'])->toBeNull();
        expect($captured['containers']['postgres']['volumes'])->toBe([]);
        expect(fn () => OwnedSetupFiles::appendCapture($context, $captured, 'postgres', str_repeat('b', 64)))->toThrow(RuntimeException::class);
        expect(fn () => OwnedSetupFiles::appendCapture($context, $record, 'redis', str_repeat('b', 64)))->toThrow(RuntimeException::class);
        expect(fn () => OwnedSetupFiles::create($context['directory'], 'ownership.json', 'untouched'))->toThrow(RuntimeException::class);
        expect(IntegrationContext::read($context['directory'], 'ownership.json'))->toBe($captured);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('rejects foreign setup filenames and symlink targets', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $dir = $fixture->record['directory'];
        expect(fn () => OwnedSetupFiles::create($dir, '../foreign.json', 'synthetic'))->toThrow(RuntimeException::class);
        $fixture->checkout->write('untouched', 'untouched');
        expect(symlink($fixture->checkout->root.'/untouched', $dir.'/setup.json'))->toBeTrue();
        expect(fn () => OwnedSetupFiles::create($dir, 'setup.json', 'synthetic'))->toThrow(RuntimeException::class);
        expect(file_get_contents($fixture->checkout->root.'/untouched'))->toBe('untouched');
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');
