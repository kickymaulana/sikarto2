# PRD — SI KARTO
**Sistem Kalibrasi Toleransi Operasional Alat Ukur Rutin Bulanan**

Versi: 1.0 | Status: Draft | Tech Stack: Laravel 13, Inertia 3, Vue 3, TypeScript, Varlet UI

---

## 1. Executive Summary & Objectives

### 1.1 Latar Belakang
Proses pengecekan dan kalibrasi rutin bulanan alat ukur di pabrik saat ini masih manual berbasis formulir kertas. Risiko: alat terlewat jadwal, data sulit ditelusuri saat audit, perhitungan koreksi dan toleransi rawan salah hitung manual.

### 1.2 Tujuan
1. Digitalisasi paperless proses pengecekan/kalibrasi alat ukur bulanan.
2. Memastikan seluruh alat ukur di semua departemen dan pabrik teruji tepat waktu (scheduling otomatis).
3. Otomatisasi perhitungan koreksi dan penentuan status kelayakan alat (OK/NG) berdasarkan acceptable limit.
4. Menyediakan rekapitulasi data untuk audit ISO/internal (export Excel/PDF).

### 1.3 Scope
- **In scope:** master data CRUD, entry pengujian, dashboard & scheduling, laporan.
- **Out of scope (fase awal):** integrasi perangkat timbangan otomatis, e-signature, workflow approval berjenjang.

### 1.4 Definisi Istilah
| Istilah | Arti |
|---|---|
| Penunjukan | Nilai yang ditunjukkan alat ukur saat diuji |
| Standar | Nilai acuan dari template pengujian (contoh: 500 gr) |
| Correction | Penunjukan − Standar |
| Acceptable Limit | Batas toleransi koreksi (±5 gr) |
| OK otomatis | Rata-rata koreksi seluruh titik dalam toleransi inklusif |
| NG otomatis | Rata-rata koreksi seluruh titik di luar toleransi |
| Status efektif | Status pilihan jika ada; selain itu status otomatis |
| Grup titik uji | Kumpulan titik per kapasitas dengan nama, media referensi, dan urutan |

---

## 2. User Roles & Permissions

Manajemen role & permission memakai **`spatie/laravel-permission`**. Roles: `super_admin`, `admin`, `inspector`, `user`.

| Fitur | super_admin | admin | inspector | user |
|---|---|---|---|---|
| Login/Logout | ✅ | ✅ | ✅ | ✅ |
| CRUD semua master (Factory–Alat Ukur) | ✅ | ✅ | ✅ | — |
| Kelola Pengguna | ✅ | — | — | — |
| Entry Pengujian | ✅ | — | ✅ | — |
| Lihat Riwayat Pengujian | ✅ | ✅ | ✅ | ✅ |
| Dashboard & Scheduling | ✅ | ✅ | ✅ | ✅ |
| Laporan & Export Excel/PDF | ✅ | ✅ | — | — |
| Lihat Laporan (read-only) | ✅ | ✅ | — | ✅

**Aturan:**
- `inspector` input pengujian + CRUD master data, tidak bisa kelola user/laporan.
- `admin` bisa CRUD master + lihat laporan/export, tidak bisa entry pengujian dan tidak bisa kelola user.
- `user` read-only: lihat dashboard, riwayat, dan laporan (tanpa export).
- `super_admin` full control: master, pengguna, pengujian, laporan.
- Semua perubahan master data dan pengujian tercatat (audit log: user, timestamp).
- Alat ukur bisa dinonaktifkan (soft delete) tanpa menghapus riwayat.

### Permission (Spatie)
| Permission | super_admin | admin | inspector | user |
|---|---|---|---|---|
| `master.create` | ✅ | ✅ | ✅ | — |
| `master.read` | ✅ | ✅ | ✅ | ✅ |
| `master.update` | ✅ | ✅ | ✅ | — |
| `master.delete` | ✅ | ✅ | ✅ | — |
| `user.manage` | ✅ | — | — | — |
| `test.create` | ✅ | — | ✅ | — |
| `test.read` | ✅ | ✅ | ✅ | ✅ |
| `report.read` | ✅ | ✅ | — | ✅ |
| `report.export` | ✅ | ✅ | — | — |

---

## 3. Database Schema & Data Requirements

### 3.1 Entitas & Relasi

