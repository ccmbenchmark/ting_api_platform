<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Core\Exception\InvalidArgumentException;

class SearchFilter extends AbstractFilter implements SearchFilterInterface, FilterInterface
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

    public function addClause(string $property, $value, string $resourceClass): string
    {
        $where = '';
        if (array_key_exists($property, $this->properties)) {
            $strategy = $this->properties[$property];
            if ($strategy === null) {
                $strategy = self::STRATEGY_EXACT;
            }

            $where = $this->andWhereByStrategy($property, $value, $strategy);
        }

        return $where;
    }

    /**
     * @param string $property
     * @param mixed  $value
     * @param string $strategy
     * @return string
     */
    public function andWhereByStrategy(string $property, $value, string $strategy = self::STRATEGY_EXACT): string
    {
        $operator = 'like';
        $caseSensitive = true;

        if (0 === strpos($strategy, 'i')) {
            $strategy = substr($strategy, 1);
            $operator = 'ilike';
            $caseSensitive = false;
        }

        $where = '';
        switch ($strategy) {
            case self::STRATEGY_EXACT:
                if (!$caseSensitive) {
                    $property = sprintf('lower(%s)', $property);
                    $value    = sprintf('lower(%s)', $value);
                }

                if (is_array($value)) {
                    $where = sprintf('%s in (%s)', $property, '"' . implode('","', $value) . '"');
                } else {
                    $where = sprintf('%s = "'.$value.'"', $property);
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
