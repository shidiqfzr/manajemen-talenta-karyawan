<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Penjaga aturan DESIGN.md pada seluruh Blade view (SUT: berkas di resources/views).
 *
 * Seluruh aturan bersifat ketat: kode saat ini sudah bersih, setiap pelanggaran baru langsung
 * gagal dengan daftar `berkas:baris`. Aturan baru yang belum bisa dipenuhi seluruh kode sebaiknya
 * dibereskan lebih dulu (utang dicatat di DESIGN.md §17), bukan dilonggarkan dengan baseline.
 */
class ViewDesignGuardTest extends TestCase
{
    /** Isi sebuah tag pembuka: tahan terhadap `>` di dalam kutip, `->` dan `=>` Blade/PHP. */
    private const TAG_BODY = '(?:"[^"]*"|\'[^\']*\'|->|=>|[^>"\'])*';

    /** Palet bawaan Tailwind yang sudah punya token pengganti (DESIGN.md §3.1). */
    private const RAW_PALETTE = '/(?<![\w-])(?:bg|text|border|ring|divide|from|via|to|placeholder|fill|stroke|outline|decoration|accent|caret|shadow)-(?:slate|gray|zinc|emerald|green|red|rose|amber|yellow|blue)-\d{2,3}(?![\w-])/';

    /** @var array<string, string>|null path relatif => isi view tanpa komentar */
    private static ?array $views = null;

    public function test_every_icon_is_hidden_from_screen_readers(): void
    {
        $this->assertNoViolations(
            $this->tags('i', fn (string $tag) => preg_match('/\bfa[srb]?\b|\bfa-/', $tag) && ! str_contains($tag, 'aria-hidden')),
            'Ikon Font Awesome wajib aria-hidden="true" (DESIGN.md §3.9). Tombol ikon tanpa teks wajib aria-label.'
        );
    }

    public function test_every_inline_svg_is_decorative_or_named(): void
    {
        $this->assertNoViolations(
            $this->tags('svg', fn (string $tag) => ! str_contains($tag, 'aria-hidden') && ! str_contains($tag, 'role=')),
            'SVG inline wajib aria-hidden="true" (dekoratif) atau role + nama aksesibel (DESIGN.md §10).'
        );
    }

    public function test_every_dialog_is_modal_labelled_and_traps_focus(): void
    {
        $this->assertNoViolations(
            $this->scan(
                '/<(?:div|section|aside)(?=[\s>])'.self::TAG_BODY.'>/',
                fn (string $tag) => str_contains($tag, 'role="dialog"')
                    && (! str_contains($tag, 'aria-modal="true"') || ! str_contains($tag, 'aria-labelledby') || ! str_contains($tag, 'x-trap'))
            ),
            'Dialog wajib aria-modal="true", aria-labelledby, dan x-trap.noscroll agar fokus terkunci dan kembali ke pemicu (DESIGN.md §7.6).'
        );
    }

    public function test_modals_are_built_on_the_shared_modal_shell(): void
    {
        // Modal tengah hanya lewat x-admin.modal (DESIGN.md §7.6). Drawer samping punya pola tersendiri dan dikecualikan.
        $shell = 'components/admin/modal.blade.php';
        $drawers = [
            'components/admin/personnel-drawer.blade.php',
            'components/admin/mutasi/talent-drawer.blade.php',
            'livewire/admin/mpp-supply-plan-editor.blade.php',
            'admin/employees/partials/import-column-guide-modal.blade.php',
        ];

        $this->assertNoViolations(
            array_values(array_filter(
                $this->tags('div', fn (string $tag) => str_contains($tag, 'role="dialog"')),
                fn (string $location) => ! in_array(explode(':', $location)[0], [$shell, ...$drawers], true)
            )),
            'Modal wajib memakai <x-admin.modal> agar overlay, kepala, footer, fokus, dan transisi seragam (DESIGN.md §7.6); jangan menulis role="dialog" sendiri.'
        );
    }

    public function test_tab_widgets_follow_the_aria_tabs_pattern(): void
    {
        $div = '/<div(?=[\s>])'.self::TAG_BODY.'>/';

        $this->assertNoViolations(
            array_merge(
                $this->tags('button', fn (string $tag) => str_contains($tag, 'role="tab"')
                    && (! str_contains($tag, 'aria-controls') || ! str_contains($tag, ':aria-selected') || ! str_contains($tag, ':tabindex'))),
                $this->scan($div, fn (string $tag) => str_contains($tag, 'role="tablist"') && ! str_contains($tag, 'aria-label')),
                $this->scan($div, fn (string $tag) => str_contains($tag, 'role="tabpanel"') && ! str_contains($tag, 'aria-labelledby')),
                $this->tags('nav', fn (string $tag) => str_contains($tag, 'aria-label="Tabs"')),
            ),
            'Tab wajib role="tab" + aria-controls + :aria-selected + :tabindex (roving), tablist berlabel, tabpanel aria-labelledby; jangan memakai <nav> untuk tab (DESIGN.md §7.4).'
        );
    }

