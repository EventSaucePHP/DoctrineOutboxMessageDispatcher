# Doctrine Message Dispatcher for EventSauce

[![Build Status](https://travis-ci.org/EventSaucePHP/DoctrineOutboxMessageDispatcher.svg?branch=master)](https://travis-ci.org/EventSaucePHP/DoctrineMessageRepository)

```bash
composer require eventsauce/doctrine-outbox-message-dispatcher
```

## Rationale

The outbox pattern is a way to ensure reliable dispatching of events. In this dispatcher events
are stored in a database table. A background process is then responsible to retrieving the stored
events and pushing them onto a regular queue for consumption.

You can use a tool like [debezium.io](https://debezium.io/) to dispatch your events to kafka,
or use your own queueing solution. 

Usage:

```php
<?php

use EventSauce\DoctrineOutboxMessageDispatcher\DoctrineOutboxMessageDispatcher;
use EventSauce\DoctrineOutboxMessageDispatcher\MessagesInOutbox;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\Time\SystemClock;

$dispatcher = new DoctrineOutboxMessageDispatcher(
    $connection, // \Doctrine\DBAL\Connection instance
    $clock = new SystemClock(),
    $serializer = new ConstructingMessageSerializer(),
    'your_table_name',
    $optionalFlagsForJsonEncode = 0
);

// This dispatcher can be used like any other:
$dispatcher->dispatch($message);

// >>>>>>>>>>>>>>>>>>> //
// For re-dispatching: // 
// >>>>>>>>>>>>>>>>>>> //

// Use your own type of dispatcher here:
$destinationDispatcher = new SynchronousMessageDispatcher();

// Retrieve not dispatched messages
/** @var MessagesInOutbox[] $messagesInOutbox */
$messagesInOutbox = $dispatcher->retrieveNotDispatchedMessages(100);

foreach ($messagesInOutbox as $messageInOutbox) {
    $destinationDispatcher->dispatch(...iterator_to_array($messageInOutbox->messages()));
    // Mark messages as dispatched
    $dispatcher->markAsDispatched($messagesInOutbox);
    
    // Mark messages as dispatched
    $dispatcher->removeFromOutbox($messagesInOutbox);
}
```
