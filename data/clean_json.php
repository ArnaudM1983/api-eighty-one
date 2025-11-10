<?php

/**
 * Nettoyer les exports JSON de phpMyAdmin (WooCommerce)
 * et préparer les fichiers clean_* pour l'import Symfony.
 * 
 */

$inputFiles = [
    'mod929_posts.json' => 'clean_posts.json',
    'mod929_postmeta.json' => 'clean_postmeta.json',
    'mod929_users.json' => 'clean_users.json',
    'mod929_usermeta.json' => 'clean_usermeta.json',
    'mod929_terms.json' => 'clean_categories.json',
    'mod929_woocommerce_order_items.json' => 'clean_orders.json',
];

echo "🧹 Nettoyage des fichiers JSON WooCommerce...\n\n";

foreach ($inputFiles as $input => $output) {

    if (!file_exists($input)) {
        echo "Fichier introuvable : $input\n";
        continue;
    }

    $raw = json_decode(file_get_contents($input), true);
    if (!$raw || !is_array($raw)) {
        echo "Erreur de lecture JSON dans : $input\n";
        continue;
    }

    // Extraire la section "data"
    $data = [];
    foreach ($raw as $item) {
        if (($item['type'] ?? '') === 'table') {
            $data = $item['data'] ?? [];
            break;
        }
    }

    if (empty($data)) {
        echo "Aucune donnée trouvée dans $input\n";
        continue;
    }

    // --- Nettoyage spécifique selon le type de fichier ---
    $clean = [];

    foreach ($data as $row) {
        // Nettoyage commun
        if (isset($row['post_status']) && in_array($row['post_status'], ['auto-draft', 'trash', 'inherit', 'revision'])) {
            continue; // on saute les brouillons / corbeilles
        }

        // Nettoyage doublons
        $idKey = $row['ID'] ?? $row['id'] ?? $row['post_id'] ?? null;
        if ($idKey && isset($clean[$idKey])) continue;

        // Nettoyage spécifique par fichier
        switch ($input) {
            case 'mod929_posts.json':
                // On garde seulement les posts pertinents
                $postType = $row['post_type'] ?? '';
                if (!in_array($postType, ['product', 'product_variation', 'attachment'])) continue;
                $clean[$idKey] = [
                    'ID' => $row['ID'] ?? null,
                    'post_parent' => $row['post_parent'] ?? '0',
                    'post_title' => $row['post_title'] ?? '',
                    'post_name' => $row['post_name'] ?? '',
                    'post_type' => $postType,
                    'post_status' => $row['post_status'] ?? '',
                    'post_content' => $row['post_content'] ?? '',
                    'guid' => $row['guid'] ?? null,
                    'post_excerpt' => $row['post_excerpt'] ?? null,
                    'post_mime_type' => $row['post_mime_type'] ?? null
                ];
                break;

            case 'mod929_postmeta.json':
                if (empty($row['meta_key'])) continue;
                $allowedKeys = [
                    '_price', '_regular_price', '_stock', '_sku',
                    '_weight', '_width', '_height', '_length',
                    '_thumbnail_id', '_product_image_gallery'
                ];
                if (!in_array($row['meta_key'], $allowedKeys)) continue;
                $clean[] = [
                    'post_id' => $row['post_id'] ?? null,
                    'meta_key' => $row['meta_key'],
                    'meta_value' => $row['meta_value'] ?? null
                ];
                break;

            case 'mod929_users.json':
                $clean[] = [
                    'ID' => $row['ID'] ?? null,
                    'user_login' => $row['user_login'] ?? '',
                    'user_email' => $row['user_email'] ?? '',
                    'user_registered' => $row['user_registered'] ?? ''
                ];
                break;

            case 'mod929_usermeta.json':
                $allowedKeys = [
                    'first_name', 'last_name', 'billing_phone',
                    'billing_address_1', 'billing_city', 'billing_postcode'
                ];
                if (!in_array($row['meta_key'], $allowedKeys)) continue;
                $clean[] = [
                    'user_id' => $row['user_id'] ?? null,
                    'meta_key' => $row['meta_key'],
                    'meta_value' => $row['meta_value'] ?? ''
                ];
                break;

            case 'mod929_terms.json':
                $clean[] = [
                    'id' => $row['term_id'] ?? $row['id'] ?? null,
                    'name' => $row['name'] ?? '',
                    'slug' => $row['slug'] ?? '',
                    'parent' => $row['parent'] ?? '0'
                ];
                break;

            case 'mod929_woocommerce_order_items.json':
                $clean[] = [
                    'order_item_id' => $row['order_item_id'] ?? null,
                    'order_id' => $row['order_id'] ?? null,
                    'order_item_name' => $row['order_item_name'] ?? '',
                    'order_item_type' => $row['order_item_type'] ?? ''
                ];
                break;

            default:
                $clean[] = $row;
        }
    }

    // Supprimer les doublons d’ID
    if (isset($clean[0])) {
        $clean = array_values(array_unique($clean, SORT_REGULAR));
    } else {
        $clean = array_values($clean);
    }

    // Sauvegarde
    file_put_contents(
        $output,
        json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    echo "$output créé (" . count($clean) . " lignes)\n";
}

echo "\nNettoyage terminé avec succès !\n";
