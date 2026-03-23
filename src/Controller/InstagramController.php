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

    /**
     * Fetches the Instagram media feed using the Graph API.
     * Requires a valid access token stored in the database.
     */
    #[Route('', name: 'feed', methods: ['GET'])]
    public function feed(): JsonResponse
    {
        // Retrieve a valid (non-expired) token from the repository
        $token = $this->tokenRepository->getValidToken();

        if (!$token) {
            return $this->json([
                'error' => 'No valid Instagram token found.'
            ], 400);
        }

        try {
            // Request media data from Instagram Graph API
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

            // Filter results to include only specific media types if necessary
            $posts = array_filter($data['data'], function ($item) {
                return in_array($item['media_type'], ['IMAGE', 'CAROUSEL_ALBUM', 'VIDEO']);
            });

            // Re-index the array to ensure a clean JSON output
            return $this->json($posts);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch Instagram posts.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
