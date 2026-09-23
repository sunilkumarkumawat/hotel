<?php

/*
|--------------------------------------------------------------------------
| MySQL dump generator
|--------------------------------------------------------------------------
| Writes database/hotel_admin.sql for people who would rather import in
| phpMyAdmin than run `php artisan migrate --seed`.
|
|     php scripts/dump-mysql.php
|
| The CREATE TABLE statements are not written by hand — every migration is
| replayed against a MySQL schema grammar that records the SQL instead of
| running it. So the dump can never drift from database/migrations: change a
| migration, re-run this, and the .sql follows.
|
| The rows come from whatever database the app is pointed at right now
| (SQLite during development is fine).
*/

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A MySQL connection that writes the SQL down instead of running it.
 *
 * Everything a migration might ask of a connection is answered without a
 * server: statements are collected, and the "does this table exist" style
 * questions are answered from what has been created so far.
 */
final class RecordingMySqlConnection extends MySqlConnection
{
    /** @var list<string> */
    public array $statements = [];

    public function __construct()
    {
        parent::__construct(fn () => null, 'dump', '', [
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB',
        ]);

        // The connection factory normally does this; there is no factory here.
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultPostProcessor();
    }

    public function statement($query, $bindings = [])
    {
        $this->statements[] = rtrim(trim($query), ';') . ';';

        return true;
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return [];
    }

    /** Target MySQL 8, not MariaDB — there is no server to ask. */
    public function isMaria()
    {
        return false;
    }

    public function getServerVersion(): string
    {
        return '8.0.36';
    }

    public function getSchemaBuilder()
    {
        $builder = new class($this) extends MySqlBuilder
        {
            public function hasTable($table)
            {
                return false;
            }

            public function hasColumn($table, $column)
            {
                return false;
            }
        };

        return $builder;
    }
}

/* ---------------------------------------------------------------------------
 | 1. Replay the migrations to collect MySQL DDL
 * ------------------------------------------------------------------------ */

$recorder = new RecordingMySqlConnection;

// Point the Schema facade at the recorder while the migrations run.
$original = DB::getDefaultConnection();
app('db')->extend('dump', fn () => $recorder);
config(['database.connections.dump' => ['driver' => 'mysql', 'database' => 'dump']]);
DB::setDefaultConnection('dump');

foreach (glob(__DIR__ . '/../database/migrations/*.php') as $file) {
    (require $file)->up();
}

DB::setDefaultConnection($original);

$ddl = $recorder->statements;

/** Table name out of a `create table X` or `alter table X` statement. */
$tableOf = function (string $sql): ?string {
    return preg_match('/(?:create|alter) table `([^`]+)`/i', $sql, $m) ? $m[1] : null;
};

/**
 * Gather every statement under the table it belongs to, in the order the
 * migrations produced them.
 *
 * This grouping is what keeps a later migration's `alter table … add column`
 * ahead of that table's INSERT. Emitting statements in raw migration order
 * would put the rows in first and the import would fail on a column that does
 * not exist yet.
 *
 * @var array<string, array{create: ?string, alters: list<string>}> $byTable
 */
$byTable = [];
$order = [];
$loose = [];

foreach ($ddl as $sql) {
    $table = $tableOf($sql);

    if ($table === null) {
        $loose[] = $sql;

        continue;
    }

    if (! isset($byTable[$table])) {
        $byTable[$table] = ['create' => null, 'alters' => []];
        $order[] = $table;
    }

    if (stripos($sql, 'create table') === 0) {
        $byTable[$table]['create'] = $sql;
    } else {
        $byTable[$table]['alters'][] = $sql;
    }
}

/* ---------------------------------------------------------------------------
 | 2. Emit the file
 * ------------------------------------------------------------------------ */

$quote = function ($value): string {
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace(
        ['\\', "'", "\n", "\r", "\0", "\x1a"],
        ['\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z'],
        (string) $value
    ) . "'";
};

$out = [];
$out[] = <<<'HEAD'
-- =============================================================================
-- Hotel Admin — database dump (MySQL / MariaDB)
--
-- Generated from database/migrations by scripts/dump-mysql.php, so it always
-- matches the app. Import this ONLY for a fresh start; if you already have the
-- app running, `php artisan migrate --seed` is the right way to pick up new
-- tables without losing your data.
--
-- phpMyAdmin:   create the `hotel_admin` database -> Import -> choose this file
-- Command line: mysql -u root hotel_admin < database/hotel_admin.sql
--
-- Sign in with: admin / password
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
HEAD;

$rowCount = 0;

foreach ($loose as $sql) {
    $out[] = "\n" . $sql;
}

foreach ($order as $table) {
    ['create' => $create, 'alters' => $alters] = $byTable[$table];

    $out[] = "\n-- -----------------------------------------------------------------------------";
    $out[] = "-- {$table}";
    $out[] = '-- -----------------------------------------------------------------------------';
    $out[] = "DROP TABLE IF EXISTS `{$table}`;";
    $out[] = $create;

    // Columns and indexes added by later migrations, before any rows go in.
    foreach ($alters as $alter) {
        $out[] = "\n" . $alter;
    }

    // Session, cache and queue tables ship empty — they are runtime scratch.
    if (in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'], true)) {
        continue;
    }

    $rows = DB::table($table)->get()->map(fn ($row) => (array) $row);

    if ($rows->isEmpty()) {
        continue;
    }

    $rowCount += $rows->count();

    // Chunked so phpMyAdmin never chokes on one giant statement.
    foreach ($rows->chunk(200) as $chunk) {
        $columns = '`' . implode('`, `', array_keys($chunk->first())) . '`';
        $values = $chunk
            ->map(fn (array $row) => '  (' . implode(', ', array_map($quote, $row)) . ')')
            ->implode(",\n");

        $out[] = "\nINSERT INTO `{$table}` ({$columns}) VALUES\n{$values};";
    }
}

/*
 * `migrations` is created by the migrator itself, not by a migration, so it
 * never appears in the recorded DDL. Write it out by hand and fill it in, so
 * `php artisan migrate` after an import does not try to run everything again.
 */
$out[] = "\n-- -----------------------------------------------------------------------------";
$out[] = '-- migrations';
$out[] = '-- -----------------------------------------------------------------------------';
$out[] = 'DROP TABLE IF EXISTS `migrations`;';
$out[] = <<<'SQL'
create table `migrations` (
  `id` int unsigned not null auto_increment primary key,
  `migration` varchar(255) not null,
  `batch` int not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;
SQL;

$out[] = "\nINSERT INTO `migrations` (`migration`, `batch`) VALUES";
$out[] = collect(glob(__DIR__ . '/../database/migrations/*.php'))
    ->map(fn ($f) => "  ('" . basename($f, '.php') . "', 1)")
    ->implode(",\n") . ';';

$out[] = "\nSET FOREIGN_KEY_CHECKS = 1;";

$sql = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../database/hotel_admin.sql', $sql);

printf(
    "Wrote database/hotel_admin.sql — %.1f KB, %d tables, %d rows\n",
    strlen($sql) / 1024,
    count($order),
    $rowCount
);
