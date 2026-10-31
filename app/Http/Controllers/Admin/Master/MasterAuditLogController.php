<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Layar baca-saja riwayat perubahan master unit, jabatan, dan kursi formasi (SECURITY.md §5).
 */
class MasterAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'entity_type' => 'nullable|string|in:unit,position,seat',
            'user_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $logs = MasterAuditLog::query()
            ->when($filters['entity_type'] ?? null, fn ($q, $type) => $q->where('entity_type', $type))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $admins = User::whereIn('id', MasterAuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.master.riwayat.index', [
            'logs' => $logs,
            'admins' => $admins,
            'filters' => $filters,
            'entityLabels' => MasterAuditLog::ENTITY_LABELS,
        ]);
    }
}
