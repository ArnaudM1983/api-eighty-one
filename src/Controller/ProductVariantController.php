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
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
     * Description : Handles the creation of a variant with property inheritance from the parent product.
     **/
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $variant = new ProductVariant();
        $variant->setName($data['name'] ?? '');
        $variant->setSku($data['sku'] ?? null);

        // Stock, Image, and Attributes (No inheritance required for these)
        $variant->setStock(isset($data['stock']) ? (int)$data['stock'] : 0);
        $variant->setImage($data['image'] ?? null);

        // Clean attribute handling (set to NULL if empty to avoid empty JSON arrays)
        $attributes = $data['attributes'] ?? null;
        $variant->setAttributes(!empty($attributes) ? $attributes : null);

        // PARENT PRODUCT ASSOCIATION & DATA INHERITANCE
        $pId = $data['product'] ?? $data['product_id'] ?? null;

        if ($pId) {
            $product = $this->em->getRepository(Product::class)->find($pId);

            if (!$product) {
                return $this->json(['error' => 'Produit parent introuvable'], 404);
            }

            $variant->setProduct($product);

            // --- 1. PRICE INHERITANCE LOGIC ---
            // Use provided price or fallback to parent product price
            if (!empty($data['price'])) {
                $variant->setPrice($data['price']);
            } else {
                $variant->setPrice($product->getPrice());
            }

            if (array_key_exists('sale_price', $data)) {
                $variant->setSalePrice($data['sale_price'] !== '' ? $data['sale_price'] : null);
            } else {
                $variant->setSalePrice($product->getSalePrice());
            }

            if (array_key_exists('special_price_from', $data)) {
                $variant->setSpecialPriceFrom(!empty($data['special_price_from']) ? new \DateTimeImmutable($data['special_price_from']) : null);
            } else {
                $variant->setSpecialPriceFrom($product->getSpecialPriceFrom());
            }

            if (array_key_exists('special_price_to', $data)) {
                $variant->setSpecialPriceTo(!empty($data['special_price_to']) ? new \DateTimeImmutable($data['special_price_to']) : null);
            } else {
                $variant->setSpecialPriceTo($product->getSpecialPriceTo());
            }

            if (array_key_exists('promo_text', $data)) {
                $variant->setPromoText($data['promo_text'] !== '' ? $data['promo_text'] : null);
            } else {
                $variant->setPromoText($product->getPromoText());
            }

            // --- 2. WEIGHT INHERITANCE LOGIC ---
            // Use provided weight or fallback to parent product weight
            if (isset($data['weight']) && $data['weight'] !== '' && $data['weight'] !== null) {
                $variant->setWeight((float)$data['weight']);
            } else {
                $variant->setWeight($product->getWeight());
            }
        } else {
            return $this->json(['error' => 'L\'ID du produit parent est requis'], 400);
        }

        $this->em->persist($variant);
        $this->em->flush();

        return $this->json($this->serializeVariant($variant), 201);
    }

    /**
     * CRUD: Update
     * HTTP Method: PUT
     * URL: /api/variants/{id}
     * Description: Update a variant.
     **/
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $variant->setName($data['name']);
        if (isset($data['sku'])) $variant->setSku($data['sku']);
        if (isset($data['price'])) $variant->setPrice($data['price']);
        if (array_key_exists('sale_price', $data)) $variant->setSalePrice($data['sale_price'] !== '' ? $data['sale_price'] : null);
        if (array_key_exists('special_price_from', $data)) $variant->setSpecialPriceFrom(!empty($data['special_price_from']) ? new \DateTimeImmutable($data['special_price_from']) : null);
        if (array_key_exists('special_price_to', $data)) $variant->setSpecialPriceTo(!empty($data['special_price_to']) ? new \DateTimeImmutable($data['special_price_to']) : null);
        if (array_key_exists('promo_text', $data)) $variant->setPromoText($data['promo_text'] !== '' ? $data['promo_text'] : null);
        if (isset($data['stock'])) $variant->setStock($data['stock']);
        if (array_key_exists('weight', $data)) {
            if ($data['weight'] !== '' && $data['weight'] !== null) {
                $variant->setWeight((float)$data['weight']);
            } else {
                $product = $variant->getProduct();
                $variant->setWeight($product ? $product->getWeight() : 0.0);
            }
        }
        if (isset($data['image'])) $variant->setImage($data['image']);
        if (isset($data['attributes'])) $variant->setAttributes($data['attributes']);
        if (isset($data['active'])) $variant->setActive((bool)$data['active']);

        if (!empty($data['product_id'])) {
            $product = $this->em->getRepository(Product::class)->find($data['product_id']);
            if ($product) $variant->setProduct($product);
        }

        $this->em->flush();

        return $this->json(['message' => 'Variant updated']);
    }

    /**
     * CRUD: Post
     * HTTP Method: Post
     * URL: /api/reorder
     * Bulk reorder variant positions.
     **/
    #[Route('/reorder', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['variants']) || !is_array($data['variants'])) {
            return $this->json(['error' => 'Invalid data'], 400);
        }

        foreach ($data['variants'] as $item) {
            $variant = $this->repo->find($item['id']);
            if ($variant) {
                $variant->setPosition((int) $item['position']);
            }
        }

        $this->em->flush();

        return $this->json(['message' => 'Variant order updated']);
    }

    /**
     * CRUD: Delete
     * HTTP Method: DELETE
     * URL: /api/variants/{id}
     * Description: Delete an existing variant.
     **/
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(ProductVariant $variant): JsonResponse
    {
        $this->em->remove($variant);
        $this->em->flush();

        return $this->json(['message' => 'Variant deleted']);
    }

    /**
     * Helper method to format variant data for JSON responses.
     */
    private function serializeVariant(ProductVariant $v): array
    {
        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;

        return [
            'id' => $v->getId(),
            'name' => $v->getName(),
            'sku' => $v->getSku(),
            'price' => $v->getPrice(),
            'sale_price' => $v->getSalePrice(),
            'special_price_from' => $v->getSpecialPriceFrom() ? $v->getSpecialPriceFrom()->format('c') : null,
            'special_price_to' => $v->getSpecialPriceTo() ? $v->getSpecialPriceTo()->format('c') : null,
            'promo_text' => $v->getPromoText(),
            'final_price' => $v->getFinalPrice(),
            'is_on_sale' => $v->isOnSale(),
            'discount_label' => $v->isOnSale() ? ($v->getPromoText() ?: '-' . round((((float)$v->getPrice() - (float)$v->getSalePrice()) / (float)$v->getPrice()) * 100) . '%') : null,
            'promo_ends_at' => $v->getSpecialPriceTo() ? $v->getSpecialPriceTo()->format('c') : null,
            'stock' => $v->getStock(),
            'weight' => $v->getWeight(),
            'image' => $formatImagePath($v->getImage()),
            'attributes' => $v->getAttributes(),
            'position' => $v->getPosition(), 
            'active' => $v->isActive(),
            'product' => $v->getProduct() ? ['id' => $v->getProduct()->getId(), 'name' => $v->getProduct()->getName()] : null
        ];
    }
}
