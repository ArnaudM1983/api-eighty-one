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
        $products = $productRepository->findAll();

        $frontendBaseUrl = rtrim($this->getParameter('app.frontend_url'), '/');
        $backendBaseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        // 1. Added UTF-8 encoding declaration
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>';
        $xmlContent .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
        $xmlContent .= '<channel>';
        $xmlContent .= '<title>Catalogue 81Store</title>';
        $xmlContent .= '<link>' . $frontendBaseUrl . '</link>';
        $xmlContent .= '<description>Flux produits pour Instagram Shopping</description>';

        foreach ($products as $product) {
            $variants = $product->getVariants();

            if ($variants->count() > 0) {
                // --- CASE: PRODUCT WITH VARIANTS ---
                foreach ($variants as $variant) {
                    $xmlContent .= '<item>';
                    $xmlContent .= '<g:id>' . $product->getId() . '_' . $variant->getId() . '</g:id>';
                    $xmlContent .= '<g:item_group_id>' . $product->getId() . '</g:item_group_id>';
                    $xmlContent .= '<g:title><![CDATA[' . $product->getName() . ' - ' . $variant->getName() . ']]></g:title>';

                    $rawDesc = $product->getDescription() ?: $product->getName();
                    $desc = strip_tags($rawDesc);
                    $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 4900) . ']]></g:description>';

                    $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '?v=' . $variant->getId() . '</g:link>';

                    // 2. Image Logic Fix (was calculated but not added to XML)
                    $variantImg = ltrim($variant->getImage() ?: $product->getMainImage(), '/');
                    $variantImg = preg_replace('/^uploads\//', '', $variantImg);
                    $imageUrl = $backendBaseUrl . '/uploads/' . $variantImg;
                    $xmlContent .= '<g:image_link>' . $imageUrl . '</g:image_link>';

                    $xmlContent .= '<g:price>' . number_format($variant->getPrice(), 2, '.', '') . ' EUR</g:price>';
                    $xmlContent .= '<g:availability>' . ($variant->getStock() > 0 ? 'in stock' : 'out of stock') . '</g:availability>';
                    $xmlContent .= '<g:brand>81Store</g:brand>';
                    $xmlContent .= '<g:condition>new</g:condition>';
                    $xmlContent .= '</item>';
                }
            } else {
                // --- CASE: SIMPLE PRODUCT (NO VARIANTS) ---
                if (!$product->getPrice() || !$product->getMainImage()) {
                    continue;
                }

                $xmlContent .= '<item>';
                $xmlContent .= '<g:id>' . $product->getId() . '</g:id>';
                $xmlContent .= '<g:title><![CDATA[' . $product->getName() . ']]></g:title>';

                $rawDesc = $product->getDescription() ?: $product->getName();
                $desc = strip_tags($rawDesc);
                $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 4900) . ']]></g:description>';

                $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '</g:link>';

                // Image Logic for simple products
                $mainImg = ltrim($product->getMainImage(), '/');
                $mainImg = preg_replace('/^uploads\//', '', $mainImg);
                $imageUrl = $backendBaseUrl . '/uploads/' . $mainImg;
                $xmlContent .= '<g:image_link>' . $imageUrl . '</g:image_link>';

                $xmlContent .= '<g:price>' . number_format($product->getPrice(), 2, '.', '') . ' EUR</g:price>';
                $xmlContent .= '<g:availability>' . ($product->getStock() > 0 ? 'in stock' : 'out of stock') . '</g:availability>';
                $xmlContent .= '<g:brand>81Store</g:brand>';
                $xmlContent .= '<g:condition>new</g:condition>';
                $xmlContent .= '</item>';
            }
        }

        $xmlContent .= '</channel>';
        $xmlContent .= '</rss>';

        // 3. Explicit UTF-8 header
        return new Response($xmlContent, 200, [
            'Content-Type' => 'text/xml; charset=utf-8'
        ]);
    }
}