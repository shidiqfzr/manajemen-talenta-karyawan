# Mutasi Blocked-State Critique Follow-up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On the single-mutation form, make a hard-blocked placement (formasi still occupied) actionable from the sticky bar, stop offering a dispensation that cannot help, show every outstanding issue at once, and tone down the visual noise.

**Architecture:** `MutasiProcess` gains two computed properties (`resolvableByChain`, `outstandingIssues`) next to the existing `blockingReason`. The Blade partials `action-bar` and `compliance` read them. The shared `x-admin.mutasi.compliance-guard` component gets an opt-in `locked` prop (default `false`) so the chain flow (`node-card`) is untouched. Server-side guards in `MutasiForm::validateForm()` stay as they are.

**Tech Stack:** Laravel 13, Livewire 4, Blade + Tailwind, PHPUnit 11.

**Spec:** Design critique of the "Uji Kelayakan Regulasi & Kepatuhan Penugasan" blocked state (conversation, 2026-10-26), priority recommendations 1–4. Builds on `docs/superpowers/plans/2026-10-26-mutasi-form-blocking-ux.md` (already implemented: `blockingReason`, status chip, `data-blocked` on the action bar).

## Global Constraints

- UI copy is Bahasa Indonesia with official business terms. Never show `AGENTS.md`/`DOMAIN.md`/`DESIGN.md`/`ENGINEERING.md`/`SECURITY.md` or "SSOT" in user-visible text (AGENTS.md rule 6).
- No rule logic in Blade: conditions live in `MutasiProcess` computed properties or services.
- One test class per SUT: every `MutasiProcess` test goes in `tests/Feature/MutasiProcessTest.php`; chain regressions are checked by running `tests/Feature/MutasiChainProcessTest.php`, not by editing it.
- Duration format on this page: `"{years} th {months} bln"` (`TalentProfileBuilder::formatMonths`).
- Do not loosen `MutasiForm::validateForm()`. Disabling controls in the UI is a convenience, not the guard.
- Text on this screen is at least 12px (`text-xs`); no `text-[10px]` / `text-[11px]` in the touched blocks.
- Interactive controls on this screen are at least 44px high at the mobile breakpoint.
- Run `./vendor/bin/pint` before each commit.

## Review Focus

1. **Retired employee / same position / reserved formasi**: the sticky bar must keep the disabled "Tinjau & Eksekusi Mutasi" button, not the chain link, because a chain mutation cannot fix those. Test in Task 1.
2. **Valid equal-RM swap and vacant target**: no chain link, no locked dispensation. Test in Task 1 and Task 2.
3. **Chain flow** (`node-card`) keeps working dispensation checkboxes in both colors/states. Run `MutasiChainProcessTest` in Task 2 and Task 3.
4. **Target switched from blocked to valid**: the locked state, the chain link and the issue summary must disappear without a page reload. Test in Task 2.
5. **Candidate tenure vs. incumbent tenure** both show "1 th 7 bln" in the screenshot. The code reads two different fields, so a test with different values must show both. Test in Task 4.

---

### Task 1: Chain link replaces the disabled primary in the sticky bar

**Files:**
- Modify: `app/Livewire/Admin/MutasiProcess.php` (add computed after `blockingReason()`, ~line 215)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/action-bar.blade.php`
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Consumes: `blockingReason(): ?string`, `$targetAnalysis`, `$isSwapAllowed`, `$employeeDetails['is_retired']`.
- Produces: `#[Computed] public function resolvableByChain(): bool` on `MutasiProcess`. True only when `targetAnalysis['type'] === 'occupied'`, `isSwapAllowed` is false and the employee is not retired. Task 3 and Task 5 read it as `$this->resolvableByChain`.

