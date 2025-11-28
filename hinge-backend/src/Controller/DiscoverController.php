<?php

namespace App\Controller;

use App\Service\DiscoverService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/discover')]
class DiscoverController extends AbstractController
{
    #[Route('', name: 'api_discover_list', methods: ['GET'])]
    public function index(Request $request, DiscoverService $discoverService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // paramètres de pagination venant du frontend
        $page  = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, min(50, (int)$request->query->get('limit', 20)));

        // Appel du DiscoverService
        $results = $discoverService->getDiscoverProfiles($user, $page, $limit);

        // Transformation en JSON propre
        $output = [];

        foreach ($results as $row) {
            $profile = $row['profile'];
            $userObj = $row['user'];

            // Trouver la photo principale
            $primaryPhoto = null;
            foreach ($profile->getPhotos() as $photo) {
                if ($photo->isPrimary()) {
                    $primaryPhoto = $photo->getPath();
                    break;
                }
            }

            $output[] = [
                'id'           => $userObj->getId(),
                'displayName'  => $userObj->getDisplayName(),
                'age'          => $this->calculateAge($userObj->getBirthDate()),
                'city'         => $profile->getCity(),
                'bio'          => $profile->getBio(),
                'interests'    => $profile->getInterests(),
                'intentions'   => $profile->getIntentions(),
                'primaryPhoto' => $primaryPhoto,
                'photosCount'  => $profile->getPhotos()->count(),
                'distanceKm'   => $row['distanceKm'],
                'score'        => $row['score'],
            ];
        }

        return $this->json([
            'page'       => $page,
            'limit'      => $limit,
            'count'      => count($output),
            'results'    => $output
        ]);
    }

    private function calculateAge(\DateTime $birthDate): int
    {
        return (new \DateTime())->diff($birthDate)->y;
    }
}
