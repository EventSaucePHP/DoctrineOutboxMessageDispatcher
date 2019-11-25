<?php

namespace EventSauce\DoctrineOutboxMessageDispatcher\Tests;

use Doctrine\DBAL\Connection;

class MysqlIntegrationTest extends DoctrineIntegrationTestCase
{
    protected function connection(): Connection
    {
        return require __DIR__ . '/mysql-connection.php';
    }
}
