<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Provider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findActiveForProviderBetween(
        Provider $provider,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('b')
            ->andWhere('b.provider = :provider')
            ->andWhere('b.startAt >= :from')
            ->andWhere('b.startAt < :to')
            ->andWhere('b.cancelledAt IS NULL')
            ->setParameter('provider', $provider)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
