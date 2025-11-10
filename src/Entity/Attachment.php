<?php

namespace App\Entity;

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
}
