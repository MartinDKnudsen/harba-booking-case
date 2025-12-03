<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ProviderRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\SlotGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractController
{
    #[Route('/api/services', name: 'api_services', methods: ['GET'])]
    public function listServices(ServiceRepository $serviceRepository): JsonResponse
    {
        $services = $serviceRepository->findAll();

        $data = [];
        foreach ($services as $service) {
            if (!$service instanceof Service) {
                continue;
            }

            $data[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'durationMinutes' => $service->getDurationMinutes(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/providers', name: 'api_providers', methods: ['GET'])]
    public function listProviders(ProviderRepository $providerRepository): JsonResponse
    {
        $providers = $providerRepository->findAll();

        $data = [];
        foreach ($providers as $provider) {
            if (!$provider instanceof Provider) {
                continue;
            }

            $data[] = [
                'id' => $provider->getId(),
                'name' => $provider->getName(),
                'workingHours' => $provider->getWorkingHours(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/providers/{id}/slots', name: 'api_provider_slots', methods: ['GET'])]
    public function providerSlots(
        int $id,
        Request $request,
        ProviderRepository $providerRepository,
        BookingRepository $bookingRepository,
        SlotGenerator $slotGenerator
    ): JsonResponse {
        $provider = $providerRepository->find($id);
        if (!$provider) {
            return new JsonResponse(['message' => 'Provider not found'], 404);
        }

        $fromParam = $request->query->get('from');
        $toParam = $request->query->get('to');

        if ($fromParam) {
            try {
                $from = new \DateTimeImmutable($fromParam . ' 00:00');
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Invalid from date'], 400);
            }
        } else {
            $from = new \DateTimeImmutable('today 00:00');
        }

        if ($toParam) {
            try {
                $to = new \DateTimeImmutable($toParam . ' 23:59');
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Invalid to date'], 400);
            }
        } else {
            $to = $from->modify('+30 days');
        }

        if ($to <= $from) {
            return new JsonResponse(['message' => 'to must be after from'], 400);
        }

        if ($from->diff($to)->days > 60) {
            return new JsonResponse(['message' => 'Date range too large (max 60 days)'], 400);
        }

        $bookings = $bookingRepository->findActiveForProviderBetween($provider, $from, $to);
        $bookedSlots = [];
        foreach ($bookings as $booking) {
            $bookedSlots[] = $booking->getStartAt()->format(\DateTimeInterface::ATOM);
        }

        $slots = $slotGenerator->generate($provider, $from, $to, $bookedSlots);

        return new JsonResponse([
            'providerId' => $provider->getId(),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'slots' => $slots,
        ]);
    }


    #[Route('/api/bookings', name: 'api_book', methods: ['POST'])]
    public function book(
        Request $request,
        UserRepository $userRepository,
        ProviderRepository $providerRepository,
        ServiceRepository $serviceRepository,
        BookingRepository $bookingRepository,
        SlotGenerator $slotGenerator,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $userRepository);
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (!isset($data['provider_id'], $data['service_id'], $data['start_at'])) {
            return new JsonResponse(['message' => 'provider_id, service_id and start_at are required'], 400);
        }

        $provider = $providerRepository->find($data['provider_id']);
        if (!$provider) {
            return new JsonResponse(['message' => 'Provider not found'], 404);
        }

        $service = $serviceRepository->find($data['service_id']);
        if (!$service) {
            return new JsonResponse(['message' => 'Service not found'], 404);
        }

        try {
            $startAt = new \DateTimeImmutable($data['start_at']);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Invalid start_at'], 400);
        }

        $from = new \DateTimeImmutable('today 00:00');
        $to = $from->modify('+30 days');

        if ($startAt < $from || $startAt >= $to) {
            return new JsonResponse(['message' => 'Start time must be within next 30 days'], 400);
        }

        $existing = $bookingRepository->findOneBy([
            'provider' => $provider,
            'startAt' => $startAt,
            'cancelledAt' => null,
        ]);

        if ($existing) {
            return new JsonResponse(['message' => 'Slot already booked'], 409);
        }

        $bookings = $bookingRepository->findActiveForProviderBetween($provider, $from, $to);
        $bookedSlots = [];
        foreach ($bookings as $booking) {
            $bookedSlots[] = $booking->getStartAt()->format(\DateTimeInterface::ATOM);
        }

        $availableSlots = $slotGenerator->generate($provider, $from, $to, $bookedSlots);
        if (!in_array($startAt->format(\DateTimeInterface::ATOM), $availableSlots, true)) {
            return new JsonResponse(['message' => 'Slot not available'], 400);
        }

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setProvider($provider);
        $booking->setService($service);
        $booking->setStartAt($startAt);

        $em->persist($booking);
        $em->flush();

        return new JsonResponse([
            'id' => $booking->getId(),
            'userId' => $user->getId(),
            'providerId' => $provider->getId(),
            'serviceId' => $service->getId(),
            'startAt' => $booking->getStartAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/api/my/bookings', name: 'api_my_bookings', methods: ['GET'])]
    public function myBookings(
        Request $request,
        UserRepository $userRepository,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $userRepository);
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $bookings = $bookingRepository->findBy(
            ['user' => $user],
            ['startAt' => 'ASC']
        );

        return new JsonResponse(array_map(function (Booking $booking) {
            return [
                'id' => $booking->getId(),
                'providerId' => $booking->getProvider()->getId(),
                'serviceId' => $booking->getService()->getId(),
                'startAt' => $booking->getStartAt()->format(\DateTimeInterface::ATOM),
                'cancelled' => $booking->isCancelled(),
            ];
        }, $bookings));
    }

    #[Route('/api/bookings/{id}/cancel', name: 'api_booking_cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        Request $request,
        UserRepository $userRepository,
        BookingRepository $bookingRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $userRepository);
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return new JsonResponse(['message' => 'Booking not found'], 404);
        }

        $isOwner = $booking->getUser()->getId() === $user->getId();
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        if ($booking->isCancelled()) {
            return new JsonResponse(['message' => 'Already cancelled'], 400);
        }

        $booking->cancel();
        $em->flush();

        return new JsonResponse(['message' => 'Cancelled']);
    }

    #[Route('/api/admin/bookings', name: 'api_admin_bookings', methods: ['GET'])]
    public function adminBookings(
        Request $request,
        UserRepository $userRepository,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $userRepository);
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $bookings = $bookingRepository->findBy([], ['startAt' => 'ASC']);

        return new JsonResponse(array_map(function (Booking $booking) {
            return [
                'id' => $booking->getId(),
                'userId' => $booking->getUser()->getId(),
                'providerId' => $booking->getProvider()->getId(),
                'serviceId' => $booking->getService()->getId(),
                'startAt' => $booking->getStartAt()->format(\DateTimeInterface::ATOM),
                'cancelled' => $booking->isCancelled(),
            ];
        }, $bookings));
    }

    private function getUserFromToken(Request $request, UserRepository $userRepository): ?User
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return $userRepository->findOneBy(['apiToken' => $token]);
    }
}
