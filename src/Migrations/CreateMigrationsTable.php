<?php

declare(strict_types=1);

namespace Conia\Puma\Migrations;

use Conia\Cli\CommandInterface;
use Throwable;

class CreateMigrationsTable implements CommandInterface
{
    public static string $group = 'Database';
    public static string $title = 'Apply missing database migrations';
    public static string $desc;

    public function run(App $app): string|int
    {
        $config = $app->config();
        $env = new Environment($config);

        if ($env->checkIfMigrationsTableExists($env->db)) {
            echo "Table '$env->table' already exists. Aborting\n";
            return 1;
        } else {
            $ddl = $env->getMigrationsTableDDL();

            if ($ddl) {
                try {
                    $env->db->execute($ddl)->run();
                    echo "\033[1;32mSuccess\033[0m: Created table '$env->table'\n";

                    return 0;
                    // Would require to create additional errornous DDL or to
                    // setup a different test database. Too much effort.
                    // @codeCoverageIgnoreStart
                } catch (Throwable $e) {
                    echo "\033[1;31mError\033[0m: While trying to create table '$env->table'\n";
                    echo $e->getMessage() . PHP_EOL;

                    if ($env->showStacktrace) {
                        echo escapeshellarg($e->getTraceAsString()) . PHP_EOL;
                    }

                    return 1;
                    // @codeCoverageIgnoreEnd
                }
            } else {
                // Cannot be reliably tested.
                // Would require an unsupported driver to be installed.
                // @codeCoverageIgnoreStart
                echo "PDO driver '$env->driver' not supported. Aborting\n";

                return 1;
                // @codeCoverageIgnoreEnd
            }
        }
    }
}
