<?php

use App\Services\LegacyFamilyBillingImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:import-legacy-families {path : Full path to the legacy CSV file} {--legacy-year=2025 : Billing year stored for the imported legacy rows} {--current-year=2026 : Current-year student dataset used for matching} {--school-code=SSP : School prefix used when converting old family ids} {--dry-run : Show the match report without writing family billing rows}', function (LegacyFamilyBillingImporter $importer) {
    $report = $importer->import(
        (string) $this->argument('path'),
        (int) $this->option('legacy-year'),
        (int) $this->option('current-year'),
        (string) $this->option('school-code'),
        (bool) $this->option('dry-run'),
    );

    $this->info('Legacy family billing import summary');
    $this->line('Processed rows: '.$report['processed_rows']);
    $this->line('Matched rows: '.$report['matched_rows']);
    $this->line('Unmatched rows: '.$report['unmatched_rows']);
    $this->line('Ambiguous rows: '.$report['ambiguous_rows']);
    $this->line('Matched by student code: '.$report['matched_by_student_code']);
    $this->line('Matched by family code + name: '.$report['matched_by_family_code_and_name']);
    $this->line('Matched by unique name only: '.$report['matched_by_unique_name_only']);
    $this->line('Billing rows upserted: '.$report['billing_rows_upserted']);

    if ($report['families'] !== []) {
        $this->newLine();
        $this->table(
            ['Family Code', 'Legacy Family', 'Fee', 'Paid', 'Status', 'Match Methods'],
            array_map(fn (array $row) => [
                $row['family_code'],
                $row['legacy_family_code'],
                number_format((float) $row['fee_amount'], 2),
                number_format((float) $row['paid_amount'], 2),
                $row['status'],
                $row['match_methods'],
            ], $report['families'])
        );
    }

    if ($report['unmatched'] !== []) {
        $this->warn('Unmatched rows:');
        foreach (array_slice($report['unmatched'], 0, 10) as $row) {
            $this->line('- '.implode(' | ', array_filter([
                $row['family_id'] ? 'family='.$row['family_id'] : null,
                $row['student_name'] ? 'name='.$row['student_name'] : null,
                $row['student_code'] ? 'student_code='.$row['student_code'] : null,
            ])));
        }
    }

    if ($report['ambiguous'] !== []) {
        $this->warn('Ambiguous rows:');
        foreach (array_slice($report['ambiguous'], 0, 10) as $row) {
            $this->line('- '.implode(' | ', array_filter([
                $row['family_id'] ? 'family='.$row['family_id'] : null,
                $row['student_name'] ? 'name='.$row['student_name'] : null,
                $row['student_code'] ? 'student_code='.$row['student_code'] : null,
            ])));
        }
    }

    if ($report['dry_run']) {
        $this->comment('Dry run only: no database rows were written.');
    }
})->purpose('Import last-year family billing history by matching against the current-year student dataset.');
