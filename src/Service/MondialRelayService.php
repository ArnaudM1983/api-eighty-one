<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SERVICE : MondialRelayService (Backend - Symfony)
 * * RÔLE :
 * 1. Interface unique pour communiquer avec l'API SOAP de Mondial Relay (WSI4).
 * 2. Gérer l'authentification et le calcul des hashs MD5 requis.
 * 3. Construire et envoyer les requêtes SOAP (e.g., recherche de Points Relais).
 * 4. Parser la réponse XML reçue et la convertir en un tableau PHP standard.
 * 5. Gérer les erreurs de statut HTTP et les erreurs internes de code STAT.
 */

class MondialRelayService
{
    // --- CONSTANTES D'AUTHENTIFICATION & D'ACTION ---
    private const ENSEIGNE = 'CC22ZCS1';
    private const CLE_PRIVEE = 'iva9oG9F';
    private const ACTION_CODE = '24R';
    private const MR_NAMESPACE = 'http://www.mondialrelay.fr/webservice/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger
    ) {}

    // ----------------------------------------------------------------------------------
    // 1. RECHERCHE DES POINTS RELAIS
    // ----------------------------------------------------------------------------------

    public function searchPointsRelais(string $postalCode, string $countryCode, float $weightInKg): array
    {
        // Paramètres pour le hash MD5
        $numPointRelais = '';
        $ville = '';
        $latitude = '';
        $longitude = '';
        $taille = '';
        $poidsHashValue = (string) round($weightInKg * 1000); // 1.0 kg -> '1000'
        $typeActivite = '';

        $action = self::ACTION_CODE;
        $delaiEnvoi = '0';
        $rayonRecherche = '50';
        $nombreResultats = '30';

        // --- SÉCURISATION DES CONSTANTES ---
        $enseigne = trim(self::ENSEIGNE);
        $clePrivee = trim(self::CLE_PRIVEE);
        // ------------------------------------

        // 1. Construction de la Chaîne pour le Hash MD5 (ORDRE CRITIQUE MR WSI4)
        $concatString = $enseigne
            . $countryCode . $numPointRelais . $ville . $postalCode . $latitude . $longitude . $taille
            . $poidsHashValue
            . $action . $delaiEnvoi . $rayonRecherche . $typeActivite . $nombreResultats
            . $clePrivee;

        $this->logger->info("MR MD5 String (COMPLETE): " . $concatString);

        // 2. Calcul du Hash MD5 en MAJUSCULES
        $securityHash = strtoupper(md5($concatString));

        // 3. Construction du corps SOAP
        $xmlBody = $this->buildSoapRequest(
            $postalCode,
            $countryCode,
            $securityHash,
            $nombreResultats
        );

        try {
            // 4. Appel HTTP au Web Service
            $response = $this->httpClient->request('POST', 'https://api.mondialrelay.com/Web_Services.asmx', [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => self::MR_NAMESPACE . 'WSI4_PointRelais_Recherche',
                ],
                'body' => $xmlBody,
            ]);

            $content = $response->getContent();
            $statusCode = $response->getStatusCode();

            // --- DEBOGAGE : Sauvegarde du XML brut pour inspection (À RETIRER APRÈS DEBOGAGE) ---
            $logPathDebug = $this->parameterBag->get('kernel.logs_dir') . '/mr_debug_' . time() . '.xml';
            file_put_contents($logPathDebug, $content);
            $this->logger->info("Réponse XML Mondial Relay enregistrée pour inspection : " . $logPathDebug);
            // ------------------------------------------------------------------------------------

            if ($statusCode !== 200) {
                $logPath = $this->parameterBag->get('kernel.logs_dir') . '/mr_error_' . time() . '.xml';
                // file_put_contents($logPath, $content); // Déjà enregistré dans le bloc debug
                $errorMessage = "Erreur HTTP {$statusCode} lors de l'appel Mondial Relay (Réponse enregistrée).";
                $this->logger->error($errorMessage);
                throw new Exception($errorMessage);
            }

            // 5. Interprétation de la réponse
            libxml_use_internal_errors(true);
            $xmlResponse = simplexml_load_string($content);

            if ($xmlResponse === false) {
                $this->logger->error("Erreur de parsing XML pour Mondial Relay. Contenu: " . $content);
                throw new Exception("Erreur de parsing XML après connexion réussie.");
            }

            $namespaces = $xmlResponse->getNamespaces(true);

            // Si le namespace 'soap' est 'env' ou autre, ajuster ici
            $soapBody = $xmlResponse->children($namespaces['soap'])->Body;

            // La réponse WSI4 utilise le namespace MR_NAMESPACE
            $mrResponse = $soapBody->children(self::MR_NAMESPACE)->WSI4_PointRelais_RechercheResponse;
            $result = $mrResponse->WSI4_PointRelais_RechercheResult ?? null;

            // Vérification de Fault (nécessaire si le hash est 97)
            if (!$result) {
                $fault = $xmlResponse->xpath('//faultstring');
                $errorMessage = (string) ($fault[0] ?? "Réponse MR inattendue ou structure invalide.");
                $this->logger->error("Réponse MR invalide (No Result): " . $errorMessage);
                throw new Exception("Réponse SOAP invalide : " . $errorMessage);
            }

            // Le code STAT est généralement directement sous le WSI4_PointRelais_RechercheResult
            $statCode = (string) ($result->STAT ?? '99');

            if ($statCode !== '0') {
                $errorMessage = "Erreur Mondial Relay (Code {$statCode}). Code postal: {$postalCode}.";
                $this->logger->error("Mondial Relay STAT code error: " . $statCode);
                throw new Exception($errorMessage);
            }

            // Si STAT = 0, on parse les PointsRelais.
            return $this->formatPudosResponse($result->PointsRelais->PointRelais_Details ?? []);
        } catch (\Exception $e) {
            $this->logger->critical("Exception fatale dans MondialRelayService: " . $e->getMessage());
            throw $e;
        }
    }

    private function buildSoapRequest(string $cp, string $pays, string $security, string $nbResultats): string
    {
        $enseigne = self::ENSEIGNE;
        $actionCode = self::ACTION_CODE;
        $mrNamespace = self::MR_NAMESPACE;

        return <<<XML
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <WSI4_PointRelais_Recherche xmlns="$mrNamespace">
      <Enseigne>$enseigne</Enseigne>
      <Pays>$pays</Pays>
      <NumPointRelais xsi:nil="true" />
      <Ville xsi:nil="true" />
      <CP>$cp</CP>
      <Latitude xsi:nil="true" />
      <Longitude xsi:nil="true" />
      <Taille xsi:nil="true" />
      <Action>$actionCode</Action>
      <DelaiEnvoi>0</DelaiEnvoi>
      <RayonRecherche>50</RayonRecherche>
      <TypeActivite xsi:nil="true" />
      <NombreResultats>$nbResultats</NombreResultats>
      <Security>$security</Security>
    </WSI4_PointRelais_Recherche>
  </soap:Body>
</soap:Envelope>
XML;
    }

    private function formatPudosResponse(object $pointsRelais): array
    {
        $formatted = [];
        foreach ($pointsRelais as $pudo) {

            // Extraction du Numéro (id)
            $pudoNum = (string) ($pudo->Num ?? '');

            // --- CORRECTION 1 : Extraction des Coordonnées (Gestion de la virgule) ---

            $latitudeStr = trim((string) ($pudo->Latitude ?? ''));
            $longitudeStr = trim((string) ($pudo->Longitude ?? ''));

            // Remplacer la virgule par un point pour garantir le casting en float
            $latitudeStr = str_replace(',', '.', $latitudeStr);
            $longitudeStr = str_replace(',', '.', $longitudeStr);

            $latitude = !empty($latitudeStr) ? (float) $latitudeStr : null;
            $longitude = !empty($longitudeStr) ? (float) $longitudeStr : null;

            // --- CORRECTION 2 : Extraction du Nom et de l'Adresse (Utiliser LgAdr1 pour le nom) ---

            // LgAdr1 contient le nom du commerce (e.g., "LOCKER ECO LAVERIE...")
            $commerceName = trim((string) ($pudo->LgAdr1 ?? ''));

            // LgAdr2 semble être le complément de nom (laissez la vérification LgAdr2 en second)
            // Note: Lgdr1/Lgdr2 (anciennes balises) ne sont pas utilisées.
            if (empty($commerceName)) {
                $commerceName = trim((string) ($pudo->LgAdr2 ?? ''));
            }

            // L'adresse de rue est dans LgAdr3 ou LgAdr2 (selon le format MR)
            $streetAddress = trim((string) ($pudo->LgAdr3 ?? ''));
            if (empty($streetAddress)) {
                $streetAddress = trim((string) ($pudo->LgAdr2 ?? ''));
            }
            // Si LgAdr3 et LgAdr2 sont vides, essayons LgAdr1 si ce n'est pas le nom du commerce
            if (empty($streetAddress)) {
                $streetAddress = trim((string) ($pudo->LgAdr1 ?? ''));
            }

            $formatted[] = [
                'id' => $pudoNum,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance' => (int) ($pudo->Distance ?? 0),
                'name' => $commerceName,
                'address' => $streetAddress,
                'postalCode' => (string) ($pudo->CP ?? ''),
                'city' => (string) ($pudo->Ville ?? ''),
                'country' => (string) ($pudo->Pays ?? ''),
            ];
        }
        return $formatted;
    }
}
