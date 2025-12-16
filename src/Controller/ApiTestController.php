<?php

namespace App\Controller;

use App\Service\MondialRelayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Exception;

class ApiTestController extends AbstractController
{
    /**
     * Endpoint de test pour appeler le service Mondial Relay et afficher le résultat.
     */
    #[Route('/api/test/mondial-relay', name: 'api_test_mr', methods: ['GET'])]
    public function testMondialRelay(MondialRelayService $mrService, LoggerInterface $logger): Response
    {
        // --- Paramètres de Test ---
        $postalCode = '69003'; // Lyon 3e
        $countryCode = 'FR';
        $weightInKg = 1.0;
        // --------------------------

        try {
            // 1. Appelle la méthode de recherche de votre service
            $pointsRelais = $mrService->searchPointsRelais($postalCode, $countryCode, $weightInKg);

            // 2. Si ça réussit, retourne les données formatées en JSON
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Points Relais trouvés et formatés.',
                'code_postal_test' => $postalCode,
                'results_count' => count($pointsRelais),
                'points_relais' => $pointsRelais
            ]);

        } catch (Exception $e) {
            // 3. En cas d'échec (Hash MD5 incorrect, statut non 200, ou STAT !== 0)
            $logger->error('Erreur lors du test MR : ' . $e->getMessage());

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'appel Mondial Relay.',
                'details' => $e->getMessage(),
                'solution' => 'Vérifiez le Hash MD5, la Clé Privée et le Code Enseigne dans MondialRelayService.php.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // NOUVEAU : Endpoint pour afficher le XML brut après échec du statut HTTP
    #[Route('/api/test/mr/last-error-xml', name: 'api_test_mr_xml', methods: ['GET'])]
    public function showLastErrorXml(): Response
    {
        // Cette méthode sert à vérifier si votre logique d'enregistrement du XML de votre service a fonctionné.
        // Recherchez dans le dossier des logs (var/log/) le fichier qui commence par 'mr_error_'.
        return new Response(
            'Vérifiez le dossier `var/log/` de votre projet Symfony. ' .
            'Si une erreur HTTP (non 200) s\'est produite, le corps de la réponse XML a été enregistré dans un fichier de type `mr_error_XXXXXXXXXX.xml`.',
            Response::HTTP_OK
        );
    }

    /**
     * Endpoint de test pour appeler le service Colissimo et diagnostiquer l'erreur 500.
     * URL: /api/test/colissimo
     */
    #[Route('/api/test/colissimo', name: 'api_test_colissimo', methods: ['GET'])]
    public function testColissimo(\App\Service\ColissimoService $colissimoService): JsonResponse
    {
        // --- Paramètres de Test Réels (Utilisez des vrais pour éviter les rejets) ---
        $zipCode = '75001'; // Paris 1er
        $city = 'Paris';
        $address = '1 rue de Rivoli';
        $weightInKg = 1.2;
        // --------------------------------------------------------------------------

        try {
            $pointsColissimo = $colissimoService->searchPointsRelais(
                $zipCode, 
                $city, 
                $address, 
                'FR', 
                $weightInKg
            );

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Connexion Colissimo réussie.',
                'params_test' => [
                    'zip' => $zipCode,
                    'address' => $address,
                    'weight_grams' => $weightInKg * 1000
                ],
                'count' => count($pointsColissimo),
                'results' => $pointsColissimo
            ]);

        } catch (\Exception $e) {
            // C'est ici qu'on va voir le message d'erreur de La Poste (ex: API KEY invalide)
            return new JsonResponse([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'advice' => 'Si vous voyez une Erreur 500 ici, vérifiez votre COLISSIMO_API_KEY dans le .env.local.'
            ], 500);
        }
    }
}