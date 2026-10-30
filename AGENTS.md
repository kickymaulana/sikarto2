# AGENTS.md

SI KARTO — Sistem Kalibrasi Toleransi Operasional Alat Ukur Rutin Bulanan. PRD lengkap: `docs/PRD.md`. Ikuti schema & business logic di sana.

## Stack
- Laravel 13 + Inertia **3** (bukan v2) + Vue 3 + TypeScript + Varlet UI + Ziggy + Spatie Permission
- Setup **bersih** (tanpa starter kit, tanpa Tailwind). Jangan tambah Tailwind.
- Varlet full import (`.use(Varlet)` di `resources/js/app.ts`), bukan on-demand.

## Commands
- Dev: `npm run dev` + `php artisan serve`
- Build: `npm run build` (setelah frontend berubah)
- Typecheck: `npm run typecheck` (`vue-tsc --noEmit`) — jalankan sebelum build
- Test: `php artisan test`
- Code style: `vendor/bin/pint`

## Struktur frontend
- Halaman: `resources/js/Pages/**/*.vue` (auto-resolve oleh `app.ts`)
- Shared props type: `resources/js/types/index.d.ts` (module augmentation `@inertiajs/core` PageProps). Tambah prop baru di `HandleInertiaRequests::share()` DAN di file types ini — typecheck fail kalau nggak sinkron.
- Routes di JS pakai Ziggy (`route()`), di-inject via `@routes` di `resources/views/app.blade.php`.
- Varlet full import = bundle ~887 KB. Jika bundle terlalu besar, upgrade ke on-demand via `@varlet/import-resolver` + unplugin.

## Desain
- Referensi: aplikasi SUKIRMAN (`D:\Apache24\htdocs\sukirman`) — mobile-first "android-layout" + Varlet MD3 light (`StyleProvider(Themes.md3Light)` di `app.ts`).
- **Tema orange**: primary `#fb8c00`, peach fill `#fdf0ea`, bg `#f8fafc`, kartu putih radius 16 border `#f1f5f9`. Tombol utama gradient pill `#fb8c00→#f57c00`.
- **Satu warna di semua CRUD**: appbar = FAB = tombol primary, semuanya orange. `StyleProvider` override `'color-primary': '#fb8c00'` di `app.ts` (wajib — tanpa itu `Themes.md3Light` bikin primary ungu `#6750A4`). AppBar pakai gradient `#fb8c00→#f57c00`, teks/ikon putih; search mode tetap putih.
- Layout helper ada di `app.css`: `.android-layout`, `.android-content`, `.top-app-bar`, `.white-card`. Halaman baru wajib pakai pola ini + AppLayout (bottom nav Beranda/Pengujian/Laporan + FAB).
- **Master menu** (`/masters` → `Masters/Menu.vue`) = `var-tree-menu` (accordion, group "Master Data" berisi semua entity + item "Alat Ukur"). Icon pakai icon font Varlet. Route list: `masters.menu`, `masters.index`.
- **Paginasi semua index list** (`Masters/Index.vue`, `Instruments/Index.vue`, `Tests/Index.vue`) pakai `var-pagination` (`:current/:total/:size`, `@change` → `router.get(route(..., {page}))`). BUKAN `var-list`/`var-pull-refresh`/infinite scroll.
- Search di list **server-side**: `watch(searchState)` → `router.get(route(..., {search}))`. Controller `index()` filter `whereLike` per kolom.
- **Desain master (sidebar + tabel):** `/masters` redirect ke `/masters/{entity}`. Halaman `Masters/Index.vue` punya sidebar kiri (TreeMenu sticky) + `var-table` (baris klik → halaman edit). Toggle sidebar di mobile. `var-pagination` di bawah tabel.
- `/laporan` redirect ke `/laporan/matrix` (alias group only, tanpa view root). `/masters/matrix` redirect ke `/laporan/matrix`. Matrix view asli ada di `resources/js/Pages/Laporan/Matrix.vue`.
- `/masters` root redirect ke `/masters/factories`.
- Master CRUD pakai **halaman terpisah** (`/masters/{entity}/create`, `/{entity}/{id}/edit`) — bukan inline form. Contoh: `Masters/Form.vue`, `Instruments/Form.vue`. List = row-card **klik-card → halaman edit**. Tombol Edit/Hapus TIDAK di list; Hapus ada di halaman edit.
- **Nonaktifkan alat ukur = `is_active=false`** (BUKAN soft delete). `index()` & resource route pakai `withTrashed()` (data lama yang pernah soft-delete tetap tampil). Form edit menampilkan tombol "Aktifkan Kembali" (`instruments.activate`) saat alat nonaktif/trashed.
- List (master & instruments) = android pattern: **app-bar search icon → toggles search input** (`composables/search.ts` shared `searchState`), `var-pull-refresh` + `var-list` infinite scroll (load-more via `router.get` + `only: [...]` + merge props). Contoh `Masters/Index.vue`.
- **AppBar = satu baris**: judul halaman (dari `pageTitle` map di `AppLayout.vue`, dinamis per route) + icon search (list pages) + icon `power` logout. Sub-page ada back button. Dashboard tampil brand "SI KARTO". AppBar sticky **lengket ke browser** — `html, body { margin:0 }` wajib di `app.css` (tanpa reset, body default margin 8px muncul celah).
- **Tombol Tambah = FAB** di semua CRUD list (masters/instruments dari AppLayout `showAdd`; tests dari page sendiri) — bukan icon di app bar.
- List pakai request-card (kode monospace bold + `var-chip` round + meta) — contoh `Tests/Index.vue`.
- Font Inter (`@fontsource/inter`). Transisi halaman slide via `<Transition>` di `app.ts`.

