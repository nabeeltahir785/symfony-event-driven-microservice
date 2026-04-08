<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventPublisherInterface;
use Psr\Log\LoggerInterface;

class KafkaProducerService implements EventPublisherInterface
{
    private ?\RdKafka\Producer $producer = null;

    public function __construct(
        private readonly string $brokers,
        private readonly string $topic,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(string $key, array $payload): void
    {
        try {
            $producer = $this->getProducer();
            $topic = $producer->newTopic($this->topic);

            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

            $topic->produce(
                RD_KAFKA_PARTITION_UA,
                0,
                $encodedPayload,
                $key
            );

            $producer->poll(0);

            $result = $producer->flush(5000);

            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->logger->error('Kafka flush failed', [
                    'topic' => $this->topic,
                    'key' => $key,
                    'error_code' => $result,
                ]);
                return;
            }

            $this->logger->info('Kafka event published', [
                'topic' => $this->topic,
                'key' => $key,
                'eventType' => $payload['eventType'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish Kafka event', [
                'topic' => $this->topic,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getProducer(): \RdKafka\Producer
    {
        if ($this->producer === null) {
            $conf = new \RdKafka\Conf();
            $conf->set('metadata.broker.list', $this->brokers);
            $conf->set('socket.timeout.ms', '5000');
            $conf->set('queue.buffering.max.messages', '100000');
            $conf->set('queue.buffering.max.ms', '500');

            $conf->setErrorCb(function (\RdKafka\Producer $kafka, int $err, string $reason): void {
                $this->logger->error('Kafka producer error', [
                    'error_code' => $err,
                    'reason' => $reason,
                ]);
            });

            $conf->setDrMsgCb(function (\RdKafka\Producer $kafka, \RdKafka\Message $message): void {
                if ($message->err) {
                    $this->logger->error('Kafka delivery failed', [
                        'error' => rd_kafka_err2str($message->err),
                    ]);
                }
            });

            $this->producer = new \RdKafka\Producer($conf);
        }

        return $this->producer;
    }
}
