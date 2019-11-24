<?php
declare(strict_types=1);

namespace EventSauce\DoctrineMessageRepository;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\PointInTime;

class MessagesInOutbox
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var PointInTime
     */
    private $timeOfRecording;

    /**
     * @var PointInTime|null
     */
    private $timeOfDispatching;

    /**
     * @var Message[]
     */
    private $messages;

    public function __construct(
        string $id,
        PointInTime $timeOfRecording,
        ?PointInTime $timeOfDispatching,
        iterable $messages
    ) {
        $this->id = $id;
        $this->timeOfRecording = $timeOfRecording;
        $this->timeOfDispatching = $timeOfDispatching;
        $this->messages = $messages;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function timeOfRecording(): PointInTime
    {
        return $this->timeOfRecording;
    }

    public function timeOfDispatching(): ?PointInTime
    {
        return $this->timeOfDispatching;
    }

    public function messages(): iterable
    {
        return $this->messages;
    }
}
