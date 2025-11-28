<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use App\Service\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $repo,
        EntityManagerInterface $em,
        ProfileService $profileService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Vérification des champs requis
        $required = ['email', 'password', 'displayName', 'birthDate', 'gender', 'city'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(["error" => "Missing field: $field"], 400);
            }
        }

        // Doublon email
        if ($repo->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['error' => 'Email already used'], 400);
        }

        try {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setDisplayName($data['displayName']);
            $user->setBirthDate(new \DateTime($data['birthDate']));
            $user->setGender($data['gender']);
            $user->setCity($data['city']);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            // Hash du mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            // Sauvegarde du User
            $em->persist($user);
            $em->flush();

            // 🔥 Création automatique du profil
            $profileService->createProfileForUser($user);

            return new JsonResponse(['message' => 'Account created'], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid data: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $repo,
        UserPasswordHasherInterface $hasher,
        JwtService $jwt
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Missing email or password'], 400);
        }

        $user = $repo->findOneBy(['email' => $data['email']]);

        if (!$user || !$hasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // JWT Token
        $token = $jwt->generate([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'exp' => time() + 3600
        ]);

        return new JsonResponse([
            'token' => $token
        ], 200);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return new JsonResponse([
            'id'          => $user->getId(),
            'email'       => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'city'        => $user->getCity(),
            'gender'      => $user->getGender(),
        ]);
    }
}
