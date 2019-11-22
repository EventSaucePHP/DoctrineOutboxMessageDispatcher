<?php

namespace EventSauce\DoctrineMessageRepository\Tests;

use Doctrine\DBAL\Connection;
use EventSauce\DoctrineMessageRepository\DoctrineOutboxMessageDispatcher;
use EventSauce\DoctrineMessageRepository\MessagesInOutbox;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Time\Clock;
use EventSauce\EventSourcing\Time\TestClock;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;

abstract class DoctrineIntegrationTestCase extends TestCase
{
    /**
     * @var DoctrineOutboxMessageDispatcher
     */
    private $dispatcher;

    /**
     * @var Clock
     */
    private $clock;

    /**
     * @var MessageDecorator
     */
    private $decorator;

    abstract protected function connection(): Connection;

    abstract protected function messageDispatcher(
        Connection $connection,
        Clock $clock,
        MessageSerializer $serializer,
        string $tableName
    ): DoctrineOutboxMessageDispatcher;

    protected function setUp()
    {
        parent::setUp();
        $this->decorator = new DefaultHeadersDecorator();
        $connection = $this->connection();
        $connection->exec('TRUNCATE TABLE messages_outbox');
        $serializer = new ConstructingMessageSerializer();
        $this->clock = new TestClock();
        $this->dispatcher = $this->messageDispatcher($connection, $this->clock, $serializer, 'messages_outbox');
    }

    public function createMessageFromEvent(object $event)
    {
        return $this->decorator->decorate(new Message($event));
    }

    /**
     * @test
     */
    public function dispatching_messages_results_in_messages_in_the_outbox()
    {
        $message = $this->createMessageFromEvent(new TestEvent('something'));
        $this->dispatcher->dispatch($message);

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));

        $this->assertCount(1, $messagesInOutbox);

        /** @var Message[] $actualMessages */
        $actualMessages = iterator_to_array($messagesInOutbox[0]->messages());

        $this->assertCount(1, $actualMessages);
        $this->assertEquals(new TestEvent('something'), $actualMessages[0]->event());
    }

    /**
     * @test
     */
    public function marking_outbox_messages_as_dispatched()
    {
        $message = $this->createMessageFromEvent(new TestEvent('something'));
        $this->dispatcher->dispatch($message);

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));

        $this->dispatcher->markAsDispatched(...$messagesInOutbox);

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesLeftInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));
        $this->assertEmpty($messagesLeftInOutbox);
    }



    /**
     * @test
     */
    public function deleting_outbox_messages_when_dispatched()
    {
        $message = $this->createMessageFromEvent(new TestEvent('something'));
        $this->dispatcher->dispatch($message);

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));

        $this->dispatcher->removeFromOutbox();
        $this->dispatcher->removeFromOutbox(...$messagesInOutbox);

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesLeftInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));
        $this->assertEmpty($messagesLeftInOutbox);
    }
}
