<?php

namespace App\Service;

use App\Entity\AdminActivity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class AdminDashboardService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function overview(): array
    {
        $connection = $this->em->getConnection();
        $userCounts = $connection->fetchAssociative(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive
             FROM users'
        );
        $appointmentCounts = $connection->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed
             FROM appointments"
        );
        $centerCounts = $connection->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN status <> 'Active' THEN 1 ELSE 0 END), 0) AS inactive
             FROM dialysis_centers"
        );
        $overdue = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM appointments
             WHERE status = 'Confirmed' AND appointment_date < CURRENT_DATE()"
        );

        return [
            'users' => array_map('intval', $userCounts),
            'appointments' => array_map('intval', $appointmentCounts),
            'centers' => array_map('intval', $centerCounts),
            'alerts' => [
                'pending_appointments' => (int) $appointmentCounts['pending'],
                'inactive_centers' => (int) $centerCounts['inactive'],
                'overdue_appointments' => $overdue,
            ],
            'recent_activities' => $this->em->getRepository(AdminActivity::class)->findBy([], ['createdAt' => 'DESC'], 8),
        ];
    }

    public function reports(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $endExclusive = $to->modify('+1 day');
        $connection = $this->em->getConnection();
        $dateParameters = [
            'from' => $from->setTime(0, 0),
            'to' => $endExclusive->setTime(0, 0),
        ];

        $monthlyAppointments = $connection->executeQuery(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total
             FROM appointments
             WHERE created_at >= :from AND created_at < :to
             GROUP BY period ORDER BY period",
            $dateParameters,
            ['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE],
        )->fetchAllAssociative();

        $appointmentStatuses = $connection->executeQuery(
            'SELECT status, COUNT(*) AS total
             FROM appointments
             WHERE created_at >= :from AND created_at < :to
             GROUP BY status ORDER BY total DESC, status',
            $dateParameters,
            ['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE],
        )->fetchAllAssociative();

        $userDistribution = $connection->fetchAllAssociative(
            "SELECT CASE
                        WHEN JSON_CONTAINS(roles, JSON_QUOTE('ROLE_ADMIN')) THEN 'System administrators'
                        WHEN JSON_CONTAINS(roles, JSON_QUOTE('ROLE_HOSPITAL_ADMIN')) THEN 'Hospital administrators'
                        WHEN JSON_CONTAINS(roles, JSON_QUOTE('ROLE_HOSPITAL_STAFF')) THEN 'Hospital staff'
                        ELSE 'Patients'
                    END AS role,
                    COUNT(*) AS total
             FROM users
             WHERE created_at >= :from AND created_at < :to
             GROUP BY role ORDER BY total DESC",
            $dateParameters,
            ['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE],
        );

        $totals = $connection->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed
             FROM appointments WHERE created_at >= :from AND created_at < :to",
            $dateParameters,
            ['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE],
        );

        return [
            'totals' => array_map('intval', $totals),
            'monthly_appointments' => $this->withPercentages(array_map(static fn (array $row): array => [
                'period' => $row['period'],
                'total' => (int) $row['total'],
            ], $monthlyAppointments)),
            'appointment_statuses' => $this->withPercentages(array_map(static fn (array $row): array => [
                'status' => $row['status'],
                'total' => (int) $row['total'],
            ], $appointmentStatuses)),
            'user_distribution' => $this->withPercentages(array_map(static fn (array $row): array => [
                'role' => $row['role'],
                'total' => (int) $row['total'],
            ], $userDistribution)),
        ];
    }

    private function withPercentages(array $rows): array
    {
        $max = max(array_column($rows, 'total') ?: [0]);

        return array_map(static function (array $row) use ($max): array {
            $row['percent'] = $max > 0 ? max(5, (int) round($row['total'] / $max * 100)) : 0;

            return $row;
        }, $rows);
    }
}
