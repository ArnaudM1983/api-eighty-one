<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Post
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $postTitle;

    #[ORM\Column(type: "string", length: 255)]
    private string $postName;

    #[ORM\Column(type: "string", length: 50)]
    private string $postType;

    #[ORM\Column(type: "string", length: 50)]
    private string $postStatus;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $postContent = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $postExcerpt = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $guid = null;

    #[ORM\Column(type: "integer")]
    private int $postParent;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $postMimeType = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: "posts")]
    #[ORM\JoinColumn(name: "category_id", referencedColumnName: "id", nullable: true)]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: "post", targetEntity: PostMeta::class)]
    private Collection $metas;

    #[ORM\OneToMany(mappedBy: "post", targetEntity: Attachment::class)]
    private Collection $attachments;

    public function __construct()
    {
        $this->metas = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    // getters et setters...
}
