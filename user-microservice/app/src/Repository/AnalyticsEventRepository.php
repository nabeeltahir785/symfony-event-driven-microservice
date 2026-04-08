<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalyticsEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsEvent>
 */
class AnalyticsEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function save(AnalyticsEvent $event, bool $flush = true): void
    {
        $this->getEntityManager()->persist($event);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return AnalyticsEvent[]
     */
    public function findByAggregateId(string $aggregateId): array
    {
        return $this->findBy(
            ['aggregateId' => $aggregateId],
            ['processedAt' => 'ASC']
        );
    }

    /**
     * @return AnalyticsEvent[]
     */
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
