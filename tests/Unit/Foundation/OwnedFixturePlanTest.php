<?php

declare(strict_types=1);

use Tests\Support\ci\OwnedServicePlan;
use Tests\Support\ci\PostgreSqlFixturePlan;
use Tests\Support\ci\RedisFixturePlan;

it('generates only owned PostgreSQL databases, restricted roles and read-only committed fixture grants', function (): void {
    $credentials = OwnedServicePlan::credentials('0123456789abcdef');
    $cluster = PostgreSqlFixturePlan::clusterSql($credentials);
    expect($cluster)->toContain('REVOKE ALL ON DATABASE postgres, template1, template0 FROM PUBLIC;', 'NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS');
    foreach (['central', 'a', 'b'] as $slot) {
        $db = 'f03_0123456789abcdef_'.$slot;
        expect($cluster)->toContain('CREATE DATABASE '.$db.' OWNER postgres;', 'REVOKE ALL ON DATABASE '.$db.' FROM PUBLIC;', 'GRANT CONNECT ON DATABASE '.$db.' TO '.$db.'_rt;');
        $sql = PostgreSqlFixturePlan::databaseSql($credentials, $slot);
        expect($sql)->toContain('REVOKE ALL ON SCHEMA public FROM PUBLIC;', 'BEGIN;', 'COMMIT;', "VALUES (100, 'f03:0123456789abcdef:".$slot."')", 'GRANT SELECT ON TABLE f03_probe.markers TO '.$db.'_rt;');
        expect($sql)->not->toContain('GRANT INSERT', 'GRANT UPDATE', 'GRANT ALL', 'DROP DATABASE');
    }
    expect(PostgreSqlFixturePlan::hba())->toBe("local all postgres trust\nlocal all all scram-sha-256\nhost all postgres all reject\nhost all all all scram-sha-256\n");
    expect(PostgreSqlFixturePlan::privilegeCheck($credentials))->toContain('has_database_privilege', 'pg_auth_members', '170012');
    expect(fn () => PostgreSqlFixturePlan::databaseSql($credentials, 'external'))->toThrow(RuntimeException::class);
})->group('testing-foundation');

it('restricts all Redis forbidden-command probes to ACL DRYRUN and distinguishes permissions from other errors', function (): void {
    foreach (['a', 'b'] as $slot) {
        $probes = RedisFixturePlan::probes('0123456789abcdef', $slot);
        expect($probes['allowed'])->toHaveCount(5);
        expect($probes['denied'])->toHaveCount(7);
        expect($probes['foreign'])->toHaveCount(3);
        foreach ($probes as $cases) {
            foreach ($cases as $argv) {
                expect(array_slice($argv, 0, 3))->toBe(['ACL', 'DRYRUN', 'f03_0123456789abcdef_'.$slot.'_rt']);
            }
        }
    }
    expect(RedisFixturePlan::denied("ERR This user has no permissions to run the 'flushall' command"))->toBeTrue();
    expect(RedisFixturePlan::denied('ERR This user has no permissions to access the key', true))->toBeTrue();
    foreach (['OK', 'NOAUTH Authentication required.', 'ERR unknown command', 'connection refused'] as $other) {
        expect(RedisFixturePlan::denied($other))->toBeFalse();
        expect(RedisFixturePlan::denied($other, true))->toBeFalse();
    }
    expect(fn () => RedisFixturePlan::probes('0123456789abcdef', 'central'))->toThrow(RuntimeException::class);
})->group('testing-foundation');
