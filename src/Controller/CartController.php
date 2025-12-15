<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Cookie;

#[Route('/api/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $cartRepo,
        private ProductRepository $productRepo,
        private ProductVariantRepository $variantRepo
    ) {}

    /**
     * Retrieve cart from cookie token
     * Private helper method
     */
    private function getCartFromCookie(Request $request): ?Cart
    {
        $token = $request->cookies->get('cart_token');
        return $token ? $this->cartRepo->findOneByToken($token) : null;
    }

    /**
     * Create a cart if user has no token
     * Private helper method
     */
    private function createCart(): Cart
    {
        $cart = new Cart();
        $cart->setToken(bin2hex(random_bytes(16)));
        $this->em->persist($cart);
        $this->em->flush();
        return $cart;
    }

    /**
     * Attach cart cookie to a JsonResponse
     * Private helper method
     */
    private function setCookieResponse(JsonResponse $response, Cart $cart)
    {
        $response->headers->setCookie(
            new Cookie(
                'cart_token',
                $cart->getToken(),
                strtotime('+30 days'),
                '/',
                null,
                false,
                true,
                false,
                'lax'
            )
        );

        return $response;
    }


    /**
     * CRUD: Create item in cart
     * HTTP Method: POST
     * URL: /api/cart/add
     * Body: { productId, variantId?, quantity }
     */
    #[Route('/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['productId'], $data['quantity'])) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $cart = $this->getCartFromCookie($request) ?? $this->createCart();

        $product = $this->productRepo->find($data['productId']);
        if (!$product) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        $variant = isset($data['variantId']) ? $this->variantRepo->find($data['variantId']) : null;

        $requestedQty = (int)$data['quantity'];
        if ($requestedQty <= 0) {
            return $this->json([
                'success' => true,
                'message' => 'Quantité nulle, aucun item ajouté au panier'
            ]);
        }

        // Vérification du stock réel
        $availableStock = $variant ? $variant->getStock() : $product->getStock();
        if ($requestedQty > $availableStock) {
            return $this->json([
                'error' => 'Stock insuffisant',
                'available' => $availableStock
            ], 400);
        }

        $price = $variant ? $variant->getPrice() : $product->getPrice();
        $unitWeight = $variant ? $variant->getWeight() : $product->getWeight();
        
        // Vérifie si l’item existe déjà dans le panier
        $existing = null;
        foreach ($cart->getItems() as $item) {
            if (
                $item->getProduct()->getId() === $product->getId()
                && ($variant ? $item->getVariant()?->getId() === $variant->getId() : $item->getVariant() === null)
            ) {
                $existing = $item;
                break;
            }
        }

        if ($existing) {
            $newQty = $existing->getQuantity() + $requestedQty;
            if ($newQty > $availableStock) {
                return $this->json([
                    'error' => 'Stock insuffisant',
                    'available' => $availableStock
                ], 400);
            }
            $existing->setQuantity($newQty);
            
        } else {
            $item = (new CartItem())
                ->setProduct($product)
                ->setVariant($variant)
                ->setPrice($price)
                ->setQuantity($requestedQty)
                ->setWeight($unitWeight) 
                ->setCart($cart);

            $cart->addItem($item);
        }

        $this->em->flush();

        $response = $this->json([
            'success' => true,
            'cartToken' => $cart->getToken(),
            'totalItems' => $cart->getTotalQuantity(),
            'availableStock' => $availableStock
        ]);

        return $this->setCookieResponse($response, $cart);
    }


    /**
     * CRUD: Get cart content
     * HTTP Method: GET
     * URL: /api/cart
     * Returns cart items + total price
     */
    #[Route('', name: 'cart_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        // Récupère le token depuis query param ou cookie
        $token = $request->query->get('cartToken') ?? $request->cookies->get('cart_token');

        if (!$token) {
            return $this->json([
                'items' => [],
                'total' => 0,
                'cartToken' => null,
                'totalWeight' => 0.0, 
            ]);
        }

        $cart = $this->cartRepo->findOneByToken($token);

        if (!$cart) {
            return $this->json([
                'items' => [],
                'total' => 0,
                'cartToken' => $token,
                'totalWeight' => 0.0, 
            ]);
        }

        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;

        $items = array_map(function ($i) use ($formatImagePath) {
            $variantImage = $i->getVariant()?->getImage();
            $productImage = $i->getProduct()->getMainImage();
            $unitWeight = $i->getWeight() ?? 0.0;
            $totalWeight = $i->getTotalWeight(); 

            return [
                'itemId' => $i->getId(),
                'productId' => $i->getProduct()->getId(),
                'variantId' => $i->getVariant()?->getId(),
                'name' => $i->getProduct()->getName(),
                'quantity' => $i->getQuantity(),
                'price' => $i->getPrice(),
                'total' => $i->getPrice() * $i->getQuantity(),
                'weight' => $unitWeight,
                'totalWeight' => $totalWeight,
                
                'image' => $formatImagePath($variantImage ?? $productImage)
            ];
        }, $cart->getItems()->toArray());

        return $this->json([
            'items' => $items,
            'total' => $cart->getTotalPrice(),
            'cartToken' => $cart->getToken(),
            'totalWeight' => $cart->getTotalWeight(), 
        ]);
    }


    /**
     * CRUD: Update quantity of a cart item
     * HTTP Method: PUT
     * URL: /api/cart/update/{itemId}
     * Body: { quantity }
     */
    #[Route('/update/{itemId}', name: 'cart_update', methods: ['PUT'])]
    public function update(Request $request, int $itemId): JsonResponse
    {
        $item = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$item) {
            return $this->json(['error' => 'Item introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['quantity'])) {
            return $this->json(['error' => 'Quantité manquante'], 400);
        }

        $requestedQty = (int)$data['quantity'];
        if ($requestedQty <= 0) {
            return $this->json(['error' => 'Quantité invalide'], 400);
        }

        // Vérification du stock réel
        $availableStock = $item->getVariant() ? $item->getVariant()->getStock() : $item->getProduct()->getStock();
        if ($requestedQty > $availableStock) {
            return $this->json([
                'error' => 'Stock insuffisant',
                'available' => $availableStock
            ], 400);
        }

        // Mise à jour de la quantité
        $item->setQuantity($requestedQty);
        $this->em->flush();

        $cart = $item->getCart();
        
        return $this->json([
            'success' => true,
            'itemId' => $item->getId(),
            'quantity' => $item->getQuantity(),
            'availableStock' => $availableStock,
            'totalWeight' => $cart->getTotalWeight() 
        ]);
    }


    /**
     * CRUD: Clear cart (remove all items)
     * HTTP Method: DELETE
     * URL: /api/cart/clear
     */
    #[Route('/clear', name: 'cart_clear', methods: ['DELETE'])]
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getCartFromCookie($request);
        if (!$cart) {
            return $this->json(['success' => true, 'message' => 'Panier déjà vide']);
        }

        foreach ($cart->getItems() as $item) {
            $this->em->remove($item);
        }

        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Panier vidé']);
    }


    /**
     * CRUD: Remove one item from cart
     * HTTP Method: DELETE
     * URL: /api/cart/remove/{itemId}
     */
    #[Route('/remove/{itemId}', name: 'cart_remove', methods: ['DELETE'])]
    public function remove(int $itemId): JsonResponse
    {
        $item = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$item) return $this->json(['error' => 'Item introuvable'], 404);

        $this->em->remove($item);
        $this->em->flush();
        
        $cart = $item->getCart();

        return $this->json([
            'success' => true, 
            'message' => 'Item supprimé',
            'totalWeight' => $cart->getTotalWeight() 
        ]);
    }
}