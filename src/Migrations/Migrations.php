<?php

declare(strict_types=1);

namespace Conia\Quma\Migrations;

use Conia\Cli\Opts;
use Conia\Quma\Connection;
use Conia\Quma\Database;
use PDOException;
use RuntimeException;
use Throwable;

class Migrations extends Command
{
    protected const STARTED = 'start';
    protected const ERROR = 'error';
    protected const WARNING = 'warning';
    protected const SUCCESS = 'success';
    protected string $name = 'migrations';
    protected string $group = 'Migrations';
    protected string $description = 'Apply missing database migrations';

    public function run(): string|int
    {
        $env = $this->env;
        $opts = new Opts();

        if (!$env->convenience || $env->checkIfMigrationsTableExists($env->db)) {
            return $this->migrate($env->db, $env->conn, $opts->has('--stacktrace'), $opts->has('--apply'));
        }
        $ddl = $env->getMigrationsTableDDL();

        if ($ddl) {
            echo "Migrations table does not exist. For '{$env->driver}' it should look like:\n\n";
            echo $ddl;
            echo "\n\nIf you want to create the table above, simply run\n\n";
            echo "    php run create-migrations-table\n\n";
            echo "If you need to change the table or column names set them via \n\n";
            echo "    \$\\Conia\\Quma\\Connection::setMigrationsTable(...)\n";
            echo "    \$\\Conia\\Quma\\Connection::setMigrationsColumnMigration(...)\n";
            echo "    \$\\Conia\\Quma\\Connection::setMigrationsColumnApplied(...)\n";
        } else {
            // An unsupported driver would have to be installed
            // to be able to test meaningfully
            // @codeCoverageIgnoreStart
            echo "Driver '{$env->driver}' is not supported.\n";
            // @codeCoverageIgnoreEnd
        }

        return 1;
    }

    protected function migrate(
        Database $db,
        Connection $conn,
        bool $showStacktrace,
        bool $apply
    ): int {
        $this->begin($db);
        $appliedMigrations = $this->getAppliedMigrations($db);
        $result = self::STARTED;
        $numApplied = 0;

        $migrations = $this->env->getMigrations();

        if ($migrations === false) {
            return 1;
        }

        foreach ($migrations as $migration) {
            assert(!empty($migration) && is_string($migration));

            if (in_array(basename($migration), $appliedMigrations)) {
                continue;
            }

            if (!$this->supportedByDriver($migration)) {
                continue;
            }

            $script = file_get_contents($migration);

            if (empty(trim($script))) {
                $this->showEmptyMessage($migration);
                $result = self::WARNING;

                continue;
            }

            switch (pathinfo($migration, PATHINFO_EXTENSION)) {
                case 'sql':
                    $result = $this->migrateSQL($db, $migration, $script, $showStacktrace);

                    break;

                case 'tpql':
                    $result = $this->migrateTPQL($db, $conn, $migration, $showStacktrace);

                    break;

                case 'php':
                    $result = $this->migratePHP($db, $migration, $showStacktrace);

                    break;
            }

            if ($result === self::ERROR) {
                break;
            }

            if ($result === self::SUCCESS) {
                $numApplied++;
            }
        }

        return $this->finish($db, $result, $apply, $numApplied);
    }

    protected function begin(Database $db): void
    {
        if ($this->supportsTransactions()) {
            $db->begin();
        }
    }

    protected function finish(
        Database $db,
        string $result,
        bool $apply,
        int $numApplied,
    ): int {
        $plural = $numApplied > 1 ? 's' : '';

        if ($this->supportsTransactions()) {
            if ($result === self::ERROR) {
                $db->rollback();
                echo "\nDue to errors no migrations applied\n";

                return 1;
            }

            if ($numApplied === 0) {
                $db->rollback();
                echo "\nNo migrations applied\n";

                return 0;
            }

            if ($apply) {
                $db->commit();
                echo "\n{$numApplied} migration{$plural} successfully applied\n";

                return 0;
            }
            echo "\n\033[1;31mNotice\033[0m: Test run only\033[0m";
            echo "\nWould apply {$numApplied} migration{$plural}. ";
            echo "Use the switch --apply to make it happen\n";
            $db->rollback();

            return 0;
        }
        if ($result === self::ERROR) {
            echo "\n{$numApplied} migration{$plural} applied until the error occured\n";

            return 1;
        }

        if ($numApplied > 0) {
            echo "\n{$numApplied} migration{$plural} successfully applied\n";

            return 0;
        }

        echo "\nNo migrations applied\n";

        return 0;
    }

