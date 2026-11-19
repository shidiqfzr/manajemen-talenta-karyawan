# Sebaran Formasi (Cross-Unit Staffing Overview) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the "Formasi Lowong" tab with one read-only "Sebaran Formasi" tab where HR sees filled and vacant formasi across all units, per Jabatan family (e.g. every "Asisten Afdeling I…XIII" seat in one row) or per unit, without opening each unit.

**Architecture:** `JobVacancyService` stays the single source: it returns every formasi seat (filled or not) with its status, and a per-family grouping built on a pure `JabatanFamily` helper. A Livewire component `FormasiDistribution` only validates filters, sorts, paginates, expands and exports. Master Formasi stays the read-only position-management view; filling a seat always goes through the existing Mutasi flow (audit in `job_histories`).

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit 11, Maatwebsite Excel, Blade + shared `x-admin.table.*` components, Laravel Pint.

**Spec:** Approved design in the conversation of 2026-11-10, summarised here:
1. One tab "Sebaran Formasi" replaces "Formasi Lowong". Tabs: Formasi | Sebaran Formasi | Riwayat Perubahan.
2. Toggle **Per Jabatan** (default; one row per Jabatan family, expandable to its units) | **Per Unit** (one row per unit–Jabatan).
3. Filters: Unit Kerja, Wilayah, RM Band, Bidang, Status Formasi, Lama Kosong. Default shows **all** statuses (filled and vacant).
4. Sortable column headers, unit name links to its formasi page, one "Isi lewat Mutasi" link on vacant rows, no other actions.
5. UI term is **"Kosong"**, never "Lowong/Lowongan" (DOMAIN.md §3 "Formasi Kosong").
6. Grouping uses the Roman-numeral family rule (default chosen by the assistant; the user has not explicitly picked it, so confirm at review).
7. Out of scope: making Mutasi → Monitoring Formasi Kosong read from the same service (follow-up, it touches uncommitted Mutasi work).

## Global Constraints

- Language: code, comments, commits and this plan in English; every user-facing string in Indonesian (user memory).
- AGENTS.md rule 6: no `DOMAIN.md`, `DESIGN.md`, `SSOT` or other doc names in any visible text; doc names only in `//` or `{{-- --}}` comments.
- AGENTS.md rule 7: "Karyawan", never "Pegawai", in new UI text. DOMAIN.md §3: never "kursi"/"seat" in UI text.
- Vacancy = `kuota − definitive holders`. Pj/Plt holders do not fill the quota (DOMAIN.md §5.2). Status comes only from `JobVacancyService::classifySeat()`.
- No calculation in Blade or the Livewire component beyond filtering, sorting and paginating rows the service returns (AGENTS.md "Service Layer").
- Only active units and active positions are counted.
- Search debounce 300ms, search capped at 100 characters, every client-writable filter re-validated against its option list on every render (ENGINEERING.md, existing table pattern).
- Excel export contains no employee name or NIK (UU PDP); the file is built with the existing `App\Exports\FormasiExport` (formula-injection escape + accessor in document properties).
- The page is admin-only: `abort_unless(auth()->user()?->role === 'admin', 403)` in `mount()`, `render()` and every action.
- One test class per SUT (AGENTS.md rule 5). Pint on every touched PHP file.
- Status labels (exact): `over` "Melebihi kuota", `full` "Penuh", `partial` "Sebagian kosong", `acting` "Diisi Pj/Plt", `empty` "Kosong".
- Commit hygiene: `DOMAIN.md` and `DESIGN.md` already hold unrelated uncommitted edits by the user. Before staging them, run `git diff <file>`; if hunks other than this plan's are present, stop and ask the user rather than committing their work.

## Review Focus