    public function test_every_table_header_declares_scope(): void
    {
        $this->assertNoViolations(
            $this->tags('th', fn (string $tag) => ! str_contains($tag, 'scope=')),
            '<th> wajib punya scope="col" atau scope="row" (DESIGN.md §10).'
        );
    }

    public function test_column_headers_use_the_shared_header_cell(): void
    {
        // Pengecualian: sel pembuat komponen, kolom centang, matriks MPP (header berkelompok), dan <th scope="row">.
        $allowed = ['components/admin/table/', 'man-power-planning.blade.php', 'mpp-supply-plan-editor.blade.php'];
        $violations = array_values(array_filter(
            $this->tags('th', fn (string $tag) => ! str_contains($tag, 'scope="row"')),
            fn (string $location) => collect($allowed)->every(fn (string $path) => ! str_contains(str_replace('\\', '/', $location), $path)),
        ));

        $this->assertNoViolations(
            $violations,
            'Header kolom tabel wajib memakai <x-admin.table.th> agar tipografi seragam di semua modul (DESIGN.md §7.5); jangan menulis <th> sendiri.'
        );
    }

    public function test_table_header_typography_is_never_set_outside_the_shared_cell(): void
    {
        $this->assertNoViolations(
            $this->scan('/<(?:thead|tr)(?=[\s>])(?:"[^"]*"|\'[^\']*\'|->|=>|[^>"\'])*\buppercase\b(?:"[^"]*"|\'[^\']*\'|->|=>|[^>"\'])*>/', fn () => true),
            'Huruf kapital dan ukuran teks header diatur di x-admin.table.th, bukan pada <thead>/<tr> (DESIGN.md §7.5).'
        );
    }

    public function test_every_nav_landmark_is_labelled(): void
    {
        $this->assertNoViolations(
            $this->tags('nav', fn (string $tag) => ! str_contains($tag, 'aria-label')),
            '<nav> wajib punya aria-label agar landmark dapat dibedakan (DESIGN.md §10).'
        );
    }

    public function test_page_shells_declare_indonesian_language(): void
    {
        $this->assertNoViolations(
            $this->tags('html', fn (string $tag) => ! str_contains($tag, 'lang="id"')),
            '<html> wajib lang="id" karena antarmuka berbahasa Indonesia (DESIGN.md §12).'
        );
    }

    public function test_category_hues_are_reserved_for_rm_band_chips(): void
    {
        $pattern = '/(?<![\w-])(?:bg|text|border|ring|divide|from|via|to|fill|stroke|shadow|decoration|outline|accent|placeholder)-(?:orange|sky|purple|indigo|violet|fuchsia|pink|cyan|lime)-\d{2,3}(?![\w-])/';
        $root = dirname(__DIR__, 2);

        $sources = [];
        foreach (self::views() as $path => $source) {
            $sources['resources/views/'.$path] = $source;
        }
        foreach (self::files($root.'/app', 'php') as $file) {
            $sources[str_replace('\\', '/', substr($file, strlen($root) + 1))] = file_get_contents($file);
        }

        $violations = [];
        foreach ($sources as $path => $source) {
            // Matriks MPP seluruhnya per RM Band (kolom grup RM-1/RM-2/RM-3).
            if (str_ends_with($path, 'man-power-planning.blade.php')) {
                continue;
            }
            foreach (explode("\n", $source) as $index => $line) {
                if (preg_match($pattern, $line) && ! preg_match('/RM-[1-4]/', $line)) {
                    $violations[] = $path.':'.($index + 1);
                }
            }
        }

        $this->assertNoViolations(
            $violations,
            'Hue orange/sky/purple hanya untuk chip RM Band (RM-1/RM-2/RM-3); hue lain (indigo, pink, dst.) tidak dipakai. Pakai earth/info/surface sesuai arti data (DESIGN.md §5.2).'
        );
    }

