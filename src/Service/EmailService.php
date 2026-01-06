<?php

// src/Service/EmailService.php
namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $shippingInfo = $order->getShippingInfo();
        
        if (!$shippingInfo || !$shippingInfo->getEmail()) {
            return; // On ne peut pas envoyer de mail sans destinataire
        }

        $email = (new TemplatedEmail())
            ->from(new Address('eightyone@hotmail.fr', 'Eighty One Store'))
            ->to(new Address($shippingInfo->getEmail(), $shippingInfo->getFirstName()))
            ->subject('Confirmation de votre commande Eighty One Store #' . $order->getId())
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}