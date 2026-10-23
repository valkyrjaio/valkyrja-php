<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Event\Collector;

use Override;
use ReflectionMethod;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Event\Attribute\Listener as Attribute;
use Valkyrja\Event\Attribute\Listener\Handler;
use Valkyrja\Event\Collector\Contract\ListenerCollectorContract;
use Valkyrja\Event\Data\Contract\ListenerContract;
use Valkyrja\Event\Data\Listener;

class AttributeListenerCollector implements ListenerCollectorContract
{
    public function __construct(
        protected CollectorContract $attributes,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getListeners(string ...$classes): array
    {
        $listeners = [];

        // Iterate through all the classes
        foreach ($classes as $class) {
            /** @var Attribute[] $attributes */
            $attributes = $this->attributes->forClassAndMembers($class, Attribute::class);

            // Get all the attributes for each class and iterate through them
            foreach ($attributes as $attribute) {
                $reflection = $attribute->getReflection();
                $method     = null;

                if ($reflection instanceof ReflectionMethod) {
                    $method   = $reflection->getName();
                }

                $listener = $this->getListenerFromAttribute($attribute);
                $listener = $this->updateHandler($listener, $class, $method);

                $listeners[] = $this->setListenerProperties($listener);
            }
        }

        return $listeners;
    }

    /**
     * Update the handler for a listener.
     *
     * @param class-string          $class  The class name
     * @param non-empty-string|null $method The method name
     */
    protected function updateHandler(ListenerContract $listener, string $class, string|null $method = null): ListenerContract
    {
        if ($method === null) {
            /** @var Handler[] $classHandlers */
            $classHandlers = $this->attributes->forClass($class, Handler::class);
            $classHandler  = $classHandlers[0] ?? null;

            $handler = $classHandler->handler ?? null;
        } else {
            /** @var Handler[] $methodHandlers */
            $methodHandlers = $this->attributes->forMethod($class, $method, Handler::class);
            $methodHandler  = $methodHandlers[0] ?? null;

            $handler = $methodHandler->handler ?? null;
        }

        if ($handler === null) {
            return $listener;
        }

        return $listener->withHandler($handler);
    }

    /**
     * Set the properties for a listener attribute.
     */
    protected function setListenerProperties(ListenerContract $listener): ListenerContract
    {
        return $listener;
    }

    /**
     * Get a listener from an attribute.
     */
    protected function getListenerFromAttribute(ListenerContract $attribute): ListenerContract
    {
        return new Listener(
            eventId: $attribute->getEventId(),
            name: $attribute->getName(),
            handler: $attribute->getHandler()
        );
    }
}
