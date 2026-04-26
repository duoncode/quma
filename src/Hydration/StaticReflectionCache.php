<?php

declare(strict_types=1);

namespace Duon\Quma\Hydration;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use Duon\Quma\Column;
use Duon\Quma\Hydratable;
use Duon\Quma\InvalidHydrationTargetException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/** @internal */
final class StaticReflectionCache implements MetadataCache
{
	/** @var array<class-string, ClassMetadata> */
	private static array $entries = [];

	/** @psalm-param class-string $class */
	#[\Override]
	public function metadata(string $class): ClassMetadata
	{
		if (!array_key_exists($class, self::$entries)) {
			self::$entries[$class] = $this->build($class);
		}

		return self::$entries[$class];
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public static function reset(): void
	{
		self::$entries = [];
	}

	/** @psalm-param class-string $class */
	private function build(string $class): ClassMetadata
	{
		if ($this->isBuiltinTypeName($class) || !class_exists($class)) {
			throw InvalidHydrationTargetException::forTarget(
				$class,
				reason: 'target is not an existing class',
			);
		}

		$reflection = new ReflectionClass($class);
		$hydratable = is_a($class, Hydratable::class, true);

		if ($hydratable) {
			$this->assertHydratableFactory($reflection, $class);

			return new ClassMetadata($class, true, $reflection->isInstantiable(), null);
		}

		if (!$reflection->isInstantiable()) {
			throw InvalidHydrationTargetException::forTarget($class, reason: 'target is not instantiable');
		}

		$constructor = $reflection->getConstructor();

		if ($constructor === null) {
			return new ClassMetadata($class, false, true, []);
		}

		$parameters = [];

		foreach ($constructor->getParameters() as $parameter) {
			$parameters[] = $this->parameterMetadata($class, $parameter);
		}

		usort(
			$parameters,
			static fn(ParameterMetadata $a, ParameterMetadata $b): int => $a->position <=> $b->position,
		);

		return new ClassMetadata($class, false, true, $parameters);
	}

	/**
	 * @psalm-param class-string $class
	 * @psalm-param ReflectionClass<object> $reflection
	 */
	private function assertHydratableFactory(ReflectionClass $reflection, string $class): void
	{
		$method = $reflection->getMethod('fromRow');

		if (!$method->isPublic() || !$method->isStatic() || $method->isAbstract()) {
			throw InvalidHydrationTargetException::forTarget(
				$class,
				reason: 'Hydratable::fromRow() must be public, static, and concrete',
			);
		}
	}

	/** @psalm-param class-string $class */
	private function parameterMetadata(
		string $class,
		ReflectionParameter $parameter,
	): ParameterMetadata {
		$name = $parameter->getName();

		if ($parameter->isVariadic()) {
			throw InvalidHydrationTargetException::forParameter($class, $name, 'is variadic');
		}

		if ($parameter->isPassedByReference()) {
			throw InvalidHydrationTargetException::forParameter($class, $name, 'is by-reference');
		}

		$type = $parameter->getType();

		if ($type === null) {
			throw InvalidHydrationTargetException::forParameter($class, $name, 'has no declared type');
		}

		$column = $this->columnName($class, $parameter, $name);
		$hasDefault = $parameter->isDefaultValueAvailable();

		return new ParameterMetadata(
			$name,
			$column,
			$this->typeMetadata($class, $name, $type),
			$type->allowsNull(),
			$hasDefault,
			$hasDefault ? $parameter->getDefaultValue() : null,
			$parameter->getPosition(),
		);
	}

	/**
	 * @psalm-param class-string $class
	 * @psalm-param non-empty-string $parameterName
	 * @psalm-return non-empty-string
	 */
	private function columnName(
		string $class,
		ReflectionParameter $parameter,
		string $parameterName,
	): string {
		$attributes = $parameter->getAttributes(Column::class);

		if ($attributes === []) {
			return $parameterName;
		}

		try {
			$column = $attributes[0]->newInstance();
		} catch (Throwable) {
			throw InvalidHydrationTargetException::forParameter(
				$class,
				$parameterName,
				'has an invalid #[Column] attribute',
			);
		}

		$columnName = $column->name;

		if (trim($columnName) === '') {
			throw InvalidHydrationTargetException::forParameter(
				$class,
				$parameterName,
				'has an empty #[Column] name',
			);
		}

		/** @var non-empty-string $columnName */
		return $columnName;
	}

	/**
	 * @psalm-param class-string $class
	 * @psalm-param non-empty-string $parameterName
	 */
	private function typeMetadata(
		string $class,
		string $parameterName,
		ReflectionType $type,
	): TypeMetadata {
		if ($type instanceof ReflectionNamedType) {
			$name = $this->namedTypeMetadata($class, $parameterName, $type);

			return new TypeMetadata('named', $type->allowsNull(), [$name]);
		}

		if ($type instanceof ReflectionUnionType) {
			$names = [];

			foreach ($type->getTypes() as $inner) {
				if (!$inner instanceof ReflectionNamedType) {
					throw InvalidHydrationTargetException::forParameter(
						$class,
						$parameterName,
						'uses an unsupported intersection or DNF type',
					);
				}

				if (strtolower($inner->getName()) === 'null') {
					continue;
				}

				$names[] = $this->namedTypeMetadata($class, $parameterName, $inner);
			}

			if ($names === []) {
				throw InvalidHydrationTargetException::forParameter(
					$class,
					$parameterName,
					'uses unsupported type null',
				);
			}

			return new TypeMetadata('union', $type->allowsNull(), $names);
		}

		if ($type instanceof ReflectionIntersectionType) {
			throw InvalidHydrationTargetException::forParameter(
				$class,
				$parameterName,
				'uses an unsupported intersection type',
			);
		}

		throw InvalidHydrationTargetException::forParameter(
			$class,
			$parameterName,
			'uses an unsupported type',
		);
	}

	/**
	 * @psalm-param class-string $class
	 * @psalm-param non-empty-string $parameterName
	 */
	private function namedTypeMetadata(
		string $class,
		string $parameterName,
		ReflectionNamedType $type,
	): NamedTypeMetadata {
		$name = $type->getName();
		$lower = strtolower($name);

		if (in_array($lower, ['self', 'parent', 'static'], true)) {
			throw InvalidHydrationTargetException::forParameter(
				$class,
				$parameterName,
				"uses unsupported type {$name}",
			);
		}

		if ($type->isBuiltin()) {
			if (in_array($lower, ['int', 'float', 'bool', 'string'], true)) {
				return new NamedTypeMetadata($lower, true, null, $lower, null, null);
			}

			throw InvalidHydrationTargetException::forParameter(
				$class,
				$parameterName,
				"uses unsupported type {$name}",
			);
		}

		if ($name === DateTimeImmutable::class) {
			return new NamedTypeMetadata($name, false, DateTimeImmutable::class, null, 'immutable', null);
		}

		if ($name === DateTime::class) {
			return new NamedTypeMetadata($name, false, DateTime::class, null, 'mutable', null);
		}

		if (is_subclass_of($name, BackedEnum::class)) {
			return new NamedTypeMetadata($name, false, $name, null, null, $name);
		}

		throw InvalidHydrationTargetException::forParameter(
			$class,
			$parameterName,
			"uses unsupported type {$name}",
		);
	}

	private function isBuiltinTypeName(string $target): bool
	{
		return in_array(
			strtolower($target),
			[
				'array',
				'bool',
				'callable',
				'false',
				'float',
				'int',
				'iterable',
				'mixed',
				'never',
				'null',
				'object',
				'string',
				'true',
				'void',
			],
			true,
		);
	}
}
