<?php

include __DIR__ . '/../vendor/autoload.php';

$connection = include __DIR__ . '/mysql-connection.php';
$connection->exec("DROP TABLE IF EXISTS messages_outbox");
$connection->exec("
CREATE TABLE IF NOT EXISTS messages_outbox (
    id VARCHAR(36) NOT NULL,
    payload JSON NOT NULL,
    time_of_recording DATETIME(6) NOT NULL,
    dispatched BOOLEAN NOT NULL,
    time_of_dispatching DATETIME(6) NULL,
    INDEX dispatched (dispatched),
    INDEX not_dispatched_ordered_by_time (dispatched, time_of_recording ASC)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB
");
