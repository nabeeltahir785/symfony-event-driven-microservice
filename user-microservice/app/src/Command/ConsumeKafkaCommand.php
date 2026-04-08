<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AnalyticsEvent;
use App\Repository\AnalyticsEventRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:consume-kafka',
    description: 'Consume user events from Kafka and store them in the analytics event store',
)]
class ConsumeKafkaCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly string $kafkaBrokers,
        private readonly string $kafkaTopic,
        private readonly string $kafkaConsumerGroup,
        private readonly AnalyticsEventRepository $analyticsEventRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_OPTIONAL,
            'Consumer poll timeout in milliseconds',
            10000
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (int) $input->getOption('timeout');

        $this->registerSignalHandlers($io);

        $io->title('Kafka Analytics Consumer');
        $io->info(sprintf(
            'Connecting to %s | Topic: %s | Group: %s',
            $this->kafkaBrokers,
            $this->kafkaTopic,
            $this->kafkaConsumerGroup,
        ));

        try {
            $consumer = $this->createConsumer();
            $consumer->subscribe([$this->kafkaTopic]);
        } catch (\Exception $e) {
            $io->error('Failed to initialize Kafka consumer: ' . $e->getMessage());
            $this->logger->error('Kafka consumer initialization failed', [
                'error' => $e->getMessage(),
                'brokers' => $this->kafkaBrokers,
            ]);
            return Command::FAILURE;
        }

        $io->success('Consumer started. Waiting for events...');
        $this->logger->info('Kafka consumer started', [
            'topic' => $this->kafkaTopic,
            'group' => $this->kafkaConsumerGroup,
        ]);

        $eventsProcessed = 0;

        while (!$this->shouldStop) {
            pcntl_signal_dispatch();

            $message = $consumer->consume($timeout);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->processMessage($message, $io);
                    $eventsProcessed++;

                    if ($eventsProcessed % 10 === 0) {
                        $io->note(sprintf('Events processed: %d', $eventsProcessed));
                    }
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    $this->logger->error('Kafka consumer error', [
                        'error' => $message->errstr(),
                        'code' => $message->err,
                    ]);
                    $io->warning('Kafka error: ' . $message->errstr());
                    break;
            }
        }

        $this->logger->info('Kafka consumer shutting down gracefully', [
            'eventsProcessed' => $eventsProcessed,
        ]);
        $io->info(sprintf('Graceful shutdown complete. %d events processed.', $eventsProcessed));

        return Command::SUCCESS;
    }

    private function registerSignalHandlers(SymfonyStyle $io): void
    {
        if (!extension_loaded('pcntl')) {
            $io->warning('pcntl extension not loaded — graceful shutdown unavailable.');
            return;
        }

        $handler = function (int $signal) use ($io): void {
            $signalName = match ($signal) {
                SIGTERM => 'SIGTERM',
                SIGINT => 'SIGINT',
                default => "signal($signal)",
            };

            $io->note(sprintf('Received %s — initiating graceful shutdown...', $signalName));
            $this->logger->info('Shutdown signal received', ['signal' => $signalName]);
            $this->shouldStop = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    private function createConsumer(): \RdKafka\KafkaConsumer
    {
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->kafkaBrokers);
        $conf->set('group.id', $this->kafkaConsumerGroup);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.commit.interval.ms', '1000');
        $conf->set('session.timeout.ms', '30000');
        $conf->set('heartbeat.interval.ms', '10000');

        $conf->setErrorCb(function (\RdKafka\KafkaConsumer $kafka, int $err, string $reason): void {
            $this->logger->error('Kafka consumer error callback', [
                'error_code' => $err,
                'reason' => $reason,
            ]);
        });

        $conf->setRebalanceCb(function (
            \RdKafka\KafkaConsumer $kafka,
            int $err,
            ?array $partitions = null,
        ): void {
            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $this->logger->info('Kafka partitions assigned', [
                        'count' => count($partitions ?? []),
                    ]);
                    $kafka->assign($partitions);
                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $this->logger->info('Kafka partitions revoked');
                    $kafka->assign(null);
                    break;

                default:
                    $this->logger->error('Kafka rebalance error', ['error' => $err]);
                    break;
            }
        });

        return new \RdKafka\KafkaConsumer($conf);
    }

    private function processMessage(\RdKafka\Message $message, SymfonyStyle $io): void
    {
        $payload = json_decode($message->payload, true);

        if ($payload === null) {
            $this->logger->warning('Received invalid JSON from Kafka', [
                'offset' => $message->offset,
                'partition' => $message->partition,
            ]);
            return;
        }

        $event = new AnalyticsEvent();
        $event->setEventType($payload['eventType'] ?? 'unknown');
        $event->setAggregateId($payload['userId'] ?? '');
        $event->setPayload($payload);
        $event->setOccurredAt(
            isset($payload['occurredAt'])
                ? new \DateTimeImmutable($payload['occurredAt'])
                : new \DateTimeImmutable()
        );

        $this->analyticsEventRepository->save($event);

        $this->logger->info('Analytics event persisted', [
            'eventType' => $event->getEventType(),
            'aggregateId' => $event->getAggregateId(),
            'offset' => $message->offset,
            'partition' => $message->partition,
        ]);

        $io->writeln(sprintf(
            '  <info>✓</info> [%s] %s → %s',
            (new \DateTimeImmutable())->format('H:i:s'),
            $event->getEventType(),
            $event->getAggregateId(),
        ));
    }
}
