<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;

final class BooleanFilter extends AbstractFilter implements FilterInterface
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

    public function apply(SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void
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
        return sprintf('%s = %s', $property, in_array($value, ['true', true], true) ? 1 : 0);
    }
}