- [ ] **Step 1: Write the failing tests** in `MutasiProcessTest`:
  - `test_occupied_block_shows_chain_link_instead_of_review_button`: using `blockedPromotion()`, `assertSee('Selesaikan melalui Mutasi Berantai')` appears inside the action bar (assert `route('admin.mutasi.chain')` is rendered at least twice: card and bar) and `assertDontSee('Tinjau &amp; Eksekusi Mutasi', false)`.
  - `test_non_chain_blocks_keep_disabled_review_button` (data provider: retired, same position, reserved): `assertSee('Tinjau &amp; Eksekusi Mutasi', false)`, `assertDontSee('Selesaikan melalui Mutasi Berantai')`, `assertSeeHtml('data-blocked="true"')`.
  - `test_unblocked_target_keeps_enabled_review_button`: `Kasubag Kosong` target from `test_mutasi_form_has_no_decorative_tags_or_developer_copy` shows the review button and no chain link in the bar.
- [ ] **Step 2: Run to verify they fail:** `php artisan test tests/Feature/MutasiProcessTest.php --filter=chain_link`. Expected: FAIL, the bar still renders the disabled button.
- [ ] **Step 3: Implement `resolvableByChain(): bool`** in `MutasiProcess.php`; it must reuse the same conditions as the `occupied` arm of `blockingReason()` plus `! is_retired`.
- [ ] **Step 4: Update `action-bar.blade.php`:** when `$this->resolvableByChain`, render an `<a href="{{ route('admin.mutasi.chain') }}">` styled like the current primary button (`fa-link` icon, label "Selesaikan melalui Mutasi Berantai") instead of the review `<button>`, and replace the sub-line with "Pejabat petahana perlu dipindahkan lebih dulu." Otherwise keep the current markup unchanged. Keep `data-blocked`.
- [ ] **Step 5: Find and fix existing tests** that assert the disabled review button for the occupied case: `grep -n "data-blocked\|Tinjau" tests/Feature/MutasiProcessTest.php`. Adjust only those assertions.
- [ ] **Step 6: Run** `php artisan test tests/Feature/MutasiProcessTest.php`. Expected: all PASS.
- [ ] **Step 7: Commit** `feat(mutasi): offer chain mutation from the action bar when formasi is occupied`

---

### Task 2: Lock dispensation while the placement is hard-blocked

**Files:**
- Modify: `resources/views/components/admin/mutasi/compliance-guard.blade.php` (add prop, lines 1–16 and both checkbox blocks)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php` (line 196, pass the prop)
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Consumes: `MutasiProcess::blockingReason()`.
- Produces: component prop `locked` (bool, default `false`). When true, both dispensation checkboxes render `disabled`, the reason inputs are hidden, and the note "Berlaku setelah formasi tersedia." is shown under each checkbox. `node-card.blade.php` does not pass it.

- [ ] **Step 1: Write the failing tests** in `MutasiProcessTest`:
  - `test_dispensation_is_locked_while_formasi_is_occupied`: promotion RM-2 → RM-1 (candidate `golongan` `IIID`, `tanggal_dalam_jabatan` 19 months ago; occupant is an RM-1 holder of the target jabatan). Assert `assertSee('Berlaku setelah formasi tersedia')` and `assertSeeHtml('disabled')` on the promotion checkbox (match `wire:model.live="form.dispensasiPromosi"` line).
  - `test_dispensation_unlocks_when_target_becomes_valid`: continue from the same state, `set('form.targetJabatan', <vacant jabatan>)`, `assertDontSee('Berlaku setelah formasi tersedia')`.
  - `test_dispensation_stays_interactive_for_waivable_promotion`: reuse the setup of `test_waivable_promotion_shows_dispensation_status_not_blocking`; `assertDontSee('Berlaku setelah formasi tersedia')`.
- [ ] **Step 2: Run to verify they fail:** `php artisan test tests/Feature/MutasiProcessTest.php --filter=dispensation`. Expected: FAIL.
- [ ] **Step 3: Add `'locked' => false`** to the component `@props`; apply `@disabled($locked)` to both checkboxes, wrap the reason inputs in `@if (! $locked && $isTenureChecked)`, and show the note in `text-xs text-surface-500` when locked.
- [ ] **Step 4: Pass `:locked="(bool) $this->blockingReason"`** from `compliance.blade.php`.
- [ ] **Step 5: Run** `php artisan test tests/Feature/MutasiProcessTest.php tests/Feature/MutasiChainProcessTest.php`. Expected: all PASS (chain dispensation tests prove the default is unchanged).
- [ ] **Step 6: Commit** `feat(mutasi): lock dispensation while the formasi is still occupied`

---

### Task 3: Outstanding-issues summary and shorter block copy

**Files:**
- Modify: `app/Livewire/Admin/MutasiProcess.php` (computed + `swapIncompatibilityMessage` text, line 409)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php` (header, under the subtitle at line 41)
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:**
- Consumes: `blockingReason()`, `$tenureAnalysis`, `$promotionAnalysis`.
- Produces: `#[Computed] public function outstandingIssues(): array` returning a list of strings in this order: the `blockingReason()` text if any, then `'Masa dinas unit kerja belum memenuhi, perlu dispensasi.'` if tenure is applicable and non-compliant, then `'Kualifikasi promosi belum memenuhi, perlu dispensasi.'` if promotion is applicable and not eligible. Empty array when nothing is outstanding.

