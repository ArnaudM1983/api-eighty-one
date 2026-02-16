<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Décrémente le stock pour une commande validée
     */
    public function decrementStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();
            $qty = $item->getQuantity();

            if ($variant) {
                // Si c'est une variante (ex: Taille M), on décrémente la variante
                $newStock = $variant->getStock() - $qty;
                $variant->setStock(max(0, $newStock)); // On évite le négatif
                
                // Optionnel : Si tu gères aussi un stock global sur le parent
                // $product->setStock($product->getStock() - $qty);
            } else {
                // Produit simple sans variante
                $newStock = $product->getStock() - $qty;
                $product->setStock(max(0, $newStock));
            }
        }
        $this->em->flush();
    }

    /**
     * Ré-incrémente le stock (Annulation de commande)
     */
    public function incrementStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();
            $qty = $item->getQuantity();

            if ($variant) {
                $variant->setStock($variant->getStock() + $qty);
            } else {
                $product->setStock($product->getStock() + $qty);
            }
        }
        $this->em->flush();
    }
}