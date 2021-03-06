<?php declare(strict_types = 1);

namespace PHPStan\Type;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\TrinaryLogic;

class IntersectionType implements CompoundType, StaticResolvableType
{

	/** @var \PHPStan\Type\Type[] */
	private $types;

	/**
	 * @param Type[] $types
	 */
	public function __construct(array $types)
	{
		$this->types = UnionTypeHelper::sortTypes($types);
	}

	/**
	 * @return Type[]
	 */
	public function getTypes(): array
	{
		return $this->types;
	}

	/**
	 * @return string[]
	 */
	public function getReferencedClasses(): array
	{
		return UnionTypeHelper::getReferencedClasses($this->types);
	}

	public function accepts(Type $otherType): bool
	{
		foreach ($this->types as $type) {
			if (!$type->accepts($otherType)) {
				return false;
			}
		}

		return true;
	}

	public function isSuperTypeOf(Type $otherType): TrinaryLogic
	{
		$results = [];
		foreach ($this->getTypes() as $innerType) {
			$results[] = $innerType->isSuperTypeOf($otherType);
		}

		return TrinaryLogic::createYes()->and(...$results);
	}

	public function isSubTypeOf(Type $otherType): TrinaryLogic
	{
		if ($otherType instanceof self || $otherType instanceof UnionType) {
			return $otherType->isSuperTypeOf($this);
		}

		$results = [];
		foreach ($this->getTypes() as $innerType) {
			$results[] = $otherType->isSuperTypeOf($innerType);
		}

		return TrinaryLogic::maxMin(...$results);
	}

	public function describe(): string
	{
		$typeNames = [];

		foreach ($this->types as $type) {
			$typeNames[] = $type->describe();
		}

		return implode('&', $typeNames);
	}

	public function canAccessProperties(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->canAccessProperties();
		});
	}

	public function hasProperty(string $propertyName): bool
	{
		foreach ($this->types as $type) {
			if ($type->hasProperty($propertyName)) {
				return true;
			}
		}

		return false;
	}

	public function getProperty(string $propertyName, Scope $scope): PropertyReflection
	{
		foreach ($this->types as $type) {
			if ($type->hasProperty($propertyName)) {
				return $type->getProperty($propertyName, $scope);
			}
		}

		throw new \PHPStan\ShouldNotHappenException();
	}

	public function canCallMethods(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->canCallMethods();
		});
	}

	public function hasMethod(string $methodName): bool
	{
		foreach ($this->types as $type) {
			if ($type->hasMethod($methodName)) {
				return true;
			}
		}

		return false;
	}

	public function getMethod(string $methodName, Scope $scope): MethodReflection
	{
		foreach ($this->types as $type) {
			if ($type->hasMethod($methodName)) {
				return $type->getMethod($methodName, $scope);
			}
		}

		throw new \PHPStan\ShouldNotHappenException();
	}

	public function canAccessConstants(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->canAccessConstants();
		});
	}

	public function hasConstant(string $constantName): bool
	{
		foreach ($this->types as $type) {
			if ($type->hasConstant($constantName)) {
				return true;
			}
		}

		return false;
	}

	public function getConstant(string $constantName): ClassConstantReflection
	{
		foreach ($this->types as $type) {
			if ($type->hasConstant($constantName)) {
				return $type->getConstant($constantName);
			}
		}

		throw new \PHPStan\ShouldNotHappenException();
	}

	public function isIterable(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->isIterable();
		});
	}

	public function getIterableKeyType(): Type
	{
		return $this->intersectTypes(function (Type $type): Type {
			return $type->getIterableKeyType();
		});
	}

	public function getIterableValueType(): Type
	{
		return $this->intersectTypes(function (Type $type): Type {
			return $type->getIterableValueType();
		});
	}

	public function isOffsetAccessible(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->isOffsetAccessible();
		});
	}

	public function getOffsetValueType(Type $offsetType): Type
	{
		return $this->intersectTypes(function (Type $type) use ($offsetType): Type {
			return $type->getOffsetValueType($offsetType);
		});
	}

	public function setOffsetValueType(?Type $offsetType, Type $valueType): Type
	{
		return $this->intersectTypes(function (Type $type) use ($offsetType, $valueType): Type {
			return $type->setOffsetValueType($offsetType, $valueType);
		});
	}

	public function isCallable(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->isCallable();
		});
	}

	public function isCloneable(): TrinaryLogic
	{
		return $this->intersectResults(function (Type $type): TrinaryLogic {
			return $type->isCloneable();
		});
	}

	public function toBoolean(): BooleanType
	{
		/** @var BooleanType $type */
		$type = $this->intersectTypes(function (Type $type): BooleanType {
			return $type->toBoolean();
		});

		return $type;
	}

	public function toNumber(): Type
	{
		$type = $this->intersectTypes(function (Type $type): Type {
			return $type->toNumber();
		});

		return $type;
	}

	public function toString(): Type
	{
		$type = $this->intersectTypes(function (Type $type): Type {
			return $type->toString();
		});

		return $type;
	}

	public function toInteger(): Type
	{
		$type = $this->intersectTypes(function (Type $type): Type {
			return $type->toInteger();
		});

		return $type;
	}

	public function toFloat(): Type
	{
		$type = $this->intersectTypes(function (Type $type): Type {
			return $type->toFloat();
		});

		return $type;
	}

	public function toArray(): Type
	{
		$type = $this->intersectTypes(function (Type $type): Type {
			return $type->toArray();
		});

		return $type;
	}

	public function resolveStatic(string $className): Type
	{
		return new self(UnionTypeHelper::resolveStatic($className, $this->getTypes()));
	}

	public function changeBaseClass(string $className): StaticResolvableType
	{
		return new self(UnionTypeHelper::changeBaseClass($className, $this->getTypes()));
	}

	/**
	 * @param mixed[] $properties
	 * @return Type
	 */
	public static function __set_state(array $properties): Type
	{
		return new self($properties['types']);
	}

	private function intersectResults(callable $getResult): TrinaryLogic
	{
		$operands = array_map($getResult, $this->types);
		return TrinaryLogic::maxMin(...$operands);
	}

	private function intersectTypes(callable $getType): Type
	{
		$operands = array_map($getType, $this->types);
		return TypeCombinator::intersect(...$operands);
	}

}
