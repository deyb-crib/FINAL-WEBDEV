<?php

namespace App\Service;

use App\Entity\AdminActivity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AdminActivityLogger
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function record(?User $actor, string $action, string $subjectType, ?string $subjectId, string $summary): void
    {
        $this->em->persist(new AdminActivity(
            $actor?->getName() ?? 'System',
            $actor?->getEmail() ?? 'system',
            $action,
            $subjectType,
            $subjectId,
            mb_substr($summary, 0, 255),
        ));
        $this->em->flush();
    }
}
