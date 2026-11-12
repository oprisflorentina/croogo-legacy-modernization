<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A row of Croogo's contacts table: a contact form's settings.
 *
 * Croogo owns the table and its admin edits it, so Symfony only reads it
 * (readOnly) and maps only the columns the contact page needs. No migration
 * ever touches it.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'contacts')]
class Contact
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $alias;

    /** HTML written by an admin, shown above the form. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    /** Where messages are emailed. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    /** "Let users leave a message": without it, the form isn't shown. */
    #[ORM\Column(name: 'message_status')]
    private bool $acceptsMessages;

    /** "Save messages in database". */
    #[ORM\Column(name: 'message_archive')]
    private bool $archivesMessages;

    /** "Notify by email instantly". */
    #[ORM\Column(name: 'message_notify')]
    private bool $notifiesByEmail;

    #[ORM\Column]
    private bool $status;

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function acceptsMessages(): bool
    {
        return $this->acceptsMessages;
    }

    public function archivesMessages(): bool
    {
        return $this->archivesMessages;
    }

    public function notifiesByEmail(): bool
    {
        return $this->notifiesByEmail;
    }

    public function isPublished(): bool
    {
        return $this->status;
    }
}
