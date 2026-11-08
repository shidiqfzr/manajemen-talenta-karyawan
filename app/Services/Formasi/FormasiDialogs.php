<?php

namespace App\Services\Formasi;

use App\Models\Position;
use App\Models\Unit;

/**
 * Menyusun payload dialog aksi Master Data (event "master-action" pada x-admin.master.action-dialog)
 * agar view tidak memuat aturan bisnis (RM Band, bidang, wilayah) maupun konfigurasi formulir.
 */
class FormasiDialogs
{
    private const REASON_HINT = 'Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master.';

    /**
     * @return list<array{value: string, label: string}>
     */
    public function wilayahOptions(): array
    {
        return collect(Unit::WILAYAH_LIST)->map(fn ($w) => ['value' => $w, 'label' => $w])->values()->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function bidangOptions(): array
    {
        return collect(Position::BIDANG_LIST)->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values()->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function levelOptions(): array
    {
        return collect(Position::LEVEL_LIST)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    /**
     * Pilihan RM Band; nilai jabatan saat ini (mis. RM-4 jabatan pool) tetap ditawarkan agar tidak hilang saat disimpan.
     *
     * @return list<array{value: string, label: string}>
     */
    public function rmOptions(?string $current = null, ?string $level = null): array
    {
        $levels = $level === 'Karpel' ? Position::KARPEL_RM_LEVELS : Position::KARPIM_RM_LEVELS;

        $options = collect($levels)
            ->map(fn ($rm) => ['value' => $rm, 'label' => Position::RM_LEVEL_LIST[$rm]])
            ->all();

        if ($current && ! in_array($current, $levels, true)) {
            $options[] = ['value' => $current, 'label' => $current.' (nilai saat ini)'];
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function createUnit(): array
    {
        $wilayah = $this->wilayahOptions();

        return [
            'title' => 'Tambah Unit Kerja',
            'description' => 'Unit baru langsung aktif. Setelah dibuat, tambahkan formasi jabatannya.',
            'url' => route('admin.master.units.store'),
            'method' => 'POST',
            'submit' => 'Tambah Unit',
            'tone' => 'primary',
            'fields' => [
                ['name' => 'nama', 'label' => 'Nama Unit Kerja', 'type' => 'text', 'value' => '', 'required' => true],
                ['name' => 'kode', 'label' => 'Kode Singkatan', 'type' => 'text', 'value' => '', 'required' => false],
                ['name' => 'wilayah', 'label' => 'Wilayah Operasional', 'type' => 'select', 'value' => $wilayah[1]['value'] ?? '', 'required' => true, 'options' => $wilayah],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function editUnit(Unit $unit): array
    {
        return [
            'title' => 'Ubah Unit Kerja',
            'description' => $unit->nama,
            'url' => route('admin.master.units.update', $unit),
            'method' => 'PUT',
            'submit' => 'Simpan Perubahan',
            'tone' => 'primary',
            'fields' => [
                ['name' => 'nama', 'label' => 'Nama Unit Kerja', 'type' => 'text', 'value' => $unit->nama, 'required' => true],
                ['name' => 'kode', 'label' => 'Kode Singkatan', 'type' => 'text', 'value' => (string) $unit->kode, 'required' => false],
                ['name' => 'wilayah', 'label' => 'Wilayah Operasional', 'type' => 'select', 'value' => $unit->wilayah, 'required' => true, 'options' => $this->wilayahOptions()],
                ['name' => 'urutan', 'label' => 'Urutan Tampil', 'type' => 'number', 'value' => (string) $unit->urutan, 'required' => false],
                ['name' => 'is_active', 'type' => 'hidden', 'value' => $unit->is_active ? '1' : '0'],
                ['name' => 'reason', 'label' => 'Alasan Perubahan (wajib bila mengubah nama)', 'type' => 'textarea', 'value' => '', 'required' => false, 'placeholder' => self::REASON_HINT],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function toggleUnit(Unit $unit): array
    {
        return [
            'title' => $unit->is_active ? 'Nonaktifkan Unit Kerja' : 'Aktifkan Unit Kerja',
            'description' => $unit->is_active
                ? $unit->nama.' tidak akan muncul pada pilihan penempatan baru. Unit yang masih memiliki karyawan tidak dapat dinonaktifkan.'
                : $unit->nama.' akan kembali muncul pada pilihan penempatan.',
            'url' => route('admin.master.units.toggle', $unit),
            'method' => 'POST',
            'submit' => $unit->is_active ? 'Nonaktifkan' : 'Aktifkan',
            'tone' => $unit->is_active ? 'danger' : 'primary',
            'fields' => $unit->is_active
                ? [['name' => 'reason', 'label' => 'Alasan Penonaktifan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => self::REASON_HINT]]
                : [],
        ];
    }

    /** @return array<string, mixed> */
    public function deleteUnit(Unit $unit): array
    {
        return [
            'title' => 'Hapus Unit Kerja',
            'description' => $unit->nama.' hanya dapat dihapus bila tidak dipakai karyawan, formasi jabatan, maupun riwayat jabatan. Bila masih dipakai, gunakan Nonaktifkan.',
            'url' => route('admin.master.units.destroy', $unit),
            'method' => 'DELETE',
            'submit' => 'Hapus Unit',
            'tone' => 'danger',
            'fields' => [['name' => 'reason', 'label' => 'Alasan Penghapusan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => self::REASON_HINT]],
        ];
    }

    /** @return array<string, mixed> */
    public function editPosition(object $seat, Unit $unit): array
    {
        return [
            'title' => 'Ubah Jabatan',
            'description' => $seat->jabatan.' — perubahan nama dan RM Band berlaku untuk seluruh unit pemakai jabatan ini.',
            'url' => route('admin.master.positions.update', $seat->position_id),
            'method' => 'PUT',
            'submit' => 'Simpan Perubahan',
            'tone' => 'primary',
            'fields' => [
                ['name' => 'nama', 'label' => 'Nama Jabatan', 'type' => 'text', 'value' => $seat->jabatan, 'required' => true],
                ['name' => 'level', 'label' => 'Strata', 'type' => 'select', 'value' => $seat->level, 'required' => true, 'options' => $this->levelOptions()],
                ['name' => 'rm_level', 'label' => 'RM Band (berlaku untuk seluruh unit pemakai jabatan)', 'type' => 'select', 'value' => (string) $seat->rm_level, 'required' => false, 'options' => $this->rmOptions($seat->rm_level, $seat->level)],
                ['name' => 'is_active', 'type' => 'hidden', 'value' => $seat->position_active ? '1' : '0'],
                ['name' => 'return_unit', 'type' => 'hidden', 'value' => (string) $unit->id],
                ['name' => 'reason', 'label' => 'Alasan Perubahan (wajib bila mengubah nama atau RM Band)', 'type' => 'textarea', 'value' => '', 'required' => false, 'placeholder' => self::REASON_HINT],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function togglePosition(object $seat, Unit $unit): array
    {
        $returnUnit = ['name' => 'return_unit', 'type' => 'hidden', 'value' => (string) $unit->id];

        return [
            'title' => $seat->position_active ? 'Nonaktifkan Jabatan' : 'Aktifkan Jabatan',
            'description' => $seat->position_active
                ? $seat->jabatan.' tidak akan muncul pada pilihan penempatan baru. Jabatan yang masih dipegang karyawan tidak dapat dinonaktifkan.'
                : $seat->jabatan.' akan kembali muncul pada pilihan penempatan.',
            'url' => route('admin.master.positions.toggle', $seat->position_id),
            'method' => 'POST',
            'submit' => $seat->position_active ? 'Nonaktifkan' : 'Aktifkan',
            'tone' => $seat->position_active ? 'danger' : 'primary',
            'fields' => $seat->position_active
                ? [
                    ['name' => 'reason', 'label' => 'Alasan Penonaktifan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => self::REASON_HINT],
                    $returnUnit,
                ]
                : [$returnUnit],
        ];
    }
}
