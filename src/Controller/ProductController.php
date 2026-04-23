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
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('p')
            ->leftJoin('p.variants', 'v')
            ->addSelect('v');

        if ($request->query->get('featured') === 'true') {
            $qb->andWhere('p.featured = :featured')->setParameter('featured', true);
        }

        if ($q = $request->query->get('q')) {
            $keywords = array_filter(explode(' ', $q));
            foreach ($keywords as $index => $word) {
                $pName = 'q' . $index;
                $qb->andWhere("p.name LIKE :$pName OR p.sku LIKE :$pName OR v.name LIKE :$pName OR v.sku LIKE :$pName")
                   ->setParameter($pName, '%' . $word . '%');
            }
        }

        $page = (int) $request->query->get('_page', 1);
        $limit = (int) $request->query->get('_limit', 20);
        $offset = ($page - 1) * $limit;

        $qb->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, true);
        $total = count($paginator);

        $products = array_map(fn($p) => $this->serializeProduct($p), iterator_to_array($paginator));

        return $this->json($products, 200, [
            'x-total-count' => (string)$total,
            'Access-Control-Expose-Headers' => 'x-total-count'
        ]);
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $product = new Product();
            $product->setName($data['name'] ?? 'Nouveau Produit');
            $product->setSlug($data['slug'] ?? 'nouveau-produit-' . uniqid());
            
            $this->hydrateProduct($product, $data);
            $this->em->persist($product);
            $this->em->flush();

            return $this->json(['message' => 'Product created', 'id' => $product->getId()], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur création : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, Product $product): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $this->hydrateProduct($product, $data);
            $product->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
            return $this->json($this->serializeProduct($product));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur mise à jour : ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJOUTÉ : Récupération du stock pour éviter la 404
     */
    #[Route('/{id}/stock', methods: ['GET'])]
    public function getStock(Product $product): JsonResponse
    {
        // On utilise la même logique de calcul que le serializer
        $variants = $product->getVariants();
        $totalStock = count($variants) > 0 ? 0 : $product->getStock();
        foreach ($variants as $v) {
            $totalStock += $v->getStock();
        }

        return $this->json(['stock' => $totalStock]);
    }

    /**
     * MODIFIÉ : Route PATCH sur /{id}/stock pour être cohérent avec le GET
     */
    #[Route('/{id}/stock', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (array_key_exists('stock', $data)) {
            $product->setStock((int) $data['stock']);
            $product->setUpdatedAt(new \DateTimeImmutable());
            
            try {
                $this->em->flush();
                return $this->json(['message' => 'Stock mis à jour', 'stock' => $product->getStock()]);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Erreur de validation : ' . $e->getMessage()], 500);
            }
        }

        return $this->json(['error' => 'Donnée manquante'], 400);
    }

    private function hydrateProduct(Product $product, array $data): void
    {
        if (!empty($data['name'])) $product->setName($data['name']);
        if (!empty($data['slug'])) $product->setSlug($data['slug']);
        if (array_key_exists('description', $data)) $product->setDescription($data['description']);
        if (array_key_exists('excerpt', $data)) $product->setExcerpt($data['excerpt']);
        if (array_key_exists('sku', $data)) $product->setSku($data['sku']);

        if (isset($data['price'])) {
            $product->setPrice($data['price']);
            foreach ($product->getVariants() as $variant) {
                $variant->setPrice($data['price']);
            }
        }

        if (array_key_exists('stock', $data)) $product->setStock($data['stock'] !== null ? (int)$data['stock'] : 0);
        if (array_key_exists('weight', $data)) $product->setWeight($data['weight'] !== null ? (float)$data['weight'] : 0.0);
        if (array_key_exists('featured', $data)) $product->setFeatured((bool)$data['featured']);
        if (isset($data['main_image'])) $product->setMainImage($data['main_image']);
        if (isset($data['faq']) && is_array($data['faq'])) $product->setFaq($data['faq']);

        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            $product->getCategories()->clear();
            foreach ($data['category_ids'] as $catId) {
                $category = $this->em->getRepository(Category::class)->find($catId);
                if ($category) $product->addCategory($category);
            }
        }

        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($product->getImages()->toArray() as $image) {
                $product->removeImage($image);
            }
            foreach ($data['images'] as $imgData) {
                if (!empty($imgData['url'])) {
                    $newImage = new ProductImage();
                    $newImage->setUrl(ltrim($imgData['url'], '/'));
                    $newImage->setAlt($imgData['alt'] ?? $product->getName());
                    $product->addImage($newImage);
                }
            }
        }

        if (isset($data['related_product_ids']) && is_array($data['related_product_ids'])) {
            $product->getRelatedProducts()->clear();
            foreach ($data['related_product_ids'] as $relatedId) {
                $relatedProd = $this->repo->find($relatedId);
                if ($relatedProd && $relatedProd->getId() !== $product->getId()) {
                    $product->addRelatedProduct($relatedProd);
                }
            }
        }
    }

    #[Route('/reorder', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['products']) || !is_array($data['products'])) return $this->json(['error' => 'Invalid data'], 400);

        try {
            foreach ($data['products'] as $item) {
                $product = $this->repo->find($item['id']);
                if ($product) $product->setPosition((int) $item['position']);
            }
            $this->em->flush();
            return $this->json(['message' => 'Order updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors du tri : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getOne(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    #[Route('/slug/{slug}', methods: ['GET'])]
    public function getBySlug(string $slug): JsonResponse
    {
        $product = $this->repo->findOneBy(['slug' => $slug]);
        if (!$product) return $this->json(['error' => 'Product not found'], 404);
        return $this->json($this->serializeProduct($product));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Product $product): JsonResponse
    {
        $this->em->remove($product);
        $this->em->flush();
        return $this->json(['message' => 'Product deleted']);
    }

    #[Route('/category/{slug}', methods: ['GET'])]
    public function getByCategory(string $slug, CategoryRepository $categoryRepo): JsonResponse
    {
        $category = $categoryRepo->findOneBy(['slug' => $slug]);
        if (!$category) return $this->json(['error' => 'Category not found'], 404);
        $products = $this->repo->findByCategory($category);
        $data = array_map(fn($p) => $this->serializeProductWithoutVariants($p), $products);
        return $this->json($data);
    }

    private function serializeProductWithoutVariants(Product $product): array
    {
        $variants = $product->getVariants();
        $totalStock = $variants->count() > 0 ? 0 : $product->getStock();
        foreach ($variants as $v) $totalStock += $v->getStock();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'sku' => $product->getSku(),
            'price' => $product->getPrice(),
            'stock' => $totalStock,
            'main_image' => $product->getMainImage(),
            'category_slugs' => $product->getCategories()->map(fn($c) => $c->getSlug())->toArray(),
        ];
    }

    private function serializeProduct(Product $p): array
    {
        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;
        $variants = $p->getVariants()->toArray();
        usort($variants, fn($a, $b) => $a->getPosition() <=> $b->getPosition());
        
        $totalStock = count($variants) > 0 ? 0 : $p->getStock();
        foreach ($variants as $v) $totalStock += $v->getStock();

        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'slug' => $p->getSlug(),
            'description' => $p->getDescription(),
            'excerpt' => $p->getExcerpt(),
            'sku' => $p->getSku(),
            'price' => $p->getPrice(),
            'weight' => $p->getWeight(),
            'featured' => $p->isFeatured(),
            'main_image' => $formatImagePath($p->getMainImage()),
            'stock' => $totalStock,
            'categories' => $p->getCategories()->map(fn($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()])->toArray(),
            'images' => $p->getImages()->map(fn($i) => ['id' => $i->getId(), 'url' => $formatImagePath($i->getUrl()), 'alt' => $i->getAlt()])->toArray(),
            'variants' => array_map(fn($v) => [
                'id' => $v->getId(), 'name' => $v->getName(), 'sku' => $v->getSku(), 'price' => $v->getPrice(), 'stock' => $v->getStock(), 'image' => $formatImagePath($v->getImage())
            ], $variants),
            'faq' => $p->getFaq(),
            'related_products' => $p->getRelatedProducts()->map(fn($rp) => ['id' => $rp->getId(), 'name' => $rp->getName(), 'price' => $rp->getPrice(), 'main_image' => $formatImagePath($rp->getMainImage()), 'slug' => $rp->getSlug()])->toArray(),
        ];
    }
}