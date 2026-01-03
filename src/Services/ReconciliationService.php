<?php

declare(strict_types=1);

namespace PayZephyr\VirtualAccounts\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PayZephyr\VirtualAccounts\Models\IncomingTransfer;
use PayZephyr\VirtualAccounts\Models\VirtualAccount;
use Throwable;

/**
 * Reconciliation Service
 *
 * Detects and resolves inconsistencies between local records and provider data.
 */
final class ReconciliationService
{
    public function __construct(
        protected VirtualAccountManager $manager
    ) {}

    /**
     * Reconcile all active accounts.
     *
     * @return array<string, mixed> Reconciliation report
     */
    public function reconcileAll(): array
    {
        $report = [
            'checked_accounts' => 0,
            'checked_providers' => [],
            'duplicates_found' => 0,
            'missing_confirmations' => 0,
            'balance_mismatches' => 0,
            'errors' => [],
        ];

        $providers = $this->manager->getEnabledProviders();

        foreach ($providers as $provider) {
            try {
                $providerReport = $this->reconcileProvider($provider);

                $report['checked_accounts'] += $providerReport['accounts_checked'];
                $report['checked_providers'][] = $provider;
                $report['duplicates_found'] += $providerReport['duplicates'];
                $report['missing_confirmations'] += $providerReport['missing_confirmations'];

            } catch (Throwable $e) {
                $report['errors'][] = [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ];

                Log::error('Reconciliation failed for provider', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Reconciliation completed', $report);

        return $report;
    }

    /**
     * Reconcile specific provider.
     *
     * @return array<string, int>
     */
    protected function reconcileProvider(string $provider): array
    {
        $accounts = VirtualAccount::where('provider', $provider)
            ->where('status', 'active')
            ->get();

        $report = [
            'accounts_checked' => $accounts->count(),
            'duplicates' => 0,
            'missing_confirmations' => 0,
        ];

        foreach ($accounts as $account) {
            // Check for duplicate transfers
            $duplicates = $this->findDuplicateTransfers($account->account_number);
            $report['duplicates'] += $duplicates->count();

            if ($duplicates->isNotEmpty()) {
                $this->resolveDuplicates($duplicates);
            }

            // Check for missing confirmations
            $pending = IncomingTransfer::where('account_number', $account->account_number)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subHours(24))
                ->get();

            $report['missing_confirmations'] += $pending->count();

            foreach ($pending as $transfer) {
                $this->investigatePendingTransfer($transfer);
            }
        }

        return $report;
    }

    /**
     * Find duplicate transfers.
     */
    public function findDuplicateTransfers(string $accountNumber): Collection
    {
        $duplicates = IncomingTransfer::where('account_number', $accountNumber)
            ->select('transaction_reference', DB::raw('COUNT(*) as count'))
            ->groupBy('transaction_reference')
            ->having('count', '>', 1)
            ->pluck('transaction_reference');

        if ($duplicates->isEmpty()) {
            return collect();
        }

        return IncomingTransfer::where('account_number', $accountNumber)
            ->whereIn('transaction_reference', $duplicates)
            ->get();
    }

    /**
     * Resolve duplicate transfers.
     */
    protected function resolveDuplicates(Collection $duplicates): void
    {
        foreach ($duplicates as $transfer) {
            // Keep the first confirmed transfer, mark others as duplicates
            if ($transfer->status !== 'confirmed') {
                $transfer->update(['status' => 'duplicate']);

                Log::warning('Duplicate transfer marked', [
                    'transfer_id' => $transfer->id,
                    'transaction_reference' => $transfer->transaction_reference,
                ]);
            }
        }
    }

    /**
     * Investigate pending transfers.
     *
     * @return array<string, int>
     */
    public function investigatePendingTransfers(): array
    {
        $staleHours = config('virtual-accounts.reconciliation.stale_transfer_hours', 24);
        
        $stale = IncomingTransfer::where('status', 'pending')
            ->where('created_at', '<', now()->subHours($staleHours))
            ->get();

        $investigated = 0;

        foreach ($stale as $transfer) {
            Log::warning('Stale pending transfer detected', [
                'transfer_id' => $transfer->id,
                'transaction_reference' => $transfer->transaction_reference,
                'age_hours' => $transfer->created_at->diffInHours(now()),
            ]);

            // Application-specific logic could:
            // - Query provider API to verify status
            // - Auto-confirm after certain period
            // - Mark as failed
            // - Send alert to operations team

            $investigated++;
        }

        return [
            'investigated' => $investigated,
            'total_stale' => $stale->count(),
        ];
    }

    /**
     * Investigate pending transfer (single).
     */
    protected function investigatePendingTransfer(IncomingTransfer $transfer): void
    {
        Log::warning('Stale pending transfer detected', [
            'transfer_id' => $transfer->id,
            'transaction_reference' => $transfer->transaction_reference,
            'age_hours' => $transfer->created_at->diffInHours(now()),
        ]);
    }

    /**
     * Get reconciliation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'total_accounts' => VirtualAccount::where('status', 'active')->count(),
            'total_transfers' => IncomingTransfer::count(),
            'confirmed_transfers' => IncomingTransfer::where('status', 'confirmed')->count(),
            'pending_transfers' => IncomingTransfer::where('status', 'pending')->count(),
            'total_value' => IncomingTransfer::where('status', 'confirmed')->sum('amount'),
            'providers' => VirtualAccount::select('provider', DB::raw('COUNT(*) as count'))
                ->groupBy('provider')
                ->get()
                ->pluck('count', 'provider')
                ->toArray(),
        ];
    }
}