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
        // On ne récupère que les parents pour structurer l'arbre
        $categories = $repo->findBy(['parent' => null]);

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
}
