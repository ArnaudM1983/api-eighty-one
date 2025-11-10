<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "string", length: 255)]
    private string $slug;

    #[ORM\Column(type: "integer")]
    private int $parent;

    #[ORM\Column(type: "integer")]
    private int $termTaxonomyId;

    #[ORM\OneToMany(mappedBy: "category", targetEntity: Post::class)]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    // getters et setters...
}
