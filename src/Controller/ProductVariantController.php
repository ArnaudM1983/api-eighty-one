<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/variants')]
class ProductVariantController extends AbstractController
{
    private EntityManagerInterface $em;
    private ProductVariantRepository $repo;

    public function __construct(EntityManagerInterface $em, ProductVariantRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    /**
     * CRUD: Read (List)
     * HTTP Method: GET
     * URL: /api/variants
     * Description: Retrieve all variants.
     **/
    #[Route('', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $variants = $this->repo->findAll();
        $data = array_map(fn(ProductVariant $v) => $this->serializeVariant($v), $variants);

        return $this->json($data);
    }

    /**
     * CRUD: Read (List)
     * HTTP Method: GET
     * URL: /api/variants
     * Description: Retrieve details of a specific variant.
     **/
    #[Route('/{id}', methods: ['GET'])]
    public function getOne(ProductVariant $variant): JsonResponse
    {
        return $this->json($this->serializeVariant($variant));
    }

    /**
     * CRUD: Create
     * HTTP Method: POST
     * URL: /api/variants
     * Description: Create a new variant.
     **/
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $variant = new ProductVariant();
        $variant->setName($data['name']);
        $variant->setSku($data['sku'] ?? null);
        $variant->setPrice($data['price'] ?? null);
        $variant->setStock($data['stock'] ?? null);
        $variant->setImage($data['image'] ?? null);
        $variant->setAttributes($data['attributes'] ?? []);

        if (!empty($data['product_id'])) {
            $product = $this->em->getRepository(Product::class)->find($data['product_id']);
            if ($product) $variant->setProduct($product);
        }

        $this->em->persist($variant);
        $this->em->flush();

        return $this->json(['message' => 'Variant created', 'id' => $variant->getId()], 201);
    }

    /**
     * CRUD: Update
     * HTTP Method: PUT
     * URL: /api/variants/{id}
     * Description: Update an variant.
     **/
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $variant->setName($data['name']);
        if (isset($data['sku'])) $variant->setSku($data['sku']);
        if (isset($data['price'])) $variant->setPrice($data['price']);
        if (isset($data['stock'])) $variant->setStock($data['stock']);
        if (isset($data['image'])) $variant->setImage($data['image']);
        if (isset($data['attributes'])) $variant->setAttributes($data['attributes']);

        if (!empty($data['product_id'])) {
            $product = $this->em->getRepository(Product::class)->find($data['product_id']);
            if ($product) $variant->setProduct($product);
        }

        $this->em->flush();

        return $this->json(['message' => 'Variant updated']);
    }

    /**
     * CRUD: Delete
     * HTTP Method: DELETE
     * URL: /api/variants/{id}
     * Description: Delete an existing variant.
     **/
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(ProductVariant $variant): JsonResponse
    {
        $this->em->remove($variant);
        $this->em->flush();

        return $this->json(['message' => 'Variant deleted']);
    }

    private function serializeVariant(ProductVariant $v): array
    {
        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;

        return [
            'id' => $v->getId(),
            'name' => $v->getName(),
            'sku' => $v->getSku(),
            'price' => $v->getPrice(),
            'stock' => $v->getStock(),
            'image' => $formatImagePath($v->getImage()), 
            'attributes' => $v->getAttributes(),
            'product' => $v->getProduct() ? ['id' => $v->getProduct()->getId(), 'name' => $v->getProduct()->getName()] : null
        ];
    }
}
