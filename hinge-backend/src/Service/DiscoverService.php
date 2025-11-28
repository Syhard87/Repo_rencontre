<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\LikeRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserMatchRepository;

class DiscoverService
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private LikeRepository $likeRepository,
        private UserMatchRepository $userMatchRepository,
    ) {}

    /**
     * Retourne une liste de profils à proposer à l'utilisateur.
     *
     * @return array<int, array{
     *     user: User,
     *     profile: Profile,
     *     distanceKm: ?float,
     *     score: float
     * }>
     */
    public function getDiscoverProfiles(User $currentUser, int $page = 1, int $limit = 20): array
    {
        $currentProfile = $currentUser->getProfile();

        // le profil de l'utilisateur est indispensable
        if (!$currentProfile) {
            return [];
        }

        $excludedUserIds = $this->getExcludedUserIds($currentUser);

        // On récupère des profils candidats.
        // On en prend un peu plus que nécessaire, on filtrera/ordonnera en PHP.
        $qb = $this->profileRepository->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->andWhere('u != :currentUser')
            ->setParameter('currentUser', $currentUser)
            ->andWhere('p.bio IS NOT NULL') // optionnel : seulement profils avec bio
        ;

        if (!empty($excludedUserIds)) {
            $qb->andWhere('u.id NOT IN (:excludedUserIds)')
               ->setParameter('excludedUserIds', $excludedUserIds);
        }

        // Optionnel : ne prendre que ceux qui ont une localisation
        $qb->andWhere('p.latitude IS NOT NULL')
           ->andWhere('p.longitude IS NOT NULL');

        // On prend plus que le strict nécessaire pour laisser le score faire le tri
        $qb->setMaxResults($limit * 3);

        /** @var Profile[] $profiles */
        $profiles = $qb->getQuery()->getResult();

        $scored = [];

        foreach ($profiles as $profile) {
            $distanceKm = $this->computeDistanceKm($currentProfile, $profile);
            $score = $this->computeProfileScore($profile, $distanceKm);

            $scored[] = [
                'user'       => $profile->getUser(),
                'profile'    => $profile,
                'distanceKm' => $distanceKm,
                'score'      => $score,
            ];
        }

        // Tri par score décroissant (meilleurs profils en premier)
        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // Pagination côté PHP
        $offset = max(0, ($page - 1) * $limit);
        return array_slice($scored, $offset, $limit);
    }

    /**
     * Retourne la liste des IDs d'utilisateurs à exclure du discover :
     * - soi-même
     * - les gens déjà likés
     * - les gens déjà matchés
     */
    private function getExcludedUserIds(User $currentUser): array
    {
        $excluded = [$currentUser->getId()];

        // Utilisateurs déjà likés par moi
        $qbLikes = $this->likeRepository->createQueryBuilder('l')
            ->select('IDENTITY(l.toUser) AS id')
            ->where('l.fromUser = :user')
            ->setParameter('user', $currentUser);

        $liked = $qbLikes->getQuery()->getScalarResult();
        foreach ($liked as $row) {
            if (!in_array($row['id'], $excluded, true)) {
                $excluded[] = (int) $row['id'];
            }
        }

        // Utilisateurs déjà matchés avec moi
        $qbMatches = $this->userMatchRepository->createQueryBuilder('m')
            ->select('IDENTITY(m.user1) AS u1', 'IDENTITY(m.user2) AS u2')
            ->where('m.user1 = :user OR m.user2 = :user')
            ->setParameter('user', $currentUser);

        $matches = $qbMatches->getQuery()->getScalarResult();

        foreach ($matches as $row) {
            $user1Id = (int) $row['u1'];
            $user2Id = (int) $row['u2'];

            if ($user1Id !== $currentUser->getId() && !in_array($user1Id, $excluded, true)) {
                $excluded[] = $user1Id;
            }
            if ($user2Id !== $currentUser->getId() && !in_array($user2Id, $excluded, true)) {
                $excluded[] = $user2Id;
            }
        }

        return $excluded;
    }

    /**
     * Calcule la distance en km entre 2 profils (Haversine).
     * Retourne null si l'un des deux n'a pas de lat/lng.
     */
    private function computeDistanceKm(Profile $from, Profile $to): ?float
    {
        if ($from->getLatitude() === null || $from->getLongitude() === null ||
            $to->getLatitude() === null || $to->getLongitude() === null) {
            return null;
        }

        $earthRadius = 6371; // km

        $latFrom = deg2rad($from->getLatitude());
        $lonFrom = deg2rad($from->getLongitude());
        $latTo   = deg2rad($to->getLatitude());
        $lonTo   = deg2rad($to->getLongitude());

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $earthRadius * $angle;
    }

    /**
     * Calcule un score "qualité + proximité" pour un profil.
     * Tu pourras affiner cet algo plus tard (premium, boost, etc.).
     */
    private function computeProfileScore(Profile $profile, ?float $distanceKm): float
    {
        $score = 0.0;

        // +10 si bio présente
        if ($profile->getBio()) {
            $score += 10;
        }

        // +5 par photo (max 6 → +30)
        $photosCount = $profile->getPhotos()->count();
        $score += min($photosCount, 6) * 5;

        // +5 si au moins 1 intention
        $intentions = $profile->getIntentions();
        if (is_array($intentions) && count($intentions) > 0) {
            $score += 5;
        }

        // Bonus proximité : plus c'est proche, plus le score est élevé
        if ($distanceKm !== null) {
            // on réduit le score si c'est loin, max -20 pts
            $distancePenalty = min($distanceKm / 5, 20); // chaque 5km = -1 pt
            $score -= $distancePenalty;
        }

        return $score;
    }
}
