<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalyticsEvent;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsEventRepository extends AbstractDoctrineRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function findByAggregateId(string $aggregateId): array
    {
        return $this->findBy(
            ['aggregateId' => $aggregateId],
            ['processedAt' => 'ASC']
        );
    }

    public function findByEventType(string $eventType, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.eventType = :type')
            ->setParameter('type', $eventType)
            ->orderBy('e.processedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
