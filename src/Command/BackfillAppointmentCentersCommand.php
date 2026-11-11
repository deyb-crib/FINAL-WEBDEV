<?php

namespace App\Command;

use App\Entity\Appointment;
use App\Entity\DialysisCenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:backfill-appointment-centers',
    description: 'Link existing appointment records to dialysis centers by their saved center name',
)]
class BackfillAppointmentCentersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $centers = $this->em->getRepository(DialysisCenter::class)->findAll();
        $centersByName = [];
        $ambiguousNames = [];
        foreach ($centers as $center) {
            if (isset($centersByName[$center->name])) {
                unset($centersByName[$center->name]);
                $ambiguousNames[$center->name] = true;
                continue;
            }
            if (!isset($ambiguousNames[$center->name])) {
                $centersByName[$center->name] = $center;
            }
        }

        $unlinked = $this->em->getRepository(Appointment::class)->findBy(['dialysisCenter' => null]);
        $linkedCount = 0;
        $unmatchedCount = 0;

        foreach ($unlinked as $appointment) {
            $center = isset($ambiguousNames[$appointment->center]) ? null : ($centersByName[$appointment->center] ?? null);
            if (!$center) {
                ++$unmatchedCount;
                continue;
            }

            $appointment->dialysisCenter = $center;
            ++$linkedCount;
        }

        if ($linkedCount > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('%d existing appointment(s) linked to dialysis centers.', $linkedCount));
        if ($unmatchedCount > 0) {
            $output->writeln(sprintf('<comment>%d appointment(s) could not be matched and remain unassigned.</comment>', $unmatchedCount));
        }

        return Command::SUCCESS;
    }
}
