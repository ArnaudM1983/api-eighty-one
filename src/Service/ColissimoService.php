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

    /**
     * Recherche les points de retrait et parse les horaires.
     */
    public function searchPointsRetrait(
        string $address,
        string $zipCode,
        string $city,
        string $countryCode = 'FR',
        float $weightInKg = 0.1
    ): array {
        $weightInGrams = (int) ($weightInKg * 1000);
        if ($weightInGrams <= 0) $weightInGrams = 100;

        try {
            $body = [
                'apiKey'       => $this->colissimoApiKey,
                'address'      => $address,
                'zipCode'      => $zipCode,
                'city'         => strtoupper($city),
                'countryCode'  => strtoupper($countryCode),
                'weight'       => (string) $weightInGrams,
                'shippingDate' => (new \DateTime('+2 day'))->format('d/m/Y'),
                'filterRelay'  => '1',
                'lang'         => 'FR',
                'optionInter'  => ($countryCode === 'FR') ? '0' : '1'
            ];

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

            $content = $response->getContent(false);
            
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }

            $data = json_decode($content, true);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $errorMsg = $data['errorMessage'] ?? 'Erreur inconnue';
                throw new \Exception("Colissimo Error ($statusCode): " . $errorMsg);
            }

            $points = $data['listePointRetraitAcheminement'] ?? [];

            return array_map(function ($point) {
                return [
                    'id'        => $point['identifiant'] ?? '',
                    'name'      => $point['nom'] ?? '',
                    'address'   => trim(($point['adresse1'] ?? '') . ' ' . ($point['adresse2'] ?? '')),
                    'zipCode'   => $point['codePostal'] ?? '',
                    'city'      => $point['localite'] ?? '',
                    'distance'  => $point['distanceEnMetre'] ?? 0,
                    'latitude'  => $point['coordGeolocalisationLatitude'] ?? null,
                    'longitude' => $point['coordGeolocalisationLongitude'] ?? null,
                    'type'      => $point['typeDePoint'] ?? '',
                    'isOpen'    => !($point['congesTotal'] ?? false),
                    'hours'     => [
                        'Lundi'    => $this->parseColissimoHours($point['horairesOuvertureLundi'] ?? ''),
                        'Mardi'    => $this->parseColissimoHours($point['horairesOuvertureMardi'] ?? ''),
                        'Mercredi' => $this->parseColissimoHours($point['horairesOuvertureMercredi'] ?? ''),
                        'Jeudi'    => $this->parseColissimoHours($point['horairesOuvertureJeudi'] ?? ''),
                        'Vendredi' => $this->parseColissimoHours($point['horairesOuvertureVendredi'] ?? ''),
                        'Samedi'   => $this->parseColissimoHours($point['horairesOuvertureSamedi'] ?? ''),
                        'Dimanche' => $this->parseColissimoHours($point['horairesOuvertureDimanche'] ?? ''),
                    ]
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

    /**
     * Transforme la chaîne Colissimo "09:00-12:00 14:00-18:00" en objet structuré.
     */
    private function parseColissimoHours(string $openingString): ?array
    {
        // Nettoyage des espaces et suppression des valeurs nulles
        $s = trim(preg_replace('/\s+/', ' ', $openingString));
        
        if (empty($s) || $s === '00:00-00:00 00:00-00:00') {
            return null;
        }

        $parts = explode(' ', $s);
        $morning = $parts[0] ?? '00:00-00:00';
        $afternoon = $parts[1] ?? '00:00-00:00';

        // Helper interne pour extraire et formater (ex: "09:00" -> "0900")
        $extract = function($range) {
            $times = explode('-', $range);
            $start = $times[0] ?? '00:00';
            $end = $times[1] ?? '00:00';

            return [
                'start' => ($start !== '00:00') ? str_replace(':', '', $start) : null,
                'end'   => ($end !== '00:00') ? str_replace(':', '', $end) : null,
            ];
        };

        $am = $extract($morning);
        $pm = $extract($afternoon);

        // Si aucune heure valide n'est trouvée
        if (!$am['start'] && !$pm['start']) {
            return null;
        }

        return [
            'am_start' => $am['start'],
            'am_end'   => $am['end'],
            'pm_start' => $pm['start'],
            'pm_end'   => $pm['end'],
        ];
    }
}