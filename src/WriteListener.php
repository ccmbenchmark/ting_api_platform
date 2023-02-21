<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Exception\ItemNotFoundException;
use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

final class WriteListener
{
    /**
     * @var RepositoryProvider
     */
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->isMethodSafe()) {
            return;
        }
        $resourceClass = $request->attributes->get('_api_resource_class');
        $repository = $this->repositoryProvider->getRepositoryFromResource($resourceClass);
        if (null === $repository) {
            return;
        }

        $entity = $event->getControllerResult();

        $action_handled = false;
        switch ($request->getMethod()) {
            case Request::METHOD_POST:
            case Request::METHOD_PUT:
                $repository->save($entity);
                $action_handled = true;
                break;
            case Request::METHOD_DELETE:
                $repository->delete($entity);
                $action_handled = true;
                break;
        }
        if ($action_handled) {
            $this->setResult($event, $entity);
        }
    }

    /**
     * setResult
     *
     * @param \CCMBenchmark\Ting\Entity\NotifyPropertyInterface|null $entity
     * @return WriteListener
     */
    private function setResult(ViewEvent $event, $entity): self
    {
        if (null === $entity) {
            throw new ItemNotFoundException();
        }

        $event->setControllerResult($entity);

        return $this;
    }
}

