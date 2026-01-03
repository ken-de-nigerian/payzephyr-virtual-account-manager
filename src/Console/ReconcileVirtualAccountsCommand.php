<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Console;

use Illuminate\Console\Command;
use PayZephyr\VirtualAccounts\Services\ReconciliationService;

/**
 * Reconciliation Command
 *
 * php artisan virtual-accounts:reconcile
 */
final class ReconcileVirtualAccountsCommand extends Command
{
    protected $signature = 'virtual-accounts:reconcile 
                          {--provider= : Reconcile specific provider only}';

    protected $description = 'Reconcile virtual accounts and detect inconsistencies';

    public function handle(ReconciliationService $reconciliation): int
    {
        $this->info('Starting virtual account reconciliation...');

        $report = $reconciliation->reconcileAll();

        $this->newLine();
        $this->info('Reconciliation Report:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Accounts Checked', $report['checked_accounts']],
                ['Providers Checked', implode(', ', $report['checked_providers'])],
                ['Duplicates Found', $report['duplicates_found']],
                ['Missing Confirmations', $report['missing_confirmations']],
                ['Errors', count($report['errors'])],
            ]
        );

        if (!empty($report['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($report['errors'] as $error) {
                $this->error("  {$error['provider']}: {$error['error']}");
            }
        }

        $this->newLine();
        $statistics = $reconciliation->getStatistics();
        $this->info('System Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Active Accounts', $statistics['total_accounts']],
                ['Total Transfers', $statistics['total_transfers']],
                ['Confirmed Transfers', $statistics['confirmed_transfers']],
                ['Pending Transfers', $statistics['pending_transfers']],
                ['Total Value', number_format($statistics['total_value'], 2)],
            ]
        );

        return self::SUCCESS;
    }
}