```
users (id, name, email, password, factory_id*, ...)
  └─ role via spatie (bukan kolom role)

roles (id, name, guard_name)
permissions (id, name, guard_name)
model_has_roles, model_has_permissions, role_has_permissions  (tabel pivot spatie)

factories (id, code, name)
  └─ contoh: KIM, Dalu 1, Dalu 2

departments (id, factory_id → factories, code, name)
  └─ contoh: Filling, Packing, Maintenance

instrument_types (id, name)
  └─ contoh: Timbangan, Caliper, Rol Siku, Thermo Hunter, Stopwatch, Laser

brands (id, name)
  └─ contoh: DICSON, Mitutoyo

capacities (id, name, value, unit)
  └─ contoh: 3 KG, 500 KG, 150 MM

specifications (id, name)
  └─ contoh: Kapasitas 3 kg, Ketelitian 0.1, Bahan stainless

acceptable_limits (id, name, min_correction, max_correction, unit)
  └─ contoh: ±5 gr → min=-5, max=5, unit=gr; ±2 kg → min=-2000, max=2000, unit=gr

instruments (id, code [unique], factory_id, department_id, instrument_type_id,
            brand_id, capacity_id, acceptable_limit_id, specification_id* [opsional],
            is_active, notes)
  └─ contoh code: W.FL.5

standard_groups (id, capacity_id → capacities, name, reference_media, sort_order)
  └─ grup pengujian PER KAPASITAS, bukan per jenis alat

standard_templates (id, standard_group_id → standard_groups, standard_value, sort_order)
  └─ titik standar per grup; urutan grup/titik berdasarkan sort_order, lalu id

calibration_tests (id, instrument_id, test_date, next_test_date,
                   tester_id → users, status [OK|NG|SPARE|NA|SERVICE],
                   computed_status*, selected_status*, avg_correction*,
                   min_correction_snapshot*, max_correction_snapshot*, notes)

calibration_test_items (id, calibration_test_id, standard_value,
                        reading_value, correction, is_within_limit,
                        group_order*, group_name*, reference_media*, unit*, point_order*)
  └─ * nullable; metadata histori lama tidak direkonstruksi dari master
```

### 3.2 Aturan Data
- `instruments.code` UNIQUE — kode alat (contoh `W.FL.5`), validasi duplikat.
- `acceptable_limits` pakai `min_correction`/`max_correction` numerik + `unit` (bukan string "±5 gr" mentah) agar validasi OK/NG bisa dihitung mesin.
- Form Kapasitas mengelola `groups`: nama, media referensi, dan titik standar. Kapasitas boleh tanpa grup; setiap grup wajib minimal satu titik. ID existing dipertahankan, ownership diverifikasi, tambah/ubah/hapus dilakukan atomik. Urutan mengikuti posisi array.
- Semua standar, penunjukan, koreksi dan toleransi satu alat memakai satuan toleransi. Unit kapasitas boleh berbeda (misalnya kapasitas kg, titik/toleransi gr); tidak ada konversi unit otomatis.
- Semua tabel master + `instruments` soft delete.
- `calibration_tests` & items immutable setelah disimpan (revisi hanya oleh pemegang hak + tercatat audit).

### 3.3 CRUD Matrix
| Master | Create | Read | Update | Delete |
|---|---|---|---|---|
| Factory | ✅ | ✅ | ✅ | ✅ (jika tak dipakai alat) |
| Departemen | ✅ | ✅ | ✅ | ✅ (jika tak dipakai alat) |
| Jenis Alat | ✅ | ✅ | ✅ | ✅ (jika tak dipakai alat) |
| Merk | ✅ | ✅ | ✅ | ✅ |
| Kapasitas | ✅ | ✅ | ✅ | ✅ |
| Spesifikasi | ✅ | ✅ | ✅ | ✅ |
| Acceptable Limit | ✅ | ✅ | ✅ | ✅ |
| Alat Ukur | ✅ | ✅ | ✅ | Soft delete |

---

## 4. Functional Requirements & User Flow

