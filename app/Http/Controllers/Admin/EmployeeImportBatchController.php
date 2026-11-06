<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ImportAbortedException;
use App\Http\Controllers\Controller;
use App\Models\EmployeeImportLog;
use App\Services\EmployeeImportGovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Riwayat dan tata kelola impor data pegawai: persetujuan empat mata (maker-checker) dan pembatalan (undo).
 * Seluruh aturan berada di EmployeeImportGovernanceService; controller hanya menerjemahkan HTTP.
 */
class EmployeeImportBatchController extends Controller
{
    private const ROWS_PER_PAGE = 25;

    public function __construct(private EmployeeImportGovernanceService $governance) {}

    private const TABS = [
        'all' => 'Semua',
        'pending' => 'Perlu Tindakan',
        'saved' => 'Tersimpan',
        'closed' => 'Ditutup',
    ];

    private const TAB_STATUSES = [
        'pending' => [EmployeeImportLog::STATUS_PENDING, EmployeeImportLog::STATUS_APPROVED],
        'saved' => [EmployeeImportLog::STATUS_COMPLETED],
        'closed' => [EmployeeImportLog::STATUS_REJECTED, EmployeeImportLog::STATUS_EXPIRED, EmployeeImportLog::STATUS_ROLLED_BACK],
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('status'), self::TABS) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));

        $logs = EmployeeImportLog::query()->withCount('changes')
            ->when(isset(self::TAB_STATUSES[$tab]), fn ($query) => $query->whereIn('status', self::TAB_STATUSES[$tab]))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('file_name', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $byStatus = EmployeeImportLog::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = (int) $byStatus->only($statuses)->sum();
        }

        return view('admin.employees.import-batches.index', [
            'logs' => $logs,
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'search' => $search,
            'requiresApproval' => EmployeeImportGovernanceService::requiresApproval(),
            'undoWindowDays' => (int) config('hris.import.undo_window_days', 7),
            'approvalTtlDays' => (int) config('hris.import.approval_ttl_days', 7),
        ]);
    }

    public function show(Request $request, EmployeeImportLog $log): View
    {
        $review = null;
        $reviewRows = null;
        $reviewError = null;

        if ($log->status === EmployeeImportLog::STATUS_PENDING) {
            try {
                $review = $this->governance->review($log, $request->boolean('refresh'));
                $reviewRows = $this->paginate($review['summary']['sample_rows'] ?? [], $request);
            } catch (ImportAbortedException $e) {
                $reviewError = $e->getMessage();
            }
        }

        $canRollback = $log->isWithinUndoWindow() && $log->changes()->exists();

        // Baris yang dilewati: dari pratinjau terkini (pengajuan) atau dari ringkasan yang tersimpan (tanpa NIK).
        $skipped = $review['summary']['errors'] ?? ($log->metadata['summary']['errors'] ?? []);

        return view('admin.employees.import-batches.show', [
            'log' => $log,
            'review' => $review,
            'reviewRows' => $reviewRows,
            'reviewError' => $reviewError,
            'isMaker' => $log->user_id === $request->user()->id,
            'canRollback' => $canRollback,
            'undoDeadline' => $log->undoDeadline(),
            'readiness' => $canRollback ? $this->governance->rollbackReadiness($log) : null,
            'typedConfirmation' => $this->governance->requiresTypedConfirmation($log),
            'confirmationWord' => EmployeeImportGovernanceService::CONFIRMATION_WORD,
            'skippedGroups' => EmployeeImportGovernanceService::groupSkippedRows($skipped),
            'skippedRows' => $skipped,
            'correctionExpiresAt' => ($log->committed_at ?? $log->created_at)->copy()->addHours(2),
            'canDownloadFailed' => $log->token && Cache::has("import_failed_rows_{$log->token}"),
        ]);
    }

    public function approve(Request $request, EmployeeImportLog $log): RedirectResponse
    {
        $data = $request->validate(['reviewed_digest' => ['required', 'string', 'size:64']]);

        try {
            $result = $this->governance->approve($log, $request->user(), $data['reviewed_digest'], $request->ip(), $request->userAgent());
        } catch (ImportAbortedException $e) {
            return redirect()->route('admin.employees.import.batches.show', $log)->with('error', $e->getMessage());
        }

        $message = ($result['is_async'] ?? false)
            ? 'Persetujuan diterima. Data pegawai sedang disimpan di antrean latar belakang.'
            : "Impor disetujui dan disimpan: {$result['created']} data baru, {$result['updated']} data diperbarui, ".($result['unchanged'] ?? 0).' data tetap.';

        return redirect()->route('admin.employees.import.batches.show', $log)->with('success', $message);
    }

    public function reject(Request $request, EmployeeImportLog $log): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 10 karakter agar tercatat jelas pada jejak audit.',
        ]);

        try {
            $this->governance->reject($log, $request->user(), $data['reason'], $request->ip());
        } catch (ImportAbortedException $e) {
            return redirect()->route('admin.employees.import.batches.show', $log)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.employees.import.batches.index')->with('success', 'Pengajuan impor ditolak. Data pegawai tidak berubah.');
    }

    public function rollback(Request $request, EmployeeImportLog $log): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'confirmation' => ['nullable', 'string', 'max:20'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan minimal 10 karakter agar tercatat jelas pada jejak audit.',
        ]);

        try {
            $result = $this->governance->rollback($log, $request->user(), $data['reason'], $request->ip(), $data['confirmation'] ?? null);
        } catch (ImportAbortedException $e) {
            return redirect()->route('admin.employees.import.batches.show', $log)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.employees.import.batches.show', $log)
            ->with('success', "Impor dibatalkan: {$result['deleted']} pegawai baru dihapus dan {$result['restored']} pegawai dikembalikan ke kondisi sebelum impor.");
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function paginate(array $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::ROWS_PER_PAGE, self::ROWS_PER_PAGE),
            count($rows),
            self::ROWS_PER_PAGE,
            $page,
            ['path' => $request->url()]
        );
    }
}
