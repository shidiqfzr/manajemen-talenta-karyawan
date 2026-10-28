# Mutasi Form Blocking-State UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the single-mutation form (`/admin/mutasi/create`) stop the admin early when the placement cannot be processed, show one clear status instead of three overlapping ones, and remove visual noise and misleading signals.

**Architecture:** One computed property `MutasiProcess::blockingReason()` becomes the single source for "this mutation cannot proceed as a single SK". The Blade partials read it to disable the SK section and the primary button and to choose the status label. Server-side validation in `MutasiForm::validateForm()` stays unchanged and remains the real guard. A shared month formatter fixes the inconsistent duration texts.

**Tech Stack:** Laravel 13, Livewire 4, Blade + Tailwind, PHPUnit 11.

**Spec:** Design critique of the "Buat Mutasi & Penempatan Baru" screen (conversation, 2026-10-26), priority recommendations 1–3 plus the consistency findings. Key decisions copied below.

## Global Constraints

- UI copy is Bahasa Indonesia using official business terms. Never show `AGENTS.md`/`DOMAIN.md`/`DESIGN.md`/`ENGINEERING.md`/`SECURITY.md` or "SSOT" in user-visible text (AGENTS.md rule 6).
- No rule calculations in Blade or controllers: blocking logic lives in the component and the duration formatting lives in the service layer.
- One test class per SUT: every `MutasiProcess` test goes in `tests/Feature/MutasiProcessTest.php`. New tests use the existing `test_snake_case()` naming and `setUp()` units (`Bagian SDM`, `Kebun Sawit A`).
- Duration format everywhere on this page: `"{years} th {months} bln"` (e.g. 19 months → `1 th 7 bln`).
- Server-side guards in `MutasiForm::validateForm()` must not be removed or loosened. The UI disabling is a convenience, not the guard.
- Run `./vendor/bin/pint` before each commit.
- The working tree has large uncommitted mutasi work. Start on a new branch (`feat/mutasi-blocking-ux`) after that work is committed. Don't stash or discard it.

## Review Focus

1. **Target changes from blocked to valid** (the admin picks another formasi): the button and SK section must re-enable. Test is in Task 1.
2. **Valid equal-RM swap (occupied, `isSwapAllowed = true`)** must NOT be treated as blocked. Test is in Task 1.
3. **Reserved formasi and same-position** are blocking states too, not only the RM mismatch. Tests are in Task 1.
4. **Bypassing the disabled button** (calling `submitMutasi` directly) must still be rejected server-side. This is already covered by `test_swap_forbids_different_rm_levels` and `test_tampered_analysis_state_cannot_bypass_swap_confirmation`, so they must keep passing.
5. **Same-unit promotion** (tenure check not applicable) must not show "Masa Dinas Unit Memenuhi Syarat". Test is in Task 3.

---

### Task 1: Blocking state disables SK section and primary action

**Files:**
- Modify: `app/Livewire/Admin/MutasiProcess.php` (add computed property after `closeTalentDrawer()`)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/action-bar.blade.php`
- Modify: `resources/views/livewire/admin/partials/mutasi-process/sk-legal.blade.php`
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Produces: `#[Computed] public function blockingReason(): ?string` on `MutasiProcess`. It returns `null` when the mutation can proceed, otherwise a one-sentence Indonesian reason. Views access it as `$this->blockingReason`. Tasks 2 and 4 rely on it.

Blocking rules, in this order:
| Condition | Reason text |
|---|---|
| `employeeDetails['is_retired']` | `Karyawan telah melewati batas usia pensiun dan tidak dapat dimutasi.` |
| `targetAnalysis['type'] === 'same'` | `Karyawan sudah menjabat pada formasi dan unit kerja yang sama.` |
| `targetAnalysis['type'] === 'reserved'` or `targetAnalysis['is_reserved']` | `Formasi telah dipesan oleh mutasi terjadwal. Pilih formasi lain.` |
| `targetAnalysis['type'] === 'occupied' && ! $isSwapAllowed` | `Formasi masih terisi pejabat definitif. Selesaikan melalui Mutasi Berantai.` |

- [ ] **Step 1: Write the failing tests**

Add a private helper to the test class (it reuses the setup of `test_swap_forbids_different_rm_levels`):

