<?php

namespace EventSauce\DoctrineMessageRepository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\PointInTime;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use EventSauce\EventSourcing\Time\Clock;
use Ramsey\Uuid\Uuid;
use function array_key_exists;
use function array_map;
use function array_values;
use function json_decode;

class DoctrineOutboxMessageDispatcher implements OutboxMessageDispatcher
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s.u';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Clock
     */
    private $clock;

    /**
     * @var MessageSerializer
     */
    protected $serializer;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var int
     */
    private $jsonEncodeOptions;

    public function __construct(Connection $connection, Clock $clock, MessageSerializer $serializer, string $tableName, int $jsonEncodeOptions = 0)
    {
        $this->connection = $connection;
        $this->clock = $clock;
        $this->serializer = $serializer;
        $this->tableName = $tableName;
        $this->jsonEncodeOptions = $jsonEncodeOptions;
    }

    public function dispatch(Message ... $messages)
    {
        if (count($messages) === 0) {
            return;
        }

        $sql = "INSERT INTO {$this->tableName} (id, time_of_recording, dispatched, payload) VALUES ";
        $params = ['dispatched' => 0];
        $values = [];

        foreach ($messages as $index => $message) {
            $payload = $this->serializer->serializeMessage($message);
            $eventIdColumn = 'id_' . $index;
            $timeOfRecordingColumn = 'time_of_recording_' . $index;
            $payloadColumn = 'payload_' . $index;
            $values[] = "(:{$eventIdColumn}, :{$timeOfRecordingColumn}, :dispatched, :{$payloadColumn})";
            $params[$timeOfRecordingColumn] = $payload['headers'][Header::TIME_OF_RECORDING]
                ?? $this->clock->dateTime()->format(self::DATETIME_FORMAT);
            $params[$eventIdColumn] = $payload['headers'][Header::EVENT_ID] = $payload['headers'][Header::EVENT_ID]
                ?? Uuid::uuid4()->toString();
            $params[$payloadColumn] = json_encode($payload, $this->jsonEncodeOptions);
        }

        $sql .= join(', ', $values);
        $this->connection->prepare($sql)->execute($params);
    }

    /**
     * @param int $limit
     * @return MessagesInOutbox[]
     */
    public function retrieveNotDispatchedMessages(int $limit): iterable
    {
        $result = $this->connection->createQueryBuilder()
            ->from($this->tableName, 't')
            ->select('t.id', 't.payload', 't.time_of_recording')
            ->where('t.dispatched = :dispatched')
            ->setParameter('dispatched', 0)
            ->orderBy('t.time_of_recording', 'ASC')
            ->setMaxResults($limit)
            ->execute();

        while ($row = $result->fetch(FetchMode::ASSOCIATIVE)) {
            yield new MessagesInOutbox(
                $row['id'],
                PointInTime::fromDateTime(DateTimeImmutable::createFromFormat(self::DATETIME_FORMAT, $row['time_of_recording'], new DateTimeZone('UTC'))),
                array_key_exists('time_of_dispatching', $row)
                    ? PointInTime::fromDateTime(DateTimeImmutable::createFromFormat(self::DATETIME_FORMAT, $row['time_of_dispatching'], new DateTimeZone('UTC')))
                    : null,
                $this->serializer->unserializePayload(json_decode($row['payload'], true)));
        }
    }

    public function markAsDispatched(MessagesInOutbox ...$messages): void
    {
        $ids = $this->getIdsFromOutboxMessages($messages);
        if (empty($ids)) {
            return;
        }
        $this->connection->createQueryBuilder()
            ->update($this->tableName, 't')
            ->set('t.time_of_dispatching', ':time_of_dispatching')
            ->setParameter('time_of_dispatching', $this->clock->pointInTime()->toString())
            ->set('t.dispatched', true)
            ->execute();
    }

    public function removeFromOutbox(MessagesInOutbox ...$messages): void
    {
        $ids = $this->getIdsFromOutboxMessages($messages);

        if (empty($ids)) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->delete($this->tableName)
            ->where('id IN (:ids)')
            ->setParameter('ids', array_values($ids), Connection::PARAM_STR_ARRAY)
            ->execute();
    }

    public function deletedAllMessagesFromOutbox(): void
    {
        $this->connection->createQueryBuilder()
            ->delete($this->tableName)
            ->execute();
    }

    /**
     * @param MessagesInOutbox[] $messages
     * @return array
     */
    private function getIdsFromOutboxMessages(array $messages): array
    {
        $ids = array_map(function (MessagesInOutbox $messageInOutbox) {
            return $messageInOutbox->id();
        }, $messages);

        return $ids;
    }
}
