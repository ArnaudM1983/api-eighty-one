<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Service\EmailService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
class OrderStatusSubscriber
{
    public function __construct(
        private EmailService $emailService
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $order = $args->getObject();

        if (!$order instanceof Order) {
            return;
        }

        if ($args->hasChangedField('status')) {
            $newStatus = $args->getNewValue('status');
            $oldStatus = $args->getOldValue('status');

            // CONDITION : On passe à 'shipped' ET ce n'était pas déjà le cas
            if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {

                // FILTRE : On n'envoie le mail QUE si ce n'est PAS un retrait boutique
                if ($order->getShippingMethod() !== 'pickup') {
                    $this->emailService->sendShippingNotification($order);
                } else {
                    // Ici, la commande passe en "shipped" (Retirée) en BDD 
                    // mais aucun mail n'est envoyé.
                }
            }
        }
    }
}
