# Proposal Aplikasi SI KARTO

**Sistem Kalibrasi Toleransi Operasional Alat Ukur Rutin Bulanan**

Diajukan untuk: Manajemen / Atasan
Penyusun: Tim SI KARTO
Tanggal: Agustus 2026

---

## 1. Ringkasan Eksekutif

Kami mengusulkan pembangunan aplikasi **SI KARTO** untuk digitalisasi proses kalibrasi alat ukur bulanan di pabrik. Aplikasi ini direncanakan menggantikan formulir kertas dengan sistem terkomputerisasi yang menghitung kelayakan alat secara otomatis, mengatur jadwal uji bulanan, dan menyimpan seluruh riwayat pengujian untuk kebutuhan audit. Dengan adanya sistem ini, risiko alat ukur tidak akurat dapat terdeteksi lebih dini sehingga kualitas produk terjaga.

## 2. Latar Belakang & Masalah

Proses pengecekan dan kalibrasi alat ukur rutin bulanan saat ini masih dilakukan secara manual menggunakan formulir kertas. Kondisi ini menimbulkan beberapa risiko:

- **Alat bisa terlewat jadwal** — tidak ada pengingat otomatis kapan alat harus diuji ulang.
- **Perhitungan rawan salah** — koreksi (penunjukan − standar) dan batas toleransi dihitung manual, berisiko salah hitung.
- **Sulit ditelusuri saat audit** — riwayat kalibrasi tersebar di formulir kertas, lambat dan tidak praktis saat audit ISO/internal.
- **Tidak ada kontrol terpusat** — data alat di berbagai pabrik/departemen tidak terpantau dalam satu tempat.

## 3. Tujuan Usulan

1. **Paperless** — menghilangkan formulir kertas dalam proses kalibrasi.
2. **Tepat waktu** — memastikan seluruh alat ukur di setiap pabrik dan departemen teruji sesuai jadwal.
3. **Akurat** — perhitungan koreksi dan status kelayakan alat (PASS/FAIL) otomatis berdasarkan toleransi.
4. **Siap audit** — riwayat pengujian tersimpan rapi dan mudah diekspor menjadi laporan.

## 4. Manfaat yang Diharapkan

| Manfaat | Penjelasan |
|---|---|
| Hemat waktu & biaya | Tidak perlu cetak formulir, input data lebih cepat, hasil langsung tersimpan. |
| Akurasi terjamin | Perhitungan koreksi & toleransi otomatis oleh sistem, mengurangi kesalahan manusia. |
| Kontrol alat terpusat | Semua alat dari seluruh pabrik/departemen terpantau di satu aplikasi. |
| Pencegahan risiko | Alat yang melewati toleransi langsung ditandai, penggantian/perbaikan lebih cepat. |
| Kesiapan audit | Riwayat kalibrasi per alat dan rekap per periode siap diakses kapan saja. |

## 5. Rencana Fitur

**a. Master Data**
Data referensi dikelola terpusat agar input konsisten: Pabrik (Factory), Departemen, Jenis Alat, Merk, Kapasitas, Toleransi (Acceptable Limit), dan Alat Ukur. Setiap alat memiliki kode unik (contoh: `W.FL.5`).

**b. Entry Pengujian**
QA cukup memilih kode alat, sistem otomatis menampilkan data alat dan titik uji standar (misal 500 gr, 700 gr, 800 gr). QA mengisi nilai penunjukan, sistem langsung menghitung koreksi dan menentukan status **PASS/FAIL** per titik serta keseluruhan alat.

**c. Dashboard & Jadwal Bulanan**
Tampilan ringkas berisi alat yang jatuh tempo bulan ini, alat yang terlambat, dan matriks status uji 12 bulan per alat. Jadwal uji berikutnya dibuat otomatis (+1 bulan dari tanggal uji).

**d. Riwayat & Laporan**
Seluruh hasil pengujian tersimpan. Laporan dapat difilter per periode, pabrik, dan status untuk keperluan audit ISO/internal.

## 6. Rencana Pengguna & Hak Akses

| Peran | Hak Akses |
|---|---|
| **Admin Master** | Kelola semua data master, kelola pengguna, entry pengujian, lihat laporan. |
| **Admin** | Lihat dashboard & laporan, export data (read-only). |
| **Inspector/QA** | Entry hasil pengujian, lihat riwayat pengujian. |

Login direncanakan menggunakan **SSO perusahaan** (satu akun untuk semua aplikasi). Akun baru yang login pertama kali akan **disetujui terlebih dahulu oleh Admin** sebelum dapat menggunakan aplikasi (konsep Manual Provisioning) — menjamin keamanan dan kontrol akses.

## 7. Alur Kerja yang Direncanakan

1. QA/Inspector masuk via SSO perusahaan.
2. Pilih alat ukur yang akan dikalibrasi → sistem menampilkan data & titik uji standar.
3. QA mengisi nilai penunjukan hasil pengukuran.
4. Sistem menghitung koreksi & menentukan status **PASS** atau **FAIL** secara otomatis.
5. Hasil tersimpan + jadwal uji bulan berikutnya dibuat otomatis.
6. Atasan/Admin memantau jadwal & melihat laporan kapan saja.

## 8. Rencana Tahapan Pekerjaan

1. **Perancangan** — penyusunan kebutuhan & desain sistem.
2. **Pengembangan** — pembangunan fitur-fitur sesuai rencana.
3. **Pengujian** — uji coba menyeluruh bersama pengguna.
4. **Sosialisasi & pelatihan** — pengenalan aplikasi kepada pengguna (QA, Admin).
5. **Deployment & pendampingan** — pemasangan resmi dan pendampingan penggunaan awal.

## 9. Penutup

Dengan adanya aplikasi SI KARTO, kami meyakini proses kalibrasi alat ukur bulanan dapat berjalan lebih tertib, akurat, dan siap menghadapi audit. Kami memohon dukungan dan persetujuan untuk merealisasikan usulan ini.

---

*Proposal ini disusun sebagai rencana awal dan dapat disesuaikan dengan kebutuhan.*
