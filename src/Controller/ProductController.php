<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Category;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    private EntityManagerInterface $em;
    private ProductRepository $repo;

    public function __construct(EntityManagerInterface $em, ProductRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    /**
     * CRUD: Read (List)
     * HTTP Method: GET
     * URL: /api/products
     * Description: Retrieve all products with categories, images, and variants.
     **/
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $products = $this->repo->findAll();
        $data = array_map(fn(Product $p) => $this->serializeProduct($p), $products);

        return $this->json($data);
    }

    /**
     * CRUD: Read (Detail)
     * HTTP Method: GET
     * URL: /api/products/{id}
     * Description: Retrieve details of a specific product with categories, images, and variants.
     **/
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    /**
     * CRUD: Create
     * HTTP Method: POST
     * URL: /api/products
     * Description: Create a new product with optional categories and images.
     **/
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name'] ?? '');
        $product->setSlug($data['slug'] ?? '');
        $product->setDescription($data['description'] ?? null);
        $product->setExcerpt($data['excerpt'] ?? null);
        $product->setSku($data['sku'] ?? null);
        $product->setPrice($data['price'] ?? null);
        $product->setStock($data['stock'] ?? null);
        $product->setMainImage($data['main_image'] ?? null);

        // Categories
        foreach ($data['category_ids'] ?? [] as $catId) {
            $category = $this->em->getRepository(Category::class)->find($catId);
            if ($category) $product->addCategory($category);
        }

        // Images
        foreach ($data['images'] ?? [] as $imgData) {
            $image = new ProductImage();
            $image->setUrl($imgData['url']);
            $image->setAlt($imgData['alt'] ?? null);
            $product->addImage($image);
        }

        $this->em->persist($product);
        $this->em->flush();

        return $this->json(['message' => 'Product created', 'id' => $product->getId()], 201);
    }

    /**
     * CRUD: Update
     * HTTP Method: PUT
     * URL: /api/products/{id}
     * Description: Update an existing product with optional categories and images.
     **/
    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['slug'])) $product->setSlug($data['slug']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['excerpt'])) $product->setExcerpt($data['excerpt']);
        if (isset($data['sku'])) $product->setSku($data['sku']);
        if (isset($data['price'])) $product->setPrice($data['price']);
        if (isset($data['stock'])) $product->setStock($data['stock']);
        if (isset($data['main_image'])) $product->setMainImage($data['main_image']);

        // Update categories
        if (isset($data['category_ids'])) {
            $product->getCategories()->clear();
            foreach ($data['category_ids'] as $catId) {
                $category = $this->em->getRepository(Category::class)->find($catId);
                if ($category) $product->addCategory($category);
            }
        }

        // Update images
        if (isset($data['images'])) {
            $product->getImages()->clear();
            foreach ($data['images'] as $imgData) {
                $image = new ProductImage();
                $image->setUrl($imgData['url']);
                $image->setAlt($imgData['alt'] ?? null);
                $product->addImage($image);
            }
        }

        $product->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['message' => 'Product updated']);
    }

    /**
     * CRUD: Delete
     * HTTP Method: DELETE
     * URL: /api/products/{id}
     * Description: Delete an existing product along with its categories and images associations.
     **/
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product): JsonResponse
    {
        $this->em->remove($product);
        $this->em->flush();

        return $this->json(['message' => 'Product deleted']);
    }

    private function serializeProduct(Product $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'slug' => $p->getSlug(),
            'description' => $p->getDescription(),
            'excerpt' => $p->getExcerpt(),
            'sku' => $p->getSku(),
            'price' => $p->getPrice(),
            'stock' => $p->getStock(),
            'main_image' => $p->getMainImage(),
            'created_at' => $p->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $p->getUpdatedAt()->format('Y-m-d H:i:s'),
            'categories' => $p->getCategories()->map(fn($c) => ['id' => $c->getId(), 'name' => $c->getName()])->toArray(),
            'images' => $p->getImages()->map(fn($i) => ['id' => $i->getId(), 'url' => $i->getUrl(), 'alt' => $i->getAlt()])->toArray(),
            'variants' => $p->getVariants()->map(fn($v) => [
                'id' => $v->getId(),
                'name' => $v->getName(),
                'sku' => $v->getSku(),
                'price' => $v->getPrice(),
                'stock' => $v->getStock(),
                'image' => $v->getImage(),
                'attributes' => $v->getAttributes()
            ])->toArray()
        ];
    }
}