```php
private function blockedPromotion()
{
    $empA = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-3']);
    Employee::factory()->create(['jabatan' => 'Kasubag B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
    Position::factory()->create(['nama' => 'Kasubag B', 'rm_level' => 'RM-2']);
    Position::factory()->create(['nama' => 'Kasubag Kosong', 'rm_level' => 'RM-2']);
    Unit::where('nama', 'Bagian SDM')->first()->positions()
        ->attach(Position::whereIn('nama', ['Kasubag B', 'Kasubag Kosong'])->pluck('id')->toArray());

    return Livewire::test(MutasiProcess::class)
        ->set('form.employeeNik', $empA->nik)
        ->set('form.jenisMutasi', 'PROMOSI')
        ->set('form.targetUnitKerja', 'Bagian SDM')
        ->set('form.targetJabatan', 'Kasubag B');
}
```

Tests:
- `test_occupied_cross_rm_target_blocks_sk_section_and_primary_action`: `$c = $this->blockedPromotion();` then `assertStringContainsString('Mutasi Berantai', $c->instance()->blockingReason)` and `$c->assertSeeHtml('data-blocked="true"')`.
- `test_switching_to_vacant_target_clears_blocking_state`: `$this->blockedPromotion()->set('form.targetJabatan', 'Kasubag Kosong')`, then `assertNull(...->instance()->blockingReason)` and `assertDontSeeHtml('data-blocked="true"')`.
- `test_equal_rm_swap_is_not_blocked`: reuse the setup of `test_swap_checkbox_is_required_for_occupied_positions` and `assertNull($c->instance()->blockingReason)`.
- `test_same_position_target_is_blocked`: target = the employee's own jabatan and unit, then `assertStringContainsString('formasi dan unit kerja yang sama', …)`.
- `test_reserved_target_is_blocked`: reuse the setup of `test_reserved_position_blocks_submission_with_validation_error`, then `assertStringContainsString('dipesan', …)`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="blocks_sk_section|clears_blocking|equal_rm_swap_is_not|same_position_target_is|reserved_target_is_blocked"`
Expected: FAIL with `Property [blockingReason] not found` (or similar).

- [ ] **Step 3: Implement `blockingReason(): ?string` in `MutasiProcess.php`**

Use `Livewire\Attributes\Computed`. It reads only `$this->employeeDetails`, `$this->targetAnalysis` and `$this->isSwapAllowed`, applying the table above in order.

- [ ] **Step 4: Wire the views**

- `action-bar.blade.php`:
  - Give the outer card `data-blocked="{{ $this->blockingReason ? 'true' : 'false' }}"` and make it sticky (`sticky bottom-4 z-30`). The root view already has `pb-32`.
  - Add `@disabled($this->blockingReason)` to the "Tinjau & Eksekusi Mutasi" button.
  - When blocked, replace the subline `<p>` with `<p class="text-xs text-rose-700 font-medium">{{ $this->blockingReason }}</p>`.
- `sk-legal.blade.php`: wrap everything below the header in `<fieldset @disabled($this->blockingReason) class="{{ $this->blockingReason ? 'opacity-50' : '' }} space-y-5">`. When blocked, show one line above it: `Data SK dapat dilengkapi setelah kendala formasi di atas diselesaikan.` (`text-xs text-surface-500`).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MutasiProcessTest`
Expected: all PASS, including the existing `test_swap_forbids_different_rm_levels` and `test_tampered_analysis_state_cannot_bypass_swap_confirmation`.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint
git add app/Livewire/Admin/MutasiProcess.php resources/views/livewire/admin/partials/mutasi-process/action-bar.blade.php resources/views/livewire/admin/partials/mutasi-process/sk-legal.blade.php tests/Feature/MutasiProcessTest.php
git commit -m "feat(mutasi): disable SK section and submit when placement is blocked"
```

---

### Task 2: One status label, two severities

**Files:**
- Modify: `resources/views/livewire/admin/partials/mutasi-process/workspace.blade.php:54-60` (connector `match`)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php:17-42, 88-93`
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Consumes: `$this->blockingReason` (Task 1).

