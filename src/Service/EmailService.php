<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $senderEmail, // Injecté via services.yaml
        private string $senderName,  // Injecté via services.yaml
        private string $adminEmail   // Injecté via services.yaml
    ) {}

    /**
     * Mail envoyé au CLIENT : Confirmation de commande
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();

        if (!$shippingInfo || !$shippingInfo->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail)) // Important pour que le client puisse répondre
            ->subject('Confirmation de votre commande ' . $this->senderName . ' #' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé à l'ADMIN : Nouvelle commande reçue
     */
    public function sendAdminNotification(Order $order): void
    {
        // On force le chargement des données pour que Twig n'ait plus besoin de la BDD
        foreach ($order->getItems() as $item) {
            $item->getProduct()->getName();
            if ($item->getVariant()) {
                $item->getVariant()->getName();
            }
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Eighty One System'))
            ->to(new Address($this->adminEmail)) // Envoi vers ton adresse Admin
            ->subject('🚀 Nouvelle commande à préparer : #' . $order->getId())
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé au CLIENT : Commande expédiée
     */
    public function sendShippingNotification(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();

        if (!$shippingInfo || !$shippingInfo->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail))
            ->subject('Bonne nouvelle ! Votre commande Eighty One #' . $order->getId() . ' est en route')
            ->htmlTemplate('emails/order_shipped.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé au CLIENT : Confirmation Retrait Boutique
     */
    public function sendPickupConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        if (!$shippingInfo || !$shippingInfo->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail))
            ->subject('Confirmation de votre commande en retrait boutique - #' . $order->getId())
            ->htmlTemplate('emails/order_pickup_confirmation.html.twig')
            ->context(['order' => $order]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé au CLIENT : Facture / Récapitulatif
     */
    public function sendInvoiceNotification(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        if (!$shippingInfo || !$shippingInfo->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail))
            ->subject('Votre facture Eighty One Store - Commande #' . $order->getId())
            ->htmlTemplate('emails/order_invoice.html.twig') 
            ->context([
                'order' => $order,
                // Calcul de la TVA pour le template (20% incluse)
                'totalTax' => (float)$order->getTotal() - ((float)$order->getTotal() / 1.2)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé à l'ADMIN : Notification spéciale retrait boutique
     */
    public function sendAdminPickupNotification(Order $order): void
    {
        // On force le chargement des données
        foreach ($order->getItems() as $item) {
            $item->getProduct()->getName(); 
            if ($item->getVariant()) {
                $item->getVariant()->getName();
            }
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Eighty One System'))
            ->to(new Address($this->adminEmail))
            ->subject('⚠️ Action requise : Retrait Boutique à encaisser #' . $order->getId())
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}