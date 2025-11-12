<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\ProductImage;
use App\Entity\Order;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:import-woocommerce',
    description: 'Importe les données WooCommerce JSON dans Symfony',
)]
class ImportWooCommerceCommand extends Command
{
    private EntityManagerInterface $em;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $em, CategoryRepository $categoryRepository, UserRepository $userRepository)
    {
        parent::__construct();
        $this->em = $em;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Début de l’import WooCommerce...');

        // Users
        $this->importUsers(
            __DIR__ . '/../../data/clean_users.json',
            __DIR__ . '/../../data/clean_usermeta.json',
            $output
        );

        // Categories
        $this->importCategories(
            __DIR__ . '/../../data/clean_categories.json',
            $output
        );

        // Products
        $this->importProducts(
            __DIR__ . '/../../data/clean_posts.json',
            __DIR__ . '/../../data/clean_postmeta.json',
            __DIR__ . '/../../data/clean_attachments.json',
            $output
        );

        // Orders
        $this->importOrders(
            __DIR__ . '/../../data/clean_orders.json',
            $output
        );

        $output->writeln('Import WooCommerce terminé.');
        return Command::SUCCESS;
    }

    // -------------------- USERS --------------------
    private function importUsers(string $usersJson, string $usermetaJson, OutputInterface $output): void
    {
        $usersData = json_decode(file_get_contents($usersJson), true);
        $metaData = json_decode(file_get_contents($usermetaJson), true);

        $usersById = [];

        foreach ($usersData as $item) {
            $user = new User();
            $user->setEmail($item['user_email']);
            $user->setPassword(password_hash('defaultpassword', PASSWORD_BCRYPT)); // mot de passe par défaut
            $user->setRoles(['ROLE_USER']);

            $usersById[$item['ID']] = $user;
            $this->em->persist($user);
        }

        // Ajouter first_name / last_name
        foreach ($metaData as $meta) {
            $userId = $meta['user_id'];
            if (isset($usersById[$userId])) {
                $user = $usersById[$userId];
                if ($meta['meta_key'] === 'first_name') {
                    $user->setFirstName($meta['meta_value']);
                } elseif ($meta['meta_key'] === 'last_name') {
                    $user->setLastName($meta['meta_value']);
                }
            }
        }

        $this->em->flush();
        $output->writeln('<info>' . count($usersById) . ' utilisateurs importés.</info>');
    }

    // -------------------- CATEGORIES --------------------
    private function importCategories(string $jsonPath, OutputInterface $output): void
    {
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) return;

        $categoriesByWooId = [];

        // Parents
        foreach ($data as $item) {
            if ($item['parent'] === "0") {
                $category = new Category();
                $category->setName($item['name']);
                $category->setSlug($item['slug']);
                $this->em->persist($category);
                $categoriesByWooId[$item['id']] = $category;
            }
        }
        $this->em->flush();

        // Enfants
        foreach ($data as $item) {
            if ($item['parent'] !== "0") {
                $category = new Category();
                $category->setName($item['name']);
                $category->setSlug($item['slug']);
                $parentId = $item['parent'];
                if (isset($categoriesByWooId[$parentId])) {
                    $category->setParent($categoriesByWooId[$parentId]);
                    $categoriesByWooId[$parentId]->addChild($category);
                }
                $this->em->persist($category);
                $categoriesByWooId[$item['id']] = $category;
            }
        }
        $this->em->flush();
        $output->writeln('<info>' . count($categoriesByWooId) . ' catégories importées.</info>');
    }

    // -------------------- PRODUCTS --------------------
    private function importProducts(string $postsJson, string $postmetaJson, string $attachmentsJson, OutputInterface $output): void
    {
        $postsData = json_decode(file_get_contents($postsJson), true);
        $metaData = json_decode(file_get_contents($postmetaJson), true);
        $attachmentsData = json_decode(file_get_contents($attachmentsJson), true);

        $productsById = [];
        $variantsById = [];
        $attachmentsById = [];

        // Préparer les attachments
        foreach ($attachmentsData as $att) {
            $attachmentsById[$att['ID']] = $att['guid'];
        }

        // Créer les produits parent
        foreach ($postsData as $post) {
            if ($post['post_type'] !== 'product' || $post['post_parent'] !== "0") continue;

            $product = new Product();
            $product->setName($post['post_title']);
            $product->setSlug($post['post_name']);
            $product->setDescription($post['post_content']);
            $product->setExcerpt($post['post_excerpt'] ?? '');
            $product->setCreatedAt(new \DateTimeImmutable());
            $product->setUpdatedAt(new \DateTimeImmutable());

            $productsById[$post['ID']] = $product;
            $this->em->persist($product);
        }

        $this->em->flush();

        // Créer les variantes
        foreach ($postsData as $post) {
            if ($post['post_type'] !== 'product' || $post['post_parent'] === "0") continue;

            $parentId = $post['post_parent'];
            if (!isset($productsById[$parentId])) continue;

            $variant = new ProductVariant();
            $variant->setName($post['post_title']);
            $variant->setProduct($productsById[$parentId]);

            $variantsById[$post['ID']] = $variant;
            $productsById[$parentId]->addVariant($variant); // Lier la variante au produit
            $this->em->persist($variant);
        }

        $this->em->flush();

        // Ajouter les informations meta
        foreach ($metaData as $meta) {
            $postId = $meta['post_id'];

            // Parent Product
            if (isset($productsById[$postId])) {
                $product = $productsById[$postId];
                switch ($meta['meta_key']) {
                    case '_sku':
                        $product->setSku($meta['meta_value']);
                        break;
                    case '_price':
                    case '_regular_price':
                        $product->setPrice((float)$meta['meta_value']);
                        break;
                    case '_stock':
                        $product->setStock((int)$meta['meta_value']);
                        break;
                    case '_thumbnail_id':
                        if (isset($attachmentsById[$meta['meta_value']])) {
                            $product->setMainImage($attachmentsById[$meta['meta_value']]);
                        }
                        break;
                    case '_product_image_gallery':
                        $imageIds = explode(',', $meta['meta_value']);
                        foreach ($imageIds as $imgId) {
                            if (isset($attachmentsById[$imgId])) {
                                $img = new ProductImage();
                                $img->setProduct($product);
                                $img->setUrl($attachmentsById[$imgId]);
                                $this->em->persist($img);
                            }
                        }
                        break;
                }
            }

            // Variante
            if (isset($variantsById[$postId])) {
                $variant = $variantsById[$postId];
                switch ($meta['meta_key']) {
                    case '_sku':
                        $variant->setSku($meta['meta_value']);
                        break;
                    case '_price':
                    case '_regular_price':
                        $variant->setPrice((float)$meta['meta_value']);
                        break;
                    case '_stock':
                        $variant->setStock((int)$meta['meta_value']);
                        break;
                    case '_thumbnail_id':
                        if (isset($attachmentsById[$meta['meta_value']])) {
                            $variant->setImage($attachmentsById[$meta['meta_value']]);
                        }
                        break;
                }
            }
        }

        $this->em->flush();

        $output->writeln('<info>' . count($productsById) . ' produits importés.</info>');
        $output->writeln('<info>' . count($variantsById) . ' variantes importées.</info>');
    }


    // -------------------- ORDERS --------------------
    private function importOrders(string $jsonPath, OutputInterface $output): void
    {
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) return;

        $importedCount = 0;

        // --- Créer ou récupérer l'utilisateur "Guest" une seule fois ---
        $defaultUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'guest@example.com']);
        if (!$defaultUser) {
            $defaultUser = new User();
            $defaultUser->setEmail('guest@example.com');
            $defaultUser->setPassword(password_hash('guestpassword', PASSWORD_BCRYPT));
            $defaultUser->setRoles(['ROLE_USER']);
            $this->em->persist($defaultUser);
            $this->em->flush();
        }

        // --- Import des commandes ---
        foreach ($data as $item) {
            $order = new Order();

            // Assigner l'utilisateur "Guest"
            $order->setUser($defaultUser);

            $order->setStatus($this->mapOrderStatus($item['post_status']));
            $order->setCreatedAt(new \DateTimeImmutable($item['post_date']));
            $order->setUpdatedAt(new \DateTimeImmutable());
            $order->setTotal(0);

            $this->em->persist($order);
            $importedCount++;
        }

        $this->em->flush();
        $output->writeln("<info>{$importedCount} commandes importées.</info>");
    }


    private function mapOrderStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'wc-pending' => 'created',
            'wc-processing' => 'paid',
            'wc-on-hold' => 'created',
            'wc-completed' => 'completed',
            'wc-cancelled' => 'cancelled',
            'wc-refunded' => 'cancelled',
            'wc-failed' => 'cancelled',
            default => 'created',
        };
    }
}