1. **Family rule over-strips a real word.** Names ending in tokens like "IT", "QC", "CV", "SDM" or "Tanjung Santan" must stay whole; only true Roman numerals (I–XXXIX, optional A–D suffix, "dan"/"&" pairs) are removed. Pinned in Task 1.
2. **A filter or search hides the family that is currently expanded.** The expanded state must quietly clear, not render an orphan panel or throw. Pinned in Task 3.
3. **Old bookmarks to `/admin/master/lowongan?unit=…&status=…`.** They must redirect to the new tab with the unit filter kept. Pinned in Task 3.
4. **Unit names with `&` in links and URL filters** (e.g. "Bagian Akuntansi & Keuangan"). The "Kosong" badge link and the `?unit=` filter must round-trip. Pinned in Task 3.
5. **Query count grows with seats.** 491 seats must still be a fixed number of queries (no N+1 from families or reservations). Pinned in Task 2.

---

## File Structure

| File | Responsibility |
|---|---|
| Create `app/Services/Formasi/JabatanFamily.php` | Pure: Jabatan name → family name. No DB. |
| Modify `app/Services/JobVacancyService.php` | `getFormasiSeats()` (all statuses + filters), `getJabatanDistribution()`, labels; `getFormasiVacancies()` becomes a thin wrapper. |
| Rename `app/Livewire/Admin/Master/FormasiVacancyTable.php` → `FormasiDistribution.php` | Tab component: view mode, filters, sort, expand, paginate, export. |
| Rename view `resources/views/livewire/admin/master/formasi-vacancy-table.blade.php` → `formasi-distribution.blade.php` | Tab markup. |
| Rename `resources/views/admin/master/lowongan/index.blade.php` → `resources/views/admin/master/sebaran/index.blade.php` | Page shell. |
| Modify `routes/web.php` | `master/sebaran` route; `master/lowongan` permanent redirect. |
| Modify `resources/views/components/admin/master/section-nav.blade.php` | Tab label and route. |
| Modify `app/Livewire/Admin/Master/FormasiEditor.php` + its Blade | "Lowong" → "Kosong" wording, links to the new tab. |
| Tests | `tests/Unit/JabatanFamilyTest.php` (new), `tests/Feature/JobVacancyServiceTest.php`, `tests/Feature/FormasiDistributionTest.php` (renamed from `FormasiVacancyTableTest.php`), `tests/Feature/FormasiEditorTest.php`, `tests/Feature/MasterDataTest.php`. |
| Docs | `DOMAIN.md` §9.3, `DESIGN.md` §8.5 + changelog row. |

---

### Task 0: Branch and baseline

**Files:** none changed.

- [ ] **Step 1: Branch from the current work**

Run: `git switch -c feat/sebaran-formasi`
Expected: `Switched to a new branch 'feat/sebaran-formasi'` (uncommitted work comes along).

- [ ] **Step 2: Baseline test run of the files this plan touches**

