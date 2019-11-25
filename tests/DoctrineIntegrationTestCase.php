<?php

namespace EventSauce\DoctrineOutboxMessageDispatcher\Tests;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use EventSauce\DoctrineOutboxMessageDispatcher\DoctrineOutboxMessageDispatcher;
use EventSauce\DoctrineOutboxMessageDispatcher\MessagesInOutbox;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\PointInTime;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Time\Clock;
use EventSauce\EventSourcing\Time\TestClock;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;
use function var_dump;

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

    protected function messageDispatcher(
        Connection $connection,
        Clock $clock,
        MessageSerializer $serializer,
        string $tableName
    ): DoctrineOutboxMessageDispatcher {
        return new DoctrineOutboxMessageDispatcher($connection, $clock, $serializer, $tableName);
    }

    protected function setUp()
    {
        parent::setUp();
        $this->clock = new TestClock();
        $this->decorator = new DefaultHeadersDecorator(null, $this->clock);
        $connection = $this->connection();
        $connection->exec('TRUNCATE TABLE messages_outbox');
        $serializer = new ConstructingMessageSerializer();
        $this->dispatcher = $this->messageDispatcher($connection, $this->clock, $serializer, 'messages_outbox');
    }

    public function createMessageFromEvent(object $event)
    {
        return $this->decorator->decorate(new Message($event));
    }

    /**
     * @test
     */
    public function dispaching_zero_messages()
    {
        $this->dispatcher->dispatch();
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
        $messageInOutbox = $messagesInOutbox[0];

        $this->assertInstanceOf(PointInTime::class, $messageInOutbox->timeOfRecording());
        $this->assertEquals($this->clock->pointInTime()->toString(), $messageInOutbox->timeOfRecording()->toString());
        $this->assertNull($messageInOutbox->timeOfDispatching());
        /** @var MessagesInOutbox $actualMessages */
        $actualMessages = iterator_to_array($messageInOutbox->messages());

        $this->assertCount(1, $actualMessages);
        $this->assertEquals(new TestEvent('something'), $actualMessages[0]->event());
    }

    /**
     * @test
     */
    public function marking_outbox_messages_as_dispatched()
    {
        // marking zero as dispatched
        $this->dispatcher->markAsDispatched();
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

    /**
     * @test
     */
    public function deleting_all_outbox_messages()
    {
        $message = $this->createMessageFromEvent(new TestEvent('something'));
        $this->dispatcher->dispatch($message);
        $message = $this->createMessageFromEvent(new TestEvent('something'));
        $this->dispatcher->dispatch($message);

        $this->dispatcher->deletedAllMessagesFromOutbox();

        /** @var MessagesInOutbox[] $messagesInOutbox */
        $messagesLeftInOutbox = iterator_to_array($this->dispatcher->retrieveNotDispatchedMessages(100));
        $this->assertEmpty($messagesLeftInOutbox);
    }
}
