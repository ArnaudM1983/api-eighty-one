<?php

namespace App\Controller;

use App\Service\MondialRelayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Exception;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ApiTestController extends AbstractController
{
    /**
     * Test endpoint to call Mondial Relay service and display formatted results.
     */
    #[Route('/api/test/mondial-relay', name: 'api_test_mr', methods: ['GET'])]
    public function testMondialRelay(MondialRelayService $mrService, LoggerInterface $logger): Response
    {
        // --- Test Parameters ---
        $postalCode = '69003'; // Lyon 3e
        $countryCode = 'FR';
        $weightInKg = 1.0;
        // --------------------------

        try {
            $pointsRelais = $mrService->searchPointsRelais($postalCode, $countryCode, $weightInKg);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Points Relais trouvés et formatés.',
                'code_postal_test' => $postalCode,
                'results_count' => count($pointsRelais),
                'points_relais' => $pointsRelais
            ]);
        } catch (Exception $e) {
            $logger->error('Erreur lors du test MR : ' . $e->getMessage());

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'appel Mondial Relay.',
                'details' => $e->getMessage(),
                'solution' => 'Vérifiez le Hash MD5, la Clé Privée et le Code Enseigne dans MondialRelayService.php.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Endpoint to provide guidance on how to check raw XML errors.
     * Note: XML logs are stored in the var/log/ directory when an HTTP error occurs.
     */
    #[Route('/api/test/mr/last-error-xml', name: 'api_test_mr_xml', methods: ['GET'])]
    public function showLastErrorXml(): Response
    {
        return new Response(
            'Vérifiez le dossier `var/log/` de votre projet Symfony. ' .
                'Si une erreur HTTP (non 200) s\'est produite, le corps de la réponse XML a été enregistré dans un fichier de type `mr_error_XXXXXXXXXX.xml`.',
            Response::HTTP_OK
        );
    }

    
}
