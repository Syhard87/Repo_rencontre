<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Crée un profil vide pour un utilisateur (appelé après register)
     */
    public function createProfileForUser(User $user): Profile
    {
        $profile = new Profile();
        $profile->setUser($user);

        $this->em->persist($profile);
        $this->em->flush();

        return $profile;
    }

    /**
     * Met à jour les champs d’un profil à partir d’un tableau de données
     */
    public function updateProfile(Profile $profile, array $data): Profile
    {
        if (isset($data['bio'])) {
            $profile->setBio($data['bio']);
        }

        if (isset($data['interests'])) {
            $profile->setInterests($data['interests']);
        }

        if (isset($data['intentions'])) {
            $profile->setIntentions($data['intentions']);
        }

        if (isset($data['prompts'])) {
            $profile->setPrompts($data['prompts']);
        }

        if (isset($data['city'])) {
            $profile->setCity($data['city']);
        }

        $this->em->flush();
        return $profile;
    }

    /**
     * Mise à jour latitude / longitude
     */
    public function updateLocation(Profile $profile, float $lat, float $lon): Profile
    {
        $profile->setLatitude($lat);
        $profile->setLongitude($lon);

        $this->em->flush();
        return $profile;
    }

    /**
     * Vérifie si un profil contient assez d’informations pour Discover
     */
    public function isProfileComplete(Profile $profile): bool
    {
        return
            $profile->getBio() !== null &&
            $profile->getCity() !== null &&
            $profile->getInterests() !== null &&
            $profile->getIntentions() !== null &&
            $profile->getPhotos()->count() >= 1;
    }
}
