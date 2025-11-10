<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Attachment
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "attachments")]
    #[ORM\JoinColumn(name: "post_parent", referencedColumnName: "id", nullable: true)]
    private ?Post $post = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $postTitle;

    #[ORM\Column(type: "string", length: 255)]
    private string $postName;

    #[ORM\Column(type: "string", length: 50)]
    private string $postType;

    #[ORM\Column(type: "string", length: 50)]
    private string $postStatus;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $guid = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $postContent = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $postExcerpt = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $postMimeType = null;

    // getters et setters...

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

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function setGuid(?string $guid): static
    {
        $this->guid = $guid;

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

    public function getPostMimeType(): ?string
    {
        return $this->postMimeType;
    }

    public function setPostMimeType(?string $postMimeType): static
    {
        $this->postMimeType = $postMimeType;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }
}
