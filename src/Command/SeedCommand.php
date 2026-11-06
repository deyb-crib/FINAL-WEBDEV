<?php

namespace App\Command;

use App\Entity\DialysisCenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed', description: 'Insert the default dialysis centers if the table is empty')]
class SeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->em->getRepository(DialysisCenter::class)->count([]) > 0) {
            $output->writeln('Centers already exist, nothing to seed.');
            return Command::SUCCESS;
        }

        $hemo = 'Hemodialysis Available';
        $rows = [
            ['Love Center', 'Dumaguete City, Negros Oriental', $hemo, 'images/hospital1.png'],
            ['Nephrology Center of Dumaguete City Dialysis, Inc.', 'Dumaguete City, Negros Oriental', $hemo, 'images/hospital2.png'],
            ['HemoCent', 'Valencia, Negros Oriental', $hemo, 'images/hospital3.png'],
            ['Sibulan Dialysis Center', 'Sibulan, Negros Oriental', $hemo, null],
            ['Bais Community Hospital', 'Bais City, Negros Oriental', $hemo, null],
            ['Tanjay Renal Care Center', 'Tanjay City, Negros Oriental', $hemo, null],
            ['Bayawan Medical Center', 'Bayawan City, Negros Oriental', $hemo, null],
            ['Dumaguete Kidney Institute', 'Dumaguete City, Negros Oriental', 'Peritoneal Dialysis Support', null],
        ];
        foreach ($rows as [$name, $address, $types, $image]) {
            $c = new DialysisCenter();
            $c->name = $name;
            $c->address = $address;
            $c->dialysisTypes = $types;
            $c->imagePath = $image;
            $this->em->persist($c);
        }
        $this->em->flush();
        $output->writeln(count($rows).' centers inserted.');

        return Command::SUCCESS;
    }
}