- [ ] **Step 1: Write the failing tests** in `MutasiProcessTest`:
  - `test_blocked_promotion_with_failed_criteria_lists_all_outstanding_issues`: same setup as Task 2. Assert `$c->instance()->outstandingIssues` has 2 entries, the first equals `blockingReason`, and the view `assertSee('2 hal perlu diselesaikan')`.
  - `test_single_issue_does_not_show_summary`: `blockedPromotion()` with eligible promotion shows no "hal perlu diselesaikan".
  - `test_promotion_block_message_is_short`: `swapIncompatibilityMessage` for PROMOSI is exactly `"Formasi target saat ini diduduki oleh {nama} ({rm}). Pejabat petahana perlu dipindahkan lebih dulu melalui Mutasi Berantai."` and the view `assertDontSee('Swap Bilateral')`.
- [ ] **Step 2: Run to verify they fail:** `--filter=outstanding\|short`. Expected: FAIL.
- [ ] **Step 3: Implement `outstandingIssues(): array`**. Use the same applicability conditions as `compliance-guard.blade.php` lines 19–20 (move them into the computed rather than duplicating them in the view).
- [ ] **Step 4: Render the summary** as a numbered `<ol class="text-xs ...">` under the subtitle only when `count($this->outstandingIssues) >= 2`, headed "{n} hal perlu diselesaikan".
- [ ] **Step 5: Change the PROMOSI message** at `MutasiProcess.php:409` to the wording above. Grep `tests/` for the old sentence (`Swap Bilateral`, `pergeseran pejabat petahana`) and update matching assertions.
- [ ] **Step 6: Run** `php artisan test tests/Feature/MutasiProcessTest.php`. Expected: PASS.
- [ ] **Step 7: Commit** `feat(mutasi): summarize outstanding issues and shorten the occupied-formasi message`

---

### Task 4: Visual calm-down and tenure data check

**Files:**
- Modify: `resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php` (lines 110–144 profile block)
- Modify: `resources/views/components/admin/mutasi/compliance-guard.blade.php` (promotion card, lines 102–155; micro text sizes)
- Test: `tests/Feature/MutasiProcessTest.php`

**Interfaces:** none new.

Decisions:
- The promotion card uses the amber palette (same as the tenure card) instead of indigo. The "Perlu Dispensasi" pill is amber everywhere.
- The incumbent's "RM-x • Definitif" pill uses neutral `surface` colors, not rose.
- The incumbent's Jabatan and Unit Kerja are stacked label-over-value text without boxed chips (`<dl>` with two `<div>`s), keeping `title` for the full value.
- All `text-[10px]` and `text-[11px]` in these two files become `text-xs`.

