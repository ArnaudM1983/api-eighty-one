<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ColissimoService
{
    private const BASE_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/rest/v2/pointretrait';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $colissimoApiKey,
        private ?string $colissimoCodTiers = null
    ) {}

    public function searchPointsRetrait(
        string $address,
        string $zipCode,
        string $city,
        string $countryCode = 'FR',
        float $weightInKg = 0.1
    ): array {
        // La doc demande le poids en grammes (entier)
        $weightInGrams = (int) ($weightInKg * 1000);
        if ($weightInGrams <= 0) $weightInGrams = 100;

        try {
            // Note: Respect strict de la casse de la doc REST (Section II.5.2.5)
            $body = [
                'apiKey'       => $this->colissimoApiKey, // 'K' majuscule selon l'exemple REST doc
                'address'      => $address,
                'zipCode'      => $zipCode,
                'city'         => strtoupper($city),
                'countryCode'  => strtoupper($countryCode),
                'weight'       => (string) $weightInGrams, // Doit être une chaîne de chiffres
                'shippingDate' => (new \DateTime('+2 day'))->format('d/m/Y'),
                'filterRelay'  => '1',
                'lang'         => 'FR',
                'optionInter'  => ($countryCode === 'FR') ? '0' : '1'
            ];

            // Si partenaire, le champ s'appelle 'codeTiersPourPartenaire' en REST (Section II.2)
            if (!empty($this->colissimoCodTiers)) {
                $body['codeTiersPourPartenaire'] = $this->colissimoCodTiers;
            }

            $response = $this->httpClient->request('POST', self::BASE_URL . '/findRDVPointRetraitAcheminement', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body
            ]);

            // Récupération sécurisée du contenu
            $content = $response->getContent(false);
            
            // La doc (I.5) impose l'UTF-8. On convertit si La Poste envoie du Latin-1 (ISO)
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }

            $data = json_decode($content, true);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $errorMsg = $data['errorMessage'] ?? 'Erreur inconnue';
                throw new \Exception("Colissimo Error ($statusCode): " . $errorMsg);
            }

            // Extraction selon la structure II.8.2
            $points = $data['listePointRetraitAcheminement'] ?? [];

            return array_map(function ($point) {
                return [
                    'id'        => $point['identifiant'] ?? '',
                    'name'      => $point['nom'] ?? '',
                    'address'   => trim(($point['adresse1'] ?? '') . ' ' . ($point['adresse2'] ?? '')),
                    'zipCode'   => $point['codePostal'] ?? '', // Attention: 'codePostal' dans le JSON retourné
                    'city'      => $point['localite'] ?? '',
                    'distance'  => $point['distanceEnMetre'] ?? 0,
                    'latitude'  => $point['coordGeolocalisationLatitude'] ?? null,
                    'longitude' => $point['coordGeolocalisationLongitude'] ?? null,
                    'type'      => $point['typeDePoint'] ?? '',
                    'isOpen'    => !($point['congesTotal'] ?? false)
                ];
            }, $points);

        } catch (\Exception $e) {
            return [
                'error'   => true,
                'message' => $e->getMessage(),
                'debug'   => [
                    'payload_sent' => $body ?? null,
                    'raw_response' => $content ?? 'No content'
                ]
            ];
        }
    }
}