    public function test_no_arbitrary_color_values_are_used(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/(?<![\w-])(?:bg|text|border|ring|from|via|to|fill|stroke)-\[#[0-9a-fA-F]{3,8}\]/'),
            'Dilarang hex manual pada kelas Tailwind. Pakai token dari @theme (DESIGN.md §3.1).'
        );
    }

    public function test_no_dark_mode_classes_are_used(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/(?<![\w-])dark:[a-z]/'),
            'Mode gelap di luar cakupan (DESIGN.md §3.10).'
        );
    }

    public function test_internal_document_names_never_reach_visible_view_text(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/\b(?:AGENTS|DOMAIN|DESIGN|ENGINEERING|SECURITY)\.md\b|\bSSOT\b/'),
            'Nama berkas dokumentasi internal hanya boleh ada di komentar kode, bukan teks pengguna (AGENTS.md aturan 6).'
        );
    }

    public function test_internal_document_names_never_reach_user_facing_php_strings(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2);
        foreach ($this->files($root.'/app', 'php') as $file) {
            $line = 1;
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (! is_array($token)) {
                    continue;
                }
                $line = $token[2];
                if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                    && preg_match('/\b(?:AGENTS|DOMAIN|DESIGN|ENGINEERING|SECURITY)\.md\b|\bSSOT\b/', $token[1])) {
                    $violations[] = str_replace('\\', '/', substr($file, strlen($root) + 1)).':'.$line;
                }
            }
        }

        $this->assertNoViolations($violations, 'Pesan validasi/notifikasi tidak boleh menyebut nama dokumen internal (AGENTS.md aturan 6).');
    }

    public function test_unescaped_blade_output_is_limited_to_the_highlight_component(): void
    {
        $violations = array_filter(
            $this->occurrences('/\{!!/'),
            fn (string $location) => ! str_starts_with($location, 'components/highlight.blade.php')
        );

        $this->assertNoViolations(
            array_values($violations),
            'Keluaran Blade wajib di-escape ({{ }}); {!! !!} hanya untuk komponen highlight yang sudah menyaring teksnya (SECURITY.md, mitigasi XSS).'
        );
    }

    public function test_retirement_rules_are_never_hardcoded_in_views(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/(?:parseInt\([^)]*\)|\byear|\bmonth)\s*[-+]\s*(?:56|6)\b/'),
            'Batas Usia Pensiun & MBT wajib bersumber dari KepegawaianPolicy, bukan angka literal di view (DOMAIN.md Bab 5.1).'
        );
    }

    public function test_no_raw_tailwind_palette_classes_are_used_in_views(): void
    {
        $this->assertNoViolations(
            $this->occurrences(self::RAW_PALETTE),
            'Gunakan token: slate/gray/zinc→surface, emerald/green→primary, red/rose→danger, amber/yellow→warning, blue→info (DESIGN.md §3.1).'
        );
    }

    public function test_no_raw_tailwind_palette_classes_are_used_in_php_class_strings(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2);
        foreach (self::files($root.'/app', 'php') as $file) {
            $source = file_get_contents($file);
            if (! preg_match_all(self::RAW_PALETTE, $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as [, $offset]) {
                $violations[] = str_replace('\\', '/', substr($file, strlen($root) + 1)).':'.(substr_count($source, "\n", 0, $offset) + 1);
            }
        }

        $this->assertNoViolations($violations, 'Kelas Tailwind pada string PHP (mis. accessor badge model) juga wajib memakai token (DESIGN.md §3.1).');
    }

    public function test_removing_the_focus_outline_always_leaves_a_visible_replacement(): void
    {
        $this->assertNoViolations(
            $this->scan('/<[a-zA-Z][\w-]*(?=[\s>])'.self::TAG_BODY.'>/', function (string $tag) {
                if (! str_contains($tag, 'focus:outline-none')) {
                    return false;
                }
                if (preg_match('/focus(?:-visible|-within)?:(?:ring|border|shadow|bg-|underline)|focus-visible:outline/', $tag)) {
                    return false;
                }

                // Panel dropdown/overlay (div tanpa tabindex) memang tidak dapat menerima fokus keyboard.
                return ! (str_starts_with($tag, '<div') && ! str_contains($tag, 'tabindex'));
            }),
            'Elemen interaktif yang menghapus outline wajib punya pengganti, mis. focus-visible:ring-2 focus-visible:ring-primary-500 (DESIGN.md §6.3).'
        );
    }

    public function test_no_text_is_smaller_than_ten_pixels(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/(?<![\w-])text-\[[1-9](?:\.\d+)?px\]/'),
            'Ukuran teks minimum text-[10px] untuk chip dan text-xs untuk kalimat (DESIGN.md §3.5).'
        );
    }

    public function test_pulse_animation_respects_reduced_motion(): void
    {
        $this->assertNoViolations(
            $this->occurrences('/(?<![\w:-])animate-pulse(?![\w-])/'),
            'Gunakan motion-safe:animate-pulse agar hormat pada prefers-reduced-motion (DESIGN.md §3.7).'
        );
    }

    public function test_shared_header_and_form_components_carry_no_decorative_gradient(): void
    {
        // DESIGN.md §1 dan §5.2: gradien dekoratif dilarang. Kepala halaman dan komponen formulir dipakai
        // di setiap halaman admin, sehingga pelanggaran di sini menyebar ke seluruh aplikasi.
        $violations = [];

        foreach (self::views() as $path => $source) {
            $shared = $path === 'components/admin/page-header.blade.php' || str_starts_with($path, 'components/admin/form/');

            if ($shared && str_contains($source, 'gradient')) {
                $violations[] = $path;
            }
        }

        $this->assertSame([], $violations, 'Komponen bersama tidak boleh memakai gradien dekoratif (DESIGN.md §1, §5.2).');
    }

    public function test_module_index_pages_use_the_shared_header_and_flash(): void
    {
        // Halaman indeks modul Pelatihan dan Penilaian disejajarkan dengan modul Karyawan (DESIGN.md §6.1, §8.5).
        $violations = [];
        foreach (['admin/trainings/index.blade.php', 'admin/evaluations/index.blade.php'] as $path) {
            $source = self::views()[$path] ?? '';

            foreach ([
                '<x-admin.module-header' => 'wajib memakai x-admin.module-header',
                '<x-admin.flash' => 'wajib memakai x-admin.flash',
            ] as $needle => $message) {
                if (! str_contains($source, $needle)) {
                    $violations[] = "{$path}: {$message}";
                }
            }

            foreach (['<h1' => 'dilarang menulis <h1> sendiri', 'shadow-lg' => 'dilarang membungkus halaman dalam kartu putih', 'bg-info ' => 'tombol utama wajib x-admin.button variant primary'] as $needle => $message) {
                if (str_contains($source, $needle)) {
                    $violations[] = "{$path}: {$message}";
                }
            }
        }

        $this->assertNoViolations(
            $violations,
            'Indeks Pelatihan dan Penilaian wajib memakai x-admin.module-header, x-admin.flash, dan tombol primer standar (DESIGN.md §6.1, §8.5).'
        );
    }

    public function test_multi_step_flows_use_the_shared_stepper_and_never_hand_roll_one(): void
    {
        // DESIGN.md §6.1: satu penanda tahap untuk semua alur bertahap (Impor Karyawan, Catat SK Struktur, dan berikutnya).
        $root = dirname(__DIR__, 2).'/resources/views';
        $handRolled = [];

        foreach (self::files($root, 'php') as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            if (! str_ends_with($file, '.blade.php') || $relative === 'components/admin/stepper.blade.php') {
                continue;
            }

            // Ciri kartu tahap tulisan tangan: kicker huruf kapital berisi "Tahap N".
            if (preg_match('/tracking-wider[^>]*>\s*Tahap\s+\d/', (string) file_get_contents($file)) === 1) {
                $handRolled[] = $relative;
            }
        }

        $this->assertSame([], $handRolled, "Penanda tahap tulisan tangan; pakai <x-admin.stepper>:\n".implode("\n", $handRolled));

        foreach (['admin/employees/import.blade.php', 'livewire/admin/master/structure-decree-wizard.blade.php'] as $wizard) {
            $this->assertStringContainsString('<x-admin.stepper', (string) file_get_contents($root.'/'.$wizard), "{$wizard} harus memakai x-admin.stepper");
        }
    }

    public function test_blade_comments_are_never_nested_so_documentation_never_leaks_onto_the_page(): void
    {
        // Komentar {{-- --}} berakhir pada `--}}` pertama: komentar bersarang memotong komentar luar dan sisa teksnya tampil di halaman.
        $root = dirname(__DIR__, 2).'/resources/views';
        $violations = [];

        foreach (self::files($root, 'php') as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file);
            preg_match_all('/\{\{--|--\}\}/', $source, $tokens, PREG_OFFSET_CAPTURE);

            $open = false;
            foreach ($tokens[0] as [$token, $offset]) {
                $line = substr_count($source, "\n", 0, $offset) + 1;
                $location = str_replace('\\', '/', substr($file, strlen($root) + 1)).':'.$line;

                if ($token === '{{--' && $open) {
                    $violations[] = $location.' (komentar bersarang)';
                } elseif ($token === '--}}' && ! $open) {
                    $violations[] = $location.' (penutup tanpa pembuka)';
                }

                $open = $token === '{{--';
            }

            if ($open) {
                $violations[] = str_replace('\\', '/', substr($file, strlen($root) + 1)).' (komentar tidak ditutup)';
            }
        }

        $this->assertNoViolations(
            $violations,
            'Komentar Blade tidak boleh bersarang atau menggantung; jangan menulis {{-- di dalam komentar (DESIGN.md §6, docblock komponen).'
        );
    }

    public function test_file_uploads_use_the_shared_upload_component(): void
    {
        // Utang desain tercatat (DESIGN.md §17 D20): form lama yang belum dimigrasi ke x-admin.file-upload. Tidak boleh bertambah.
        $debt = [
            'components/admin/file-upload.blade.php',
            'components/admin/employee-form.blade.php',
            'admin/employees/partials/import-step1.blade.php',
            'admin/trainings/add-participant.blade.php',
            'admin/trainings/edit-participant.blade.php',
        ];

        $violations = array_values(array_filter(
            $this->tags('input', fn (string $tag) => preg_match('/type=["\']file["\']/', $tag) === 1),
            fn (string $location) => ! in_array(explode(':', $location)[0], $debt, true),
        ));

        $this->assertNoViolations(
            $violations,
            'Unggah berkas wajib memakai <x-admin.file-upload> (progres dan nama berkas terlihat, accept spesifik); jangan menulis <input type="file"> sendiri (DESIGN.md §6.1).'
        );
    }

    public function test_table_cards_always_carry_the_shared_table_header(): void
    {
        // Pengecualian: cangkang komponen itu sendiri dan tabel sematan profil pengguna non-admin (utang D21).
        $allowed = ['components/admin/table/card.blade.php', 'user/trainings/show.blade.php'];

        $violations = [];
        foreach (self::views() as $path => $source) {
            if (in_array($path, $allowed, true)) {
                continue;
            }
            if (str_contains($source, '<x-admin.table.card') && ! str_contains($source, '<x-admin.table.header')) {
                $violations[] = $path;
            }
        }

        $this->assertNoViolations(
            $violations,
            'Kartu tabel (x-admin.table.card) wajib memuat bilah judul bersama x-admin.table.header; dilarang membuat header tabel sendiri (DESIGN.md §7.5).'
        );
    }

    /**
     * @return array<string, string> path relatif => isi view dengan komentar Blade/HTML diganti baris kosong
     */
    private static function views(): array
    {
        if (self::$views !== null) {
            return self::$views;
        }

        $root = dirname(__DIR__, 2).'/resources/views';
        $views = [];
        foreach (self::files($root, 'php') as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file);
            // Komentar dibuang tetapi jumlah baris dipertahankan agar nomor baris laporan tetap akurat.
            $source = preg_replace_callback(
                '/\{\{--.*?--\}\}|<!--.*?-->/s',
                fn (array $m) => str_repeat("\n", substr_count($m[0], "\n")),
                $source
            );
            $views[str_replace('\\', '/', substr($file, strlen($root) + 1))] = $source;
        }
        ksort($views);

        return self::$views = $views;
    }

    /** @return list<string> */
    private static function files(string $dir, string $extension): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Tag pembuka <$name ...> yang melanggar aturan, sebagai "path:baris".
     *
     * @param  callable(string): mixed  $violates
     * @return list<string>
     */
    private function tags(string $name, callable $violates): array
    {
        return $this->scan('/<'.$name.'(?=[\s>])'.self::TAG_BODY.'>/', $violates);
    }

    /** @return list<string> */
    private function occurrences(string $pattern): array
    {
        return $this->scan($pattern, fn () => true);
    }

    /**
     * @param  callable(string): mixed  $violates
     * @return list<string>
     */
    private function scan(string $pattern, callable $violates): array
    {
        $found = [];
        foreach (self::views() as $path => $source) {
            if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as [$text, $offset]) {
                if ($violates($text)) {
                    $found[] = $path.':'.(substr_count($source, "\n", 0, $offset) + 1);
                }
            }
        }

        return $found;
    }

    /** @param list<string> $violations */
    private function assertNoViolations(array $violations, string $rule): void
    {
        $this->assertSame(
            [],
            $violations,
            $rule."\nPelanggaran (".count($violations)."):\n  ".implode("\n  ", array_slice($violations, 0, 25))
        );
    }
}