Run: `php artisan test tests/Feature/JobVacancyServiceTest.php tests/Feature/FormasiVacancyTableTest.php tests/Feature/FormasiEditorTest.php tests/Feature/MasterDataTest.php tests/Feature/JobVacancyTableTest.php`
Expected: all pass. (The 7 known failures in `FormasiSeederTest`, `FormasiCatalogTest`, `ManPowerPlanningTest` come from the user's uncommitted `master_formasi.json` change, 486→491 seats. They are not part of this plan; do not "fix" them.)

---

### Task 1: `JabatanFamily` helper

**Files:**
- Create: `app/Services/Formasi/JabatanFamily.php`
- Test: `tests/Unit/JabatanFamilyTest.php`

**Interfaces:**
- Produces: `App\Services\Formasi\JabatanFamily::of(string $jabatan): string`, a final class with a static method. It returns the name with trailing Roman-numeral tokens removed and is idempotent. It never returns an empty string and never strips when only one word would remain.

- [ ] **Step 1: Write the failing test**

```php
/** @dataProvider names */
public function test_family_of(string $jabatan, string $family): void
{
    $this->assertSame($family, JabatanFamily::of($jabatan));
}

public static function names(): array
{
    return [
        ['Asisten Afdeling I', 'Asisten Afdeling'],
        ['Asisten Afdeling XIII', 'Asisten Afdeling'],
        ['Asisten Afdeling IIA', 'Asisten Afdeling'],
        ['Asisten Afdeling I dan II', 'Asisten Afdeling'],
        ['Asisten Afdeling VII & VIII', 'Asisten Afdeling'],
        ['Asisten Afdeling', 'Asisten Afdeling'],
        ['Asisten Afdeling Tanjung Santan', 'Asisten Afdeling Tanjung Santan'],
        ['Asisten Teknik IT', 'Asisten Teknik IT'],
        ['Asisten QC', 'Asisten QC'],
        ['Kepala Bagian SDM', 'Kepala Bagian SDM'],
        ['Manajer', 'Manajer'],
        ['  Asisten   Afdeling  V ', 'Asisten Afdeling'],
        ['I', 'I'],
    ];
}

public function test_family_is_idempotent(): void
{
    $once = JabatanFamily::of('Asisten Afdeling VI dan VII');
    $this->assertSame($once, JabatanFamily::of($once));
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Unit/JabatanFamilyTest.php`
Expected: FAIL, `Class "App\Services\Formasi\JabatanFamily" not found`.

- [ ] **Step 3: Implement `JabatanFamily::of()`**

Collapse whitespace, then repeatedly drop a trailing numeral group while more than one word remains. A numeral token is `^(X{0,3})(IX|IV|V?I{0,3})[A-D]?$`, non-empty and case-sensitive. A group is `<numeral>` or `<numeral> (dan|&) <numeral>`. Add a class docblock explaining that the family groups formasi numbered per afdeling/bagian for the Sebaran Formasi view (DOMAIN.md §9.3).

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test tests/Unit/JabatanFamilyTest.php`
Expected: PASS (14 assertions).

- [ ] **Step 5: Pint and commit**

```bash
./vendor/bin/pint app/Services/Formasi/JabatanFamily.php tests/Unit/JabatanFamilyTest.php
git add app/Services/Formasi/JabatanFamily.php tests/Unit/JabatanFamilyTest.php
git commit -m "feat(formasi): derive the Jabatan family from numbered position names"
```

---

### Task 2: Service: all seats, filters and per-family distribution

**Files:**
- Modify: `app/Services/JobVacancyService.php` (the formasi block added on 2026-11-10: `SEAT_STATUS_LABELS` … `formasiRows()`)
- Test: `tests/Feature/JobVacancyServiceTest.php`

**Interfaces:**
- Consumes: `JabatanFamily::of(string): string` (Task 1).
- Produces:
  - `JobVacancyService::SEAT_STATUS_LABELS`: same keys, `partial` relabelled "Sebagian kosong".
  - `JobVacancyService::VACANT_STATUSES = ['partial', 'acting', 'empty']` (unchanged).
  - `getFormasiSeats(?string $search = null, ?string $unitKerja = null, ?string $status = null, ?string $wilayah = null, ?string $rmLevel = null, ?string $bidang = null): Collection` returns every seat on active units and positions. `$status` accepts a `SEAT_STATUS_LABELS` key or `'kosong'` (any of `VACANT_STATUSES`); any other non-empty value matches nothing. Each row has the existing fields (`position_unit_id, unit_id, unit_kerja, jabatan, level, rm_level, bidang, kuota, filled, acting, vacant_slots, status, status_label, vacant_since, vacant_since_formatted, days_vacant, severity, is_reserved, reserved_occupant_name, reserved_tmt`) plus `wilayah` (string) and `family` (string). Default order: `vacant_slots` desc, then `days_vacant` desc (nulls last), then `unit_kerja`, then `jabatan`. Search matches jabatan, family or unit (case-insensitive).
  - `getFormasiVacancies(...)` keeps its current signature and returns `getFormasiSeats(..., status: $status ?: 'kosong')`, so existing callers and tests keep working.
  - `getJabatanDistribution(` same six parameters `): Collection` returns one object per family: `family, rm_levels (list<string>, sorted), jabatan_count, unit_count, kuota, filled, acting, vacant_slots, vacant_units (units with vacant_slots > 0), fill_rate (int %, min(filled,kuota)/kuota), seats (Collection of seat rows, ordered like getFormasiSeats)`. Filters apply to seats **before** grouping, so a family only appears if one of its seats matches. Default order: `vacant_slots` desc, then `family`.
  - `getFormasiStatistics()` and `getFormasiUnitSummary()`: unchanged contracts.

- [ ] **Step 1: Write the failing tests** (add to the existing class; relabel the `partial` expectations)

```php
public function test_seats_include_filled_formasi_and_carry_family_and_wilayah(): void
// seat 'Asisten Afdeling I' kuota 1 + 1 definitive holder; seat 'Asisten Afdeling II' kuota 1, empty
// assert getFormasiSeats() has both rows; row I: status 'full', family 'Asisten Afdeling', wilayah 'Wilayah Kalimantan Barat'

public function test_kosong_status_matches_every_vacant_state_only(): void
// seats: full, partial, acting, empty → getFormasiSeats(status: 'kosong') jabatan == [partial, acting, empty] (any order), not the full one

public function test_rm_bidang_and_wilayah_filters(): void
// two units in different wilayah, seats RM-2/KEU and RM-3/TAN
// assert each filter returns only its seat; getFormasiSeats(rmLevel: 'RM-9') is empty

public function test_distribution_groups_numbered_positions_into_one_family(): void
// 'Asisten Afdeling I' in unit A (kuota 1, filled), 'Asisten Afdeling II' in unit A (kuota 1, empty),
// 'Asisten Afdeling III' in unit B (kuota 2, 1 filled)
$row = $this->service->getJabatanDistribution()->firstWhere('family', 'Asisten Afdeling');
$this->assertSame(3, $row->jabatan_count);
$this->assertSame(2, $row->unit_count);
$this->assertSame(4, $row->kuota);
$this->assertSame(2, $row->filled);
$this->assertSame(2, $row->vacant_slots);
$this->assertSame(2, $row->vacant_units);
$this->assertSame(50, $row->fill_rate);
$this->assertCount(3, $row->seats);

public function test_distribution_filters_seats_before_grouping(): void
// same fixture; getJabatanDistribution(unitKerja: unit B) → family row has kuota 2, unit_count 1, seats count 1

public function test_query_count_is_fixed_regardless_of_seat_count(): void
// create 30 seats over 3 units with holders and one pending JobHistory; DB::enableQueryLog();
// getJabatanDistribution(); assert count(DB::getQueryLog()) <= 5   // Review Focus 5
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/JobVacancyServiceTest.php`
Expected: the new tests FAIL with `Call to undefined method ...getFormasiSeats()` / `getJabatanDistribution()`; the relabel assertions fail on "Ada lowongan".

- [ ] **Step 3: Implement**

Select `units.wilayah` and set `family = JabatanFamily::of($jabatan)` in `formasiRows()`. Add the filtering into `getFormasiSeats()`. Build `getJabatanDistribution()` as `getFormasiSeats(...)->groupBy('family')->map(...)`. Rewrite `getFormasiVacancies()` as the wrapper. Keep the docblocks (Indonesian, as in the file).

- [ ] **Step 4: Run the service and existing vacancy tests**

Run: `php artisan test tests/Feature/JobVacancyServiceTest.php tests/Feature/JobVacancyTableTest.php tests/Feature/MutasiChainProcessTest.php`
Expected: PASS. (`getVacancies()`, the Mutasi history list, is untouched.)

- [ ] **Step 5: Pint and commit**

```bash
./vendor/bin/pint app/Services/JobVacancyService.php tests/Feature/JobVacancyServiceTest.php
git add app/Services/JobVacancyService.php tests/Feature/JobVacancyServiceTest.php
git commit -m "feat(formasi): list every formasi seat and group it per Jabatan family"
```

---

### Task 3: "Sebaran Formasi" tab (component, page, route, nav)

**Files:**
- Rename + modify: `app/Livewire/Admin/Master/FormasiVacancyTable.php` → `app/Livewire/Admin/Master/FormasiDistribution.php`
- Rename + rewrite: `resources/views/livewire/admin/master/formasi-vacancy-table.blade.php` → `resources/views/livewire/admin/master/formasi-distribution.blade.php`
- Rename + modify: `resources/views/admin/master/lowongan/index.blade.php` → `resources/views/admin/master/sebaran/index.blade.php`
- Modify: `routes/web.php` (the `lowongan` route added on 2026-11-10), `resources/views/components/admin/master/section-nav.blade.php`
- Rename + rewrite test: `tests/Feature/FormasiVacancyTableTest.php` → `tests/Feature/FormasiDistributionTest.php`
- Modify: `tests/Feature/MasterDataTest.php` (`test_master_pages_share_the_section_navigation_with_their_own_link_marked_current`)

**Interfaces:**
- Consumes: `getFormasiSeats()`, `getJabatanDistribution()`, `getFormasiStatistics()`, `SEAT_STATUS_LABELS`, `VACANT_STATUSES` (Task 2).
- Produces:
  - Route `admin.master.sebaran.index` → `GET admin/master/sebaran`.
  - `GET admin/master/lowongan` → 301 to `admin.master.sebaran.index`, keeping `unit` and `search`. `status` maps to the same key, except any legacy vacant value maps to `kosong`.
  - Livewire `admin.master.formasi-distribution` with URL-bound public properties:
    `$view` (`#[Url(as: 'tampilan', except: 'jabatan')]`, `'jabatan'|'unit'`), `$search`, `$unitFilter` (`unit`), `$wilayahFilter` (`wilayah`), `$rmFilter` (`rm`), `$bidangFilter` (`bidang`), `$statusFilter` (`status`; options `kosong` "Ada kekosongan" + `SEAT_STATUS_LABELS`), `$severityFilter` (`durasi`; red/yellow/green/unknown as today), `$sortField` (`sort`), `$sortDirection` (`direction`), `$perPage` (`per_page`, options 10/25/50, default 25), `$expanded` (`?string` family, not in the URL).
  - Methods: `setView(string)`, `sortBy(string)` (whitelist per view: jabatan view `family, unit_count, kuota, filled, vacant_slots, fill_rate`; unit view `unit_kerja, jabatan, kuota, filled, vacant_slots, days_vacant`; anything else is ignored), `toggleFamily(string)` (accepted only if the family is in the current filtered result, otherwise sets `null`), `resetFilters()` (clears filters and `expanded`, keeps `view`), `export()` (the shared header's default action).

- [ ] **Step 1: Rename the files with history**

Run: `git mv` for the three files above and the test. Then rename the class, view name and test class inside them.

- [ ] **Step 2: Write the failing tests** in `FormasiDistributionTest` (SUT: `FormasiDistribution`). Keep the existing admin-only, search-cap, reset, empty-state and reservation tests, renamed to the new properties. Add:

```php
public function test_default_view_groups_per_family_and_shows_filled_and_vacant(): void
// fixture: 'Asisten Afdeling I' unit Alfa filled, 'Asisten Afdeling II' unit Beta empty
// assertSee('Asisten Afdeling'), assertSee('2 unit'), assertDontSee('Asisten Afdeling II') until expanded

public function test_expanding_a_family_lists_each_unit_with_status_and_unit_link(): void
// ->call('toggleFamily', 'Asisten Afdeling') → assertSee('Unit Alfa Uji'), assertSee('Penuh'), assertSee('Kosong'),
//   assertSeeHtml(route('admin.master.formasi.show', $alfa)), assertSeeHtml('aria-expanded="true"')

public function test_expanded_family_clears_when_a_filter_hides_it(): void   // Review Focus 2
// toggleFamily('Asisten Afdeling') then set('search', 'Manajer') → assertSet('expanded', null)
// call('toggleFamily', 'Tidak Ada') → assertSet('expanded', null)

public function test_unit_view_lists_rows_and_only_vacant_rows_get_the_mutasi_link(): void
// set('view', 'unit') → filled row has no 'Isi lewat Mutasi'; vacant row href contains route('admin.mutasi.create')

public function test_status_kosong_hides_full_formasi_in_both_views(): void

public function test_sorting_accepts_only_whitelisted_columns(): void
// call('sortBy', 'vacant_slots') → sortField 'vacant_slots'; call('sortBy', 'nik') → unchanged

public function test_unit_filter_with_ampersand_round_trips_through_the_url(): void   // Review Focus 4
// unit 'Bagian Akuntansi & Keuangan'; Livewire::withQueryParams(['unit' => 'Bagian Akuntansi & Keuangan'])
//   ->assertSet('unitFilter', 'Bagian Akuntansi & Keuangan')

public function test_legacy_lowongan_url_redirects_to_sebaran(): void   // Review Focus 3
// get('/admin/master/lowongan?unit=Unit+Alfa+Uji') → assertRedirect(route('admin.master.sebaran.index', ['unit' => 'Unit Alfa Uji'])), status 301

public function test_export_follows_the_view_and_never_contains_names(): void
// jabatan view → headings ['NO','JABATAN','RM BAND','JUMLAH UNIT','KUOTA','TERISI','KOSONG','KETERISIAN (%)']
// unit view → ['NO','UNIT KERJA','WILAYAH','JABATAN','RM BAND','BIDANG','KUOTA','TERISI','KOSONG','STATUS FORMASI','KOSONG SEJAK']
// filename /^Sebaran_Formasi_(Jabatan|Unit)_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/; json of rows has no reserved employee name

public function test_ui_never_says_lowong(): void
// both views, html lower-cased: assertStringNotContainsString('lowong', ...)
```

And in `MasterDataTest`: the three sections become `formasi.index` "Formasi", `sebaran.index` "Sebaran Formasi", `riwayat.index` "Riwayat Perubahan".

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test tests/Feature/FormasiDistributionTest.php tests/Feature/MasterDataTest.php`
Expected: FAIL (route, properties and methods missing).

- [ ] **Step 4: Implement the component, route, redirect and nav**

Follow the current `FormasiVacancyTable` patterns: memoised unit options, `normaliseAll()` on mount/update/render, in-memory `LengthAwarePaginator`. Change the nav entry to `['label' => 'Sebaran Formasi', 'route' => 'admin.master.sebaran.index', 'match' => 'admin.master.sebaran.*', 'icon' => 'fa-table-cells']`, ordered between Formasi and Riwayat Perubahan.

- [ ] **Step 5: Write the view** (DESIGN.md §7.5 table standard, §8.5 Master Formasi pattern)

Page header: title "Sebaran Formasi". Subtitle "Lihat formasi jabatan yang terisi dan kosong di seluruh unit kerja, per jabatan atau per unit." Breadcrumb Core HR › Master Formasi › Sebaran Formasi.
- **KPI row** (4 cards, from `getFormasiStatistics()`):
  - Formasi Jabatan (`total_seats`).
  - Terisi (`filled` + `fill_rate`% badge).
  - Formasi Kosong (`vacant_formasi`, subtitle "`vacant_slots` kuota kosong"). This card is clickable and toggles `statusFilter` to `kosong`, with `aria-pressed`.
  - Diisi Pj/Plt (`acting_count`).
- **Card**:
  - Toolbar with search placeholder "Cari jabatan atau unit kerja...".
  - Segmented control "Per Jabatan | Per Unit" (`role="radiogroup"`, buttons with `aria-checked`) in the header `actions` slot, then Ekspor Excel.
  - The coverage note line from today, reworded without "lowong".
- **Per Jabatan table**, max 6 data columns:
  - Columns: Jabatan (family + RM badges + "`jabatan_count` jabatan"), Unit, Kuota, Terisi, Kosong (`vacant_slots` in `vacant_units` unit), Keterisian (bar + %).
  - A chevron `<button aria-expanded aria-controls>` sits in the Jabatan cell.
  - The expanded panel is a nested table: Unit Kerja (link to `admin.master.formasi.show`), Jabatan, Terisi/Kuota, Status badge, Kosong Sejak, and "Isi lewat Mutasi" when `vacant_slots > 0` and not reserved, or the "Terjadwal (SK)" lock.
- **Per Unit table**: Unit Kerja (link + wilayah sub-line), Jabatan (+ RM/Bidang badges, reserved chip), Terisi/Kuota (+ "n kosong", "n Pj/Plt"), Status, Kosong Sejak, Aksi (same link rule).
- **Shared rules**:
  - Sortable headers via `x-admin.table.th sortable`.
  - Status badge colours as in the current views (empty = danger, partial = warning, acting = earth, full = primary, over = danger outline).
  - Loading bar and opacity on `wire:target`.
  - Empty state: "Tidak ada formasi yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter." or, with no filters, "Belum ada formasi jabatan pada unit aktif."

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/FormasiDistributionTest.php tests/Feature/MasterDataTest.php`
Expected: PASS.

- [ ] **Step 7: Pint and commit**

```bash
./vendor/bin/pint app/Livewire/Admin/Master/FormasiDistribution.php tests/Feature/FormasiDistributionTest.php tests/Feature/MasterDataTest.php routes/web.php
git add -A app/Livewire/Admin/Master resources/views/livewire/admin/master resources/views/admin/master resources/views/components/admin/master/section-nav.blade.php routes/web.php tests/Feature/FormasiDistributionTest.php tests/Feature/FormasiVacancyTableTest.php tests/Feature/MasterDataTest.php
git commit -m "feat(formasi): add the Sebaran Formasi tab with per-Jabatan and per-unit views"
```

(Check `git diff --cached --stat` first: `routes/web.php` must show only the Master Formasi route lines.)

---

### Task 4: Formasi Unit Kerja wording and links

**Files:**
- Modify: `app/Livewire/Admin/Master/FormasiEditor.php`, `resources/views/livewire/admin/master/formasi-editor.blade.php`
- Test: `tests/Feature/FormasiEditorTest.php`

**Interfaces:**
- Consumes: route `admin.master.sebaran.index` and its `unit` / `status` / `tampilan` query keys (Task 3); `SEAT_STATUS_LABELS` (Task 2).
- Produces: no new PHP interfaces. Keeps the property values `seatStatusFilter = 'vacant'` and `statusFilter = 'vacant'` (internal keys, already in users' URLs); only labels change.

- [ ] **Step 1: Update the failing tests**

In `test_acting_holder_leaves_the_seat_vacant_and_the_vacancy_filter_finds_it` and `test_unit_list_shows_vacant_slots_and_can_filter_units_with_vacancies`: expect "2 kosong" instead of "2 lowong", and "Formasi Kosong". Add:

```php
public function test_editor_screens_never_say_lowong(): void
// overview and unit view html, lower-cased, has no 'lowong'

public function test_unit_kosong_badge_links_to_sebaran_filtered_by_unit(): void
// assertSeeHtml(e(route('admin.master.sebaran.index', ['tampilan' => 'unit', 'unit' => 'Kebun Editor Uji', 'status' => 'kosong'])))
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/FormasiEditorTest.php`
Expected: FAIL on "lowong" text.

- [ ] **Step 3: Change wording and links**
- Unit KPI "Lowong" → "Kosong" (subtitle "Klik untuk melihat formasi yang belum terisi").
- Overview KPI "Formasi Lowong" → "Formasi Kosong" (subtitle "`n` kuota kosong, klik untuk rincian"), linking to `admin.master.sebaran.index` with `status=kosong`.
- Overview column "Lowong" → "Kosong", with the badge "`n` kosong" linking as in the test.
- Filter labels: `vacant` "Semua kosong" (seat filter) and "Ada formasi kosong" (unit filter).

- [ ] **Step 4: Run to verify they pass**

Run: `php artisan test tests/Feature/FormasiEditorTest.php`
Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
./vendor/bin/pint app/Livewire/Admin/Master/FormasiEditor.php tests/Feature/FormasiEditorTest.php
git add app/Livewire/Admin/Master/FormasiEditor.php resources/views/livewire/admin/master/formasi-editor.blade.php tests/Feature/FormasiEditorTest.php
git commit -m "fix(formasi): use the official Formasi Kosong wording and link to Sebaran Formasi"
```

---

### Task 5: SSOT documents and full verification

**Files:**
- Modify: `DOMAIN.md` §9.3 (the "Pelacak Formasi Lowong" bullet block), `DESIGN.md` §8.5 (new numbered item) and the changelog table (new row `v3.26`, date 10/11/2026).

- [ ] **Step 1: DOMAIN.md §9.3**

Rename the block to **Sebaran Formasi** (route `/admin/master/sebaran`, component `FormasiDistribution`).
- Document `getFormasiSeats()`, `getJabatanDistribution()`, the `'kosong'` status alias, `JabatanFamily::of()` with its rule and examples ("Asisten Afdeling I dan II" → "Asisten Afdeling"; "Asisten Teknik IT" unchanged), and the "Sebagian kosong" label.
- Keep the Pj/Plt, coverage, read-only, UU PDP export and "`getVacancies` unchanged" sentences.
- State the follow-up: Mutasi → Monitoring Formasi Kosong will move to the same source.

- [ ] **Step 2: DESIGN.md §8.5 item + changelog**

Describe the tab:
- the two views, with Per Jabatan as the default;
- the expandable family row (`aria-expanded`/`aria-controls`);
- the segmented control (`role="radiogroup"`);
- the KPI card that filters to `kosong`;
- the max-6-column rule held;
- the single "Isi lewat Mutasi" link;
- the wording rule "Kosong", never "Lowong".

Add the changelog row.

- [ ] **Step 3: Full verification**

Run: `./vendor/bin/pint --test` on every PHP file touched by Tasks 1–4, then `php artisan test`.
Expected: Pint `passed`; the suite passes except the 7 pre-existing seeder/catalog/MPP failures listed in Task 0. Any other failure blocks the commit.

- [ ] **Step 4: Manual check in the browser** (admin user, 1366px and 390px wide)
- `/admin/master/sebaran`: search "Asisten Afdeling" shows one family row; expanding it lists the units.
- "Per Unit" works, sorting works, the Formasi Kosong KPI filters, and Ekspor works in both views.
- `/admin/master/lowongan?unit=…` redirects with the unit kept.
- No horizontal page scroll at 390px (the table scrolls inside its card).

- [ ] **Step 5: Commit the docs** (follow the Global Constraints rule on mixed hunks)

```bash
git diff DOMAIN.md DESIGN.md     # only this plan's hunks? otherwise ask the user
git add DOMAIN.md DESIGN.md
git commit -m "docs(formasi): document Sebaran Formasi and the Jabatan family rule"
```

---

## Follow-up (separate plan, not in scope)

Point Mutasi → Monitoring Formasi Kosong (`JobVacancyTable`) at `getFormasiSeats(status: 'kosong')`, keeping its chain-vacancy and last-occupant details, so every vacancy count in the app matches. Needs its own review because it touches the user's uncommitted Mutasi work and `MutasiChainProcessTest`.
