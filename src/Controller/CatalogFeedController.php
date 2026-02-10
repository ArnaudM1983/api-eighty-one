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
    #[Route('/feed.xml', name: 'api_catalog_feed', methods: ['GET'])]
    public function feed(ProductRepository $productRepository, Request $request): Response
    {
        // Récupérer les produits
        $products = $productRepository->findAll();

        // CONFIGURATION DES URL
        
        // URL du FRONT (Next.js) -> C'est là que le client sera redirigé
        $frontendBaseUrl = $this->getParameter('app.frontend_url');

        // URL du BACK (Symfony) -> Pour que Facebook puisse télécharger l'image
        $backendBaseUrl = $request->getSchemeAndHttpHost(); 

        // Construction du XML (Format Google Merchant / Facebook)
        $xmlContent = '<?xml version="1.0"?>';
        $xmlContent .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
        $xmlContent .= '<channel>';
        $xmlContent .= '<title>Catalogue 81Store</title>';
        $xmlContent .= '<link>' . $frontendBaseUrl . '</link>';
        $xmlContent .= '<description>Flux produits pour Instagram Shopping</description>';

        foreach ($products as $product) {
            // Sécurité : on ignore les produits sans prix ou sans image
            if (!$product->getPrice() || !$product->getMainImage()) {
                continue;
            }

            $xmlContent .= '<item>';
            
            $xmlContent .= '<g:id>' . $product->getId() . '</g:id>';
            $xmlContent .= '<g:title><![CDATA[' . $product->getName() . ']]></g:title>';
            
            // --- CORRECTION 1 : GESTION DE LA DESCRIPTION VIDE ---
            // Si la description est vide, on utilise le Nom du produit
            $rawDesc = $product->getDescription();
            if (empty($rawDesc)) {
                $rawDesc = $product->getName() . ' - Disponible sur 81Store.';
            }
            $desc = strip_tags($rawDesc);
            $xmlContent .= '<g:description><![CDATA[' . substr($desc, 0, 5000) . ']]></g:description>';
            
            $xmlContent .= '<g:link>' . $frontendBaseUrl . '/produit/' . $product->getSlug() . '</g:link>';
            
            // --- CORRECTION 2 : GESTION DU CHEMIN D'IMAGE ---
            // On nettoie le chemin venant de la BDD pour éviter le double "uploads"
            $imagePathInDb = $product->getMainImage(); // ex: "uploads/image.png" ou "image.png"
            
            // Si le chemin en BDD commence déjà par "uploads/", on ne l'ajoute pas
            if (str_starts_with($imagePathInDb, 'uploads/')) {
                $imageUrl = $backendBaseUrl . '/' . $imagePathInDb;
            } else {
                // Sinon on l'ajoute
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