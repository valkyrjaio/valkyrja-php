<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Event\Collection;

use Closure;
use Override;
use Valkyrja\Event\Collection\Contract\ListenerCollectionContract;
use Valkyrja\Event\Data\Contract\ListenerContract;
use Valkyrja\Event\Data\EventData;

use function array_keys;
use function array_map;

class ListenerCollection implements ListenerCollectionContract
{
    /**
     * The events.
     *
     * @var array<class-string, array<string, string>>
     */
    protected array $events = [];

    /**
     * The listeners.
     *
     * @var array<string, Closure():ListenerContract>
     */
    protected array $listeners = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function getData(): EventData
    {
        return new EventData(
            events: $this->events,
            listeners: $this->listeners,
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setFromData(EventData $data): void
    {
        $this->events    = $data->events;
        $this->listeners = $data->listeners;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasListener(ListenerContract $listener): bool
    {
        return $this->hasListenerById($listener->getName());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasListenerById(string $listenerId): bool
    {
        return isset($this->listeners[$listenerId]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addListener(ListenerContract $listener): void
    {
        $listenerId = $listener->getName();
        $eventId    = $listener->getEventId();

        $this->events[$eventId] ??= [];
        $this->events[$eventId][$listenerId] = $listenerId;
        $this->listeners[$listenerId]        = static fn (): ListenerContract => $listener;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeListener(ListenerContract $listener): void
    {
        $listenerId = $listener->getName();
        $eventId    = $listener->getEventId();

        unset($this->events[$eventId][$listenerId], $this->listeners[$listenerId]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeListenerById(string $listenerId): void
    {
        foreach ($this->events as $eventId => $listeners) {
            unset($this->events[$eventId][$listenerId]);
        }

        unset($this->listeners[$listenerId]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasListenersForEvent(object $event): bool
    {
        return isset($this->events[$event::class])
            && $this->events[$event::class] !== [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasListenersForEventById(string $eventId): bool
    {
        return isset($this->events[$eventId])
            && $this->events[$eventId] !== [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getListenersForEvent(object $event): array
    {
        return $this->getListenersForEventById($event::class);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getListenersForEventById(string $eventId): array
    {
        $listenerIds = $this->events[$eventId] ?? null;
        $listeners   = [];

        if ($listenerIds === null) {
            return [];
        }

        foreach ($listenerIds as $listenerId) {
            $listener               = $this->listeners[$listenerId];
            $listeners[$listenerId] = $this->ensureListener($listener);
        }

        return $listeners;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setListenersForEvent(object $event, ListenerContract ...$listeners): void
    {
        $this->setListenersForEventById($event::class, ...$listeners);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setListenersForEventById(string $eventId, ListenerContract ...$listeners): void
    {
        foreach ($listeners as $listener) {
            $this->addListener(
                $listener->withEventId($eventId)
            );
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeListenersForEvent(object $event): void
    {
        $this->removeListenersForEventById($event::class);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeListenersForEventById(string $eventId): void
    {
        $listeners = $this->getListenersForEventById($eventId);

        foreach ($listeners as $listener) {
            $this->removeListener($listener);
        }

        unset($this->events[$eventId]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getListeners(): array
    {
        return array_map(
            fn (Closure $listener): ListenerContract => $this->ensureListener($listener),
            $this->listeners
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEvents(): array
    {
        return array_keys($this->events);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventsWithListeners(): array
    {
        $eventsWithListeners = [];
        $events              = $this->events;

        foreach ($events as $eventId => $listenerIds) {
            $eventsWithListeners[$eventId] = $this->getListenersForEventById($eventId);
        }

        return $eventsWithListeners;
    }

    /**
     * Ensure a listener, or null, is returned.
     *
     * @param Closure():ListenerContract $listener The listener
     */
    protected function ensureListener(Closure $listener): ListenerContract
    {
        return $listener();
    }
}
