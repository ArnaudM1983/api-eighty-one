<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StockServiceTest extends TestCase
{
    public function testDecrementStockStandaloneProduct(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $product = new Product();
        $product->setStock(10);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(3);

        $order = new Order();
        $order->addItem($item);

        $service = new StockService($emMock);
        $service->decrementStock($order);

        $this->assertEquals(7, $product->getStock());
    }

    public function testDecrementStockProductVariant(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $product = new Product();
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setStock(5);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setQuantity(2);

        $order = new Order();
        $order->addItem($item);

        $service = new StockService($emMock);
        $service->decrementStock($order);

        $this->assertEquals(3, $variant->getStock());
    }

    public function testDecrementStockNegativeLimit(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $product = new Product();
        $product->setStock(5);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(10);

        $order = new Order();
        $order->addItem($item);

        $service = new StockService($emMock);
        $service->decrementStock($order);

        $this->assertEquals(0, $product->getStock());
    }

    public function testIncrementStockStandaloneProduct(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $product = new Product();
        $product->setStock(10);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(5);

        $order = new Order();
        $order->addItem($item);

        $service = new StockService($emMock);
        $service->incrementStock($order);

        $this->assertEquals(15, $product->getStock());
    }

    public function testIncrementStockProductVariant(): void
    {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('flush');

        $product = new Product();
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setStock(4);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setVariant($variant);
        $item->setQuantity(3);

        $order = new Order();
        $order->addItem($item);

        $service = new StockService($emMock);
        $service->incrementStock($order);

        $this->assertEquals(7, $variant->getStock());
    }
}
