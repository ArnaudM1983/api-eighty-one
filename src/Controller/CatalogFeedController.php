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
            $variants = $product->getVariants();

            if ($variants->count() > 0) {
                // --- CASE: PRODUCT WITH VARIANTS ---
                foreach ($variants as $variant) {
                    $xmlContent .= '<item>';

                    // Unique ID for the variant (e.g., ParentID_VariantID)
                    $xmlContent .= '<g:id>' . $product->getId() . '_' . $variant->getId() . '</g:id>';

                    // Shared ID to group variants under the same product display
                    $xmlContent .= '<g:item_group_id>' . $product->getId() . '</g:item_group_id>';

                    // Title: Product Name + Variant Name (e.g., Montana Gold - Red)
                    $xmlContent .= '<g:title><![CDATA[' . $product->getName() . ' - ' . $variant->getName() . ']]></g:title>';

                    // Description handling: Use variant description if available, fallback to parent
                    $rawDesc = $product->getDescription() ?: $product->getName();
                    $desc = strip_tags($rawDesc);
                    $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 5000) . ']]></g:description>';

                    // Link to the product page (Deep linking to variant is recommended via query param)
                    $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '?v=' . $variant->getId() . '</g:link>';

                    // Image logic: Use variant image if set, otherwise fallback to main product image
                    $variantImg = ltrim($variant->getImage() ?: $product->getMainImage(), '/');
                    $variantImg = preg_replace('/^uploads\//', '', $variantImg);
                    $imageUrl = $backendBaseUrl . '/uploads/' . $variantImg;

                    // Price and Availability based on the specific variant
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
                $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 5000) . ']]></g:description>';

                $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '</g:link>';

                $mainImg = $product->getMainImage();
                $imageUrl = str_starts_with($mainImg, 'uploads/') ? $backendBaseUrl . '/' . $mainImg : $backendBaseUrl . '/uploads/' . $mainImg;
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

        return new Response($xmlContent, 200, ['Content-Type' => 'application/xml']);
    }
}
