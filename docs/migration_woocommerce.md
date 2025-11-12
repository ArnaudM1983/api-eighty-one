# Migration WooCommerce → Symfony (Eighty-One)

## 🎯 Objectif
Importer les données WooCommerce (produits, catégories, clients, commandes) dans la nouvelle base MySQL utilisée par Symfony.

---

## ⚙️ Étapes

### 1. Préparer les fichiers sources
Exporter les fichiers JSON depuis WooCommerce :
- `clean_users.json`
- `clean_usermeta.json`
- `clean_categories.json`
- `clean_posts.json`
- `clean_postmeta.json`
- `clean_attachments.json`
- `clean_orders.json`

Placer ces fichiers dans le dossier :
/data/

---

### 2. Réinitialiser la base de données
Si nécessaire :
```sql
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE order_item;
TRUNCATE TABLE `order`;
TRUNCATE TABLE product_variant;
TRUNCATE TABLE product_image;
TRUNCATE TABLE product_category;
TRUNCATE TABLE product;
TRUNCATE TABLE category;
TRUNCATE TABLE user;
SET FOREIGN_KEY_CHECKS = 1;
```

### 3. Lancer l’import Symfony
Depuis le terminal :
php bin/console app:import-woocommerce

### 4. Vérifier l'import
Comptage rapide :
```sql
SELECT COUNT(*) FROM product;
SELECT COUNT(*) FROM product_variant;
SELECT COUNT(*) FROM category;
SELECT COUNT(*) FROM `order`;

Exemple pour vérifier un produit parent et ses variantes :
SELECT * FROM product WHERE id = 1;
SELECT * FROM product_variant WHERE product_id = 1;
```

### 5. Logs et cohérence
Vérifier dans la console :
Nombre d’entités importées
Absence d’erreurs Doctrine
Vérifier que les produits ont bien des slugs, images, catégories
