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

#[Route('/api/shipping_tariffs')]
class ShippingTariffController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private ShippingTariffRepository $shippingTariffRepository
    ) {}

    /**
     * Liste tous les tarifs de la grille.
     */
    #[Route('', name: 'api_shipping_tariff_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $tariffs = $this->shippingTariffRepository->findBy([], [
            'countryCode' => 'ASC', 
            'modeCode' => 'ASC', 
            'weightMaxG' => 'ASC'
        ]);

        // Suppression du contexte 'groups'. Symfony va normaliser tous les champs publics/getters par défaut.
        return $this->json($tariffs);
    }

    /**
     * Crée un nouveau palier tarifaire.
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
                    // Suppression du contexte 'groups' ici aussi
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

        return $this->json($tariff);
    }

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