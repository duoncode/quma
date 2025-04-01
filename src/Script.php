<?php

declare(strict_types=1);

namespace Duon\Quma;

use InvalidArgumentException;

/** @psalm-api */
class Script
{
	protected $db;
	protected $script;
	protected $isTemplate;

	public function __construct(Database $db, string $script, bool $isTemplate)
	{
		$this->db = $db;
		$this->script = $script;
		$this->isTemplate = $isTemplate;
	}

	public function __invoke(mixed ...$args): Query
	{
		return $this->invoke(...$args);
	}

	public function invoke(mixed ...$argsArray): Query
	{
		$args = new Args($argsArray);

		if ($this->isTemplate) {
			if ($args->type() === ArgType::Positional) {
				throw new InvalidArgumentException(
					'Template queries `*.sql.php` allow named parameters only',
				);
			}

			$script = $this->evaluateTemplate($this->script, $args);

			// We need to wrap the result of the prepare call in an array
			// to get back to the format of ...$argsArray.
			$args = new Args([$this->prepareTemplateVars($script, $args)]);
		} else {
			$script = $this->script;
		}

		return new Query($this->db, $script, $args);
	}

	/** @psalm-suppress PossiblyUnusedParam - $path and $args are used but psalm complains anyway */
	protected function evaluateTemplate(string $path, Args $args): string
	{
		// Hide $path. Could be overwritten if 'path' exists in $args.
		$____template_path____ = $path;
		unset($path);

		extract(array_merge(
			// Add the pdo driver to args to allow dynamic
			// queries based on the platform.
			['pdodriver' => $this->db->getPdoDriver()],
			$args->get(),
		));

		ob_start();

		/** @psalm-suppress UnresolvableInclude */
		include $____template_path____;

		return ob_get_clean();
	}

	/**
	 * Removes all keys from $params which are not present
	 * in the $script.
	 *
	 * PDO does not allow unused parameters.
	 */
	protected function prepareTemplateVars(string $script, Args $args): array
	{
		// Remove PostgreSQL blocks
		$script = preg_replace(Query::PATTERN_BLOCK, ' ', $script);
		// Remove strings
		$script = preg_replace(Query::PATTERN_STRING, ' ', $script);
		// Remove /* */ comments
		$script = preg_replace(Query::PATTERN_COMMENT_MULTI, ' ', $script);
		// Remove single line comments
		$script = preg_replace(Query::PATTERN_COMMENT_SINGLE, ' ', $script);

		$newArgs = [];

		// Match everything starting with : and a letter.
		// Exclude multiple colons, like type casts (::text).
		// Would not find a var if it is at the very beginning of script.
		if (
			preg_match_all(
				'/[^:]:[a-zA-Z][a-zA-Z0-9_]*/',
				$script,
				$result,
				PREG_PATTERN_ORDER,
			)
		) {
			$argsArray = $args->get();
			$newArgs = [];

			foreach (array_unique($result[0]) as $arg) {
				$a = substr($arg, 2);
				assert(!empty($a));

				/** @psalm-var array<non-empty-string, mixed> */
				$newArgs[$a] = $argsArray[$a];
			}
		}

		return $newArgs;
	}
}
