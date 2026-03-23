<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/catalog')]
class CatalogFeedController extends AbstractController
{
    /**
     * Generates an XML product feed for Google Merchant Center and Facebook/Instagram Shopping.
     */
    #[Route('/feed.xml', name: 'api_catalog_feed', methods: ['GET'])]
    public function feed(ProductRepository $productRepository, Request $request): Response
    {
        // Fetch all products from the database
        $products = $productRepository->findAll();

        // --- URL CONFIGURATION ---
        
        // Frontend URL (Next.js) -> Where the user is redirected from the ad
        $frontendBaseUrl = $this->getParameter('app.frontend_url');

        // Backend URL (Symfony) -> Where the platform downloads the product images
        $backendBaseUrl = $request->getSchemeAndHttpHost(); 

        // Building the RSS/XML structure (Google Merchant / Facebook standard)
        $xmlContent = '<?xml version="1.0"?>';
        $xmlContent .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
        $xmlContent .= '<channel>';
        $xmlContent .= '<title>Catalogue 81Store</title>';
        $xmlContent .= '<link>' . $frontendBaseUrl . '</link>';
        $xmlContent .= '<description>Flux produits pour Instagram Shopping</description>';

        foreach ($products as $product) {
            // Safety check: Skip products without a price or a main image
            if (!$product->getPrice() || !$product->getMainImage()) {
                continue;
            }

            $xmlContent .= '<item>';
            
            $xmlContent .= '<g:id>' . $product->getId() . '</g:id>';
            $xmlContent .= '<g:title><![CDATA[' . $product->getName() . ']]></g:title>';
            
            // --- DESCRIPTION HANDLING ---
            // If description is empty, fallback to product name
            $rawDesc = $product->getDescription();
            if (empty($rawDesc)) {
                $rawDesc = $product->getName() . ' - Disponible sur 81Store.';
            }
            // Strip HTML tags and limit length for XML compatibility
            $desc = strip_tags($rawDesc);
            $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 5000) . ']]></g:description>';
            
            $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '</g:link>';
            
            // --- IMAGE PATH LOGIC ---
            // Standardize the image path coming from the database
            $imagePathInDb = $product->getMainImage(); 
            
            // Check if the path already contains the "uploads/" prefix to avoid duplication
            if (str_starts_with($imagePathInDb, 'uploads/')) {
                $imageUrl = $backendBaseUrl . '/' . $imagePathInDb;
            } else {
                $imageUrl = $backendBaseUrl . '/uploads/' . $imagePathInDb;
            }
            
            $xmlContent .= '<g:image_link>' . $imageUrl . '</g:image_link>';
            
            $xmlContent .= '<g:price>' . $product->getPrice() . ' EUR</g:price>';
            $xmlContent .= '<g:availability>' . ($product->getStock() > 0 ? 'in stock' : 'out of stock') . '</g:availability>';
            $xmlContent .= '<g:brand>81Store</g:brand>';
            $xmlContent .= '<g:condition>new</g:condition>';
            
            $xmlContent .= '</item>';
        }

        $xmlContent .= '</channel>';
        $xmlContent .= '</rss>';

        return new Response($xmlContent, 200, ['Content-Type' => 'application/xml']);
    }
}