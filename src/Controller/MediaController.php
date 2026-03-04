<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/api/media')]
#[IsGranted('ROLE_ADMIN')] // Ajout de la sécurité pour restreindre l'accès aux administrateurs
class MediaController extends AbstractController
{
    #[Route('/upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        // Validation du type de fichier (MIME type)
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['error' => 'Type de fichier non autorisé. Seules les images (JPEG, PNG, GIF, WEBP) sont acceptées.'], 400);
        }

        // Validation de la taille du fichier (par exemple, max 5MB)
        $maxFileSize = 5 * 1024 * 1024; // 5 MB
        if ($file->getSize() > $maxFileSize) {
            return $this->json(['error' => 'Fichier trop volumineux. La taille maximale autorisée est de 5MB.'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir').'/public/uploads',
                $newFilename
            );
        } catch (FileException $e) { // Utiliser une exception plus spécifique
            return $this->json(['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }

        return $this->json([
            'url' => 'uploads/' . $newFilename
        ]);
    }
}