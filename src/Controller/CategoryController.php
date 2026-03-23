<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
     * Provides a hierarchical tree structure of categories.
     * Primarily used by the Front-end (Next.js) and Admin Selectors 
     * to display nested categories (Parent > Children).
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
     * Provides a list of all categories for administrative purposes.
     * Primarily used by the Admin Dashboard (Refine) for data tables, 
     * filtering, and simplified parent-child management.
     */
    #[Route('/admin', methods: ['GET'], priority: 2)]
    public function adminList(): JsonResponse
    {
        $categories = $this->repo->findAll();
        
        $data = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
            'parentId' => $c->getParent()?->getId(), 
        ], $categories);

        return $this->json($data);
    }

    /**
     * Retrieve a single category for the Edit form
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
     * Create a new category or sub-category
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
     * Update an existing category
     */
    #[Route('/{id}', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $category->setName($data['name']);
        if (isset($data['slug'])) $category->setSlug($data['slug']);
        
        // Handle parent changes with security check
        if (array_key_exists('parentId', $data)) {
            $parent = $data['parentId'] ? $this->repo->find($data['parentId']) : null;
            
            // Security: A category cannot be its own parent
            if ($parent && $parent->getId() === $category->getId()) {
                return $this->json(['error' => 'Une catégorie ne peut pas être son propre parent'], 400);
            }
            
            $category->setParent($parent);
        }

        $this->em->flush();

        return $this->json(['status' => 'updated']);
    }

    /**
     * Delete a category
     */
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Category $category): JsonResponse
    {
        $this->em->remove($category);
        $this->em->flush();

        return $this->json(['status' => 'deleted']);
    }
}