### FR-1 Dashboard & Scheduling
- **FR-1.1** Statistik ringkas: total alat, alat perlu uji bulan ini, alat terlambat (overdue), rasio OK/NG bulan berjalan.
- **FR-1.2** Matriks/kalender status uji per alat per bulan (Jan–Des) — sekarang adalah **halaman sendiri di `/masters/matrix`** (bukan section di dashboard). Warna status: hijau=OK, merah=NG, biru=SPARE, abu-abu=NA, oranye=SERVICE, putih=belum ada uji. Tiap bulan punya 2 kolom (Uji = test_date, Next = next_test_date) berisi tanggal saja (misal `21`) atau `—`. Kolom info instrumen (Kode Alat, Merk, Kapasitas, Lokasi) sticky kiri.
- **FR-1.3** Daftar alat yang jatuh tempo bulan ini (berdasarkan `next_test_date`), satu klik → langsung ke form entry.
- **FR-1.4** Filter dashboard per factory & departemen.

**Flow:** Login → Dashboard → klik "Alat Perlu Uji" → masuk form pengujian. Matriks dilihat di `/masters/matrix`.

### FR-2 Form Entry Pengujian (mobile/web friendly)
- **FR-2.1** Input/pilih `Kode Alat` → sistem auto-fill: Factory, Departemen, Jenis, Merk, Kapasitas, Toleransi.
- **FR-2.2** Grup dari `instrument.capacity.groups.standards`, accordion berisi nama, media referensi, progres dan titik standar. Ganti alat mereset input/status; urutan mengikuti master.
- **FR-2.3** QC input **Penunjukan** per titik. Nol valid; kosong bukan nol.
- **FR-2.4** Koreksi real-time dan rata-rata sementara per grup (titik valid saja). Rata-rata global hanya tersedia setelah seluruh titik valid; dihitung dari seluruh titik, bukan rata-rata antargrup.
- **FR-2.5** Save atomik: test + items snapshot, `next_test_date = test_date + 1 bulan` untuk semua status.
- **FR-2.6** Otomatis/OK/NG wajib seluruh titik tepat satu kali dan toleransi valid. Pilihan status menggantikan otomatis; SPARE/NA/SERVICE mengabaikan items tanpa validasi pengukuran atau penyimpanan items. Tidak ada konfirmasi NG tambahan.
- **FR-2.7** Detail menampilkan status otomatis/pilihan/efektif, toleransi snapshot, grup dan rata-rata grup dari koreksi snapshot. Histori lama ditandai metadata tidak tersedia; tidak memakai master terkini untuk rekonstruksi.

**Flow:**
1. Pilih/scan kode alat → autofill data alat.
2. Sistem render tabel titik standar.
3. QC isi penunjukan → koreksi & status terhitung otomatis.
4. Simpan → status alat ditentukan → jadwal bulan depan dibuat.

### FR-3 Master Data
- **FR-3.1** CRUD terpisah per master (Factory, Departemen, Jenis Alat, Merk, Kapasitas, Spesifikasi, Acceptable Limit, Alat Ukur).
- **FR-3.2** Setiap form master memakai dropdown referensi master lain (konsistensi input).
- **FR-3.3** Form alat ukur: pilih Factory → pilih Departemen (difilter per factory) → Jenis → Merk → Kapasitas → Toleransi → Spesifikasi (opsional) → isi Kode Alat unik.
- **FR-3.4** Form Acceptable Limit: input nama (contoh: "±5 gr"), nilai min, nilai max, unit.
- **FR-3.5** Pencarian, paginasi, sorting di semua list.

### FR-4 Laporan & Rekapitulasi
- **FR-4.1** Rekap pengujian per periode (bulan/tahun), filter factory/departemen/jenis/status.
- **FR-4.2** Riwayat kalibrasi per alat (untuk audit ISO).
- **FR-4.3** Export Excel & PDF dari tiap laporan.
- **FR-4.4** Header laporan: nama pabrik, periode, kolom tanda tangan QC & Supervisor (kosong untuk cetak).

---

## 5. Business Logic & Validation Rules

### 5.1 Rumus Koreksi
```
Correction = Penunjukan − Standar
```
- `Penunjukan` = nilai input QC; `Standar` = nilai dari template.
- Nilai numerik desimal; unit pengukuran mengikuti toleransi alat, tanpa konversi otomatis dari unit kapasitas.

