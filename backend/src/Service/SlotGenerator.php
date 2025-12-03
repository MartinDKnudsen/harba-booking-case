<?php

namespace App\Service;

use App\Entity\Provider;
use Exception;

class SlotGenerator
{
    /**
     * @throws Exception
     */
    public function generate(Provider $provider, \DateTimeImmutable $from, \DateTimeImmutable $to, array $bookedSlots): array
    {
        $workingHours = $provider->getWorkingHours();
        $booked = [];
        foreach ($bookedSlots as $dt) {
            $booked[(new \DateTimeImmutable($dt))->format('Y-m-d H:i')] = true;
        }

        $slots = [];
        $now = new \DateTimeImmutable();

        $current = $from;
        while ($current < $to) {
            $dayKey = strtolower($current->format('D'));
            if (isset($workingHours[$dayKey]['start'], $workingHours[$dayKey]['end'])) {
                $startTime = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $current->format('Y-m-d') . ' ' . $workingHours[$dayKey]['start']
                );
                $endTime = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $current->format('Y-m-d') . ' ' . $workingHours[$dayKey]['end']
                );

                if ($startTime && $endTime && $startTime < $endTime) {
                    $slot = $startTime;
                    while ($slot < $endTime) {
                        if ($slot >= $now) {
                            $key = $slot->format('Y-m-d H:i');
                            if (!isset($booked[$key])) {
                                $slots[] = $slot->format(\DateTimeInterface::ATOM);
                            }
                        }
                        $slot = $slot->modify('+30 minutes');
                    }
                }
            }

            $current = $current->modify('+1 day')->setTime(0, 0);
        }

        return $slots;
    }
}
