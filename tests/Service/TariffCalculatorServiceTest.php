<?php

namespace App\Tests\Service;

use App\Entity\ShippingTariff;
use App\Repository\ShippingTariffRepository;
use App\Service\TariffCalculatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TariffCalculatorServiceTest extends TestCase
{
    public function testCalculateShippingCostSuccess(): void
    {
        $tariffRepositoryMock = $this->createMock(ShippingTariffRepository::class);
        $parameterBagMock = $this->createMock(ParameterBagInterface::class);

        $tariff = new ShippingTariff();
        $tariff->setCountryCode('FR');
        $tariff->setModeCode('pr');
        $tariff->setWeightMaxG(1000);
        $tariff->setPriceHt('10.00'); // 10.00 € HT

        $tariffRepositoryMock
            ->expects($this->once())
            ->method('findTariff')
            ->with('FR', 'pr', 500)
            ->willReturn($tariff);

        $service = new TariffCalculatorService($tariffRepositoryMock, $parameterBagMock);
        
        // 10.00 € HT * 1.20 = 12.00 € TTC
        $cost = $service->calculateShippingCost(0.5, 'pr', 'FR');

        $this->assertEquals(12.00, $cost);
    }

    public function testCalculateShippingCostRounding(): void
    {
        $tariffRepositoryMock = $this->createMock(ShippingTariffRepository::class);
        $parameterBagMock = $this->createMock(ParameterBagInterface::class);

        $tariff = new ShippingTariff();
        $tariff->setPriceHt('10.41'); // 10.41 € HT

        $tariffRepositoryMock
            ->method('findTariff')
            ->willReturn($tariff);

        $service = new TariffCalculatorService($tariffRepositoryMock, $parameterBagMock);
        
        // 10.41 € HT * 1.20 = 12.492 € TTC -> arrondi à 12.49
        $cost = $service->calculateShippingCost(0.5, 'pr', 'FR');

        $this->assertEquals(12.49, $cost);
    }

    public function testCalculateShippingCostNotFoundThrowsException(): void
    {
        $tariffRepositoryMock = $this->createMock(ShippingTariffRepository::class);
        $parameterBagMock = $this->createMock(ParameterBagInterface::class);

        $tariffRepositoryMock
            ->method('findTariff')
            ->willReturn(null);

        $service = new TariffCalculatorService($tariffRepositoryMock, $parameterBagMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Aucun tarif trouvé pour FR/pr avec un poids max de 500 grammes.");

        $service->calculateShippingCost(0.5, 'pr', 'FR');
    }
}
