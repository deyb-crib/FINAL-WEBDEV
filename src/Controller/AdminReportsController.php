<?php

namespace App\Controller;

use App\Service\AdminDashboardService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports')]
class AdminReportsController extends AbstractController
{
    #[Route('', name: 'app_admin_reports', methods: ['GET'])]
    public function index(Request $request, AdminDashboardService $dashboard): Response
    {
        $today = new DateTimeImmutable('today');
        $defaultFrom = $today->modify('-5 months')->modify('first day of this month');
        $fromInput = (string) $request->query->get('from', $defaultFrom->format('Y-m-d'));
        $toInput = (string) $request->query->get('to', $today->format('Y-m-d'));
        $from = $this->parseDate($fromInput);
        $to = $this->parseDate($toInput);
        $errors = [];

        if (!$from || !$to) {
            $errors[] = 'Choose valid start and end dates.';
        } elseif ($from > $to) {
            $errors[] = 'The start date must be on or before the end date.';
        } elseif ($from->diff($to)->days > 366) {
            $errors[] = 'Choose a date range of no more than one year.';
        }

        if ($errors) {
            $from = $defaultFrom;
            $to = $today;
        }

        $report = $dashboard->reports($from, $to);
        if (!$errors && $request->query->getBoolean('export')) {
            return $this->exportCsv($report, $from, $to);
        }

        return $this->render('admin/reports.html.twig', [
            'report' => $report,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'errors' => $errors,
        ], new Response(status: $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $parseErrors = DateTimeImmutable::getLastErrors();

        if (!$date || ($parseErrors && ($parseErrors['warning_count'] || $parseErrors['error_count'])) || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function exportCsv(array $report, DateTimeImmutable $from, DateTimeImmutable $to): Response
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to prepare the report export.');
        }

        fputcsv($stream, ['KATOC operational report', $from->format('Y-m-d'), $to->format('Y-m-d')]);
        fputcsv($stream, []);
        fputcsv($stream, ['Appointments', 'Total', $report['totals']['total']]);
        fputcsv($stream, ['Pending', 'Total', $report['totals']['pending']]);
        fputcsv($stream, ['Completed', 'Total', $report['totals']['completed']]);
        fputcsv($stream, []);
        fputcsv($stream, ['Monthly appointment requests']);
        fputcsv($stream, ['Month', 'Requests']);
        foreach ($report['monthly_appointments'] as $row) {
            fputcsv($stream, [$row['period'], $row['total']]);
        }
        fputcsv($stream, []);
        fputcsv($stream, ['Appointment statuses']);
        fputcsv($stream, ['Status', 'Count']);
        foreach ($report['appointment_statuses'] as $row) {
            fputcsv($stream, [$row['status'], $row['total']]);
        }
        fputcsv($stream, []);
        fputcsv($stream, ['User registrations by role']);
        fputcsv($stream, ['Role', 'Count']);
        foreach ($report['user_distribution'] as $row) {
            fputcsv($stream, [$row['role'], $row['total']]);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if ($content === false) {
            throw new \RuntimeException('Unable to read the report export.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="katoc-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
