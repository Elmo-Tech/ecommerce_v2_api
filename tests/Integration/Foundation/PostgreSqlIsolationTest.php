<?php

declare(strict_types=1);

use Tests\Support\IntegrationConnections;

/**
 * Real service tests. Not discovered by the default fast XML. The future
 * IntegrationConnections::current() must verify job-owned identity and
 * endpoints before native I/O. There is no skip, SQLite or mock fallback.
 */
function f03PostgresConnections(): IntegrationConnections
{
    expect(is_callable([IntegrationConnections::class, 'current']))->toBeTrue();

    return IntegrationConnections::current();
}

it('opens the three independent owned databases using their own restricted native runtime roles and committed overlapping IDs', function (): void {
    $connections = f03PostgresConnections();
    $token = $connections->token();
    $markers = [];
    foreach (['central', 'a', 'b'] as $slot) {
        $first = $connections->postgres($slot);
        $second = $connections->postgres($slot);
        expect($first)->toBeInstanceOf(PDO::class);
        expect($second)->toBeInstanceOf(PDO::class)->not()->toBe($first);
        expect($first->getAttribute(PDO::ATTR_PERSISTENT))->toBeFalse();
        expect($first->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('pgsql');

        $identity = $first->query('SELECT current_database() AS db, current_user AS role, session_user AS session_role')->fetch(PDO::FETCH_ASSOC);
        $name = 'f03_'.$token.'_'.$slot;
        expect($identity)->toBe(['db' => $name, 'role' => $name.'_rt', 'session_role' => $name.'_rt']);
        expect((int) $first->query('SHOW server_version_num')->fetchColumn())->toBe(170012);

        $marker = $first->query('SELECT marker FROM f03_probe.markers WHERE id = 100')->fetchColumn();
        expect($marker)->toBe('f03:'.$token.':'.$slot);
        expect($second->query('SELECT marker FROM f03_probe.markers WHERE id = 100')->fetchColumn())->toBe($marker);
        expect($first->inTransaction())->toBeFalse();
        $markers[] = $marker;
        $first = $second = null;
    }
    expect(array_unique($markers))->toHaveCount(3);
})->group('testing-foundation-integration');

dataset('f03 postgres directed denial pairs', [
    ['central', 'a'], ['central', 'b'],
    ['a', 'central'], ['a', 'b'],
    ['b', 'central'], ['b', 'a'],
    ['central', 'postgres'], ['central', 'template1'],
    ['a', 'postgres'], ['a', 'template1'],
    ['b', 'postgres'], ['b', 'template1'],
]);

it('proves an actual cross database or maintenance CONNECT denial with valid own credentials, not a timeout or authentication failure', function (string $source, string $target): void {
    $connections = f03PostgresConnections();
    $own = $connections->postgres($source);
    expect((int) $own->query('SELECT 1')->fetchColumn())->toBe(1);
    $targetDatabase = in_array($target, ['postgres', 'template1'], true)
        ? $target
        : 'f03_'.$connections->token().'_'.$target;
    $statement = $own->prepare("SELECT CASE WHEN has_database_privilege(current_user, :target, 'CONNECT') THEN 1 ELSE 0 END");
    $statement->execute(['target' => $targetDatabase]);
    expect((int) $statement->fetchColumn())->toBe(0);

    // The method must make a real negative connection attempt only on the
    // closed owned pair matrix. It reports safe, actual server denial
    // evidence (42501 or independently correlated safe server evidence).
    // It must fail on a successful foreign connection, bad auth or network.
    $result = $connections->postgresDenial($source, $target);
    expect($result)->toMatchArray([
        'source' => $source,
        'target' => $target,
        'sqlstate' => '42501',
        'reason' => 'CONNECT_DENIED',
    ]);
    $own = null;
})->with('f03 postgres directed denial pairs')->group('testing-foundation-integration');

it('proves runtime role flags, membership, database, schema, table and default grant restrictions in the actual database', function (string $slot): void {
    $connections = f03PostgresConnections();
    $db = $connections->postgres($slot);
    $flags = $db->query('SELECT rolsuper::int, rolcreatedb::int, rolcreaterole::int, rolinherit::int, rolreplication::int, rolbypassrls::int FROM pg_roles WHERE rolname = current_user')->fetch(PDO::FETCH_NUM);
    expect(array_map('intval', $flags))->toBe([0, 0, 0, 0, 0, 0]);
    expect((int) $db->query('SELECT count(*) FROM pg_auth_members WHERE member = (SELECT oid FROM pg_roles WHERE rolname = current_user)')->fetchColumn())->toBe(0);

    foreach ([
        "has_database_privilege(current_user, current_database(), 'CREATE')",
        "has_database_privilege(current_user, current_database(), 'TEMP')",
        "has_schema_privilege(current_user, 'public', 'CREATE')",
        "has_schema_privilege(current_user, 'f03_probe', 'CREATE')",
        "has_table_privilege(current_user, 'f03_probe.markers', 'INSERT')",
        "has_table_privilege(current_user, 'f03_probe.markers', 'UPDATE')",
        "has_table_privilege(current_user, 'f03_probe.markers', 'DELETE')",
        "has_table_privilege(current_user, 'f03_probe.markers', 'TRUNCATE')",
    ] as $check) {
        expect((int) $db->query('SELECT CASE WHEN '.$check.' THEN 1 ELSE 0 END')->fetchColumn())->toBe(0);
    }
    expect((int) $db->query("SELECT CASE WHEN has_schema_privilege(current_user, 'f03_probe', 'USAGE') AND has_table_privilege(current_user, 'f03_probe.markers', 'SELECT') THEN 1 ELSE 0 END")->fetchColumn())->toBe(1);
    // No unsafe default table/sequence grants to PUBLIC or this runtime role.
    expect((int) $db->query("SELECT count(*) FROM pg_default_acl d CROSS JOIN LATERAL aclexplode(d.defaclacl) a WHERE d.defaclobjtype IN ('r','S') AND a.grantee IN (0, (SELECT oid FROM pg_roles WHERE rolname = current_user))")->fetchColumn())->toBe(0);
    $db = null;
})->with(['central', 'a', 'b'])->group('testing-foundation-integration');

it('actually denies controlled schema, table and role creation inside the verified owned database', function (string $slot, string $kind): void {
    /** @var 'schema'|'table'|'role' $kind */

    $connections = f03PostgresConnections();
    $db = $connections->postgres($slot);
    // All statements are fixed and scoped to verified disposable resources.
    // A surprisingly allowed operation is rolled back and the test fails.
    $sql = match ($kind) {
        'schema' => 'CREATE SCHEMA f03_denied_probe',
        'table' => 'CREATE TABLE f03_probe.denied_probe (id integer)',
        'role' => 'CREATE ROLE f03_'.$connections->token().'_denied',
    };
    $state = null;
    $db->beginTransaction();
    try {
        $db->exec($sql);
    } catch (PDOException $error) {
        $state = (string) ($error->errorInfo[0] ?? $error->getCode());
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    expect($state)->toBe('42501');
    $db = null;
})->with(['central', 'a', 'b'])->with(['schema', 'table', 'role'])->group('testing-foundation-integration');

it('rejects an arbitrary denial probe target rather than treating it as part of the closed owned matrix', function (): void {
    $connections = f03PostgresConnections();
    expect(fn () => $connections->postgresDenial('a', 'unknown'))->toThrow(RuntimeException::class);
    expect(fn () => $connections->postgresDenial('a', 'a'))->toThrow(RuntimeException::class);
})->group('testing-foundation-integration');
