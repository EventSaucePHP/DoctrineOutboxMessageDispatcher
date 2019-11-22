<?php
declare(strict_types=1);

namespace EventSauce\DoctrineMessageRepository;

use EventSauce\EventSourcing\Message;

class MessagesInOutbox
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var Message[]
     */
    private $messages;

    public function __construct(string $id, iterable $messages)
    {
        $this->id = $id;
        $this->messages = $messages;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function messages(): iterable
    {
        return $this->messages;
    }
}
