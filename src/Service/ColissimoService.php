<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ColissimoService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $colissimoApiKey
    ) {}

    /**
     * Génère le token d'authentification pour le Widget Colissimo V2.
     * Ce token est temporaire (env. 30 min).
     */
    public function generateWidgetToken(): string
    {
        try {
            $response = $this->client->request('POST', 'https://ws.colissimo.fr/widget-colissimo/rest/authenticate.rest', [
                'json' => [
                    'apikey' => $this->colissimoApiKey
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $data = $response->toArray(false); 

            if ($response->getStatusCode() !== 200) {
                $errorMsg = $data['errorMessage'] ?? 'Erreur d\'authentification';
                throw new \Exception("Colissimo Auth Error: " . $errorMsg);
            }

            return $data['token'] ?? throw new \Exception("Token manquant.");
        } catch (\Exception $e) {
            throw new \Exception("Impossible de générer le token Colissimo : " . $e->getMessage());
        }
    }
}