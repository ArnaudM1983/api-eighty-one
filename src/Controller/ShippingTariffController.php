<?php

namespace App\Controller;

use App\Entity\ShippingTariff;
use App\Repository\ShippingTariffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/shipping_tariffs')]
#[IsGranted('ROLE_ADMIN')] // Security: Access restricted to administrators for managing price grids
class ShippingTariffController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private ShippingTariffRepository $shippingTariffRepository
    ) {}

    /**
     * Lists all shipping tariffs in the price grid.
     * Ordered by country, shipping mode, and weight for clarity.
     */
    #[Route('', name: 'api_shipping_tariff_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $tariffs = $this->shippingTariffRepository->findBy([], [
            'countryCode' => 'ASC', 
            'modeCode' => 'ASC', 
            'weightMaxG' => 'ASC'
        ]);

        // Default normalization (serializes all public getters/properties)
        return $this->json($tariffs);
    }

    /**
     * Creates a new shipping tariff entry.
     * Uses Symfony Serializer to map JSON to Entity and Validator for data integrity.
     */
    #[Route('', name: 'api_shipping_tariff_new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        try {
            $tariff = $this->serializer->deserialize(
                $request->getContent(), 
                ShippingTariff::class, 
                'json'
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides.'], 400);
        }

        // Data Validation (Checks for @Assert constraints in the entity)
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

        return $this->json($tariff, 201);
    }

    /**
     * Updates an existing shipping tariff entry.
     * Uses 'object_to_populate' to merge request data into the existing entity instance.
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
                ]
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Données JSON invalides.'], 400);
        }
        
        // Ensure the updated entity still respects constraints
        $errors = $this->validator->validate($tariff);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        $this->em->flush();

        return $this->json($tariff);
    }

    /**
     * Deletes a shipping tariff entry.
     */
    #[Route('/{id}', name: 'api_shipping_tariff_delete', methods: ['DELETE'])]
    public function delete(ShippingTariff $tariff): JsonResponse
    {
        $this->em->remove($tariff);
        $this->em->flush();

        return new JsonResponse(null, 204); 
    }
}