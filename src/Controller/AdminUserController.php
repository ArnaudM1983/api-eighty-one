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
#[IsGranted('ROLE_ADMIN')] // Security: Only administrators can access these routes
class AdminUserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * LIST ALL USERS (ADMINS AND CUSTOMERS)
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
            ];
        }, $users);

        
        return $this->json($data, 200, ['x-total-count' => count($data)]);
    }

    /**
     * CREATE A NEW USER (ADMIN OR STAFF)
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email et mot de passe sont obligatoires'], 400);
        }

        // Check if email already exists
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], 400);
        }

        // Initialize new User entity
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName'] ?? null);
        $user->setLastName($data['lastName'] ?? null);
        
        // Set user roles (default to ROLE_ADMIN if not provided)
        $roles = $data['roles'] ?? ['ROLE_ADMIN'];
        $user->setRoles($roles);

        // PASSWORD HASHING (CRITICAL)
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
     * UPDATE USER DETAILS (Email, Name, Roles)
     */
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) $user->setEmail($data['email']);
        if (isset($data['firstName'])) $user->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $user->setLastName($data['lastName']);
        if (isset($data['roles'])) $user->setRoles($data['roles']);

        // Handle password update if provided
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
     * DELETE A USER
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        // Security check: Prevent administrators from deleting their own account
        if ($user === $this->getUser()) {
            return $this->json(['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'Utilisateur supprimé']);
    }

    /**
     * FETCH A SINGLE USER
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