<?php

// Berkas uji E2E untuk Impor Data Karyawan: dibuat dari kursi formasi nyata pada basis data sementara (tanpa NIK atau nama sungguhan),
// memakai satu daftar kolom (EmployeeImportColumns) seperti template resmi. Fakta yang diperiksa spesifikasi ditulis ke JSON.
use App\Models\Employee;
use App\Services\Import\EmployeeImportColumns;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tmp = base_path('tests/e2e/.tmp');
@mkdir($tmp, 0777, true);

$seats = DB::table('position_unit')
    ->join('units', 'units.id', '=', 'position_unit.unit_id')
    ->join('positions', 'positions.id', '=', 'position_unit.position_id')
    ->where('units.is_active', true)->where('positions.is_active', true)
    ->where('positions.is_overflow', false)->where('positions.is_diperban', false)
    ->where('position_unit.kuota', '>', 0)
    ->orderBy('units.urutan')->orderBy('positions.nama')
    ->get(['units.nama as unit', 'positions.nama as jabatan', 'positions.level', 'position_unit.kuota'])
    ->all();

// Hanya kursi yang masih punya sisa kuota: seeder lain sudah menempatkan karyawan pada sebagian kursi, dan kursi penuh ditolak impor.
$occupied = Employee::aktif()->where('status_penugasan', 'Definitif')
    ->selectRaw('unit_kerja, jabatan, count(*) as total')->groupBy('unit_kerja', 'jabatan')->get()
    ->mapWithKeys(fn ($r) => [$r->unit_kerja.'|'.$r->jabatan => (int) $r->total]);
$seats = array_values(array_filter($seats, fn (object $seat) => (int) $seat->kuota > ($occupied[$seat->unit.'|'.$seat->jabatan] ?? 0)));

$row = function (string $nik, string $nama, object $seat, array $override = []): array {
    $karpim = $seat->level === 'Karpim';
    $values = $override + [
        'nik' => $nik, 'nama' => $nama, 'jenis_kelamin' => 'Laki-laki', 'tempat_lahir' => 'Pontianak', 'tanggal_lahir' => '1988-05-10',
        'agama' => 'Islam', 'susunan_keluarga' => 'K/2', 'status_karyawan' => 'Tetap (PKWTT)', 'unit_kerja' => $seat->unit, 'jabatan' => $seat->jabatan,
        'golongan' => $karpim ? 'IIIA/00' : 'IIA/00', 'status_penugasan' => 'Definitif', 'tugas_rangkap_penugasan_sementara' => '',
        'tmt_bekerja' => '2015-01-01', 'tanggal_diangkat_staf' => '2017-01-01', 'tanggal_dalam_jabatan' => '2020-01-01', 'tmt_unit_kerja' => '2020-01-01',
        'jalur_masuk' => 'Reguler', 'job_grade' => $karpim ? 11 : 5, 'person_grade' => $karpim ? 11 : 5, 'pendidikan_terakhir' => 'S1', 'sekolah' => 'Universitas Uji',
        'tanggal_mbt' => '', 'tanggal_pensiun' => '',
    ];

    return array_map(fn (string $field) => $values[$field], EmployeeImportColumns::fields(EmployeeImportColumns::LAYOUT_TEMPLATE));
};

$write = function (string $name, array $rows) use ($tmp): void {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([EmployeeImportColumns::headings(EmployeeImportColumns::LAYOUT_TEMPLATE, true), ...$rows], null, 'A1', true);
    (new Xlsx($book))->save("{$tmp}/{$name}");
};

// 1. Berkas valid: 30 karyawan baru pada 30 kursi berbeda.
$valid = array_slice($seats, 0, 30);
$validNiks = [];
$validRows = [];
foreach ($valid as $i => $seat) {
    $nik = sprintf('7700%04d', $i + 1);
    $validNiks[] = $nik;
    $validRows[] = $row($nik, sprintf('KARYAWAN UJI %02d', $i + 1), $seat);
}
$write('import-valid.xlsx', $validRows);

// 2. Berkas dengan kendala: 3 baris valid, satu tanpa jabatan, satu rangkap tanpa unit untuk jabatan yang ada di unit lain.
$seatA = $seats[0];
// Jabatan yang hanya ada pada satu unit (selain unit karyawan), supaya petunjuk unit pada pesan galat pasti memuatnya.
$seatB = collect($seats)->first(function (object $candidate) use ($seatA, $seats): bool {
    return $candidate->unit !== $seatA->unit
        && collect($seats)->where('jabatan', $candidate->jabatan)->pluck('unit')->unique()->count() === 1;
});
$errorRows = [
    $row('7710001', 'KENDALA VALID SATU', $seats[1]),
    $row('7710002', 'KENDALA VALID DUA', $seats[2]),
    $row('7710003', 'KENDALA VALID TIGA', $seats[3]),
    $row('7710101', 'KENDALA TANPA JABATAN', $seats[4], ['jabatan' => '']),
    $row('7710102', 'KENDALA RANGKAP TANPA UNIT', $seatA, ['tugas_rangkap_penugasan_sementara' => 'Plt. '.$seatB->jabatan.' TMT 15 Juli 2026']),
];
// NIK wajib 8 digit.
foreach ($errorRows as &$errorRow) {
    $errorRow[0] = str_pad($errorRow[0], 8, '0', STR_PAD_RIGHT);
}
unset($errorRow);
$write('import-with-errors.xlsx', $errorRows);

file_put_contents("{$tmp}/import-fixtures.json", json_encode([
    'validCount' => count($validRows),
    'validNiks' => $validNiks,
    'validFirstName' => 'KARYAWAN UJI 01',
    'errors' => [
        'validCount' => 3,
        'failedCount' => 2,
        'failedNames' => ['KENDALA TANPA JABATAN', 'KENDALA RANGKAP TANPA UNIT'],
        'hintUnit' => $seatB->unit,
        'niks' => array_map(fn (array $r) => $r[0], $errorRows),
    ],
], JSON_PRETTY_PRINT));

echo "Berkas uji impor dibuat: {$tmp}\n";
