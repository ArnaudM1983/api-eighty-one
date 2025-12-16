<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ColissimoService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $colissimoApiKey
    ) {}

    public function searchPointsRelais(string $zipCode, string $city, string $address, string $countryCode = 'FR', float $weightKg = 1.0): array
    {
        // Sécurité : poids minimum 10g et champs non vides
        $weightGrams = max((int)($weightKg * 1000), 10);
        $cleanAddress = !empty(trim($address)) ? $address : ' ';
        $cleanCity = !empty(trim($city)) ? $city : ' ';

        $response = $this->client->request('POST', 'https://ws.colissimo.fr/pointretrait-ws-cxf/rest/v2/pointretrait/findRDVPointRetraitAcheminement', [
            'json' => [
                'apikey'       => $this->colissimoApiKey,
                'address'      => !empty($address) ? mb_substr($address, 0, 100) : ' ',
                'zipCode'      => $zipCode,
                'city'         => !empty($city) ? mb_substr($city, 0, 50) : ' ',
                'countryCode'  => $countryCode ?: 'FR',
                'weight'       => (string) max((int)($weightKg * 1000), 10), // Forcer en String
                'shippingDate' => (new \DateTime())->format('d/m/Y'),         // Format strict JJ/MM/AAAA
                'filterRelay'  => "1",
                'lang'         => 'FR',
                'optionInter'  => ($countryCode === 'FR' || empty($countryCode)) ? "0" : "1"
            ]
        ]);

        // On récupère la réponse sans lever d'exception pour analyser le errorCode
        $data = $response->toArray(false);

        if (isset($data['errorCode']) && $data['errorCode'] != 0) {
            throw new \Exception("Colissimo API Error " . $data['errorCode'] . ": " . ($data['errorMessage'] ?? 'Erreur inconnue'));
        }

        $pudos = [];
        if (isset($data['listePointRetraitAcheminement'])) {
            foreach ($data['listePointRetraitAcheminement'] as $item) {
                $pudos[] = [
                    'id'        => $item['identifiant'],
                    'name'      => $item['nom'],
                    'address'   => trim(($item['adresse1'] ?? '') . ' ' . ($item['adresse2'] ?? '')),
                    'postalCode' => $item['codePostal'],
                    'city'      => $item['localite'],
                    'country'   => $item['codePays'],
                    'latitude'  => (float)$item['coordGeolocalisationLatitude'],
                    'longitude' => (float)$item['coordGeolocalisationLongitude'],
                    'hours'     => [
                        'Lundi'    => $this->formatHours($item['horairesOuvertureLundi'] ?? ''),
                        'Mardi'    => $this->formatHours($item['horairesOuvertureMardi'] ?? ''),
                        'Mercredi' => $this->formatHours($item['horairesOuvertureMercredi'] ?? ''),
                        'Jeudi'    => $this->formatHours($item['horairesOuvertureJeudi'] ?? ''),
                        'Vendredi' => $this->formatHours($item['horairesOuvertureVendredi'] ?? ''),
                        'Samedi'   => $this->formatHours($item['horairesOuvertureSamedi'] ?? ''),
                        'Dimanche' => $this->formatHours($item['horairesOuvertureDimanche'] ?? ''),
                    ]
                ];
            }
        }
        return $pudos;
    }

    private function formatHours(string $colissimoTime): array
    {
        if (empty($colissimoTime) || $colissimoTime === "00:00-00:00 00:00-00:00") {
            return ['am_start' => '', 'am_end' => '', 'pm_start' => '', 'pm_end' => ''];
        }
        $parts = explode(' ', $colissimoTime);
        $am = explode('-', $parts[0] ?? '00:00-00:00');
        $pm = explode('-', $parts[1] ?? '00:00-00:00');

        return [
            'am_start' => str_replace(':', '', $am[0] ?? ''),
            'am_end'   => str_replace(':', '', $am[1] ?? ''),
            'pm_start' => str_replace(':', '', $pm[0] ?? ''),
            'pm_end'   => str_replace(':', '', $pm[1] ?? ''),
        ];
    }
}
