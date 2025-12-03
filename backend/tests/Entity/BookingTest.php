<?php

namespace App\Tests\Entity;

use App\Entity\Booking;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase
{
    public function testCancelAndSoftDeleteFlags(): void
    {
        $booking = new Booking();

        self::assertFalse($booking->isCancelled());
        self::assertFalse($booking->isDeleted());

        $booking->cancel();
        self::assertTrue($booking->isCancelled());
        $cancelledAt1 = $booking->getCancelledAt();

        $booking->softDelete();
        self::assertTrue($booking->isDeleted());
        $deletedAt1 = $booking->getDeletedAt();

        $booking->cancel();
        $booking->softDelete();

        self::assertSame($cancelledAt1, $booking->getCancelledAt());
        self::assertSame($deletedAt1, $booking->getDeletedAt());
    }
}
