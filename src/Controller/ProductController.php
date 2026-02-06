<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Category;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

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
     */
    #[Route('', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('p')
            ->leftJoin('p.variants', 'v')
            ->addSelect('v');

        if ($request->query->get('featured') === 'true') {
            $qb->andWhere('p.featured = :featured')
                ->setParameter('featured', true);
        }

        if ($q = $request->query->get('q')) {
            $keywords = array_filter(explode(' ', $q));
            foreach ($keywords as $index => $word) {
                $parameterName = 'q' . $index;
                $qb->andWhere('
                    p.name LIKE :' . $parameterName . ' OR 
                    p.sku LIKE :' . $parameterName . ' OR 
                    v.name LIKE :' . $parameterName . ' OR 
                    v.sku LIKE :' . $parameterName . '
                ')
                    ->setParameter($parameterName, '%' . $word . '%');
            }
        }

        $page = (int) $request->query->get('_page', 1);
        $limit = (int) $request->query->get('_limit', 20);
        $offset = ($page - 1) * $limit;

        $qb->orderBy('p.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, true);
        $total = count($paginator);

        $products = [];
        foreach ($paginator as $product) {
            $products[] = $this->serializeProduct($product);
        }

        return $this->json($products, 200, [
            'x-total-count' => (string)$total,
            'Access-Control-Expose-Headers' => 'x-total-count'
        ]);
    }

    /**
     * CRUD: Search
     */
    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if (!$q) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }

        $products = $this->repo->createQueryBuilder('p')
            ->andWhere('p.name LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn(Product $p) => $this->serializeProduct($p), $products);

        return $this->json($data);
    }

    /**
     * CRUD: Get One by ID
     */
    #[Route('/{id}', methods: ['GET'])]
    public function getOne(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    /**
     * CRUD: Get One by Slug
     */
    #[Route('/slug/{slug}', methods: ['GET'])]
    public function getBySlug(string $slug): JsonResponse
    {
        $product = $this->repo->findOneBy(['slug' => $slug]);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }

        return $this->json($this->serializeProduct($product));
    }


    /**
     * CRUD: Create
     */
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
        $product->setWeight(isset($data['weight']) ? (float)$data['weight'] : null);
        $product->setFeatured($data['featured'] ?? false);
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

        // --- NOUVEAU : Produits Associés à la création ---
        if (isset($data['related_product_ids']) && is_array($data['related_product_ids'])) {
            foreach ($data['related_product_ids'] as $relatedId) {
                $relatedProd = $this->repo->find($relatedId);
                if ($relatedProd) {
                    $product->addRelatedProduct($relatedProd);
                }
            }
        }

        $this->em->persist($product);
        $this->em->flush();

        return $this->json(['message' => 'Product created', 'id' => $product->getId()], 201);
    }


    /**
     * CRUD: Update
     */
    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs basiques
        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['slug'])) $product->setSlug($data['slug']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['excerpt'])) $product->setExcerpt($data['excerpt']);
        if (isset($data['sku'])) $product->setSku($data['sku']);
        if (isset($data['price'])) {
            $newPrice = $data['price'];
            $product->setPrice($newPrice);
            // SYNCHRONISATION DES VARIANTES
            foreach ($product->getVariants() as $variant) {
                $variant->setPrice($newPrice);
            }
        }
        if (isset($data['stock'])) $product->setStock($data['stock']);
        if (isset($data['weight'])) $product->setWeight($data['weight']);
        if (isset($data['featured'])) $product->setFeatured($data['featured']);
        if (isset($data['main_image'])) $product->setMainImage($data['main_image']);

        // Update des catégories
        if (isset($data['category_ids'])) {
            foreach ($product->getCategories() as $category) {
                $product->removeCategory($category);
            }
            foreach ($data['category_ids'] as $catId) {
                $category = $this->em->getRepository(Category::class)->find($catId);
                if ($category) {
                    $product->addCategory($category);
                }
            }
        }

        // Update des images
        if (isset($data['images'])) {
            $currentImages = $product->getImages();
            foreach ($currentImages as $image) {
                $product->removeImage($image);
            }
            foreach ($data['images'] as $imgData) {
                if (!empty($imgData['url'])) {
                    $newImage = new ProductImage();
                    $url = ltrim($imgData['url'], '/');
                    $newImage->setUrl($url);
                    $newImage->setAlt($product->getName());
                    $product->addImage($newImage);
                }
            }
        }

        // --- NOUVEAU : Update des Produits Associés ---
        if (isset($data['related_product_ids']) && is_array($data['related_product_ids'])) {
            // 1. On nettoie les anciennes relations
            foreach ($product->getRelatedProducts() as $related) {
                $product->removeRelatedProduct($related);
            }
            
            // 2. On ajoute les nouvelles
            foreach ($data['related_product_ids'] as $relatedId) {
                $relatedProd = $this->repo->find($relatedId);
                // On s'assure que le produit existe et qu'on ne lie pas le produit à lui-même
                if ($relatedProd && $relatedProd->getId() !== $product->getId()) {
                    $product->addRelatedProduct($relatedProd);
                }
            }
        }

        $product->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json($this->serializeProduct($product));
    }

    /**
     * CRUD: Delete
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product): JsonResponse
    {
        $this->em->remove($product);
        $this->em->flush();

        return $this->json(['message' => 'Product deleted']);
    }

    /**
     * Get by Category
     */
    #[Route('/category/{slug}', methods: ['GET'])]
    public function getByCategory(string $slug, CategoryRepository $categoryRepo): JsonResponse
    {
        $category = $categoryRepo->findOneBy(['slug' => $slug]);

        if (!$category) {
            return $this->json(['error' => 'Category not found'], 404);
        }

        $products = $this->repo->findByCategory($category);
        $data = array_map(fn(Product $p) => $this->serializeProductWithoutVariants($p), $products);

        return $this->json($data);
    }

    /**
     * Get Stock
     */
    #[Route('/{id}/stock', methods: ['GET'])]
    public function getStock(Product $product): JsonResponse
    {
        return $this->json(['stock' => $product->getStock()]);
    }

    /**
     * Update Stock
     */
    #[Route('/{id}', methods: ['PATCH'])]
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['stock'])) {
            $product->setStock((int) $data['stock']);
            $product->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            return $this->json(['message' => 'Stock mis à jour avec succès', 'stock' => $product->getStock()]);
        }

        return $this->json(['error' => 'Donnée de stock manquante'], 400);
    }

    /**
     * Reorder
     */
    #[Route('/reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['products']) || !is_array($data['products'])) {
            return $this->json(['error' => 'Invalid data'], 400);
        }

        foreach ($data['products'] as $item) {
            $product = $this->repo->find($item['id']);
            if ($product) {
                $product->setPosition((int) $item['position']);
            }
        }

        $this->em->flush();

        return $this->json(['message' => 'Order updated successfully']);
    }

    // Serializer Léger (pour les listes)
    private function serializeProductWithoutVariants(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'excerpt' => $product->getExcerpt(),
            'sku' => $product->getSku(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'weight' => $product->getWeight(),
            'featured' => $product->isFeatured(),
            'main_image' => $product->getMainImage(),
            'created_at' => $product->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'has_variants' => $product->getVariants()->count() > 0,
        ];
    }

    // Serializer Complet (pour le détail)
    private function serializeProduct(Product $p): array
    {
        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;

        $variants = $p->getVariants()->toArray();
        $variantCount = count($variants);

        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'slug' => $p->getSlug(),
            'description' => $p->getDescription(),
            'excerpt' => $p->getExcerpt(),
            'sku' => $p->getSku(),
            'price' => $p->getPrice(),
            'stock' => $p->getStock(),
            'weight' => $p->getWeight(),
            'featured' => $p->isFeatured(),
            'main_image' => $formatImagePath($p->getMainImage()),
            'created_at' => $p->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $p->getUpdatedAt()->format('Y-m-d H:i:s'),
            'categories' => $p->getCategories()->map(fn($c) => [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'slug' => $c->getSlug(),
            ])->toArray(),
            'images' => $p->getImages()->map(fn($i) => [
                'id' => $i->getId(),
                'url' => $formatImagePath($i->getUrl()),
                'alt' => $i->getAlt()
            ])->toArray(),
            'variants' => array_map(fn($v) => [
                'id' => $v->getId(),
                'name' => $v->getName(),
                'sku' => $v->getSku(),
                'price' => $v->getPrice(),
                'stock' => $v->getStock(),
                'image' => $formatImagePath($v->getImage()),
                'attributes' => $v->getAttributes()
            ], $variants),
            'variant_count' => $variantCount,
            'has_variants' => $variantCount > 0,
            
            // --- NOUVEAU : Sérialisation des produits liés ---
            'related_products' => $p->getRelatedProducts()->map(fn($rp) => [
                'id' => $rp->getId(),
                'name' => $rp->getName(),
                'price' => $rp->getPrice(),
                'main_image' => $formatImagePath($rp->getMainImage()),
                'slug' => $rp->getSlug(),
            ])->toArray(),
        ];
    }
}