<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')] // Sécurité : Seuls les admins peuvent accéder à ces routes
class AdminUserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * LISTE DES UTILISATEURS (ADMINS ET CLIENTS)
     */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        
        $users = $this->em->getRepository(User::class)->findAll();

        $data = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                // JAMAIS de mot de passe ici
            ];
        }, $users);

        
        return $this->json($data, 200, ['x-total-count' => count($data)]);
    }

    /**
     * CRÉER UN NOUVEL UTILISATEUR (ADMIN OU STAFF)
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et mot de passe sont obligatoires'], 400);
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], 400);
        }

        // Création
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName'] ?? null);
        $user->setLastName($data['lastName'] ?? null);
        
        // Définir le rôle (par défaut ROLE_ADMIN si créé depuis cette interface, ou au choix)
        $roles = $data['roles'] ?? ['ROLE_ADMIN'];
        $user->setRoles($roles);

        // HACHAGE DU MOT DE PASSE (CRUCIAL)
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'message' => 'Utilisateur créé avec succès'
        ], 201);
    }

    /**
     * MODIFIER UN UTILISATEUR (Email, Nom, Prénom)
     */
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) $user->setEmail($data['email']);
        if (isset($data['firstName'])) $user->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $user->setLastName($data['lastName']);
        if (isset($data['roles'])) $user->setRoles($data['roles']);

        // GESTION DU CHANGEMENT DE MOT DE PASSE
        if (!empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        $this->em->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'message' => 'Utilisateur mis à jour'
        ]);
    }

    /**
     * SUPPRIMER UN UTILISATEUR
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        // Sécurité : On empêche l'admin de se supprimer lui-même
        if ($user === $this->getUser()) {
            return $this->json(['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'Utilisateur supprimé']);
    }

    /**
     * VOIR UN UTILISATEUR
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }
}