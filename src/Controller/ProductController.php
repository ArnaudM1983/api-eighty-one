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
    public function getAll(): JsonResponse
    {
        $products = $this->repo->findAll();
        $data = array_map(fn(Product $p) => $this->serializeProduct($p), $products);

        return $this->json($data, 200, [
        'Access-Control-Expose-Headers' => 'x-total-count',
        'x-total-count' => (string)count($products)
    ]);
    }

    /**
     * CRUD: Search products by name
     * HTTP Method: GET
     * URL: /api/products/search?q=terme
     **/
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
     * CRUD: Read (Detail)
     * HTTP Method: GET
     * URL: /api/products/{id}
     * Description: Retrieve details of a specific product with categories, images, and variants.
     **/
    #[Route('/{id}', methods: ['GET'])]
    public function getOne(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    /**
     * CRUD: Read (Detail)
     * HTTP Method: GET
     * URL: /api/products/{id}
     * Description: Retrieve details of a specific product with categories, images, and variants.
     **/
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
        $product->setFeatured($data['featured'] ?? false);
        // --------------------------------------------------
        // TODO: Stockage local pour main_image
        // Stockage des images sur le serveur local,
        // remplacer le champ 'main_image' par un upload via FileType ou multipart/form-data,
        // déplacer le fichier dans /public/uploads/products/ et stocker l'URL relative ici :
        // $product->setMainImage('/uploads/products/'.$newFilename);
        // --------------------------------------------------
        $product->setMainImage($data['main_image'] ?? null);

        // Categories
        foreach ($data['category_ids'] ?? [] as $catId) {
            $category = $this->em->getRepository(Category::class)->find($catId);
            if ($category) $product->addCategory($category);
        }

        // Images
        // --------------------------------------------------
        // TODO: Stockage local pour images supplémentaires
        // Pour chaque image :
        // 1. Recevoir un fichier via FileType / multipart/form-data
        // 2. Déplacer le fichier dans /public/uploads/products/
        // 3. Stocker l'URL relative dans $image->setUrl('/uploads/products/'.$newFilename)
        // --------------------------------------------------
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
        if (isset($data['featured'])) $product->setFeatured($data['featured']);
        // --------------------------------------------------
        // TODO: Update stockage local pour main_image
        // Remplacer 'main_image' par l'upload local si nécessaire
        // --------------------------------------------------
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
        // --------------------------------------------------
        // TODO: Update stockage local pour images supplémentaires
        // Supprimer les images existantes si nécessaire
        // Puis uploader les nouveaux fichiers dans /public/uploads/products/
        // et stocker les URLs relatives
        // --------------------------------------------------
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

    /**
     * HTTP Method: GET
     * URL: /api/products/category/{slug}
     * Description: Retrieve all parent products of a category (no variants).
     **/
    #[Route('/category/{slug}', methods: ['GET'])]
    public function getByCategory(string $slug, CategoryRepository $categoryRepo): JsonResponse
    {
        $category = $categoryRepo->findOneBy(['slug' => $slug]);

        if (!$category) {
            return $this->json(['error' => 'Category not found'], 404);
        }

        $products = $this->repo->findByCategory($category);

        // Use the NEW serializer without variants
        $data = array_map(fn(Product $p) => $this->serializeProductWithoutVariants($p), $products);

        return $this->json($data);
    }

    /**
     * HTTP Method: GET
     * URL: /api/products/{id}/stock
     * Description: Retrieve the current stock of a product.
     **/
    #[Route('/{id}/stock', methods: ['GET'])]
    public function getStock(Product $product): JsonResponse
    {
        return $this->json([
            'stock' => $product->getStock()
        ]);
    }

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
        ];
    }

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
        ];
    }
}
