<?php

declare(strict_types=1);

namespace Conia\Puma\Migrations;

use Conia\Cli\Opts;

class Add extends Command
{
    public static string $group = 'Database';
    public static string $title = 'Initialize a new migrations';
    public static string $desc;

    protected function run(): string|int
    {
        $env = $this->env;
        $opts = new Opts();
        $fileName = $opts->get('-f', $opts->get('--file', ''));

        if (empty($fileName)) {
            // Would stop the test suit and wait for input
            // @codeCoverageIgnoreStart
            $fileName = readline('Name of the migration script: ');
            // @codeCoverageIgnoreEnd
        }

        $fileName = str_replace(' ', '-', $fileName);
        $fileName = str_replace('_', '-', $fileName);
        $fileName = strtolower($fileName);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!$ext) {
            $fileName .= '.sql';
        } else {
            if (!in_array($ext, ['sql', 'php', 'tpql'])) {
                echo "Wrong file extension '$ext'. Use 'sql', 'php' or 'tpql' instead.\nAborting.\n";
                return 1;
            }
        }

        $migrations = $env->getMigrations();

        // Get the first migrations directory from the list (the last one added)
        // TODO: let the user choose the migrations dir if there are more than one
        $migrationsDir = $migrations[0];

        echo ($migrationsDir . PHP_EOL);
        if ($migrationsDir && strpos($migrationsDir, '/vendor') !== false) {
            echo "The migrations directory is inside './vendor'.\n  -> $migrationsDir\nAborting.\n";
            return 1;
        }

        if (!$migrationsDir || !is_dir($migrationsDir)) {
            echo "The migrations directory does not exist: $migrationsDir\n  -> Aborting.\n";
            return 1;
        }

        if (!is_writable($migrationsDir)) {
            echo "Migrations directory is not writable\n  -> $migrationsDir\nAborting. \n";
            return 1;
        }

        $timestamp = date('ymd-His', time());

        $migration = $migrationsDir . DIRECTORY_SEPARATOR . $timestamp . '-' . $fileName;
        $f = fopen($migration, 'w');

        if ($ext === 'php') {
            fwrite($f, $this->getPhpContent($fileName, $timestamp));
        } elseif ($ext === 'tpql') {
            fwrite($f, $this->getTpqlContent());
        }

        fclose($f);
        echo "Migration created:\n$migration\n";

        return $migration;
    }

    protected function getPhpContent(string $fileName, string $timestamp): string
    {
        // Translates what-is-up.sql into WhatIsUp
        $className = implode(
            '',
            explode(
                '-',
                explode(
                    '.',
                    ucwords($fileName, '-')
                )[0]
            )
        ) . '_' . str_replace('-', '_', $timestamp);

        return "<?php

declare(strict_types=1);

use \PDO;
use Conia\Puma\Connection;
use Conia\Puma\Database;
use Conia\Puma\MigrationInterface;


class $className implements MigrationInterface
{
    public function run(Database \$db): bool
    {
        \$db->execute('')->run();
        \$result = \$db->execute('')->all(PDO::FETCH_ASSOC);

        return true;
    }
}

return new $className();";
    }

    protected function getTpqlContent(): string
    {
        return "<?php if (\$driver === 'pgsql') : ?>

<?php else : ?>

<?php endif ?>
";
    }
}
