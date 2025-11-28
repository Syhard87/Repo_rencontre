<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

class PhotoService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Ajoute une photo au profil
     */
    public function addPhoto(Profile $profile, File $file): Photo
    {
        // Vérifie limite des photos
        if ($profile->getPhotos()->count() >= 6) {
            throw new \Exception("Maximum 6 photos allowed.");
        }

        $photo = new Photo();
        $photo->setProfile($profile);
        $photo->setFile($file);

        // Position = dernière + 1
        $position = $profile->getPhotos()->count() + 1;
        $photo->setPosition($position);

        // Première photo = principale
        if ($position === 1) {
            $photo->setIsPrimary(true);
        } else {
            $photo->setIsPrimary(false);
        }

        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    /**
     * Supprime une photo + réorganise les positions restantes
     */
    public function deletePhoto(Photo $photo): void
    {
        $profile = $photo->getProfile();
        $oldPosition = $photo->getPosition();

        $this->em->remove($photo);
        $this->em->flush();

        // Réorganise les positions après suppression
        $photos = $profile->getPhotos()->toArray();
        usort($photos, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        $newPosition = 1;
        foreach ($photos as $p) {
            if ($p->getPosition() !== $newPosition) {
                $p->setPosition($newPosition);
            }
            $newPosition++;
        }

        $this->em->flush();
    }

    /**
     * Définit une photo comme photo principale
     */
    public function setPrimaryPhoto(Photo $photo): void
    {
        $profile = $photo->getProfile();

        foreach ($profile->getPhotos() as $p) {
            $p->setIsPrimary(false);
        }

        $photo->setIsPrimary(true);

        $this->em->flush();
    }

    /**
     * Réordonne les photos à partir d'un tableau d'IDs
     */
    public function reorderPhotos(Profile $profile, array $orderedIds): void
    {
        $photos = $profile->getPhotos()->toArray();

        // Vérifie cohérence
        if (count($orderedIds) !== count($photos)) {
            throw new \Exception("Photo count mismatch.");
        }

        $photoById = [];
        foreach ($photos as $p) {
            $photoById[$p->getId()] = $p;
        }

        $position = 1;
        foreach ($orderedIds as $id) {
            if (!isset($photoById[$id])) {
                throw new \Exception("Invalid photo ID: " . $id);
            }

            $photoById[$id]->setPosition($position);
            $position++;
        }

        $this->em->flush();
    }
}
