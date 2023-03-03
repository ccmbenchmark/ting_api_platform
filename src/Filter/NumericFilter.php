<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;

class NumericFilter extends AbstractFilter implements FilterInterface
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

            $description[$property . '[]'] = $description[$property];
        }

        return $description;
    }

    /**
     * @param mixed $value
     */
    public function addClause(string $property, $value): string
    {
        return $this->andWhere($property, $value);
    }

    /**
     * @param mixed $value
     */
    public function andWhere(string $property, $value): string
    {
        if (is_array($value)) {
            return sprintf('%s in (%s)', $property, implode(',', $value));
        }

        return sprintf('%s = ' . $value, $property);
    }
}
