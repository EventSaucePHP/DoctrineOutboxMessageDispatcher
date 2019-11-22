<?php
declare(strict_types=1);

namespace EventSauce\DoctrineMessageRepository;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\PointInTime;

interface OutboxMessageDispatcher extends MessageDispatcher
{
    /**
     * @param int $limit
     * @return MessagesInOutbox[]
     */
    public function retrieveNotDispatchedMessages(int $limit): iterable;

    public function markAsDispatched(MessagesInOutbox ... $messages): void;

    public function removeFromOutbox(MessagesInOutbox ... $messages): void;

    public function deletedAllMessagesFromOutbox(): void;
}