- [ ] **Step 1: Write the failing tests** in `MutasiProcessTest`:
  - `test_promotion_dispensation_uses_warning_palette`: blocked promotion with failed criteria; `assertDontSee('indigo-')`.
  - `test_incumbent_pill_is_neutral`: `assertDontSee('bg-rose-100 text-rose-900')`. If another partial on the page legitimately uses that class string and the assertion fails for that reason, scope it with a unique marker class on the pill instead of deleting the test.
  - `test_candidate_and_incumbent_tenure_are_separate_values`: candidate `tanggal_dalam_jabatan` 19 months ago (`1 th 7 bln`), incumbent `tmt_unit_kerja` 3 years ago; the page shows `1 th 7 bln` in the criteria row and `3 th 0 bln` in the incumbent block. This documents that the identical numbers in the screenshot come from different fields.
- [ ] **Step 2: Run to verify they fail:** `--filter=warning_palette\|incumbent_pill`. Expected: FAIL (the tenure test should already pass; if it fails, that is a real data bug: stop and report it).
- [ ] **Step 3: Apply the four decisions** to the two Blade files. The micro-text rule is verified by command, not by a page-wide test (other partials legitimately use small text): `grep -c "text-\[1[01]px\]" resources/views/components/admin/mutasi/compliance-guard.blade.php resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php` must print `0` for both files.
- [ ] **Step 4: Run** `php artisan test tests/Feature/MutasiProcessTest.php tests/Feature/MutasiChainProcessTest.php`. Expected: PASS (the chain tests assert text such as "Perlu Dispensasi", not colors; fix any color-coupled assertion there only if it fails).
- [ ] **Step 5: Run `npm run build`** so Tailwind picks up changed classes, then open `/admin/mutasi/create` with a blocked promotion and compare against the screenshots.
- [ ] **Step 6: Commit** `style(mutasi): amber dispensation palette, neutral incumbent pill, stacked profile fields`

---

### Task 5: Accessibility and layout fixes

**Files:**
- Modify: `resources/views/livewire/admin/partials/mutasi-process/compliance.blade.php` (red card wrapper at line 83, CTA at line 156)
- Modify: `resources/views/livewire/admin/partials/mutasi-process/action-bar.blade.php` (link and button classes)
- Test: `tests/Feature/MutasiProcessTest.php`

Do NOT touch `resources/views/livewire/admin/mutasi-process.blade.php`: the working tree already has `pb-32` on its root wrapper (uncommitted user work), so bottom padding is done.

**Interfaces:**
- Consumes: `resolvableByChain` (Task 1).

- [ ] **Step 1: Write the failing tests** in `MutasiProcessTest`:
  - `test_occupied_block_card_is_announced`: `blockedPromotion()` renders `role="alert"` on the "Formasi Masih Terisi Pejabat Definitif" card.
  - `test_disabled_review_button_references_its_reason`: a reserved target renders `aria-describedby="blocking-reason"` on the button and `id="blocking-reason"` on the reason paragraph.
  - `test_action_controls_meet_touch_target`: `assertSee('min-h-11')` appears on the chain CTA in the card and on the bar's link/button (Tailwind `min-h-11` = 44px).
- [ ] **Step 2: Run to verify they fail:** `--filter=announced\|references_its_reason\|touch_target`. Expected: FAIL.
- [ ] **Step 3: Implement:** add `role="alert"` to the card wrapper; give the bar's reason `<p>` `id="blocking-reason"` and the button `aria-describedby="blocking-reason"` when blocked; add `min-h-11` to the chain CTA, the bar's chain link, the review button and Batal. Check the installed Tailwind version first (`npm ls tailwindcss`); `min-h-11` exists from 3.4, otherwise use `min-h-[44px]` and adjust the test's expected class.
- [ ] **Step 4: Run** `php artisan test tests/Feature/MutasiProcessTest.php tests/Feature/MutasiChainProcessTest.php`, then `./vendor/bin/pint`, then `php artisan test`. Expected: all PASS.
- [ ] **Step 5: Commit** `fix(mutasi): announce blocking state, bigger touch targets, clear space under the action bar`
