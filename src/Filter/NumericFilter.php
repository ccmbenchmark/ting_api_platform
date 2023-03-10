<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryInterface;

final class NumericFilter extends AbstractFilter implements FilterInterface
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

    public function apply(QueryInterface&SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->getPropertiesForFilter(
            $resourceClass,
            $context,
            $this->getDescription($resourceClass),
            function($property, $value) use ($queryBuilder) {
                $clause = $this->addClause($property['columnName'], $value);
                $queryBuilder->where($clause);
            }
        );
    }

    /**
     * @param mixed $value
     */
    private function addClause(string $property, $value): string
    {
        if (is_array($value)) {
            return sprintf('%s in (%s)', $property, implode(',', $value));
        }

        return sprintf('%s = ' . $value, $property);
    }
}
