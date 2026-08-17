<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Support;

use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnexpectedValueException;

final class ModelValue
{
    public static function key(Model $model): int|string
    {
        return self::identifier($model->getKey(), 'key');
    }

    public static function foreignKey(Model $model, string $attribute): int|string
    {
        return self::identifier($model->getAttribute($attribute), "attribute [{$attribute}]");
    }

    public static function sameIdentifier(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        if ((! is_int($left) && ! is_string($left) && ! $left instanceof Stringable)
            || (! is_int($right) && ! is_string($right) && ! $right instanceof Stringable)) {
            return false;
        }

        return (string) $left === (string) $right;
    }

    private static function identifier(mixed $value, string $description): int|string
    {
        if (is_string($value) || is_int($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return $value->__toString();
        }

        throw new UnexpectedValueException("Configured iCalendar model {$description} must be an integer, string, or stringable value.");
    }

    public static function string(Model $model, string $attribute): string
    {
        $value = $model->getAttribute($attribute);
        if (! is_string($value)) {
            throw new UnexpectedValueException("Model attribute [{$attribute}] must be a string.");
        }

        return $value;
    }
}
