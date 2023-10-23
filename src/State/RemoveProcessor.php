<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;

use function is_object;

/**
 * @template T of object
 * @implements ProcessorInterface<void>
 */
final class RemoveProcessor implements ProcessorInterface
{
    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    /**
     * @param Operation<T>         $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (! is_object($data)) {
            return;
        }

        $manager = $this->managerRegistry->getManagerForClass($data::class);
        if ($manager === null) {
            return;
        }

        $manager->getRepository()->delete($data);
    }
}