## Otorisasi
- Spatie (`spatie/laravel-permission`), config `config/permission.php`. Role: `super_admin`, `admin`, `inspector`, `user`. Users table tanpa kolom role — pakai Spatie.
- `super_admin` = full (master + user.manage + test + report). `admin` = master CRUD + test.read + report.read/export (BUKAN test.create, BUKAN user.manage). `inspector` = master CRUD + test.create + test.read. `user` = read-only (master.read, test.read, report.read).
- Endpoint harus cek permission, bukan hanya role. Semua form validasi server-side (jangan percaya browser).

## SSO Login (Manual Provisioning)
- **Login wajib SSO** (OAuth2 Authorization Code) — BUKAN email/password. SSO server: `sekali_login` (`http://localhost/sekali_login/public`). Panduan: `public/SSO-Integrasi-Laravel-Manual-Provisioning.md`. Implementasi: `AuthController` (redirectSso/callbackSso/pendingRole).
- Client terdaftar di DB `oauth_clients` sekalilogin (client_id `299dec87-8cd6-4882-b525-faa5ae86d853`, secret `sikarto-sso-secret-2026`), redirect_uri `http://localhost/sikarto/public/callback`. Config di `.env` (`SSO_BASE_URL`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`) + `config/services.php`.
- **Manual Provisioning**: callback match by `nik`. NIK tak dikenal → auto-create user `is_approved=false` → redirect `/pending-role` (pilih role inspector/admin + factory). Admin approve via `/users` (`Users/Index.vue`, permission `user.manage`, role di-assign via Spatie + `is_approved=true`).
- Users pakai kolom `nik` (unique), `is_approved`, `requested_role`. User `is_approved=false` TIDAK bisa login.

## DB
- Default SQLite (`database/database.sqlite`). Prod: MySQL/MariaDB — ubah `.env`.
- Index wajib: `instruments.code`, `calibration_tests.next_test_date`, `calibration_tests.instrument_id`.
- `factories` & `departments` TIDAK punya kolom `code` (dihapus). Identitas = `name`. Departemen unik per factory via relasi, bukan code.
- **Grup titik uji per kapasitas**: `Capacity::groups()` → `standard_groups(capacity_id, name, reference_media, sort_order)` → `StandardGroup::standards()` → `standard_templates(standard_group_id, standard_value, sort_order)`. `/tests/create` load `instrument.capacity.groups.standards`. Form Kapasitas mengirim `groups` ke `MasterController::syncGroups`; ID existing dipertahankan, ownership divalidasi, sinkronisasi atomik. Kapasitas boleh tanpa grup; setiap grup wajib nama, media referensi, dan minimal satu titik. Urutan = posisi array, relasi sort by `sort_order`, lalu `id`.
- Migrasi grup mempertahankan ID/nilai/urutan standar lama dalam grup default `Penimbangan` / `Anak Timbangan` per kapasitas yang memiliki titik; sesuaikan nama/media untuk alat non-timbangan. Null/orphan `capacity_id` ditolak sebelum mutasi. Snapshot histori lama tetap NULL, tidak diisi dari master sekarang. SQLite diuji di memory; MySQL/MariaDB wajib staging + backup karena DDL implicit commit dan kegagalan bisa meninggalkan migrasi parsial. Rollback menghilangkan metadata grup/snapshot, bukan rollback lossless. Deploy pending: `php artisan migrate --force` setelah backup dan maintenance; jangan jalankan migrasi pada DB aplikasi saat review.

## Testing
- `php artisan test` — feature test grup kapasitas, rata-rata global OK/NG, override/manual bypass, snapshot, rollback transaksi/migrasi dan permission di `tests/Feature/CalibrationTestTest.php`. Checks: `vendor/bin/pint --test`, `npm run typecheck` lalu `npm run build`, `git diff --check`.
- Test wajib memakai SQLite memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), bukan MariaDB database aplikasi. `tests/bootstrap.php` menghapus config cache dan memaksa environment test sebelum Laravel boot.
- Jangan jalankan test dengan `DB_DATABASE=sikarto` atau konfigurasi produksi; `RefreshDatabase` dapat menghapus seluruh data user, role, dan permission pada database aktif.
- **phpunit.xml override `APP_URL=http://localhost`** — jangan dihapus. `.env` pakai subfolder `/sikarto/public`; tanpa override ini `$this->post('/x')` di test jadi `http://localhost/sikarto/public/x` → 404.

## Gotchas
- Nama package composer: `tightenco/ziggy` (composer) + `ziggy-js` (npm) — dua-duanya ada.
- Business logic OK/NG: `correction = penunjukan − standar`; `avg_correction = round(sum(koreksi seluruh titik) / jumlah seluruh titik, 4)` (half-away-from-zero), BUKAN rata-rata dari rata-rata grup. Otomatis OK jika rata-rata global dalam toleransi inklusif, selain itu NG; indikator per titik tidak menentukan status global. Rata-rata per grup ditampilkan di create (sementara, titik valid saja) dan detail (snapshot), bukan dasar keputusan.
- Auto maupun pilihan OK/NG wajib semua titik kapasitas tepat satu kali, tanpa duplikat/titik asing/tambahan; nol valid, kosong invalid. Angka maksimal 4 desimal dan rentang ±99999999.9999; toleransi valid wajib tersedia. Server menghitung ulang dari master dan menolak koreksi overflow.
- Status efektif `status = selected_status ?? computed_status`; pilihan OK/NG menggantikan otomatis tetapi tetap menghitung/menyimpan hasil. SPARE/NA/SERVICE bypass pengukuran: items diabaikan walau invalid, tidak disimpan; `avg_correction`/`computed_status` NULL. Semua status tetap menjadwalkan bulan berikutnya.
- Snapshot saat simpan: standar, penunjukan, koreksi, indikator titik, nama/urutan grup, media, satuan toleransi, urutan titik, min/max toleransi, status otomatis/pilihan/efektif. Detail memakai snapshot, bukan master terkini; histori lama yang NULL ditandai tidak tersedia, bukan direkonstruksi. Identitas alat masih relasi hidup (termasuk soft-deleted), bukan snapshot. Test + items disimpan dalam satu transaksi.
- `next_test_date = test_date + 1 bulan` (auto, wajib di semua jalur simpan pengujian).
- Varlet `InputType` HANYA `text|password|number|tel|email`. Textarea = `:textarea="true"`, tanggal = native `<input type="date">` (bukan `type="date"`/`type="textarea"` — typecheck fail).
- **Varlet icon font KUSTOM** (bukan MDI) — daftar ikon cek `node_modules/@varlet/ui/es/icon/icon.css` (`.var-icon-<name>`). `logout`, `pencil`, `gauge`, `close`, `account`, `tune-variant`, `chart-timeline-variant` dll TIDAK ADA. Pakai tombol teks untuk aksi (Edit/Hapus/Keluar), bukan ikon.
- **Konfirmasi (hapus dll) wajib pakai `Dialog` Varlet** (import `{ Dialog } from '@varlet/ui'`), BUKAN `confirm()`. Tombol bahasa Indonesia: `confirmButtonText: 'Ya, Hapus'`, `cancelButtonText: 'Batal'`. Contoh: `Masters/Form.vue`.
- **Varlet Form**: pakai prop `:onsubmit="fn"` (handler terima `valid: boolean`, guard `if (!valid) return`). JANGAN `@submit.prevent` — `.prevent` bungkus handler, Varlet panggil `onSubmit(valid)` → `valid.preventDefault()` crash ("e.preventDefault is not a function").
- **Varlet API yang TIDAK ada** (cek `node_modules/@varlet/ui/types/*.d.ts` dulu): `var-avatar` tidak punya `text-color`; `var-input` tidak punya `focus` (pakai `autofocus`).
- MCP Varlet: package npm yang benar **`@fe-fast/varlet-mcp`** (di `opencode.json`). `@varlet/mcp` 404 — jangan dipakai. Registry MCP parsial: `var-app-bar`, `var-bottom-navigation`, `var-chip`, `var-select` dll TIDAK ada di MCP — cek types lokal.
- Role/permission endpoint: cek `permission:` middleware. Role list: `super_admin`, `admin`, `inspector`, `user`. Inspector BISA master CRUD (sesuai permission); `user` read-only.
- **Paginator Inertia TIDAK punya key `meta`** — keys top-level: `current_page`, `last_page`, `from`, `to`, `total`, `data`. JANGAN `items.meta.current_page` (undefined → crash render). Pakai `items.current_page`.
- **Semua URL di JS wajib pakai Ziggy `route()`** — app di subfolder `/sikarto/public`; URL hardcoded (`/auth/sso`, `/pending-role`, dll) jadi `http://localhost/...` → 404. Khusus link eksternal (redirect SSO) pakai `<a :href="route('sso.redirect')">`.
- **`route().current()` TIDAK reaktif** — saat navigasi Inertia SPA, computed yang bergantung padanya (pageTitle, showAdd, showBack, isDashboard) jadi stale sampai refresh. Fix: `AppLayout` pakai `watch(() => page.url)` → set `currentRoute`/`currentParams` (ref) → semua computed baca ref itu.
- **Matriks Uji Bulanan** = halaman sendiri di `/laporan/matrix` (group alias: `/laporan` redirect ke matrix, `/masters/matrix` redirect ke `/laporan/matrix`). Backend pakai `test_cell[1..12]` + `next_cell[1..12]` (tiap cell = `{day, status}`). Header tabel 2 baris (bulan + Uji/Next). Cell isi: `21` (tanggal saja) atau `—` jika kosong, dengan background warna status halus. Tiap bulan punya 2 kolom (Uji, Next). Semua kolom info instrumen (Kode Alat, Merk, Kapasitas, Lokasi) sticky kiri saat scroll horizontal. **Filter Jenis Alat** (default = `Timbangan Digital`) di toolbar (`?type_id=?`). Toolbar TANPA label — langsung `var-select` (placeholder Tahun / Pilih jenis). **Export Excel** = tombol 📊 Export Excel di toolbar → GET `laporan.matrix.export` (permission `master.read|report.read`) → `maatwebsite/excel` → `App\\Exports\\MatrixExport` (styling header bold + border + bg status, landscape A4 fit-to-width, TANPA judul, 1 sheet per tahun). Filename: `Matriks_Uji_{typeName}-{year}.xlsx`. **Natural sort**: `usort` di `buildMatrixData` — split by digits, compare numeric segments numerically (W.FL.1 < W.FL.2 < W.FL.3 < W.FL.10 < W.FL.20).
- AppLayout top bar minimal: judul halaman + back (sub-page) + search icon (list) + plus hijau (create) + logout icon `power`. Bottom nav & FAB HANYA di halaman dashboard. **Dashboard appbar = nama user yang login** (bukan brand).
- Role `user` di dashboard hanya tampil card `Laporan`; card lain disembunyi walau permission masih ada.
- Pending-role (`/pending-role`): user baru pilih role `super_admin`/`admin`/`inspector`/`user` + factory. Footer login: "Departemen QA".
