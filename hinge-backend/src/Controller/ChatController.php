<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\UserMatch;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/chat')]
class ChatController extends AbstractController
{
    // 1. Récupérer l'historique des messages d'un Match
    #[Route('/{id}', name: 'api_chat_history', methods: ['GET'])]
    public function history(int $id, EntityManagerInterface $em, MessageRepository $messageRepo): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Vérifier que le match existe et que j'en fais partie
        $match = $em->getRepository(UserMatch::class)->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match not found'], 404);
        }

        // Sécurité : Est-ce que je suis User1 ou User2 ?
        if ($match->getUser1()->getId() !== $currentUser->getId() && $match->getUser2()->getId() !== $currentUser->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Récupération des messages
        $messages = $messageRepo->findMessagesForMatch($id);

        // Formatage manuel (plus sûr que le serializer automatique pour commencer)
        $output = array_map(function (Message $msg) use ($currentUser) {
            return [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'createdAt' => $msg->getCreatedAt()->format('c'),
                'isMine' => $msg->getSender()->getId() === $currentUser->getId(),
                'senderId' => $msg->getSender()->getId(),
                'isSeen' => $msg->isSeen()
            ];
        }, $messages);

        return $this->json($output);
    }

    // 2. Envoyer un message
    #[Route('/{id}', name: 'api_chat_send', methods: ['POST'])]
    public function send(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $match = $em->getRepository(UserMatch::class)->find($id);
        if (!$match || ($match->getUser1()->getId() !== $currentUser->getId() && $match->getUser2()->getId() !== $currentUser->getId())) {
            return $this->json(['error' => 'Match not found or access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (empty(trim($content))) {
            return $this->json(['error' => 'Message empty'], 400);
        }

        // Création du message
        $message = new Message();
        $message->setMatch($match); // Utilisation de ta méthode setMatch
        $message->setSender($currentUser);
        $message->setContent($content);
        $message->setIsSeen(false); // Utilisation de ta méthode setIsSeen

        $em->persist($message);
        $em->flush();

        // TODO: Ici, on déclenchera Mercure pour le temps réel plus tard

        return $this->json([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format('c'),
            'isMine' => true
        ], 201);
    }
}