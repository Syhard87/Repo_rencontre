<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserMatch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/matches')]
class MatchController extends AbstractController
{
    #[Route('', name: 'api_matches_list', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // 1. Récupérer tous les matchs où je suis impliqué (soit user1, soit user2)
        // On utilise le QueryBuilder pour faire un "OR" propre
        $matches = $em->getRepository(UserMatch::class)->createQueryBuilder('m')
            ->where('m.user1 = :me')
            ->orWhere('m.user2 = :me')
            ->setParameter('me', $currentUser)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $output = [];

        foreach ($matches as $match) {
            // 2. Déterminer qui est "l'autre" personne (le Friend)
            // Si je suis user1, l'autre est user2. Sinon l'inverse.
            $isUser1 = $match->getUser1()->getId() === $currentUser->getId();
            $friend = $isUser1 ? $match->getUser2() : $match->getUser1();

            // 3. Récupérer la photo principale du friend
            $friendProfile = $friend->getProfile();
            $primaryPhoto = null;
            
            if ($friendProfile) {
                foreach ($friendProfile->getPhotos() as $photo) {
                    if ($photo->isPrimary()) {
                        $primaryPhoto = $photo->getPath();
                        break;
                    }
                }
                // Fallback si pas de photo primary
                if (!$primaryPhoto && count($friendProfile->getPhotos()) > 0) {
                    $primaryPhoto = $friendProfile->getPhotos()[0]->getPath();
                }
            }

            // 4. Construire la réponse
            $output[] = [
                'match_id' => $match->getId(),
                'matched_at' => $match->getCreatedAt()->format('c'), // Format ISO 8601
                'friend' => [
                    'id' => $friend->getId(),
                    'displayName' => $friend->getDisplayName(),
                    'age' => $this->calculateAge($friendProfile?->getBirthDate()),
                    'city' => $friendProfile?->getCity(),
                    'avatar' => $primaryPhoto,
                ],
                // Placeholders pour le futur système de chat
                'last_message' => null, 
                'unread_count' => 0
            ];
        }

        return $this->json([
            'count' => count($output),
            'results' => $output
        ]);
    }

    private function calculateAge(?\DateTimeInterface $birthDate): ?int
    {
        if (!$birthDate) return null;
        return (new \DateTime())->diff($birthDate)->y;
    }
}