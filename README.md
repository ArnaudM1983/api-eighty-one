# API E-Commerce "Eighty-One Store"

Ce dépôt contient le code source de l'API backend pour la boutique en ligne "Eighty-One Store". Il s'agit d'une application Symfony robuste qui gère les produits, les commandes, les paiements, les utilisateurs et bien plus encore.

## 🚀 Fonctionnalités Principales

*   **Gestion de Produits Complète** : CRUD pour les produits, avec gestion des variantes (ex: couleurs, tailles), des images multiples, et des produits associés (cross-selling).
*   **Catégorisation** : Système de catégories et sous-catégories pour organiser le catalogue.
*   **Panier d'Achat** : Gestion du panier via un token unique stocké dans un cookie, avec ajout, mise à jour et suppression d'articles.
*   **Processus de Commande Sécurisé** : Tunnel de commande en plusieurs étapes, de la création à la confirmation.
*   **Paiements Multi-Fournisseurs** : Intégration sécurisée avec **Stripe** (via Payment Intents) et **PayPal** (via API REST), avec gestion des webhooks pour la confirmation des paiements.
*   **Calcul de Frais de Port Avancé** :
    *   Calcul dynamique basé sur le poids total de la commande.
    *   Intégration avec **Mondial Relay** (via API SOAP) pour la recherche de points relais.
    *   Intégration avec **Colissimo** (via API REST) pour la recherche de points de retrait.
    *   Grille tarifaire personnalisable pour les frais de port.
*   **Système d'Authentification et Rôles** : Gestion des utilisateurs avec des rôles (`ROLE_USER`, `ROLE_ADMIN`). Les routes sensibles sont protégées.
*   **Dashboard Administrateur** : Endpoints dédiés pour les statistiques de ventes (chiffre d'affaires, panier moyen, meilleures ventes) et la gestion des commandes.
*   **Upload de Fichiers Sécurisé** : Endpoint pour téléverser des médias avec validation du type MIME, de la taille et nettoyage du nom de fichier.
*   **Flux de Catalogue** : Génération d'un flux `feed.xml` compatible avec Google Merchant Center et Facebook/Instagram Shopping.
*   **Intégration Instagram** : Endpoint pour récupérer et afficher les derniers posts d'un compte Instagram.

## 🛠️ Stack Technique

*   **Backend** : PHP 8.1+ / Symfony 6.4+
*   **Base de Données** : Doctrine ORM (conçu pour MySQL/MariaDB, mais adaptable).
*   **API** : Symfony avec une approche RESTful.
*   **Sécurité** : Symfony Security (gestion des rôles, hachage des mots de passe).
*   **Paiements** : `stripe/stripe-php`, `symfony/http-client` pour PayPal.
*   **Services Externes** : Mondial Relay, Colissimo, Instagram Graph API.

## ⚙️ Installation et Lancement

Suivez ces étapes pour lancer le projet en environnement de développement.

### Prérequis

*   PHP 8.1 ou supérieur
*   Composer
*   Symfony CLI
*   Un serveur de base de données (ex: MySQL, MariaDB) ou Docker.

### 1. Cloner le projet

```bash
git clone <votre-repository-url>
cd api-eighty-one
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration de l'environnement

Créez un fichier `.env.local` à la racine du projet pour surcharger les variables d'environnement. Voici les variables les plus importantes à configurer :

```dotenv
# .env.local

#-- Base de données
# Exemple pour MySQL/MariaDB
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=10.11.2-MariaDB"

#-- Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

#-- PayPal
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_MODE=SANDBOX # ou LIVE

#-- Colissimo
COLISSIMO_API_KEY=...

#-- Email (exemple avec Mailtrap ou autre service SMTP)
MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525

#-- URL du Front-end (pour les emails et les flux)
APP_FRONTEND_URL=http://localhost:3000
```

### 4. Mettre en place la base de données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Appliquer les migrations
php bin/console doctrine:migrations:migrate
```

### 5. Lancer le serveur

Utilisez le serveur web de Symfony pour un démarrage rapide.

```bash
symfony server:start -d
```

L'API est maintenant accessible à l'adresse `https://127.0.0.1:8000`.

## 🌐 Aperçu des Endpoints de l'API

Voici une liste non-exhaustive des routes principales.

| Méthode | Route                                  | Description                                           | Accès       |
| :------ | :------------------------------------- | :---------------------------------------------------- | :---------- |
| `GET`   | `/api/products`                        | Liste des produits (avec filtres et pagination)       | Public      |
| `GET`   | `/api/products/{id}`                   | Détail d'un produit                                   | Public      |
| `POST`  | `/api/products`                        | Créer un produit                                      | Admin       |
| `PUT`   | `/api/products/{id}`                   | Mettre à jour un produit                              | Admin       |
| `GET`   | `/api/categories`                      | Liste des catégories                                  | Public      |
| `POST`  | `/api/cart/add`                        | Ajouter un article au panier                          | Public      |
| `GET`   | `/api/cart`                            | Récupérer le contenu du panier                        | Public      |
| `DELETE`| `/api/cart/remove/{itemId}`            | Supprimer un article du panier                        | Public      |
| `POST`  | `/api/order/create`                    | Créer une commande depuis un panier                   | Public      |
| `GET`   | `/api/order/{id}`                      | Voir le détail d'une commande                         | Propriétaire/Admin |
| `PATCH` | `/api/order/{id}/status`               | Changer le statut d'une commande                      | Admin       |
| `POST`  | `/api/payment/stripe/create-intent/{id}`| Créer une intention de paiement Stripe                | Propriétaire/Admin |
| `POST`  | `/api/payment/paypal/create/{id}`      | Créer une commande PayPal                             | Propriétaire/Admin |
| `POST`  | `/api/media/upload`                    | Uploader un fichier média                             | Admin       |
| `GET`   | `/api/dashboard/stats`                 | Obtenir les statistiques de ventes                    | Admin       |
| `GET`   | `/api/admin/users`                     | Lister tous les utilisateurs                          | Admin       |

## 🛡️ Sécurité

La sécurité est un aspect central de ce projet.

*   **Contrôle d'accès** : Les routes sensibles sont protégées par `#[IsGranted('ROLE_ADMIN')]`. L'accès aux commandes par les clients est vérifié via un token unique (`checkOrderAccess`) pour prévenir les failles de type IDOR.
*   **Authentification** : La gestion des utilisateurs et le hachage des mots de passe sont assurés par le composant `security` de Symfony.
*   **Paiements** : Les transactions ne sont jamais traitées directement par notre serveur. Nous utilisons les **Payment Intents** de Stripe et l'API de PayPal, qui sont des solutions conformes à la norme PCI-DSS. Les confirmations de paiement sont validées via la signature des webhooks.
*   **Injections SQL** : L'utilisation de l'ORM Doctrine avec des requêtes paramétrées prévient systématiquement les injections SQL.
*   **Uploads** : Les fichiers uploadés sont validés par leur type MIME et leur taille pour n'autoriser que les images et éviter l'exécution de code malveillant.
*   **Gestion des erreurs** : En production, les messages d'erreur détaillés sont masqués pour ne pas exposer la structure interne de l'application.