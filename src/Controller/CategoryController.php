<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    private EntityManagerInterface $em;
    private CategoryRepository $repo;

    public function __construct(EntityManagerInterface $em, CategoryRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    /**
     * Utilisé pour le sélecteur dans ProductEdit (Structure Arbre)
     */
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $categories = $this->repo->findBy(['parent' => null]);

        $data = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'children' => array_map(fn(Category $child) => [
                'id' => $child->getId(),
                'name' => $child->getName(),
            ], $c->getChildren()->toArray())
        ], $categories);

        return $this->json($data);
    }

    /**
     * Utilisé par Refine pour la page CategoryList (Structure Plate)
     */
    #[Route('/admin', methods: ['GET'], priority: 2)]
    public function adminList(): JsonResponse
    {
        $categories = $this->repo->findAll();
        
        $data = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
            'parentId' => $c->getParent()?->getId(), // ID simple pour le selecteur
        ], $categories);

        return $this->json($data);
    }

    /**
     * Récupérer une seule catégorie pour le formulaire Edit
     */
    #[Route('/{id}', methods: ['GET'])]
    public function getOne(Category $category): JsonResponse
    {
        return $this->json([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent()?->getId(),
        ]);
    }

    /**
     * Créer une catégorie ou sous-catégorie
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $category = new Category();
        $category->setName($data['name'] ?? '');
        $category->setSlug($data['slug'] ?? '');

        if (!empty($data['parentId'])) {
            $parent = $this->repo->find($data['parentId']);
            if ($parent) {
                $category->setParent($parent);
            }
        }

        $this->em->persist($category);
        $this->em->flush();

        return $this->json(['id' => $category->getId()], 201);
    }

    /**
     * Mettre à jour une catégorie
     */
    #[Route('/{id}', methods: ['PATCH', 'PUT'])]
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $category->setName($data['name']);
        if (isset($data['slug'])) $category->setSlug($data['slug']);
        
        // Gestion du changement de parent
        if (array_key_exists('parentId', $data)) {
            $parent = $data['parentId'] ? $this->repo->find($data['parentId']) : null;
            
            // Sécurité : on ne peut pas être son propre parent
            if ($parent && $parent->getId() === $category->getId()) {
                return $this->json(['error' => 'Une catégorie ne peut pas être son propre parent'], 400);
            }
            
            $category->setParent($parent);
        }

        $this->em->flush();

        return $this->json(['status' => 'updated']);
    }

    /**
     * Supprimer une catégorie
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Category $category): JsonResponse
    {
        // Note: Si une catégorie parente est supprimée, les enfants deviennent orphelins 
        // ou sont supprimés selon votre config orphanRemoval.
        $this->em->remove($category);
        $this->em->flush();

        return $this->json(['status' => 'deleted']);
    }
}