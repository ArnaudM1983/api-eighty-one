<?php

namespace App\Controller;

use App\Repository\InstagramTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/instagram', name: 'api_instagram_')]
class InstagramController extends AbstractController
{
    private InstagramTokenRepository $tokenRepository;
    private HttpClientInterface $httpClient;

    public function __construct(
        InstagramTokenRepository $tokenRepository,
        HttpClientInterface $httpClient
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->httpClient = $httpClient;
    }

    #[Route('', name: 'feed', methods: ['GET'])]
    public function feed(): JsonResponse
    {
        // Get Valid Token
        $token = $this->tokenRepository->getValidToken();

        if (!$token) {
            return $this->json([
                'error' => 'No valid Instagram token found.'
            ], 400);
        }

        try {
            // Appel API Instagram Graph pour récupérer les posts
            $response = $this->httpClient->request(
                'GET',
                'https://graph.instagram.com/me/media', 
                [
                    'query' => [
                        'fields' => 'id,caption,media_url,permalink,media_type,thumbnail_url',
                        'access_token' => $token,
                    ]
                ]
            );

            $data = $response->toArray();

            // Filtrer seulement les images ou reels si besoin
            $posts = array_filter($data['data'], function ($item) {
                return in_array($item['media_type'], ['IMAGE', 'CAROUSEL_ALBUM', 'VIDEO']);
            });

            return $this->json($posts);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch Instagram posts.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
