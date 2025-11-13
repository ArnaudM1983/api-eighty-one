<?php

namespace App\Command;

use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:clean-variant-image-paths')]
class CleanVariantImagePathsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $variants = $this->em->getRepository(ProductVariant::class)->findAll();
        $count = 0;

        foreach ($variants as $variant) {
            $image = $variant->getImage();
            if ($image && str_starts_with($image, 'https://eightyonestore.com/wp-content/uploads/')) {
                $cleanPath = preg_replace(
                    '#https://eightyonestore\.com/wp-content/uploads/[0-9]{4}/[0-9]{2}/#',
                    'uploads/',
                    $image
                );
                $variant->setImage($cleanPath);
                $count++;
            }
        }

        $this->em->flush();
        $output->writeln("✅ $count variant images updated to local paths.");

        return Command::SUCCESS;
    }
}
