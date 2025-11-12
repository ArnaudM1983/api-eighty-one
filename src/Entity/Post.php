<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

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

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPostTitle(): ?string
    {
        return $this->postTitle;
    }

    public function setPostTitle(string $postTitle): static
    {
        $this->postTitle = $postTitle;

        return $this;
    }

    public function getPostName(): ?string
    {
        return $this->postName;
    }

    public function setPostName(string $postName): static
    {
        $this->postName = $postName;

        return $this;
    }

    public function getPostType(): ?string
    {
        return $this->postType;
    }

    public function setPostType(string $postType): static
    {
        $this->postType = $postType;

        return $this;
    }

    public function getPostStatus(): ?string
    {
        return $this->postStatus;
    }

    public function setPostStatus(string $postStatus): static
    {
        $this->postStatus = $postStatus;

        return $this;
    }

    public function getPostContent(): ?string
    {
        return $this->postContent;
    }

    public function setPostContent(?string $postContent): static
    {
        $this->postContent = $postContent;

        return $this;
    }

    public function getPostExcerpt(): ?string
    {
        return $this->postExcerpt;
    }

    public function setPostExcerpt(?string $postExcerpt): static
    {
        $this->postExcerpt = $postExcerpt;

        return $this;
    }

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function setGuid(?string $guid): static
    {
        $this->guid = $guid;

        return $this;
    }

    public function getPostParent(): ?int
    {
        return $this->postParent;
    }

    public function setPostParent(int $postParent): static
    {
        $this->postParent = $postParent;

        return $this;
    }

    public function getPostMimeType(): ?string
    {
        return $this->postMimeType;
    }

    public function setPostMimeType(?string $postMimeType): static
    {
        $this->postMimeType = $postMimeType;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, PostMeta>
     */
    public function getMetas(): Collection
    {
        return $this->metas;
    }

    public function addMeta(PostMeta $meta): static
    {
        if (!$this->metas->contains($meta)) {
            $this->metas->add($meta);
            $meta->setPost($this);
        }

        return $this;
    }

    public function removeMeta(PostMeta $meta): static
    {
        if ($this->metas->removeElement($meta)) {
            // set the owning side to null (unless already changed)
            if ($meta->getPost() === $this) {
                $meta->setPost(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setPost($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): static
    {
        if ($this->attachments->removeElement($attachment)) {
            // set the owning side to null (unless already changed)
            if ($attachment->getPost() === $this) {
                $attachment->setPost(null);
            }
        }

        return $this;
    }
}
