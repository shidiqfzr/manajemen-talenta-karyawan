<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Penjaga DRY (ENGINEERING.md §2.4): satu implementasi untuk setiap balok bangun bersama, tanpa salinan baru di komponen lain.
 */
class SharedBuildingBlocksGuardTest extends TestCase
{
    /** @return list<string> path relatif terhadap akar proyek */
    private static function files(string $dir, string $extension): array
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        sort($found);

        return $found;
    }

    public function test_nik_masking_has_exactly_one_implementation(): void
    {
        $copies = [];

        foreach (self::files('app', '.php') as $file) {
            if ($file === 'app/Support/NikMasker.php') {
                continue;
            }

            $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);
            // Ciri salinan: membangun bintang dari panjang NIK (mis. str_repeat('*', strlen($nik) - 3)).
            if (preg_match('/str_repeat\(\s*\'\*\'\s*,\s*(max\()?\s*strlen\(/', $source) === 1) {
                $copies[] = $file;
            }
        }

        $this->assertSame([], $copies, "Penyamaran NIK harus lewat App\\Support\\NikMasker:\n".implode("\n", $copies));
    }

    public function test_view_toggles_use_the_shared_segmented_component(): void
    {
        // employee-form memakai radiogroup untuk isian formulir (bukan pengalih tampilan), jadi dikecualikan.
        $allowed = ['resources/views/components/admin/segmented.blade.php', 'resources/views/components/admin/employee-form.blade.php'];
        $copies = [];

        foreach (self::files('resources/views', '.blade.php') as $file) {
            if (in_array($file, $allowed, true)) {
                continue;
            }

            if (str_contains((string) file_get_contents(dirname(__DIR__, 2).'/'.$file), 'role="radiogroup"')) {
                $copies[] = $file;
            }
        }

        $this->assertSame([], $copies, "Pengalih tampilan harus memakai <x-admin.segmented>:\n".implode("\n", $copies));
    }

    public function test_in_memory_tables_share_the_collection_table_concern(): void
    {
        $copies = [];

        foreach (self::files('app/Livewire', '.php') as $file) {
            if (str_ends_with($file, 'Concerns/WithCollectionTable.php') || str_ends_with($file, 'Concerns/WithDataTable.php')) {
                continue;
            }

            $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);
            // Salinan: opsi/bawaan baris per halaman atau pengirisan koleksi ditulis ulang di komponen.
            if (preg_match('/function (perPageOptions|defaultPerPage)\(/', $source) === 1 || str_contains($source, 'new LengthAwarePaginator(')) {
                $copies[] = $file;
            }
        }

        $this->assertSame([], $copies, "Tabel data dalam memori harus memakai WithCollectionTable:\n".implode("\n", $copies));
    }
}
