<?php

namespace EventSauce\DoctrineOutboxMessageDispatcher\Tests;

use Doctrine\DBAL\Connection;

class PostgresIntegrationTest extends DoctrineIntegrationTestCase
{
    protected function connection(): Connection
    {
        return require __DIR__ . '/postgres-connection.php';
    }
}
