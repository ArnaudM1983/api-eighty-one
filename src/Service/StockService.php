<?php

namespace App\Service;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Decrements the stock levels for a validated order.
     * Handles both standalone products and specific product variants.
     */
    public function decrementStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();
            $qty = $item->getQuantity();

            if ($variant) {
                // If a variant exists (e.g., Size M), decrement the variant's stock
                $newStock = $variant->getStock() - $qty;
                // Use max(0, ...) to prevent negative stock values
                $variant->setStock(max(0, $newStock)); 
                
            } else {
                // Standalone product without variants
                $newStock = $product->getStock() - $qty;
                $product->setStock(max(0, $newStock));
            }
        }
        $this->em->flush();
    }

    /**
     * Increments the stock levels back (Order Cancellation).
     * Restores stock for products and variants when an order is cancelled.
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