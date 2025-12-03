<?php

namespace App\Tests\Service;

use App\Entity\Provider;
use App\Service\SlotGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SlotGeneratorTest extends KernelTestCase
{
    public function testGeneratesSlotsRespectsWorkingHoursAndBookedSlots(): void
    {
        self::bootKernel();
        $generator = static::getContainer()->get(SlotGenerator::class);

        $provider = new Provider();
        $provider->setName('Test Provider');
        $provider->setWorkingHours([
            'mon' => ['start' => '09:00', 'end' => '10:00'],
            'tue' => null,
            'wed' => null,
            'thu' => null,
            'fri' => null,
            'sat' => null,
            'sun' => null,
        ]);

        $from = new \DateTimeImmutable('2024-01-01 00:00:00');
        $to = $from->modify('+1 day');

        $bookedSlot = $from->setTime(9, 30)->format(\DateTimeInterface::ATOM);
        $bookedSlots = [$bookedSlot];

        $slots = $generator->generate($provider, $from, $to, $bookedSlots);

        $this->assertIsArray($slots);
        $this->assertNotContains($bookedSlot, $slots);
    }
}
