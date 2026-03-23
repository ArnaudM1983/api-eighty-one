<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:clean-product-image-paths', description: 'Cleans and standardizes image paths for main products and their galleries.')]
class CleanProductImagePathsCommand extends Command
{
    private const OLD_BASE_URL = 'https://eightyonestore.com/wp-content/uploads/';
    private const NEW_PATH_PREFIX = 'uploads/';

    /**
     * Regex to target the old WordPress URL structure, including year and month (e.g., 2023/11/)
     */
    private const CLEANING_REGEX = '#https://eightyonestore\.com/wp-content/uploads/[0-9]{4}/[0-9]{2}/#';

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        $images = $this->em->getRepository(ProductImage::class)->findAll();
        
        $mainImageCount = 0;
        $galleryImageCount = 0;

        // 1. Process main product images (mainImage field in Product entity)
        $output->writeln("Nettoyage des images principales...");
        foreach ($products as $product) {
            $mainImage = $product->getMainImage();
            
            if ($mainImage && str_starts_with($mainImage, self::OLD_BASE_URL)) {
                $cleanPath = preg_replace(
                    self::CLEANING_REGEX,
                    self::NEW_PATH_PREFIX,
                    $mainImage
                );
                $product->setMainImage($cleanPath);
                $mainImageCount++;
            }
        }

        // 2. Process gallery images (url field in ProductImage entity)
        $output->writeln("Nettoyage des images de galerie...");
        foreach ($images as $image) {
            $imageUrl = $image->getUrl();

            if ($imageUrl && str_starts_with($imageUrl, self::OLD_BASE_URL)) {
                $cleanPath = preg_replace(
                    self::CLEANING_REGEX,
                    self::NEW_PATH_PREFIX,
                    $imageUrl
                );
                $image->setUrl($cleanPath);
                $galleryImageCount++;
            }
        }

        // Persist all changes to the database
        $this->em->flush();
        
        $output->writeln("<info>$mainImageCount images principales mises à jour.</info>");
        $output->writeln("<info>$galleryImageCount images de galerie mises à jour.</info>");

        return Command::SUCCESS;
    }
}