<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Repository\ProfileRepository;
use App\Service\PhotoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/profile/photos')]
class PhotoController extends AbstractController
{
    #[Route('', name: 'api_photo_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        ProfileRepository $profileRepo,
        PhotoService $photoService
    ): JsonResponse {
        $user = $this->getUser();
        $profile = $profileRepo->findOneBy(['user' => $user]);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found'], 404);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'Missing upload file'], 400);
        }

        try {
            $photo = $photoService->addPhoto($profile, $file);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'message' => 'Photo uploaded',
            'photoId' => $photo->getId(),
            'path' => $photo->getPath(),
        ]);
    }

    #[Route('/{id}', name: 'api_photo_delete', methods: ['DELETE'])]
    public function delete(
        Photo $photo,
        PhotoService $photoService
    ): JsonResponse {
        $user = $this->getUser();

        // securité : empêcher de supprimer la photo d'un autre
        if ($photo->getProfile()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        try {
            $photoService->deletePhoto($photo);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['message' => 'Photo deleted']);
    }

    #[Route('/{id}/primary', name: 'api_photo_primary', methods: ['PATCH'])]
    public function setPrimary(
        Photo $photo,
        PhotoService $photoService
    ): JsonResponse {
        $user = $this->getUser();

        if ($photo->getProfile()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $photoService->setPrimaryPhoto($photo);

        return $this->json(['message' => 'Primary photo updated']);
    }

    #[Route('/reorder', name: 'api_photo_reorder', methods: ['PATCH'])]
    public function reorder(
        Request $request,
        ProfileRepository $profileRepo,
        PhotoService $photoService
    ): JsonResponse {
        $user = $this->getUser();
        $profile = $profileRepo->findOneBy(['user' => $user]);

        if (!$profile) {
            return $this->json(['error' => 'Profile not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['ids']) || !is_array($data['ids'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        try {
            $photoService->reorderPhotos($profile, $data['ids']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['message' => 'Photos reordered']);
    }
}
