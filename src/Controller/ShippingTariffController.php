<?php

namespace App\Controller\Api;

use App\Entity\ShippingTariff;
use App\Repository\ShippingTariffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/shipping_tariffs')]
class ShippingTariffController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private ShippingTariffRepository $shippingTariffRepository
    ) {}

    // --- LECTURE (READ : GET /api/shipping_tariffs) ---
    /**
     * Liste tous les tarifs de la grille.
     */
    #[Route('', name: 'api_shipping_tariff_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // Récupère toutes les entrées, triées par pays, mode et poids pour la lisibilité
        $tariffs = $this->shippingTariffRepository->findBy([], [
            'countryCode' => 'ASC', 
            'modeCode' => 'ASC', 
            'weightMaxG' => 'ASC'
        ]);

        return $this->json($tariffs, 200, [], [
            'groups' => ['tariff:read'], 
        ]);
    }

    // --- CRÉATION (CREATE : POST /api/shipping_tariffs) ---
    /**
     * Crée un nouveau palier tarifaire.
     */
    #[Route('', name: 'api_shipping_tariff_new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        try {
            /** @var ShippingTariff $tariff */
            $tariff = $this->serializer->deserialize(
                $request->getContent(), 
                ShippingTariff::class, 
                'json'
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides.'], 400);
        }

        $errors = $this->validator->validate($tariff);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        $this->em->persist($tariff);
        $this->em->flush();

        return $this->json($tariff, 201, [], ['groups' => ['tariff:read']]);
    }

    // --- MISE À JOUR (UPDATE : PUT /api/shipping_tariffs/{id}) ---
    /**
     * Met à jour un palier tarifaire existant.
     */
    #[Route('/{id}', name: 'api_shipping_tariff_edit', methods: ['PUT'])]
    public function edit(Request $request, ShippingTariff $tariff): JsonResponse
    {
        try {
            $tariff = $this->serializer->deserialize(
                $request->getContent(), 
                ShippingTariff::class, 
                'json', 
                [
                    'object_to_populate' => $tariff,
                    'groups' => ['tariff:read'],
                ]
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides.'], 400);
        }
        
        $errors = $this->validator->validate($tariff);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        $this->em->flush();

        return $this->json($tariff, 200, [], ['groups' => ['tariff:read']]);
    }

    // --- SUPPRESSION (DELETE : DELETE /api/shipping_tariffs/{id}) ---
    /**
     * Supprime un palier tarifaire.
     */
    #[Route('/{id}', name: 'api_shipping_tariff_delete', methods: ['DELETE'])]
    public function delete(ShippingTariff $tariff): JsonResponse
    {
        $this->em->remove($tariff);
        $this->em->flush();

        return new JsonResponse(null, 204); 
    }
}