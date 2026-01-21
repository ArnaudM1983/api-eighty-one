<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(CategoryRepository $repo): JsonResponse
    {
        $categories = $repo->findAll();
        
        $data = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
            // Refine utilise souvent 'label' pour les select, 
            // on peut l'ajouter ici pour simplifier le front
            'label' => $c->getName(), 
            'value' => $c->getId()
        ], $categories);

        return $this->json($data);
    }
}