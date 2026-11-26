<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use RuntimeException;
use SensitiveParameter;

/** Synthetic fixture SQL only. No domain tables, migrations or external target. */
final class PostgreSqlFixturePlan
{
    public static function hba(): string
    {
        // Setup admin accepts only the container-local Unix socket. Runtime
        // connections over the published loopback port require SCRAM.
        return "local all postgres trust\nlocal all all scram-sha-256\nhost all postgres all reject\nhost all all all scram-sha-256\n";
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    public static function clusterSql(#[SensitiveParameter] array $credentials): string
    {
        OwnedServicePlan::passwords($credentials);
        $sql = [
            "SET password_encryption = 'scram-sha-256';",
            'REVOKE ALL ON DATABASE postgres, template1, template0 FROM PUBLIC;',
        ];
        foreach (['central', 'a', 'b'] as $slot) {
            $target = self::target($credentials, $slot);
            $db = $target['database'];
            $role = $target['role'];
            $sql[] = 'CREATE ROLE '.$role." LOGIN PASSWORD '".$target['password']."' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;";
            // CREATE DATABASE must run outside a transaction; psql processes
            // each complete statement independently, with ON_ERROR_STOP.
            $sql[] = 'CREATE DATABASE '.$db.' OWNER postgres;';
            $sql[] = 'REVOKE ALL ON DATABASE '.$db.' FROM PUBLIC;';
            $sql[] = 'GRANT CONNECT ON DATABASE '.$db.' TO '.$role.';';
        }
        return implode("\n", $sql)."\n";
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    public static function databaseSql(#[SensitiveParameter] array $credentials, string $slot): string
    {
        $target = self::target($credentials, $slot);
        return implode("\n", [
            'BEGIN;',
            'REVOKE ALL ON SCHEMA public FROM PUBLIC;',
            'CREATE SCHEMA f03_probe AUTHORIZATION postgres;',
            'REVOKE ALL ON SCHEMA f03_probe FROM PUBLIC;',
            'CREATE TABLE f03_probe.markers (id integer PRIMARY KEY, marker text NOT NULL);',
            "INSERT INTO f03_probe.markers (id, marker) VALUES (100, '".$target['marker']."');",
            'REVOKE ALL ON TABLE f03_probe.markers FROM PUBLIC;',
            'GRANT USAGE ON SCHEMA f03_probe TO '.$target['role'].';',
            'GRANT SELECT ON TABLE f03_probe.markers TO '.$target['role'].';',
            'COMMIT;',
        ])."\n";
    }

    /**
     * @param array<array-key, mixed> $credentials
     * @return array{database: string, role: string, password: string, marker: string, ...}
     */
    public static function target(#[SensitiveParameter] array $credentials, string $slot): array
    {
        OwnedServicePlan::token($credentials['token']);
        if (!in_array($slot, ['central', 'a', 'b'], true)) {
            throw new RuntimeException('Unknown owned PostgreSQL fixture slot.');
        }
        $target = $credentials['postgres'][$slot];
        $database = 'f03_'.$credentials['token'].'_'.$slot;
        if ($target['database'] !== $database || $target['role'] !== $database.'_rt'
            || $target['marker'] !== 'f03:'.$credentials['token'].':'.$slot
            || !preg_match('/^[0-9a-f]{64}$/D', $target['password'])) {
            throw new RuntimeException('Owned PostgreSQL fixture identity rejected.');
        }
        return $target;
    }

    public static function hbaCheck(): string
    {
        return <<<'SQL'
SELECT CASE WHEN count(*) = 4 AND count(*) FILTER (WHERE error IS NOT NULL) = 0
AND count(*) FILTER (WHERE type = 'local' AND database = ARRAY['all']::text[] AND user_name = ARRAY['postgres']::text[] AND auth_method = 'trust') = 1
AND count(*) FILTER (WHERE type = 'local' AND database = ARRAY['all']::text[] AND user_name = ARRAY['all']::text[] AND auth_method = 'scram-sha-256') = 1
AND count(*) FILTER (WHERE type = 'host' AND database = ARRAY['all']::text[] AND user_name = ARRAY['postgres']::text[] AND address = 'all' AND auth_method = 'reject') = 1
AND count(*) FILTER (WHERE type = 'host' AND database = ARRAY['all']::text[] AND user_name = ARRAY['all']::text[] AND address = 'all' AND auth_method = 'scram-sha-256') = 1
THEN 'f03-hba-ok' ELSE 'f03-hba-invalid' END
FROM pg_hba_file_rules;
SQL;
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    public static function privilegeCheck(#[SensitiveParameter] array $credentials): string
    {
        // Verify actual restricted attributes, committed DB ownership and
        // complete CONNECT/TEMP matrix from the container-local setup role.
        $terms = ["current_setting('server_version_num') = '170012'", "current_setting('password_encryption') = 'scram-sha-256'"];
        foreach (['central', 'a', 'b'] as $slot) {
            $target = self::target($credentials, $slot);
            $role = $target['role'];
            $db = $target['database'];
            $terms[] = "EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$role' AND rolcanlogin AND NOT rolsuper AND NOT rolcreatedb AND NOT rolcreaterole AND NOT rolinherit AND NOT rolreplication AND NOT rolbypassrls)";
            $terms[] = "NOT EXISTS (SELECT 1 FROM pg_auth_members WHERE member = (SELECT oid FROM pg_roles WHERE rolname = '$role'))";
            $terms[] = "EXISTS (SELECT 1 FROM pg_database WHERE datname = '$db' AND pg_get_userbyid(datdba) = 'postgres')";
            foreach (['central', 'a', 'b', 'postgres', 'template1', 'template0'] as $other) {
                $database = in_array($other, ['postgres', 'template1', 'template0'], true) ? $other : self::target($credentials, $other)['database'];
                $terms[] = ($other === $slot ? '' : 'NOT ')."has_database_privilege('$role', '$database', 'CONNECT')";
                $terms[] = "NOT has_database_privilege('$role', '$database', 'TEMP')";
            }
        }
        return 'SELECT CASE WHEN '.implode(' AND ', $terms)." THEN 'f03-privileges-ok' ELSE 'f03-privileges-invalid' END;\n";
    }
}
