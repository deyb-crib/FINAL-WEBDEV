<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_activities')]
#[ORM\Index(name: 'idx_admin_activity_created', columns: ['created_at'])]
class AdminActivity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $actorName;

    #[ORM\Column(length: 180)]
    private string $actorEmail;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 80)]
    private string $subjectType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId;

    #[ORM\Column(length: 255)]
    private string $summary;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $actorName,
        string $actorEmail,
        string $action,
        string $subjectType,
        ?string $subjectId,
        string $summary,
    ) {
        $this->actorName = $actorName;
        $this->actorEmail = $actorEmail;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->summary = $summary;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getActorName(): string { return $this->actorName; }
    public function getActorEmail(): string { return $this->actorEmail; }
    public function getAction(): string { return $this->action; }
    public function getSubjectType(): string { return $this->subjectType; }
    public function getSubjectId(): ?string { return $this->subjectId; }
    public function getSummary(): string { return $this->summary; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
