<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dialysis_centers')]
class DialysisCenter
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 150)]
    public string $name = '';

    #[ORM\Column(length: 255)]
    public string $address = '';

    #[ORM\Column(length: 60)]
    public string $operatingHours = '8:00 am - 5:00 pm';

    #[ORM\Column(length: 255)]
    public string $dialysisTypes = 'Hemodialysis Available';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $imagePath = null;

    #[ORM\Column(length: 20)]
    public string $status = 'Active';
}
