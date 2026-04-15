<?php

namespace App\Controller;

use App\Entity\Guide;
use App\Repository\GuideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/guides')]
class GuideController extends AbstractController
{
    private EntityManagerInterface $em;
    private GuideRepository $repo;

    public function __construct(EntityManagerInterface $em, GuideRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    /**
     * CRUD: Reorder guides
     */
    #[Route('/reorder', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['guides']) || !is_array($data['guides'])) {
            return $this->json(['error' => 'Invalid data'], 400);
        }

        foreach ($data['guides'] as $item) {
            $guide = $this->repo->find($item['id']);
            if ($guide) {
                $guide->setPosition((int) $item['position']);
            }
        }

        $this->em->flush();

        return $this->json(['message' => 'Order updated successfully'], 200);
    }

    /**
     * CRUD: List guides with pagination
     */
    #[Route('', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $qb = $this->repo->createQueryBuilder('g')
            ->orderBy('g.position', 'ASC') 
            ->addOrderBy('g.createdAt', 'DESC');

        if ($q = $request->query->get('q')) {
            $keywords = array_filter(explode(' ', $q));
            foreach ($keywords as $index => $word) {
                $parameterName = 'q' . $index;
                $qb->andWhere('g.title LIKE :' . $parameterName . ' OR g.description LIKE :' . $parameterName)
                   ->setParameter($parameterName, '%' . $word . '%');
            }
        }

        $page = (int) $request->query->get('_page', 1);
        $limit = (int) $request->query->get('_limit', 20);
        $offset = ($page - 1) * $limit;

        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb, true);
        $total = count($paginator);

        $guides = [];
        foreach ($paginator as $guide) {
            $guides[] = $this->serializeGuide($guide);
        }

        return $this->json($guides, 200, [
            'x-total-count' => (string)$total,
            'Access-Control-Expose-Headers' => 'x-total-count'
        ]);
    }

    /**
     * CRUD: Retrieve a single guide by ID (Used by Refine Show/Edit)
     */
    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function getOne(Guide $guide): JsonResponse
    {
        return $this->json($this->serializeGuide($guide));
    }

    /**
     * CRUD: Retrieve a single guide by Slug (Used by Next.js detail page)
     */
    #[Route('/slug/{slug}', methods: ['GET'])]
    public function getBySlug(string $slug): JsonResponse
    {
        $guide = $this->repo->findOneBySlug($slug);

        if (!$guide) {
            return $this->json(['error' => 'Guide not found'], 404);
        }

        return $this->json($this->serializeGuide($guide));
    }

    /**
     * CRUD: Create a new guide
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $guide = new Guide();
        $this->hydrateGuide($guide, $data);

        $this->em->persist($guide);
        $this->em->flush();

        return $this->json(['message' => 'Guide created', 'id' => $guide->getId()], 201);
    }

    /**
     * CRUD: Update an existing guide
     */
    #[Route('/{id<\d+>}', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, Guide $guide): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $this->hydrateGuide($guide, $data);
        $guide->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json($this->serializeGuide($guide));
    }

    /**
     * CRUD: Delete a guide
     */
    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Guide $guide): JsonResponse
    {
        $this->em->remove($guide);
        $this->em->flush();

        return $this->json(['message' => 'Guide deleted'], 204);
    }

    /**
     * Helper: Hydrate entity from array data
     */
    private function hydrateGuide(Guide $guide, array $data): void
    {
        if (isset($data['title'])) $guide->setTitle($data['title']);
        if (isset($data['slug'])) $guide->setSlug($data['slug']);
        if (isset($data['description'])) $guide->setDescription($data['description']);
        if (isset($data['content'])) $guide->setContent($data['content']);
        
        // L'image envoyée par Refine après l'upload dans le MediaController
        if (array_key_exists('image', $data)) {
            $guide->setImage($data['image']);
        }

        if (isset($data['switcher']) && is_array($data['switcher'])) {
            $guide->setSwitcher($data['switcher']);
        }

        if (isset($data['faq']) && is_array($data['faq'])) {
            $guide->setFaq($data['faq']);
        }

        if (isset($data['expertChoiceSlugs']) && is_array($data['expertChoiceSlugs'])) {
            $guide->setExpertChoiceSlugs($data['expertChoiceSlugs']);
        }
    }

    /**
     * Helper: Serialize guide for JSON response
     * Correspond exactement à ton type TypeScript "Guide" sur Next.js
     */
    private function serializeGuide(Guide $g): array
    {
        $formatImagePath = fn(?string $path) => $path ? '/' . ltrim($path, '/') : null;

        return [
            'id' => $g->getId(),
            'title' => $g->getTitle(),
            'slug' => $g->getSlug(),
            'description' => $g->getDescription(),
            'image' => $formatImagePath($g->getImage()),
            'content' => $g->getContent(),
            'switcher' => $g->getSwitcher(),
            'faq' => $g->getFaq(),
            'expertChoiceSlugs' => $g->getExpertChoiceSlugs(),
            'position' => $g->getPosition(),
            'created_at' => $g->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $g->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}