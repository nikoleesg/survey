<?php

namespace Nikoleesg\Survey\Data\Transformers;

use Spatie\LaravelData\Transformers\Transformer;
use Spatie\LaravelData\Support\DataProperty;

class JsonableTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value): string
    {
        return json_encode($value);
    }
}
