<?php

namespace App\Controller;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\RateLimiter\Attribute\RateLimit;



class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $input = new RegisterRequest();
        $input->email = $data['email'] ?? null;
        $input->password = $data['password'] ?? null;

        $errors = $validator->validate($input);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $existing = $userRepository->findOneBy(['email' => mb_strtolower($input->email)]);
        if ($existing) {
            return new JsonResponse(['message' => 'Email is already in use'], 409);
        }

        $user = new User();
        $user->setEmail($input->email);
        $hashedPassword = $passwordHasher->hashPassword($user, $input->password);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_USER']);

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], 201);
    }



    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request, UserRepository $userRepository): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $token = substr($authHeader, 7);
        $user = $userRepository->findOneBy(['apiToken' => $token]);

        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    #[RateLimit('login')]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['message' => 'email and password are required'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);
        if (!$user) {
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
        }

        if (!$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $em->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'token' => $token,
        ]);
    }

    private function validationError(ConstraintViolationListInterface $errors): JsonResponse
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[$error->getPropertyPath()] = $error->getMessage();
        }

        return new JsonResponse(['errors' => $messages], 422);
    }

}
