<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'appointments')]
#[ORM\Index(name: 'idx_slot', columns: ['center', 'appointment_date', 'appointment_time'])]
class Appointment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(length: 150)]
    public string $center = '';

    #[ORM\Column(length: 255)]
    public string $location = '';

    #[ORM\Column(length: 80)]
    public string $dialysisType = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public ?\DateTimeImmutable $appointmentDate = null;

    #[ORM\Column(length: 20)]
    public string $session = '';

    #[ORM\Column(length: 5)]
    public string $appointmentTime = '';

    #[ORM\Column(length: 100)]
    public string $patientName = '';

    #[ORM\Column(length: 30)]
    public string $patientContact = '';

    #[ORM\Column(length: 254)]
    public string $patientEmail = '';

    #[ORM\Column(length: 100)]
    public string $emergencyName = '';

    #[ORM\Column(length: 30)]
    public string $emergencyContact = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(length: 20)]
    public string $status = 'Pending';

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
