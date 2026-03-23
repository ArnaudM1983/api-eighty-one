<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SERVICE: MondialRelayService (Backend - Symfony)
 * ROLE:
 * 1. Single interface to communicate with Mondial Relay SOAP API (WSI4).
 * 2. Manage authentication and mandatory MD5 security hashes.
 * 3. Build and send SOAP requests (e.g., Pick-up point search).
 * 4. Parse XML responses into standard PHP arrays.
 * 5. Handle HTTP status errors and internal API 'STAT' error codes.
 */

class MondialRelayService
{
    // --- AUTHENTICATION & ACTION CONSTANTS ---
    private const ENSEIGNE = 'CC22ZCS1';
    private const CLE_PRIVEE = 'iva9oG9F';
    private const ACTION_CODE = '24R';
    private const MR_NAMESPACE = 'http://www.mondialrelay.fr/webservice/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger
    ) {}

    /**
     * Search for nearby Pick-up Points (Points Relais).
     */
    public function searchPointsRelais(string $postalCode, string $countryCode, float $weightInKg): array
    {
        // Parameters for MD5 Hash
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

        // --- SECURITY: CREDENTIALS SANITIZATION ---
        $enseigne = trim(self::ENSEIGNE);
        $clePrivee = trim(self::CLE_PRIVEE);
        // ------------------------------------

        // 1. Construct the MD5 Hash String (STRICT MR WSI4 ORDER)
        $concatString = $enseigne
            . $countryCode . $numPointRelais . $ville . $postalCode . $latitude . $longitude . $taille
            . $poidsHashValue
            . $action . $delaiEnvoi . $rayonRecherche . $typeActivite . $nombreResultats
            . $clePrivee;

        $this->logger->info("MR MD5 String (COMPLETE): " . $concatString);

        // 2. Compute MD5 Hash in UPPERCASE
        $securityHash = strtoupper(md5($concatString));

        // 3. Build SOAP Envelope
        $xmlBody = $this->buildSoapRequest(
            $postalCode,
            $countryCode,
            $securityHash,
            $nombreResultats
        );

        try {
            // 4. HTTP POST request to the Web Service
            $response = $this->httpClient->request('POST', 'https://api.mondialrelay.com/Web_Services.asmx', [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => self::MR_NAMESPACE . 'WSI4_PointRelais_Recherche',
                ],
                'body' => $xmlBody,
            ]);

            $content = $response->getContent();
            $statusCode = $response->getStatusCode();


            if ($statusCode !== 200) {
                $logPath = $this->parameterBag->get('kernel.logs_dir') . '/mr_error_' . time() . '.xml';
                $errorMessage = "Erreur HTTP {$statusCode} lors de l'appel Mondial Relay (Réponse enregistrée).";
                $this->logger->error($errorMessage);
                throw new Exception($errorMessage);
            }

            // 5. Response Interpretation
            libxml_use_internal_errors(true);
            $xmlResponse = simplexml_load_string($content);

            if ($xmlResponse === false) {
                $this->logger->error("Erreur de parsing XML pour Mondial Relay. Contenu: " . $content);
                throw new Exception("Erreur de parsing XML après connexion réussie.");
            }

            $namespaces = $xmlResponse->getNamespaces(true);

            $soapBody = $xmlResponse->children($namespaces['soap'])->Body;

            // WSI4 response uses the MR_NAMESPACE
            $mrResponse = $soapBody->children(self::MR_NAMESPACE)->WSI4_PointRelais_RechercheResponse;
            $result = $mrResponse->WSI4_PointRelais_RechercheResult ?? null;

            // SOAP Fault Check
            if (!$result) {
                $fault = $xmlResponse->xpath('//faultstring');
                $errorMessage = (string) ($fault[0] ?? "Réponse MR inattendue ou structure invalide.");
                $this->logger->error("Réponse MR invalide (No Result): " . $errorMessage);
                throw new Exception("Réponse SOAP invalide : " . $errorMessage);
            }

            // STAT code check (STAT = 0 means Success)
            $statCode = (string) ($result->STAT ?? '99');

            if ($statCode !== '0') {
                $errorMessage = "Erreur Mondial Relay (Code {$statCode}). Code postal: {$postalCode}.";
                $this->logger->error("Mondial Relay STAT code error: " . $statCode);
                throw new Exception($errorMessage);
            }

            // Parse and format results if STAT = 0
            return $this->formatPudosResponse($result->PointsRelais->PointRelais_Details ?? []);
        } catch (\Exception $e) {
            $this->logger->critical("Exception fatale dans MondialRelayService: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Builds the raw SOAP XML request body.
     */
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

    /**
     * Formats daily XML schedule strings into a structured array.
     */
    private function formatDayHours(\SimpleXMLElement $horairesXml = null): array
    {
        if (!$horairesXml || !$horairesXml->string) {
            return [
                'am_start' => '',
                'am_end'   => '',
                'pm_start' => '',
                'pm_end'   => '',
            ];
        }

        $strings = [];
        foreach ($horairesXml->string as $str) {
            $strings[] = (string) $str;
        }

        $strings = array_pad($strings, 4, '');

        return [
            'am_start' => $strings[0],
            'am_end'   => $strings[1],
            'pm_start' => $strings[2],
            'pm_end'   => $strings[3],
        ];
    }

    /**
     * Formats the raw SOAP object into a clean PUDO (Pick-up Drop-off) array.
     */
    private function formatPudosResponse(object $pointsRelais): array
    {
        $formatted = [];
        $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        foreach ($pointsRelais as $pudo) {

            $pudoNum = (string) ($pudo->Num ?? '');

            // Coordination extraction (Handling French comma vs standard dot)
            $latitudeStr = trim((string) ($pudo->Latitude ?? ''));
            $longitudeStr = trim((string) ($pudo->Longitude ?? ''));

            $latitudeStr = str_replace(',', '.', $latitudeStr);
            $longitudeStr = str_replace(',', '.', $longitudeStr);

            $latitude = !empty($latitudeStr) ? (float) $latitudeStr : null;
            $longitude = !empty($longitudeStr) ? (float) $longitudeStr : null;

            // Name and Address extraction (Handling MR specific fields LgAdr1/2/3)
            $commerceName = trim((string) ($pudo->LgAdr1 ?? ''));

            if (empty($commerceName)) {
                $commerceName = trim((string) ($pudo->LgAdr2 ?? ''));
            }

            $streetAddress = trim((string) ($pudo->LgAdr3 ?? ''));
            if (empty($streetAddress)) {
                $streetAddress = trim((string) ($pudo->LgAdr2 ?? ''));
            }
            if (empty($streetAddress)) {
                $streetAddress = trim((string) ($pudo->LgAdr1 ?? ''));
            }

            // Schedule extraction
            $horaires = [];
            foreach ($daysOfWeek as $day) {
                $propName = 'Horaires_' . $day;
                $horaires[$day] = $this->formatDayHours($pudo->$propName ?? null);
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
                'hours' => $horaires,
            ];
        }
        return $formatted;
    }
}
