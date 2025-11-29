<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Récupère les messages d'un match, du plus ancien au plus récent
     */
    public function findMessagesForMatch(int $matchId): array
    {
        return $this->createQueryBuilder('m')
            // Attention : on utilise 'm.match' car c'est le nom de ta propriété dans l'Entité
            ->andWhere('m.match = :matchId')
            ->setParameter('matchId', $matchId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}