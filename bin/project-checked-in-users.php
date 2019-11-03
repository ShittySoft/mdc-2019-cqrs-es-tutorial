#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Building\App;

use Building\Domain\Aggregate\Building;
use Building\Domain\DomainEvent\UserCheckedIn;
use Building\Domain\DomainEvent\UserCheckedOut;
use Interop\Container\ContainerInterface;
use Prooph\EventSourcing\AggregateChanged;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream\StreamName;

call_user_func(static function () {
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/../container.php';

    $eventStore = $container->get(EventStore::class);

    /** @var AggregateChanged[] $history */
    $history = $eventStore->loadEventsByMetadataFrom(
        new StreamName('event_stream'),
        ['aggregate_type' => Building::class]
    );

    $usersPerBuilding = [];

    foreach ($history as $event) {
        if (! array_key_exists($event->aggregateId(), $usersPerBuilding)) {
            $usersPerBuilding[$event->aggregateId()] = [];
        }

        if ($event instanceof UserCheckedIn) {
            $usersPerBuilding[$event->aggregateId()][$event->username()] = null;
        }

        if ($event instanceof UserCheckedOut) {
            unset($usersPerBuilding[$event->aggregateId()][$event->username()]);
        }
    }

    array_walk($usersPerBuilding, function (array $users, string $buildingId) {
        file_put_contents(
            __DIR__ . '/../public/building-' . $buildingId . '.json',
            json_encode(array_keys($users))
        );
    });
});
