<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Support;

use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnexpectedValueException;

final class ModelValue
{
    public static function key(Model $model): string
    {
        $key = $model->getKey();
        if (is_string($key) || is_int($key)) {
            return (string) $key;
        }
        if ($key instanceof Stringable) {
            return $key->__toString();
        }

        throw new UnexpectedValueException('Configured iCalendar models must have a scalar or stringable key.');
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