Decisions:
- Connector, `$isSwapBlocked` branch: label `Perlu Mutasi Berantai`, icon `fa-link`, `iconClass` `bg-rose-50 text-rose-600 border border-rose-200` (no saturated fill, no ring), badge unchanged.
- Compliance header badge has three states:
  - `$this->blockingReason` → rose pill `Tidak Dapat Diproses Tunggal` (icon `fa-ban`, header icon box `bg-rose-100 text-rose-800`).
  - Else `$hasAnyWarning` → amber pill `Perlu Dispensasi`.
  - Else the existing emerald `Memenuhi Standar Regulasi`.
- Delete the `Konflik Okupansi` chip (compliance.blade.php ~line 90) and the `Verifikasi Pra-SK` caption (~line 40).

- [ ] **Step 1: Write the failing tests**

- `test_blocked_target_shows_single_blocking_status`: `$this->blockedPromotion()` → `assertSee('Tidak Dapat Diproses Tunggal')`, `assertSee('Perlu Mutasi Berantai')`, `assertDontSee('Swap Ditolak')`, `assertDontSee('Konflik Okupansi')`, `assertDontSee('Memerlukan Perhatian / Dispensasi')`.
- `test_waivable_promotion_shows_dispensation_status_not_blocking`: use the setup of `test_promosi_requires_dispensasi_when_not_eligible` up to `targetJabatan`, then `assertSee('Perlu Dispensasi')` and `assertDontSee('Tidak Dapat Diproses Tunggal')`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="single_blocking_status|dispensation_status_not_blocking"`
Expected: FAIL on `assertSee('Tidak Dapat Diproses Tunggal')`.

- [ ] **Step 3: Apply the view changes listed under Decisions**

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MutasiProcessTest`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint
git add resources/views/livewire/admin/partials/mutasi-process/workspace.blade.php resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php tests/Feature/MutasiProcessTest.php
git commit -m "fix(mutasi): unify blocking status label and separate it from dispensation"
```

---

### Task 3: Correct tenure badge and consistent duration format

**Files:**
- Modify: `app/Services/Mutasi/TalentProfileBuilder.php` (add static formatter, and use it at lines 48, 61, 73)
- Modify: `app/Services/Mutasi/MutasiGovernanceGuard.php` (tenure `message` strings ~line 70–71, RM-3→RM-2 criterion `value`, RM-2→RM-1 criterion `value`)
- Modify: `app/Livewire/Admin/MutasiProcess.php:405-412` (occupant tenure text)
- Modify: `resources/views/components/admin/mutasi/compliance-guard.blade.php:75, 108-115`
- Test: `tests/Unit/TalentProfileBuilderTest.php` (create), `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Produces: `public static function formatMonths(int $months): string` on `App\Services\Mutasi\TalentProfileBuilder`, which returns `intdiv($months, 12).' th '.($months % 12).' bln'`. The `years` and `months` keys of the analysis arrays stay as they are, because other code reads them.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/TalentProfileBuilderTest.php` (extends `PHPUnit\Framework\TestCase`):

```php
public function test_format_months_renders_years_and_months(): void
{
    $this->assertSame('1 th 7 bln', TalentProfileBuilder::formatMonths(19));
    $this->assertSame('0 th 0 bln', TalentProfileBuilder::formatMonths(0));
    $this->assertSame('2 th 0 bln', TalentProfileBuilder::formatMonths(24));
}
```

In `MutasiProcessTest`:
- `test_same_unit_promotion_does_not_claim_unit_tenure_compliance`: use the setup of `test_promosi_requires_dispensasi_when_not_eligible` (same unit, not eligible), then `assertDontSee('Masa Dinas Unit Memenuhi Syarat')` and `assertSee('1 th 0 bln')` (the criterion value for `tanggal_dalam_jabatan` = now − 1 year), and `assertDontSee('1.0 thn')`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="format_months|does_not_claim_unit_tenure"`
Expected: FAIL (`Call to undefined method formatMonths`, and the badge is visible).

- [ ] **Step 3: Implement**

