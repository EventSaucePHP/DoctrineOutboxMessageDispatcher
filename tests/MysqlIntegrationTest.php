<?php

namespace EventSauce\DoctrineMessageRepository\Tests;

use Doctrine\DBAL\Connection;
use EventSauce\DoctrineMessageRepository\DoctrineOutboxMessageDispatcher;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Time\Clock;

class MysqlIntegrationTest extends DoctrineIntegrationTestCase
{
    protected function connection(): Connection
    {
        return require __DIR__ . '/mysql-connection.php';
    }

    protected function messageDispatcher(
        Connection $connection,
        Clock $clock,
        MessageSerializer $serializer,
        string $tableName
    ): DoctrineOutboxMessageDispatcher {
        return new DoctrineOutboxMessageDispatcher($connection, $clock, $serializer, $tableName);
    }
}
