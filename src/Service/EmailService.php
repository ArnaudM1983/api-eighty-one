<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    // On définit l'adresse principale ici pour pouvoir la modifier partout d'un coup
    private const STORE_EMAIL = 'eightyone@hotmail.fr';
    private const STORE_TEST = 'admin-test@eightyone.com';
    private const STORE_NAME = 'Eighty One Store';

    public function __construct(private MailerInterface $mailer) {}

    /**
     * Mail envoyé au CLIENT
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();

        if (!$shippingInfo || !$shippingInfo->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address(self::STORE_EMAIL, self::STORE_NAME))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->subject('Confirmation de votre commande ' . self::STORE_NAME . ' #' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé à l'ADMIN (vous)
     */
    public function sendAdminNotification(Order $order): void
    {

        // On force le chargement des données pour que Twig n'ait plus besoin de la BDD
        foreach ($order->getItems() as $item) {
            $item->getProduct()->getName(); // Force le chargement du produit
            if ($item->getVariant()) {
                $item->getVariant()->getName(); // Force le chargement de la variante
            }
        }
        $email = (new TemplatedEmail())
            ->from(new Address(self::STORE_EMAIL, 'Eighty One System'))
            ->to(self::STORE_TEST) // C'est ici que vous recevez le mail
            ->subject('🚀 Nouvelle commande à préparer : #' . $order->getId())
            ->htmlTemplate('emails/admin_order_notification.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé au CLIENT quand la commande est expédiée
     */
    public function sendShippingNotification(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();

        if (!$shippingInfo || !$shippingInfo->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address(self::STORE_EMAIL, self::STORE_NAME))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->subject('Bonne nouvelle ! Votre commande Eighty One #' . $order->getId() . ' est en route')
            ->htmlTemplate('emails/order_shipped.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Mail envoyé dès que la commande "Pickup" est validée 
     * (soit après paiement Stripe, soit après validation du choix Boutique)
     */
    public function sendPickupConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        if (!$shippingInfo || !$shippingInfo->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from(new Address(self::STORE_EMAIL, self::STORE_NAME))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->subject('Confirmation de votre commande en retrait boutique - #' . $order->getId())
            ->htmlTemplate('emails/order_pickup_confirmation.html.twig')
            ->context(['order' => $order]);

        $this->mailer->send($email);
    }
}
