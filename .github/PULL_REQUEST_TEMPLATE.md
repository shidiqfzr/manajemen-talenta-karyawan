## What and why

<!-- What changes, and why it is needed. Link the issue if there is one. -->

## How it was checked

<!-- The commands you ran and what you saw, or the screens you tried. -->

## Checklist

**Reference documents** (see CONTRIBUTING.md)
- [ ] I read the reference documents that touch this change and it follows them
- [ ] Business rules live in services; no RM Band or bidang calculation is hardcoded in a view or controller
- [ ] Writes that touch more than one table run inside `DB::transaction()`
- [ ] Organisation structure changes go through the SK service only (no edit of `master_formasi.json`, no re-seeding)
- [ ] Mutations and appointments are recorded in `job_histories`

**Quality gate**
- [ ] `composer verify` passes (Pint, audits, PHPUnit, JavaScript tests)
- [ ] New behaviour has a test written first, and each component has one test class
- [ ] For screen changes: the browser suite (`npm run e2e`) passes

**Interface and wording**
- [ ] User-visible text is Indonesian and uses the official terms ("Karyawan", "Karyawan Pimpinan/Pelaksana", "Nomor Induk Karyawan")
- [ ] No reference document name or the acronym "SSOT" appears in anything a user can see
- [ ] Loading states, debounce (300 ms) and the design tokens follow DESIGN.md

**Data and security**
- [ ] No real employee data (NIK, names, scores, certificates) or screenshots showing it in this pull request, its commits or its description
- [ ] No secrets or `.env` values committed
- [ ] Uploads, queries and output follow SECURITY.md (parameter binding, escaped output, MIME checks)
- [ ] Documentation is updated where behaviour or rules changed