### 5.2 Status, Rata-rata dan Snapshot
```
is_within_limit = (min_correction ≤ correction ≤ max_correction)
avg_correction = round(sum(koreksi seluruh titik) / jumlah seluruh titik, 4)
computed_status = OK jika min_correction ≤ avg_correction ≤ max_correction; selain itu NG
status = selected_status ?? computed_status
```
- Pembulatan 4 desimal half-away-from-zero. Rata-rata global berbobot jumlah titik, BUKAN rata-rata dari rata-rata grup. Indikator tiap titik bukan penentu status global.
- Pilihan OK/NG tetap menghitung dan menyimpan pengukuran lengkap, meskipun berbeda dari status otomatis. NG tetap tersimpan.
- SPARE/NA/SERVICE bypass: items diabaikan walau invalid, tidak disimpan; `avg_correction` dan `computed_status` NULL. Min/max toleransi disalin jika tersedia; satuan snapshot berada pada items sehingga tidak tersedia pada bypass.
- Server mengambil standar/toleransi dari master, menghitung ulang koreksi/status, lalu menyimpan test + items dalam satu transaksi.
- Snapshot mencakup standar, penunjukan, koreksi, indikator titik, nama/urutan grup, media referensi, satuan toleransi, urutan titik, min/max toleransi, serta status otomatis/pilihan/efektif. Detail/rata-rata grup memakai snapshot; identitas alat tetap relasi hidup termasuk alat soft-deleted.
- Migrasi memindahkan standar lama ke satu grup `Penimbangan` / `Anak Timbangan` per kapasitas tanpa mengubah ID/nilai/urutan titik. Nama/media alat non-timbangan perlu disesuaikan. Null/orphan `capacity_id` ditolak sebelum mutasi.
- Histori lama mempertahankan hasil lama dan metadata snapshot baru NULL; tidak diisi dari master sekarang. Rollback menghapus metadata grup/snapshot, tidak lossless.
- SQLite diverifikasi di memory. MySQL/MariaDB wajib staging, backup dan maintenance: DDL implicit commit bisa meninggalkan migrasi parsial. Foreign key grup dilepas sebelum perubahan nullability lalu dipasang kembali. Deployment pending: `php artisan migrate --force`; jangan jalankan pada DB aplikasi saat review.

### 5.3 Scheduling
- `next_test_date = test_date + 1 bulan` (tanggal uji aktual, bukan bulan kalender).
- Alat masuk "jatuh tempo" bila `next_test_date ≤ hari ini` dan belum ada uji baru.
- Matriks bulanan = status dari `calibration_tests` terakhir per alat.

### 5.4 Validasi Input
| Aturan | Ketentuan |
|---|---|
| Kode Alat | Wajib, unik, format bebas (contoh W.FL.5) |
| Penunjukan | Otomatis/OK/NG: seluruh titik kapasitas tepat satu kali, tanpa duplikat/titik asing/tambahan; nol valid, kosong invalid. SPARE/NA/SERVICE bypass pengukuran |
| Angka pengukuran | Standar/penunjukan/toleransi maksimal 4 desimal, rentang ±99999999.9999; koreksi overflow ditolak |
| Toleransi | Otomatis/OK/NG wajib min/max valid, min ≤ max, satuan tersedia |
| Tanggal uji | Tidak boleh di masa depan; boleh backdate (uji susulan) |
| Master referensi | Tidak bisa dihapus jika masih dipakai (foreign key) |
| Dropdown | Nilai selalu dari master, tidak ada free-text |

---

## 6. Non-Functional Requirements

### 6.1 UI/UX
- SPA dengan Inertia **v3** + Vue 3 + TypeScript; komponen UI memakai **Varlet UI** (mobile-first, cocok dipakai QC via HP di lapangan).
- Bahasa Indonesia seluruh antarmuka.
- Loading state, empty state, error state pada setiap halaman.
- Pesan validasi inline.

#### Design System
Referensi desain: aplikasi **SUKIRMAN** (`D:\Apache24\htdocs\sukirman`). Adopsi pola "android-layout" mobile-first + Varlet MD3 light.

**Tema:**
- Primary: **orange** `#FB8C00` (ORANGE_600) — app bar, tombol utama, ikon.
- Accent fill: **peach** `#FDF0EA` — input & kartu.
- Background: `#f8fafc`; kartu putih radius 16, border `#f1f5f9`, shadow halus.
- Status chip semantic: hijau OK, merah NG, kuning/amber overdue, `var-chip` round.
- Font: **Inter** (`@fontsource/inter` 400–800), fallback Roboto.

**Framework setup:**
- `StyleProvider(Themes.md3Light)` dari `@varlet/ui`.
- `@varlet/touch-emulator` (kontrol layar sentuh).
- Transisi antar halaman: slide (translateX 30px → 0) via `Transition`.
- Bottom navigation `var-bottom-navigation` + FAB.

