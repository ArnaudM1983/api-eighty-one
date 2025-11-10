<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PostMeta
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: "metas")]
    #[ORM\JoinColumn(name: "post_id", referencedColumnName: "id")]
    private Post $post;

    #[ORM\Column(type: "string", length: 255)]
    private string $metaKey;

    #[ORM\Column(type: "text")]
    private string $metaValue;

    // getters et setters...
}
