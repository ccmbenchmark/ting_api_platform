<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;

class BooleanFilter extends AbstractFilter implements FilterInterface
{
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $strategy)
        {
            $description[$property] = [
                'property' => $property,
                'type' => $this->getTypeForProperty($property, $resourceClass),
                'required' => false
            ];
        }

        return $description;
    }

    /**
     * @param mixed $value
     */
    public function addClause(string $property, $value): string
    {
        return sprintf('%s = %s', $property, in_array($value, ['true', true], true) ? 1 : 0);
    }
}
