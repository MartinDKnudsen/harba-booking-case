<?php

namespace App\Tests\Repository;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingRepositoryTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private BookingRepository $bookingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->bookingRepository = $this->em->getRepository(Booking::class);
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    public function testFindActiveForProviderBetweenRespectsCancelledAndDeleted(): void
    {
        $user = new User();
        $user->setEmail('repo-test-' . uniqid() . '@example.com');
        $user->setPassword('test');

        $provider = new Provider();
        $provider->setName('Repo Provider');
        $provider->setWorkingHours([
            'mon' => ['start' => '09:00', 'end' => '17:00'],
        ]);

        $service = new Service();
        $service->setName('Repo Service');
        $service->setDurationMinutes(30);

        $this->em->persist($user);
        $this->em->persist($provider);
        $this->em->persist($service);
        $this->em->flush();

        $from = new \DateTimeImmutable('today 00:00');
        $to = $from->modify('+2 days');

        $b1 = new Booking();
        $b1->setUser($user);
        $b1->setProvider($provider);
        $b1->setService($service);
        $b1->setStartAt($from->modify('+1 day 10:00'));

        $b2 = new Booking();
        $b2->setUser($user);
        $b2->setProvider($provider);
        $b2->setService($service);
        $b2->setStartAt($from->modify('+1 day 11:00'));
        $b2->cancel();

        $b3 = new Booking();
        $b3->setUser($user);
        $b3->setProvider($provider);
        $b3->setService($service);
        $b3->setStartAt($from->modify('+1 day 12:00'));
        $b3->softDelete();

        $this->em->persist($b1);
        $this->em->persist($b2);
        $this->em->persist($b3);
        $this->em->flush();

        $results = $this->bookingRepository->findActiveForProviderBetween($provider, $from, $to);

        $this->assertCount(1, $results);
        $this->assertSame($b1->getId(), $results[0]->getId());
    }
}
