<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\ManyToMany(targetEntity: DialysisCenter::class)]
    #[ORM\JoinTable(name: 'user_dialysis_centers')]
    private Collection $assignedCenters;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $active = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->assignedCenters = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = mb_strtolower($email); return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function addRole(string $role): static
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function isHospitalAdmin(): bool
    {
        return \in_array('ROLE_HOSPITAL_ADMIN', $this->getRoles(), true);
    }

    public function getAssignedCenters(): Collection
    {
        return $this->assignedCenters;
    }

    public function assignCenter(DialysisCenter $center): static
    {
        if (!$this->assignedCenters->contains($center)) {
            $this->assignedCenters->add($center);
        }

        return $this;
    }

    public function removeAssignedCenter(DialysisCenter $center): static
    {
        $this->assignedCenters->removeElement($center);

        return $this;
    }

    public function setAdmin(bool $admin): static
    {
        $roles = array_values(array_filter($this->roles, static fn (string $role): bool => $role !== 'ROLE_ADMIN'));
        if ($admin) {
            $roles[] = 'ROLE_ADMIN';
        }
        $this->roles = $roles;

        return $this;
    }

    public function setHospitalAdmin(bool $hospitalAdmin): static
    {
        $roles = array_values(array_filter($this->roles, static fn (string $role): bool => $role !== 'ROLE_HOSPITAL_ADMIN'));
        if ($hospitalAdmin) {
            $roles[] = 'ROLE_HOSPITAL_ADMIN';
        }
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->id === $user->id
            && $this->email === $user->email
            && $this->password === $user->password
            && $this->getRoles() === $user->getRoles()
            && $this->active === $user->active;
    }
}
