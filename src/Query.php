<?php

declare(strict_types=1);

namespace Conia\Puma;

use InvalidArgumentException;
use PDO;

class Query implements QueryInterface
{
    protected \PDOStatement $stmt;
    protected bool $executed = false;

    // Matches multi line single and double quotes and handles \' \" escapes
    const PATTERN_STRING = '/([\'"])(?:\\\1|[\s\S])*?\1/';
    // PostgreSQL blocks delimited with $$
    const PATTERN_BLOCK = '/(\$\$)[\s\S]*?\1/';
    // Multi line comments /* */
    const PATTERN_COMMENT_MULTI = '/\/\*([\s\S]*?)\*\//';
    // Single line comments --
    const PATTERN_COMMENT_SINGLE = '/--.*$/m';

    public function __construct(
        protected DatabaseInterface $db,
        protected string $query,
        protected Args $args
    ) {
        $this->stmt = $this->db->getConn()->prepare($query);

        if ($args->count() > 0) {
            $this->bindArgs($args->get(), $args->type());
        }

        if ($db->shouldPrint()) {
            $msg = "\n\n-----------------------------------------------\n\n" .
                $this->interpolate() .
                "\n------------------------------------------------\n";

            if ($_SERVER['SERVER_SOFTWARE'] ?? false) {
                // @codeCoverageIgnoreStart
                error_log($msg);
                // @codeCoverageIgnoreEnd
            } else {
                print($msg);
            };
        }
    }

    protected function bindArgs(array $args, ArgType $argType): void
    {
        foreach ($args as $a => $value) {
            if ($argType === ArgType::Named) {
                $arg = ':' . $a;
            } else {
                $arg = (int)$a + 1; // question mark placeholders ar 1-indexed
            }

            switch (gettype($value)) {
                case 'boolean':
                    $this->stmt->bindValue($arg, $value, PDO::PARAM_BOOL);
                    break;
                case 'integer':
                    $this->stmt->bindValue($arg, $value, PDO::PARAM_INT);
                    break;
                case 'string':
                    $this->stmt->bindValue($arg, $value, PDO::PARAM_STR);
                    break;
                case 'NULL':
                    $this->stmt->bindValue($arg, $value, PDO::PARAM_NULL);
                    break;
                case 'array':
                    $this->stmt->bindValue($arg, json_encode($value), PDO::PARAM_STR);
                    break;
                default:
                    throw new InvalidArgumentException(
                        'Only the types bool, int, string, null and array are supported'
                    );
            }
        }
    }

    protected function nullIfNot(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        return $value ?: null;
    }

    public function one(?int $fetchMode = null): ?array
    {
        $this->db->connect();

        if (!$this->executed) {
            $this->stmt->execute();
            $this->executed = true;
        }

        $result = $this->nullIfNot($this->stmt->fetch($fetchMode ?? $this->db->getFetchMode()));

        return $result;
    }

    public function all(?int $fetchMode = null): array
    {
        $this->db->connect();
        $this->stmt->execute();
        $result = $this->stmt->fetchAll($fetchMode ?? $this->db->getFetchMode());

        return $result;
    }

    public function run(): bool
    {
        $this->db->connect();

        return $this->stmt->execute();
    }

    public function len(): int
    {
        $this->db->connect();
        $this->stmt->execute();

        return $this->stmt->rowCount();
    }

    protected function convertValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_array($value)) {
            return "'" . json_encode($value) . "'";
        }

        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }

    protected function prepareQuery(string $query): PreparedQuery
    {
        $patterns = [
            self::PATTERN_BLOCK,
            self::PATTERN_STRING,
            self::PATTERN_COMMENT_MULTI,
            self::PATTERN_COMMENT_SINGLE,
        ];
        $swaps = [];

        $i = 0;

        do {
            $found = false;

            foreach ($patterns as $pattern) {
                $matches = [];

                if (preg_match($pattern, $query, $matches)) {
                    $match = $matches[0];
                    $replacement = "___CHUCK_REPLACE_${i}___";
                    $swaps[$replacement] = $match;

                    $query = preg_replace($pattern, $replacement, $query, limit: 1);
                    $found = true;
                    $i++;

                    break;
                }
            }
        } while ($found);

        return new PreparedQuery($query, $swaps);
    }

    protected function restoreQuery(string $query, PreparedQuery $prep): string
    {
        foreach ($prep->swaps as $swap => $replacement) {
            $query = str_replace($swap, $replacement, $query);
        }

        return $query;
    }


    protected function interpolateNamed(string $query, array $args): string
    {
        $map = [];

        foreach ($args as $key => $value) {
            $key = ':' . $key;
            $map[$key] = $this->convertValue($value);
        }

        return strtr($query, $map);
    }


    protected function interpolatePositional(string $query, array $args): string
    {
        foreach ($args as $value) {
            $query = preg_replace('/\\?/', $this->convertValue($value), $query, 1);
        }

        return $query;
    }

    /**
     * For debugging purposes only.
     *
     * Replaces any parameter placeholders in a query with the
     * value of that parameter and returns the query as string.
     *
     * Covers most of the cases but is not perfect.
     */
    public function interpolate(): string
    {
        $prep = $this->prepareQuery($this->query);
        $argsArray = $this->args->get();

        if ($this->args->type() === ArgType::Named) {
            $interpolated = $this->interpolateNamed($prep->query, $argsArray);
        } else {
            $interpolated = $this->interpolatePositional($prep->query, $argsArray);
        }

        return $this->restoreQuery($interpolated, $prep);
    }

    public function __toString(): string
    {
        return $this->interpolate();
    }
}
