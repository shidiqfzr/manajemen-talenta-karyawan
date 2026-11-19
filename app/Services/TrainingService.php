<?php

namespace App\Services;

use App\Models\MasterAuditLog;
use App\Models\Training;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public function recalculateManHours(Training $training): void
    {
        $jumlahPeserta = $training->employees()->count();
        $jumlahHari = Carbon::parse($training->tanggal_mulai)->diffInDays(Carbon::parse($training->tanggal_akhir)) + 1;
        $jamPerHari = $training->jam_belajar_per_hari;

        $training->jumlah_man_hours = $jumlahHari * $jamPerHari * $jumlahPeserta;
        $training->save();
    }

    /**
     * Menyimpan pelatihan baru beserta Man Hours awal dan jejak audit dalam satu transaksi.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Training
    {
        // Kolom keterangan wajib terisi di basis data; isian kosong disimpan sebagai teks kosong.
        $data['keterangan'] ??= '';

        return DB::transaction(function () use ($data): Training {
            $training = Training::create($data);
            $this->recalculateManHours($training);

            $this->audit('training.created', $training, null, $this->snapshot($training, array_keys($data)));

            return $training;
        });
    }

    /**
     * Memperbarui pelatihan, menghitung ulang Man Hours, dan mencatat perubahan dalam satu transaksi.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Training $training, array $data): Training
    {
        $data['keterangan'] ??= '';

        return DB::transaction(function () use ($training, $data): Training {
            $training->fill($data);
            $columns = array_keys($training->getDirty());
            $old = $this->snapshot($training, $columns, original: true);
            $training->save();
            $this->recalculateManHours($training);

            if ($columns !== []) {
                $this->audit('training.updated', $training, $old, $this->snapshot($training, $columns));
            }

            return $training;
        });
    }

    /**
     * Menghapus pelatihan (beserta keikutsertaan peserta lewat relasi) dan mencatat penghapusan.
     */
    public function delete(Training $training, string $reason): void
    {
        DB::transaction(function () use ($training, $reason): void {
            $this->audit('training.deleted', $training, null, ['jumlah' => $training->employees()->count()], $reason);

            $training->delete();
        });
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function snapshot(Training $training, array $columns, bool $original = false): array
    {
        $snapshot = [];

        foreach (array_diff($columns, ['jumlah_man_hours']) as $column) {
            $value = $original ? $training->getOriginal($column) : $training->getAttribute($column);
            $snapshot[$column] = $value instanceof CarbonInterface ? $value->format('Y-m-d') : $value;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(string $action, Training $training, ?array $old, ?array $new, ?string $reason = null): void
    {
        MasterAuditLog::record(auth()->user(), $action, 'training', $training->id, 'Pelatihan '.$training->judul, $old, $new, $reason);
    }
}
