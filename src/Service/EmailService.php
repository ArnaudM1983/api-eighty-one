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
        private string $senderEmail,
        private string $senderName,
        private string $adminEmail
    ) {}

    /**
     * Méthode interne pour centraliser l'envoi et le log d'erreur
     */
    private function safeSend(TemplatedEmail $email, string $label, int $orderId): void
    {
        $logFile = 'debug_emails_eightyone.txt';
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        try {
            $this->mailer->send($email);
            file_put_contents($logFile, "[$now] SUCCÈS : $label pour Commande #$orderId\n", FILE_APPEND);
        } catch (\Exception $e) {
            $error = "[$now] ERREUR : $label pour Commande #$orderId -> " . $e->getMessage() . "\n";
            file_put_contents($logFile, $error, FILE_APPEND);
        }
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        if (!$shippingInfo || !$shippingInfo->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail))
            ->subject('Confirmation de votre commande ' . $this->senderName . ' #' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context(['order' => $order]);

        $this->safeSend($email, "Mail Client Confirmation", $order->getId());
    }

    public function sendAdminNotification(Order $order): void
    {
        // Forcer le chargement pour éviter les Lazy Loading Errors
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()) $item->getProduct()->getName();
            if ($item->getVariant()) $item->getVariant()->getName();
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Eighty One System'))
            ->to(new Address($this->adminEmail))
            ->subject('🚀 Nouvelle commande à préparer : #' . $order->getId())
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context(['order' => $order]);

        $this->safeSend($email, "Mail Admin Notification", $order->getId());
    }

    public function sendShippingNotification(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        if (!$shippingInfo || !$shippingInfo->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->replyTo(new Address($this->adminEmail))
            ->subject('Bonne nouvelle ! Votre commande Eighty One #' . $order->getId() . ' est en route')
            ->htmlTemplate('emails/order_shipped.html.twig')
            ->context(['order' => $order]);

        $this->safeSend($email, "Mail Client Expédition", $order->getId());
    }

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

        $this->safeSend($email, "Mail Client Pickup", $order->getId());
    }

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
                'totalTax' => (float)$order->getTotal() - ((float)$order->getTotal() / 1.2)
            ]);

        $this->safeSend($email, "Mail Client Facture", $order->getId());
    }

    public function sendAdminPickupNotification(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()) $item->getProduct()->getName(); 
            if ($item->getVariant()) $item->getVariant()->getName();
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Eighty One System'))
            ->to(new Address($this->adminEmail))
            ->subject('⚠️ Action requise : Retrait Boutique à encaisser #' . $order->getId())
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context(['order' => $order]);

        $this->safeSend($email, "Mail Admin Pickup", $order->getId());
    }
}