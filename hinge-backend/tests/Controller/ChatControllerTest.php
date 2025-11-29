<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\UserMatch;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class ChatControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // ✅ NETTOYAGE OBLIGATOIRE
        $this->entityManager->createQuery('DELETE FROM App\Entity\Message')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserMatch')->execute();
        // Si tu as des likes ou profils, supprime-les aussi ici
        $this->entityManager->createQuery('DELETE FROM App\Entity\Profile')->execute(); 
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testChatFlow()
    {
        // 1. Création des utilisateurs
        $alice = $this->createUser('alice@test.com', 'Alice');
        $bob = $this->createUser('bob@test.com', 'Bob');

        // 2. Création du Match
        $match = new UserMatch();
        $match->setUser1($alice);
        $match->setUser2($bob);
        $this->entityManager->persist($match);
        $this->entityManager->flush();

        // 3. Test d'Envoi de message (Alice -> Match)
        $this->client->request(
            'POST',
            '/api/chat/' . $match->getId(),
            [],
            [],
            $this->getAuthHeaders($alice), // On authentifie Alice
            json_encode(['content' => 'Salut Bob, ça va ?'])
        );

        // Vérification succès (201 Created)
        $this->assertResponseStatusCodeSame(201);
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Salut Bob, ça va ?', $responseContent['content']);
        $this->assertTrue($responseContent['isMine']);

        // 4. Test de Récupération de l'historique (Bob lit le chat)
        $this->client->request(
            'GET',
            '/api/chat/' . $match->getId(),
            [],
            [],
            $this->getAuthHeaders($bob) // On authentifie Bob
        );

        $this->assertResponseIsSuccessful();
        $history = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertCount(1, $history);
        $this->assertEquals('Salut Bob, ça va ?', $history[0]['content']);
        $this->assertFalse($history[0]['isMine']); 
    }

    // --- Helpers ---

    private function createUser(string $email, string $name): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('password123');
        $user->setDisplayName($name);
        $user->setBirthDate(new \DateTime('1990-01-01'));
        $user->setGender('M');
        $user->setCity('Paris');
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function getAuthHeaders(User $user): array
    {
        // CORRECTION : On utilise l'Encoder pour forcer le contenu du token
        // cela garantit que le token contient bien l'email et l'username attendus par Symfony
        $encoder = static::getContainer()->get(JWTEncoderInterface::class);
        
        $token = $encoder->encode([
            'username' => $user->getEmail(), // Standard Lexik
            'email' => $user->getEmail(),    // Ton app
            'user_id' => $user->getId()      // Ton app
        ]);

        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ];
    }
}