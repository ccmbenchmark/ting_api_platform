<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\Repository\Repository;

final class SearchFilter extends AbstractFilter implements SearchFilterInterface, FilterInterface
{
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $strategy)
        {
            if ($strategy === null) {
                $strategy = self::STRATEGY_EXACT;
            }

            $description[$property] = [
                'property' => $property,
                'strategy' => $strategy,
                'type' => $this->getTypeForProperty($property, $resourceClass),
                'required' => false
            ];

            if (self::STRATEGY_EXACT === $strategy) {
                $description[$property.'[]'] = $description[$property];
            }
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
                if ($clause !== '') {
                    $queryBuilder->where($clause);
                }
            }
        );
    }

    private function addClause(string $property, mixed $value): string
    {
        return $this->andWhereByStrategy($property, $value, $this->properties[$property] ?? self::STRATEGY_EXACT);
    }

    private function andWhereByStrategy(string $property, mixed $value, string $strategy = self::STRATEGY_EXACT): string
    {
        $operator = 'like';
        $caseSensitive = true;

        if (strpos($strategy, 'i') === 0) {
            $strategy = substr($strategy, 1);
            $operator = 'ilike';
            $caseSensitive = false;
        }

        if (is_array($value)) {
            //TODO : supports others strategies for array
            $strategy = self::STRATEGY_EXACT;
        }

        $where = '';
        switch ($strategy) {
            case self::STRATEGY_EXACT:
                if (!$caseSensitive) {
                    $property = sprintf('lower(%s)', $property);
                    $value = sprintf('lower(%s)', $value);
                }

                if (is_array($value)) {
                    $where = sprintf('%s in (%s)', $property, '"' . implode('","', $value) . '"');
                } else {
                    $where = $value === null ? sprintf('%s IS NULL', $property) : sprintf('%s = "' . $value . '"', $property);
                }
                break;
            case self::STRATEGY_PARTIAL:
                $where = sprintf('%s %s %s', $property, $operator, '"%' . $value . '%"');
                break;
            case self::STRATEGY_START:
                $where = sprintf('%s %s %s', $property, $operator, '"' . $value . '%"');
                break;
            case self::STRATEGY_END:
                $where = sprintf('%s %s %s', $property, $operator, '"%' . $value . '"');
                break;
            case self::STRATEGY_WORD_START:
                $where = sprintf('%s %s %s or %s %s %s', $property, $operator, '"' . $value . '%"', $property, $operator, '"% ' . $value . '%"');
                break;
            default:
                throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy));
        }

        return $where;
    }
}
