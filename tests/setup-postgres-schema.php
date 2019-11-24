<?php

use Doctrine\DBAL\Connection;

include __DIR__ . '/../vendor/autoload.php';

/** @var Connection $connection */
$connection = include __DIR__ . '/postgres-connection.php';
$connection->exec("DROP TABLE IF EXISTS messages_outbox");
/**
 * id VARCHAR(36) NOT NULL,
payload JSON NOT NULL,
time_of_recording DATETIME(6) NOT NULL,
dispatched BOOLEAN NOT NULL,
time_of_dispatching DATETIME(6) NULL,
INDEX dispatched (dispatched),
INDEX not_dispatched_ordered_by_time (dispatched, time_of_recording ASC)
 */

$connection->exec("CREATE TABLE messages_outbox (
    id UUID NOT NULL,
    payload JSON NOT NULL,
    dispatched BOOLEAN NOT NULL,
    time_of_recording TIMESTAMP(6) NOT NULL,
    time_of_dispatching TIMESTAMP(6) NULL,
    PRIMARY KEY(id)
)");
$connection->exec("CREATE INDEX
    IF NOT EXISTS
    not_dispatched_ordered_by_time on messages_outbox (
        dispatched,
        time_of_recording ASC
    )");
