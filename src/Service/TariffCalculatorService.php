<?php

namespace App\Service;

use App\Repository\ShippingTariffRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TariffCalculatorService
{
    // Déclaration des propriétés
    private ShippingTariffRepository $shippingTariffRepository;
    private ParameterBagInterface $parameterBag;

    // Taux de TVA 
    private const TVA_RATE_FRANCE = 0.20; 

    public function __construct(
        ShippingTariffRepository $shippingTariffRepository,
        ParameterBagInterface $parameterBag
    ) {
        $this->shippingTariffRepository = $shippingTariffRepository;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Calcule le coût final (TTC) d'expédition en fonction du poids, du mode et du pays.
     * @param float $weightInKg Poids total du colis en kilogrammes.
     * @param string $modeCode Code du mode de livraison (ex: 'pr', 'locker').
     * @param string $countryCode Code ISO du pays (ex: 'FR').
     * @return float Le coût de livraison TTC.
     * @throws \Exception Si aucun tarif n'est trouvé pour le poids/mode donné.
     */
    public function calculateShippingCost(float $weightInKg, string $modeCode, string $countryCode = 'FR'): float
    {
        $weightInGrams = (int) ($weightInKg * 1000);

        $tariff = $this->shippingTariffRepository->findTariff(
            $countryCode,
            $modeCode,
            $weightInGrams
        );

        if (!$tariff) {
            throw new \Exception(sprintf(
                "Aucun tarif trouvé pour %s/%s avec un poids max de %d grammes.",
                $countryCode,
                $modeCode,
                $weightInGrams
            ));
        }

        $priceHt = (float) $tariff->getPriceHt();
        
        // Application de la TVA
        $tvaRate = self::TVA_RATE_FRANCE; 
        
        $priceTtc = $priceHt * (1 + $tvaRate);
        
        return round($priceTtc, 2);
    }
}