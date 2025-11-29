<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Like;
use App\Service\DiscoverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/discover')]
class DiscoverController extends AbstractController
{
    // =========================================================================
    // 1. ROUTE PRINCIPALE
    // =========================================================================
    #[Route('', name: 'api_discover_list', methods: ['GET'])]
    public function index(Request $request, DiscoverService $discoverService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Paramètres de pagination
        $page  = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, min(50, (int)$request->query->get('limit', 20)));

        // Appel du Service
        $results = $discoverService->getDiscoverProfiles($user, $page, $limit);

        // Transformation en JSON propre
        $output = [];

        foreach ($results as $row) {
            $profile = $row['profile'] ?? $row; 
            $userObj = $row['user'] ?? (method_exists($profile, 'getUser') ? $profile->getUser() : null);

            if (!$profile || !$userObj) continue;

            $primaryPhoto = null;
            foreach ($profile->getPhotos() as $photo) {
                if ($photo->isPrimary()) {
                    $primaryPhoto = $photo->getPath();
                    break;
                }
            }
            if (!$primaryPhoto && count($profile->getPhotos()) > 0) {
                $primaryPhoto = $profile->getPhotos()[0]->getPath();
            }

            $output[] = [
                'id'           => $userObj->getId(),
                'displayName'  => $userObj->getDisplayName() ?? $userObj->getEmail(),
                'age'          => $this->calculateAge($userObj->getProfile()->getBirthDate()),
                'city'         => $profile->getCity(),
                'bio'          => $profile->getBio(),
                'interests'    => $profile->getInterests(),
                'intentions'   => $profile->getIntentions(),
                'primaryPhoto' => $primaryPhoto,
                'photosCount'  => $profile->getPhotos()->count(),
                'distanceKm'   => $row['distanceKm'] ?? 'N/A',
                'score'        => $row['score'] ?? 0,
            ];
        }

        return $this->json([
            'page'    => $page,
            'limit'   => $limit,
            'count'   => count($output),
            'results' => $output
        ]);
    }

    // =========================================================================
    // 2. ROUTE DE DEBUG (CORRIGÉE)
    // =========================================================================
    #[Route('/debug', name: 'api_discover_debug', methods: ['GET'])]
    public function debug(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['error' => 'Tu dois être connecté'], 401);
        }

        // 1. Récupération des infos de l'utilisateur courant
        $myProfile = $currentUser->getProfile();
        
        // 2. Récupération de la cible
        $targetId = $request->query->get('target_id', 4);
        $targetUser = $em->getRepository(User::class)->find($targetId);

        if (!$targetUser) {
            return $this->json(['error' => "User ID $targetId introuvable"], 404);
        }

        $targetProfile = $targetUser->getProfile();
        
        // 3. Analyse complète
        $analysis = [
            '1_me' => [
                'id' => $currentUser->getId(),
                'has_profile' => $myProfile !== null,
                'lat' => $myProfile?->getLatitude(),
                'lng' => $myProfile?->getLongitude(),
            ],
            '2_target' => [
                'id' => $targetUser->getId(),
                'has_profile' => $targetProfile !== null,
                'lat' => $targetProfile?->getLatitude(),
                'lng' => $targetProfile?->getLongitude(),
            ],
            '3_checks' => []
        ];

        // CHECK A : Les coordonnées
        if (!$myProfile?->getLatitude() || !$targetProfile?->getLatitude()) {
            $analysis['3_checks']['COORDINATES'] = '❌ FAIL: L\'un des deux n\'a pas de latitude/longitude';
        } else {
            $analysis['3_checks']['COORDINATES'] = '✅ OK';
            
            $dist = $this->calculateDistance(
                $myProfile->getLatitude(), $myProfile->getLongitude(),
                $targetProfile->getLatitude(), $targetProfile->getLongitude()
            );
            $analysis['3_checks']['DISTANCE_CALC'] = round($dist, 2) . ' km';
            $analysis['3_checks']['IN_RADIUS_50KM'] = ($dist <= 50) ? '✅ OUI' : '❌ NON (Trop loin)';
        }

        // CHECK B : Déjà liké ?
        $existingLike = $em->getRepository(Like::class)->findOneBy([
            'fromUser' => $currentUser, // ✅ CORRIGÉ (anciennement 'sender')
            'toUser'   => $targetUser   // ✅ CORRIGÉ (anciennement 'target')
        ]);
        
        if ($existingLike) {
            // ✅ CORRIGÉ : On utilise isSuperLike() au lieu de getType()
            $type = $existingLike->isSuperLike() ? 'SUPER LIKE' : 'LIKE STANDARD';
            $analysis['3_checks']['ALREADY_LIKED'] = '❌ OUI (Type: ' . $type . ') -> Profil exclu';
        } else {
            $analysis['3_checks']['ALREADY_LIKED'] = '✅ NON (Aucun like trouvé)';
        }

        // CHECK C : Est-ce moi-même ?
        if ($currentUser->getId() === $targetUser->getId()) {
            $analysis['3_checks']['SELF_CHECK'] = '❌ FAIL: C\'est toi-même !';
        } else {
            $analysis['3_checks']['SELF_CHECK'] = '✅ OK';
        }

        return $this->json($analysis);
    }

    // =========================================================================
    // 3. FONCTIONS PRIVÉES
    // =========================================================================

    private function calculateAge(?\DateTimeInterface $birthDate): ?int
    {
        if (!$birthDate) return null;
        return (new \DateTime())->diff($birthDate)->y;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}