**Layout ("android-layout"):**
- Container: `100vh` flex column, `overflow: hidden`.
- Top bar: putih sticky, avatar + greeting + nama user.
- Content: `flex:1` scroll, padding 16–20px, bottom padding ±80px (ruang bottom nav).
- Bottom nav: Beranda / Pengujian / Laporan (sesuai permission) + FAB untuk entry pengujian.

**Komponen:**
- Welcome card: gradient orange → deep orange, radius 20, teks putih.
- Stat grid: 2 kolom, ikon pastel + angka tebal.
- Feature/quick-link card: bg pastel + border berwarna + ikon + chevron.
- Request-card list: kode alat monospace bold + chip status + meta (tanggal/tester).
- Master CRUD: **halaman terpisah** `/masters/{entity}/create` & `/{entity}/{id}/edit` (bukan inline form/dialog). List = `var-table` + klik-barris → halaman edit; tombol Edit/Hapus tidak di list, Hapus di halaman edit. Contoh: `Masters/Form.vue`, `Instruments/Form.vue`.
- **Master menu** `/masters`: redirect ke `/masters/{entity}`. Halaman `Masters/Index.vue` = **sidebar kiri TreeMenu** (sticky, tetap saat pindah entity) + `var-table` (data entity) + `var-pagination`. Toggle sidebar di mobile. `/masters` default entity = `factories`.
- List master & alat ukur = android pattern: **icon search di app bar** → toggle kotak pencarian (shared `searchState` via `composables/search.ts`), `var-pull-refresh` + `var-list` infinite scroll (load-more via `router.get` + `only: [...]` + merge props).
- **AppBar satu baris**: judul halaman (dari `pageTitle` map di `AppLayout.vue`) + back (sub-page) + icon search (list) + icon `power` logout. Bottom nav & FAB hanya di halaman dashboard. **Dashboard appbar = nama user yang login** (bukan brand). AppBar sticky lengket ke browser (`html, body { margin:0 }` di `app.css`).
- **Semua URL di JS wajib Ziggy `route()`** — app di subfolder `/sikarto/public`; URL hardcoded → 404. Link eksternal SSO pakai `<a :href="route('sso.redirect')">`.
- **Satu warna orange**: appbar gradient `#fb8c00→#f57c00` (teks/ikon putih) = FAB = tombol primary. `StyleProvider` override `'color-primary': '#fb8c00'` di `app.ts` (wajib — `Themes.md3Light` default primary ungu `#6750A4`). Search mode appbar putih.
- **Tombol Tambah = FAB** (bukan icon app bar) di semua CRUD list — AppLayout `showAdd` untuk master/instruments, FAB sendiri di `Tests/Index.vue`.
- Tombol: pill (border-radius penuh), gradient orange untuk tombol utama.
- Login: bg `#f8fafc`, ikon/logo, judul bold orange, benefit box, tombol pill gradient, footer.

**Varlet Usage Rules (wajib — cek `node_modules/@varlet/ui/types/` sebelum pakai komponen):**
- `var-form` pakai prop `:onsubmit="fn"` (handler terima `valid: boolean`, guard `if (!valid) return`). JANGAN `@submit.prevent` → crash `e.preventDefault is not a function`.
- `InputType` HANYA `text|password|number|tel|email`. Textarea = `:textarea="true"`. Tanggal = native `<input type="date">`.
- `var-avatar`: tidak ada `text-color`. `var-input`: tidak ada `focus`, pakai `autofocus`.
- **`var-input` & `var-select` TIDAK punya prop `label`** — label wajib manual via `<label class="field-label">` di dalam `field-block` (lihat `Masters/Form.vue`, `Instruments/Form.vue`).
- **Icon font kustom** (bukan MDI): daftar ikon di `node_modules/@varlet/ui/es/icon/icon.css`. `logout`, `pencil`, `gauge`, `close` dll TIDAK ADA — aksi pakai tombol teks.
- **Konfirmasi wajib pakai `Dialog` bawaan Varlet** (import `{ Dialog } from '@varlet/ui'`) — JANGAN `confirm()`. Tombol dialog bahasa Indonesia: `confirmButtonText: 'Ya, Hapus'`, `cancelButtonText: 'Batal'`. Contoh: `Masters/Form.vue`, `Instruments/Form.vue`.
- Cek komponen lain via `node_modules/@varlet/ui/types/<component>.d.ts` (registry MCP parsial, tidak mencakup semua komponen).