    protected function supportsTransactions(): bool
    {
        switch ($this->env->driver) {
            case 'sqlite':
                return true;

            case 'pgsql':
                return true;

            case 'mysql':
                return false;
        }

        // An unsupported driver would have to be installed
        // to be able to test meaningfully
        // @codeCoverageIgnoreStart
        throw new RuntimeException('Database driver not supported');
        // @codeCoverageIgnoreEnd
    }

    protected function getAppliedMigrations(Database $db): array
    {
        $table = $this->env->table;
        $column = $this->env->columnMigration;
        $migrations = $db->execute("SELECT {$column} FROM {$table};")->all();

        return array_map(fn (array $mig): string => (string)$mig['migration'], $migrations);
    }

    /**
     * Returns if the given migration is driver specific.
     */
    protected function supportedByDriver(string $migration): bool
    {
        // First checks if there are brackets in the filename.
        if (preg_match('/\[[a-z]{3,8}\]/', $migration)) {
            // We have found a driver specific migration.
            // Check if it matches the current driver.
            if (preg_match('/\[' . $this->env->driver . '\]/', $migration)) {
                return true;
            }

            return false;
        }

        // This is no driver specific migration
        return true;
    }

    protected function migrateSQL(
        Database $db,
        string $migration,
        string $script,
        bool $showStacktrace
    ): string {
        try {
            $db->execute($script)->run();
            $this->logMigration($db, $migration);
            $this->showMessage($migration);

            return self::SUCCESS;
        } catch (PDOException $e) {
            $this->showMessage($migration, $e, $showStacktrace);

            return self::ERROR;
        }
    }

    protected function migrateTPQL(
        Database $db,
        Connection $conn,
        string $migration,
        bool $showStacktrace
    ): string {
        try {
            $load = function (string $migrationPath, array $context = []): void {
                // Hide $migrationPath. Could be overwritten if $context['templatePath'] exists.
                $____migration_path____ = $migrationPath;

                extract($context);

                /** @psalm-suppress UnresolvableInclude */
                include $____migration_path____;
            };

            $error = null;
            $context = [
                'driver' => $db->getPdoDriver(),
                'db' => $db,
                'conn' => $conn,
            ];

            ob_start();

            try {
                $load($migration, $context);
            } catch (Throwable $e) {
                $error = $e;
            }

            $script = ob_get_contents();
            ob_end_clean();

            if ($error !== null) {
                throw $error;
            }

            if (empty(trim($script))) {
                $this->showEmptyMessage($migration);

                return self::WARNING;
            }

            return $this->migrateSQL($db, $migration, $script, $showStacktrace);
        } catch (Throwable $e) {
            $this->showMessage($migration, $e, $showStacktrace);

            return self::ERROR;
        }
    }

    /** @psalm-suppress UnresolvableInclude, MixedAssignment, MixedMethodCall */
    protected function migratePHP(
        Database $db,
        string $migration,
        bool $showStacktrace
    ): string {
        try {
            $migObj = require $migration;
            $migObj->run($this->env);
            $this->logMigration($db, $migration);
            $this->showMessage($migration);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->showMessage($migration, $e, $showStacktrace);

            return self::ERROR;
        }
    }

    protected function logMigration(Database $db, string $migration): void
    {
        $name = basename($migration);
        $db->execute(
            'INSERT INTO migrations (migration) VALUES (:migration)',
            ['migration' => $name]
        )->run();
    }

    protected function showEmptyMessage(string $migration): void
    {
        echo "\033[33mWarning\033[0m: Migration '\033[1;33m" .
            basename($migration) .
            "'\033[0m is empty. Skipped\n";
    }

    protected function showMessage(
        string $migration,
        Throwable|null $e = null,
        bool $showStacktrace = false
    ): void {
        if ($e) {
            echo "\033[1;31mError\033[0m: while working on migration '\033[1;33m" .
                basename($migration) .
                "\033[0m'\n";
            echo $e->getMessage() . "\n";

            if ($showStacktrace) {
                echo $e->getTraceAsString() . "\n";
            }

            return;
        }

        echo "\033[1;32mSuccess\033[0m: Migration '\033[1;33m" .
            basename($migration) .
            "\033[0m' successfully applied\n";
    }
}
