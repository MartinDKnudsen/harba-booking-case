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
use App\Dto\BookRequest;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;


class BookingController extends AbstractController
{
    /**
     * @param ServiceRepository $serviceRepository
     * @return JsonResponse
     */
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

    /**
     * @param ProviderRepository $providerRepository
     * @return JsonResponse
     */
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

    /**
     * @param int $id
     * @param Request $request
     * @param ProviderRepository $providerRepository
     * @param BookingRepository $bookingRepository
     * @param SlotGenerator $slotGenerator
     * @return JsonResponse
     */
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


    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/api/bookings', name: 'api_book', methods: ['POST'])]
    public function book(
        Request $request,
        UserRepository $userRepository,
        ProviderRepository $providerRepository,
        ServiceRepository $serviceRepository,
        BookingRepository $bookingRepository,
        SlotGenerator $slotGenerator,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $userRepository);
        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $input = new BookRequest();
        $input->providerId = isset($data['provider_id']) ? (int) $data['provider_id'] : null;
        $input->serviceId = isset($data['service_id']) ? (int) $data['service_id'] : null;
        $input->startAt = $data['start_at'] ?? null;

        $errors = $validator->validate($input);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $provider = $providerRepository->find($input->providerId);
        if (!$provider) {
            return new JsonResponse(['message' => 'Provider not found'], 404);
        }

        $service = $serviceRepository->find($input->serviceId);
        if (!$service) {
            return new JsonResponse(['message' => 'Service not found'], 404);
        }

        try {
            $startAt = new \DateTimeImmutable($input->startAt);
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
            'deletedAt' => null,
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
        $booking->setNote($data['note'] ?? null);

        $em->persist($booking);
        $em->flush();

        return new JsonResponse([
            'id' => $booking->getId(),
            'userId' => $user->getId(),
            'providerId' => $provider->getId(),
            'serviceId' => $service->getId(),
            'startAt' => $booking->getStartAt()->format(\DateTimeInterface::ATOM),
            'cancelled' => $booking->isCancelled(),
            'deleted' => $booking->isDeleted(),
            'note' => $booking->getNote(),
        ], 201);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BookingRepository $bookingRepository
     * @return JsonResponse
     */
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
                'deleted' => $booking->isDeleted(),
                'note' => $booking->getNote(),
            ];
        }, $bookings));
    }

    /**
     * @param int $id
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BookingRepository $bookingRepository
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
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

        if ($booking->isDeleted()) {
            return new JsonResponse(['message' => 'Booking is deleted'], 400);
        }

        if ($booking->isCancelled()) {
            return new JsonResponse(['message' => 'Already cancelled'], 400);
        }

        $booking->cancel();
        $em->flush();

        return new JsonResponse(['message' => 'Cancelled']);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BookingRepository $bookingRepository
     * @return JsonResponse
     */
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
                'userId' => $booking->getUser()?->getId(),
                'userEmail' => $booking->getUser()?->getEmail(),
                'providerId' => $booking->getProvider()?->getId(),
                'serviceId' => $booking->getService()?->getId(),
                'startAt' => $booking->getStartAt()->format(\DateTimeInterface::ATOM),
                'cancelled' => $booking->isCancelled(),
                'deleted' => $booking->isDeleted(),
                'note' => $booking->getNote(),
            ];
        }, $bookings));

    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     * @return object|null
     */
    private function getUserFromToken(Request $request, UserRepository $userRepository): ?object
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return $userRepository->findOneBy(['apiToken' => $token]);
    }

    /**
     * @param int $id
     * @param Request $request
     * @param UserRepository $userRepository
     * @param BookingRepository $bookingRepository
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/admin/bookings/{id}/delete', name: 'api_admin_booking_delete', methods: ['POST'])]
    public function adminDelete(
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

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return new JsonResponse(['message' => 'Booking not found'], 404);
        }

        if ($booking->isDeleted()) {
            return new JsonResponse(['message' => 'Already deleted'], 400);
        }

        $booking->softDelete();
        $em->flush();

        return new JsonResponse(['message' => 'Deleted']);
    }

    /**
     * @param ConstraintViolationListInterface $errors
     * @return JsonResponse
     */
    private function validationError(ConstraintViolationListInterface $errors): JsonResponse
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[$error->getPropertyPath()] = $error->getMessage();
        }

        return new JsonResponse(['errors' => $messages], 422);
    }
}