### 6.2 Security
- **Autentikasi wajib SSO** (OAuth2 Authorization Code) — BUKAN email/password. SSO server `sekali_login` lokal (`http://localhost/sekali_login/public`), prod: `sekalilogin.gotechdynamics.com`. Client SI KARTO terdaftar (client_id `299dec87-8cd6-4882-b525-faa5ae86d853`, redirect `http://localhost/sikarto/public/callback`). Panduan: `public/SSO-Integrasi-Laravel-Manual-Provisioning.md`.
- **Manual Provisioning**: user dicocokkan via `nik` (unique). NIK baru → auto-create `is_approved=false` → `/pending-role` (pilih role `super_admin`/`admin`/`inspector`/`user` + factory) → Admin setujui di `/users` (assign Spatie role + `is_approved=true`). User belum disetujui TIDAK bisa login.
- Autentikasi Laravel (session-based), CSRF aktif.
- Otorisasi berbasis role & permission via **`spatie/laravel-permission`** (middleware/policy) — tiap endpoint cek permission.
- Validasi input server-side (bukan hanya client) — jangan percaya data dari browser.
- Jangan expose data yang tidak perlu via Inertia shared props.
- Log aktivitas penting (create/update/delete master & pengujian).
- Env & secret via `.env`, tidak pernah di commit.

### 6.3 Performance
- Paginasi semua list master & riwayat.
- Eager loading relasi pada query dashboard/report.
- Index pada kolom: `instruments.code`, `calibration_tests.next_test_date`, `calibration_tests.instrument_id`, foreign key.
- Rendering matriks Jan–Des: batch query status terakhir per alat, bukan query per-alat.
- Export Excel/PDF di queue bila data besar (fase lanjut).

### 6.4 Tech Stack & Setup
| Lapisan | Teknologi |
|---|---|
| Backend | Laravel **13** (terbaru), PHP |
| Frontend | Inertia **3**, Vue 3 Composition API, TypeScript |
| UI Library | **Varlet UI** + `@varlet/import-resolver` |
| Routing JS | **`tightenco/ziggy`** |
| Otorisasi | **`spatie/laravel-permission`** |
| Build | Vite |
| Database | MySQL/MariaDB |
| Report | Excel (maatwebsite/excel atau sejenisnya) + PDF |

**Setup:** `laravel new` **tanpa starter kit** → pasang Inertia 3 server + client (`@inertiajs/vue3`), Vue 3 + TS, Varlet + import resolver, Ziggy, Spatie.

### 6.5 Deliverables Awal (Fase 1)
1. Setup Laravel 13 + Inertia 3 + Vue 3 + TS + Varlet + Ziggy + Spatie.
2. Migrasi & seeder seluruh master + roles/permissions + user admin awal.
3. Halaman login + otorisasi role/permission.
4. CRUD 7 master data.
5. Form entry pengujian (autofill + hitung otomatis + OK/NG + next date).
6. Dashboard & matriks scheduling.

#### 6.5.1 Matriks Uji Bulanan (`/masters/matrix`)
- Halaman sendiri (bukan section di Dashboard). Toolbar: dropdown **Tahun** + dropdown **Jenis Alat** (default = `Timbangan Digital`, TANPA label) + legenda + tombol **📊 Export Excel**.
- Tabel 12 bulan × 2 kolom (Uji/Next). Cell isi: `21` (tanggal saja) atau `—` jika kosong, background warna status halus. 4 kolom info instrumen (Kode Alat, Merk, Kapasitas, Lokasi) sticky kiri, **natural sort** ascending by `code`.
- Backend: `MasterController::matrix()`. Filter `?year=&type_id=`. Default `type_id` = `Timbangan Digital` id.
- **Export Excel**: tombol → GET `masters.matrix.export` (permission `master.read`) → `maatwebsite/excel` → `App\Exports\MatrixExport` (header bold + border + bg status, landscape A4 fit-to-width, TANPA judul, 1 sheet per tahun). Filename: `Matriks_Uji_{typeName}-{year}.xlsx`.
- **Natural sort**: `usort` di `buildMatrixData` — split by digits, compare numeric segments numerically (W.FL.1 < W.FL.2 < W.FL.10 < W.FL.20).
7. Laporan + export Excel/PDF.
