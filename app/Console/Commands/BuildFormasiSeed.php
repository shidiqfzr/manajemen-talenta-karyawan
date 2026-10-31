<?php

namespace App\Console\Commands;

use App\Services\Formasi\FormasiExcelParser;
use Illuminate\Console\Command;

class BuildFormasiSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formasi:build-seed
                            {file : Path berkas Excel formasi (Unit dan Jabatan Karyawan Pimpinan)}
                            {--out=database/data/master_formasi.json : Berkas JSON hasil}
                            {--metadata=database/data/unit_metadata.json : Kode, wilayah dan urutan per unit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membangun berkas seed master formasi dari Excel formasi Karyawan Pimpinan';

    /**
     * Execute the console command.
     */
    public function handle(FormasiExcelParser $parser): int
    {
        $result = $parser->parse((string) $this->argument('file'));

        if ($result['errors'] !== []) {
            $this->error('Berkas formasi memiliki kesalahan:');
            foreach ($result['errors'] as $error) {
                $this->line("Baris {$error['row']}: {$error['message']}");
            }

            return self::FAILURE;
        }

        $metadataPath = $this->resolvePath((string) $this->option('metadata'));
        $metadata = json_decode((string) @file_get_contents($metadataPath), true);
        if (! is_array($metadata)) {
            $this->error("Metadata unit tidak ditemukan atau tidak valid: {$metadataPath}");

            return self::FAILURE;
        }

        $units = [];
        foreach ($result['seats'] as $seat) {
            $units[$seat['unit']][] = [
                'nama' => $seat['jabatan'],
                'bidang' => $seat['bidang'],
                'level' => 'Karpim',
                'rm_level' => $seat['rm_level'],
                'kuota' => $seat['kuota'],
            ];
        }

        $missing = array_values(array_diff(array_keys($units), array_keys($metadata)));
        if ($missing !== []) {
            $this->error('Unit berikut belum memiliki kode/wilayah/urutan pada metadata unit:');
            foreach ($missing as $name) {
                $this->line("- {$name}");
            }

            return self::FAILURE;
        }

        $seed = [];
        foreach ($units as $name => $positions) {
            $seed[] = [
                'nama' => $name,
                'kode' => $metadata[$name]['kode'],
                'wilayah' => $metadata[$name]['wilayah'],
                'is_active' => true,
                'urutan' => $metadata[$name]['urutan'],
                'positions' => $positions,
            ];
        }

        $out = $this->resolvePath((string) $this->option('out'));
        file_put_contents($out, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info(sprintf(
            'Seed formasi dibuat: %d unit, %d kombinasi jabatan-unit, %d kursi.',
            count($seed),
            count($result['seats']),
            array_sum(array_column($result['seats'], 'kuota'))
        ));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : base_path($path);
    }
}
