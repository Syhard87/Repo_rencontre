<?php

namespace App\Controller;

use App\Entity\Like;
use App\Entity\User;
use App\Entity\UserMatch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/like')]
class LikeController extends AbstractController
{
    #[Route('/{id}', name: 'api_like_user', methods: ['POST'])]
    public function like(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // 1. Vérifier que la cible existe
        $targetUser = $em->getRepository(User::class)->find($id);

        if (!$targetUser) {
            return $this->json(['error' => 'User not found'], 404);
        }

        if ($currentUser->getId() === $targetUser->getId()) {
            return $this->json(['error' => 'You cannot like yourself'], 400);
        }

        // 2. Vérifier si déjà liké pour éviter les doublons
        $existingLike = $em->getRepository(Like::class)->findOneBy([
            'fromUser' => $currentUser,
            'toUser'   => $targetUser
        ]);

        if ($existingLike) {
            return $this->json(['message' => 'Already liked'], 200);
        }

        // 3. Créer le Like
        $like = new Like();
        $like->setFromUser($currentUser);
        $like->setToUser($targetUser);
        
        // Gestion du Super Like (optionnel, on le lit du JSON si envoyé)
        $data = json_decode($request->getContent(), true);
        if (isset($data['isSuperLike']) && $data['isSuperLike'] === true) {
            $like->setIsSuperLike(true);
        }

        $em->persist($like);

        // 4. 🔥 ALGO DE MATCH : Est-ce que l'autre m'a déjà liké ?
        $reverseLike = $em->getRepository(Like::class)->findOneBy([
            'fromUser' => $targetUser,
            'toUser'   => $currentUser
        ]);

        $isMatch = false;

        if ($reverseLike) {
            $isMatch = true;

            // Création du Match
            $match = new UserMatch(); 
            
            // ✅ CORRECTION : Utilisation de setUser1 et setUser2 selon ton Entité
            $match->setUser1($currentUser);
            $match->setUser2($targetUser);
            // createdAt est déjà géré par le __construct() de ton entité, pas besoin de le setter manuellement
            
            $em->persist($match);
        }

        $em->flush();

        return $this->json([
            'message' => $isMatch ? 'It\'s a match!' : 'Like sent',
            'match'   => $isMatch,
            'target'  => [
                'id' => $targetUser->getId(),
                'displayName' => $targetUser->getDisplayName()
            ]
        ], 201);
    }
}