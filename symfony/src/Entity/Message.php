<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A row of Croogo's messages table: a message sent through a contact form,
 * stored when the form archives messages. Croogo's admin lists them.
 *
 * The validation rules and their messages are Croogo's (app/models/
 * message.php). Croogo's "notEmpty" fails on whitespace too, hence the
 * trim normalizer. The text is stored as typed; pages that show it escape it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'messages')]
class Message
{
    private const BLANK = 'This field cannot be left blank.';
    private const INVALID_EMAIL = 'Please provide a valid email address.';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'contact_id', nullable: false)]
    private Contact $contact;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: self::BLANK, normalizer: 'trim')]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: self::INVALID_EMAIL)]
    #[Assert\Email(message: self::INVALID_EMAIL, mode: Assert\Email::VALIDATION_MODE_STRICT)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: self::BLANK, normalizer: 'trim')]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(message: self::BLANK, normalizer: 'trim')]
    private ?string $body = null;

    /** Croogo's admin marks messages as read with this; new ones are unread. */
    #[ORM\Column]
    private bool $status = false;

    #[ORM\Column]
    private \DateTimeImmutable $created;

    #[ORM\Column]
    private \DateTimeImmutable $updated;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
        $this->created = $this->updated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }
}
