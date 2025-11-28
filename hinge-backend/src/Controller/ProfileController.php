<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/profile')]
class ProfileController extends AbstractController
{
    #[Route('/me', name: 'api_profile_me', methods: ['GET'])]
    public function me(ProfileRepository $profileRepo): JsonResponse
    {
        $user = $this->getUser();
        $profile = $profileRepo->findOneBy(['user' => $user]);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found'], 404);
        }

        return $this->json([
            'id' => $profile->getId(),
            'bio' => $profile->getBio(),
            'interests' => $profile->getInterests(),
            'intentions' => $profile->getIntentions(),
            'prompts' => $profile->getPrompts(),
            'city' => $profile->getCity(),
            'latitude' => $profile->getLatitude(),
            'longitude' => $profile->getLongitude(),
            'photos' => array_map(function ($photo) {
                return [
                    'id' => $photo->getId(),
                    'path' => $photo->getPath(),
                    'position' => $photo->getPosition(),
                    'isPrimary' => $photo->isPrimary(),
                ];
            }, $profile->getPhotos()->toArray())
        ]);
    }

    #[Route('', name: 'api_profile_update', methods: ['PATCH'])]
    public function update(Request $request, ProfileRepository $profileRepo, ProfileService $service): JsonResponse
    {
        $user = $this->getUser();
        $profile = $profileRepo->findOneBy(['user' => $user]);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $service->updateProfile($profile, $data);

        return $this->json(['message' => 'Profile updated']);
    }

    #[Route('/location', name: 'api_profile_update_location', methods: ['PATCH'])]
    public function updateLocation(Request $request, ProfileRepository $profileRepo, ProfileService $service): JsonResponse
    {
        $user = $this->getUser();
        $profile = $profileRepo->findOneBy(['user' => $user]);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['latitude']) || !isset($data['longitude'])) {
            return $this->json(['error' => 'Missing latitude or longitude'], 400);
        }

        $service->updateLocation($profile, $data['latitude'], $data['longitude']);

        return $this->json(['message' => 'Location updated']);
    }
}
