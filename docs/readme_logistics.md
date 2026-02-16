# Documentation Technique : Flux Commandes, Stocks & Paiements

Cette documentation détaille le cycle de vie des commandes, la gestion des stocks, les flux de paiement (Stripe vs Boutique) et les notifications par email.

---

## 1. Vue d'ensemble des Services

### `StockService`
Service centralisé gérant les mouvements de stock.
* **Décrémentation (-1)** : Appliquée lors de la validation du paiement (Stripe) ou de la confirmation de réservation (Retrait Boutique).
* **Incrémentation (+1)** : Appliquée lors de l'annulation d'une commande (Restockage automatique).

### `EmailService`
Gère l'envoi des emails transactionnels via Twig/Mailer.
* Dispose de méthodes distinctes pour les livraisons standards et les retraits boutique.
* Gère les notifications Admin.

---

## 2. Scénarios de Commande

### A. Livraison Domicile / Point Relais (Paiement Stripe)

C'est le flux standard e-commerce.

1.  **Action Client** : Paiement validé sur le front.
2.  **Déclencheur** : Webhook Stripe (`payment_intent.succeeded`) reçu par `StripeController`.
3.  **Processus Backend** :
    * Statut commande : Passe à `paid`.
    * Stock : **Décrémenté** immédiatement (`StockService`).
    * Paiement : Enregistré en BDD avec ID Transaction Stripe.
4.  **Emails** :
    * Client : Reçoit `emails/order_confirmation.html.twig` (Template standard).
    * Admin : Reçoit `emails/admin_order_notification.html.twig`.

### B. Retrait Boutique + Paiement en Ligne (Click & Collect Prépayé)

Le client réserve et paie en ligne, il passe juste récupérer le sac.

1.  **Action Client** : Paiement validé sur le front.
2.  **Déclencheur** : Webhook Stripe (`payment_intent.succeeded`) reçu par `StripeController`.
3.  **Processus Backend** :
    * Détection du mode `pickup`.
    * Statut commande : Passe à `paid`.
    * Stock : **Décrémenté** immédiatement.
4.  **Emails** :
    * Client : Reçoit `emails/order_pickup_confirmation.html.twig`.
        * *Affichage* : Bloc Vert "Commande payée en ligne".
    * Admin : Reçoit notification standard ou spécifique.

### C. Retrait Boutique + Paiement Sur Place (Click & Collect Comptoir)

Le client réserve en ligne mais paie physiquement au magasin.

1.  **Action Client** : Clic sur "Confirmer la commande" (Front).
2.  **Déclencheur** : Route API `POST /api/order/{id}/confirm-pickup` (`OrderController`).
3.  **Processus Backend** :
    * **Stock** : **Décrémenté** immédiatement pour réserver les articles (évite la survente).
    * Statut commande : Reste `created` (ou `pending_payment`).
    * Pas de transaction Stripe créée.
4.  **Emails** :
    * Client : Reçoit `emails/order_pickup_confirmation.html.twig`.
        * *Affichage* : Bloc Orange "Paiement à réaliser sur place".
    * Admin : Reçoit `emails/admin_order_notification.html.twig`.
        * *Objet* : "Action requise : Retrait Boutique à encaisser".
5.  **Validation Finale (Admin)** :
    * L'admin passe le statut à `shipped` (Retiré) via le Dashboard.
    * Le système crée une entité `Payment` (Méthode: `boutique`).
    * Le client reçoit sa facture par email.

---

## 3. Gestion Post-Commande (Dashboard Admin)

### Passage en "Expédié / Retiré" (`shipped`)

* **Si commande Stripe** : Simple changement de statut.
* **Si commande Boutique (non payée)** :
    * Création automatique d'une ligne de paiement `boutique`.
    * Génération d'un ID de transaction interne (`CASH-{id}-{timestamp}`).
* **Facturation** :
    * Déclenche l'envoi automatique de la facture (`emails/order_invoice.html.twig`) si c'est la première fois que la commande passe à `shipped`.

### Annulation (`cancelled`)

L'annulation est gérée par `OrderController::updateStatus`. Elle est sécurisée et automatisée.

1.  **Vérification de l'éligibilité au Restockage** :
    * Si la commande était `paid`, `shipped`, ou `pickup` (réservée), le système **ré-incrémente le stock (+1)** via `StockService`.
2.  **Remboursement Stripe Automatique** :
    * Si la commande contient un paiement Stripe `success`, l'API Stripe est appelée pour un **remboursement total**.
    * Le statut du paiement passe à `refunded`.
3.  **Sécurité Frontend** :
    * Une popup avertit l'admin des conséquences (Stock + Remboursement).

---

## 4. Récapitulatif Technique des Templates Email

| Fichier Twig | Contexte d'envoi | Particularité |
| :--- | :--- | :--- |
| `order_confirmation.html.twig` | Livraison Domicile / Point Relais (Payé) | Récapitulatif standard avec adresse de livraison. |
| `order_pickup_confirmation.html.twig` | Retrait Boutique (Payé OU Non Payé) | Conditionnel Twig : Affiche un message Vert (Payé) ou Orange (À payer). |
| `admin_order_notification.html.twig` | Notification Admin (Toutes commandes) | Affiche un bandeau d'alerte si paiement comptoir requis. |
| `order_invoice.html.twig` | Passage au statut "Expédié" ou "Retiré" | Sert de facture / preuve d'achat finale. |

---

## 5. Matrice des Statuts de Commande

| Statut BDD | Signification | Stock | Paiement |
| :--- | :--- | :--- | :--- |
| `created` | Panier validé (Pickup) ou Abandonné | Décrémenté (si Pickup validé) | En attente |
| `pending` | Transaction Stripe initiée | Intact | En cours |
| `paid` | Paiement Stripe validé | Décrémenté | Reçu (Stripe) |
| `shipped` | Colis envoyé OU Retrait effectué | Décrémenté | Reçu (Stripe ou Boutique) |
| `cancelled`| Commande annulée | **Restocké (+1)** | Remboursé (si applicable) |
| `completed`| Commande archivée/terminée | Décrémenté | Reçu |

---

## 6. Flux API Simplifié

| Action | Route API | Contrôleur Responsable |
| :--- | :--- | :--- |
| **Création Panier** | `POST /api/order/create` | `OrderController` |
| **Validation Pickup** | `POST /api/order/{id}/confirm-pickup` | `OrderController` |
| **Succès Stripe** | `POST /api/payment/stripe/webhook` | `StripeController` |
| **Update Statut (Admin)** | `PATCH /api/order/{id}/status` | `OrderController` |