- Add `formatMonths()` and replace the three inline `"{$y} th {$m} bln"` builds in `TalentProfileBuilder` with it.
- In `MutasiGovernanceGuard`, build criterion values and tenure messages with `TalentProfileBuilder::formatMonths($months)`. Examples: `"Gol: {$emp->golongan} | Masa Dinas: ".TalentProfileBuilder::formatMonths($jobTenureMonths)` and `TalentProfileBuilder::formatMonths($jobTenureMonths)` for RM-2→RM-1. Keep the `>=` comparisons on `$jobTenureYears` unchanged.
- In `MutasiProcess::runAnalysis()`, set `$occupantTenureText = TalentProfileBuilder::formatMonths((int) $tmt->diffInMonths(now()))`.
- In `compliance-guard.blade.php`:
  - Line 75: show `{{ \App\Services\Mutasi\TalentProfileBuilder::formatMonths($tenureAnalysis['months'] ?? 0) }}`.
  - Line 108: change the condition to `($tenureAnalysis['is_applicable'] ?? false) && ($tenureAnalysis['is_compliant'] ?? false)`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test`
Expected: all PASS. Check `MutasiServiceTest` and `MutasiChainProcessTest` in particular for any assertions on the old `"X tahun (Y bulan)"` or `"thn"` strings, and update them only if they assert that exact text.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint
git add app/Services/Mutasi/TalentProfileBuilder.php app/Services/Mutasi/MutasiGovernanceGuard.php app/Livewire/Admin/MutasiProcess.php resources/views/components/admin/mutasi/compliance-guard.blade.php tests/Unit/TalentProfileBuilderTest.php tests/Feature/MutasiProcessTest.php
git commit -m "fix(mutasi): consistent tenure format and no false unit-tenure badge"
```

---

### Task 4: Reduce badge noise and replace developer copy

**Files:**
- Modify: `resources/views/livewire/admin/partials/mutasi-process/workspace.blade.php:12` (delete `Asal Posisi`) and the `Tujuan Penempatan` caption in the right column header
- Modify: `resources/views/livewire/admin/partials/mutasi-process/sk-legal.blade.php:12-16` (delete the `SK Penugasan Individual` pill) and `:34-36` (delete the `Klausul SK Direksi` pill)
- Modify: `resources/views/components/admin/employee-profile-card.blade.php:70` and `resources/views/livewire/admin/partials/mutasi-process/swap-simulation.blade.php:96` — `9-Box Lengkap` → `Lihat 9-Box`
- Modify: `tests/Feature/MutasiChainProcessTest.php:542` — expect `Lihat 9-Box`
- Modify copy (`atomik` → plain language):
  - `action-bar.blade.php:34` → `Seluruh perubahan disimpan sekaligus; bila gagal, tidak ada data yang berubah.`
  - `partials/mutasi-chain-process/submit-bar.blade.php:49` → `Seluruh perpindahan disimpan sekaligus; bila satu gagal, tidak ada yang berubah.`
  - `components/admin/mutasi/review-modal.blade.php:170` → `Setelah dikonfirmasi, seluruh data kepegawaian dan riwayat penugasan disahkan sekaligus.`
- Modify: in `workspace.blade.php`, add `title="{{ … }}"` with the full selected value to the Status Penugasan select and pass the full unit name as `title` on the unit combobox's selected-text element (`resources/views/components/admin/unit-combobox.blade.php`)
- Test: `tests/Feature/MutasiProcessTest.php`

- [ ] **Step 1: Write the failing test**

`test_mutasi_form_has_no_decorative_tags_or_developer_copy`: `$this->blockedPromotion()` → `assertDontSee('Asal Posisi')`, `assertDontSee('Tujuan Penempatan')`, `assertDontSee('SK Penugasan Individual')`, `assertDontSee('atomik')`, `assertSee('Lihat 9-Box')`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=no_decorative_tags`
Expected: FAIL on `assertDontSee('Asal Posisi')`.

- [ ] **Step 3: Apply the edits listed under Files**

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint
git add resources/views tests/Feature/MutasiProcessTest.php tests/Feature/MutasiChainProcessTest.php
git commit -m "style(mutasi): remove decorative tags, plain-language copy, full-value tooltips"
```

---

## Out of Scope (named, deliberately skipped)

- Moving the incumbent profile into the empty right column. This is a layout restructure; revisit after these tasks land.
- A dispensation evidence attachment. The reason/decree-number field already appears once each dispensation checkbox is ticked (`compliance-guard.blade.php`, `$isTenureChecked` / `$isPromotionChecked` blocks), so the critique overstated this gap.
- Suggesting the minimum golongan for the target RM band, numbering sections 3/4, and a measured contrast/font-size audit (use `design:accessibility-review` separately).
