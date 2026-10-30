# Integrasi SSO Perusahaan — Manual Provisioning

Dokumentasi ini menjelaskan cara menghubungkan aplikasi Anda ke **Single Sign-On (SSO) perusahaan** menggunakan **OAuth 2.0 (Authorization Code Grant)** dengan konsep **Manual Provisioning** (direkomendasikan).

---

## 1. Ringkasan

| Item | Keterangan |
|---|---|
| SSO Server | `https://sekalilogin.gotechdynamics.com` |
| Protokol | OAuth 2.0 — **Authorization Code Grant** |
| Framework | Laravel Passport v13 |
| Identitas utama | **NIK** (Nomor Induk Karyawan), dilengkapi `name` & `email` |
| Mode akun | **Manual Provisioning** (tidak ada auto-provisioning/SCIM) |

Fitur yang disediakan SSO:
- Login terpusat (sekali login untuk semua aplikasi perusahaan).
- Mengembalikan data identitas user: **NIK, nama, email**.
- Memberikan akses berbasis token (Access Token + Refresh Token).

SSO **tidak** mengatur role/peran aplikasi Anda, dan **tidak otomatis membuat akun** di aplikasi Anda. Hal ini dipegang oleh aplikasi Anda sendiri (lihat konsep Manual Provisioning di bawah).

---

## 2. Konsep Manual Provisioning (PENTING — baca dulu)

Manual Provisioning berarti:

> **SSO hanya berperan sebagai autentikasi (membuktikan "siapa" user). Akun di aplikasi Anda sepenuhnya dikelola oleh aplikasi Anda.**

Cara kerjanya sederhana:

1. User login lewat SSO → SSO mengembalikan `nik` user.
2. Aplikasi Anda **mencocokkan NIK** dengan user lokal:
   - Jika **NIK sudah ada** di aplikasi → langsung login sebagai user tersebut.
   - Jika **NIK belum ada** → aplikasi yang memutuskan:
     - tolak akses, atau
     - buat akun (biasanya perlu **aktivasi oleh admin aplikasi**)—tidak otomatis.

Contoh nyata: aplikasi **SUKIRMAN** memakai pola ini — user baru yang login SSO dengan NIK yang belum dikenal akan melewati proses aktivasi oleh admin aplikasi sebelum bisa dipakai.

### Kenapa Manual Provisioning disarankan?
- **Aman & terkontrol** — akun di aplikasi Anda hanya diberikan oleh orang/administrator aplikasi Anda, bukan otomatis dari luar.
- **Mudah diimplementasikan** — cukup satu baris pencarian by NIK.
- **Hak akses tepat** — SSO tidak perlu tahu role aplikasi Anda (Manager, Operator, dsb.) yang diatur sendiri oleh aplikasi Anda.

> ⚠️ Ini **bukan** auto-provisioning (seperti SCIM). Jika suatu hari dibutuhkan sinkronisasi otomatis user dari sistem HR, itu ditangani terpisah oleh aplikasi Anda (misalnya via CSV/masterdata), bukan oleh SSO.

---

## 3. Prasyarat

Aplikasi Anda harus:
- Berbasis **web / dapat mengarahkan browser** (untuk Authorization Code Grant).
- Diakses via **HTTPS** (SSO akan membandingkan Redirect URI secara ketat).
- Punya **callback endpoint** (URL yang menerima redirect setelah login SSO).

---

## 4. Mendaftarkan Client (Client ID & Secret)

1. Login sebagai **Admin** di SSO.
2. Buka menu **OAuth Clients**.
3. Klik **Buat Client**, isi:
   - **Nama Aplikasi** — misal `Portal HRD`, `SUKIRMAN`, dsb.
   - **Redirect URI** — URL callback aplikasi Anda, contoh `https://app-anda.com/auth/callback`. **(Harus persis, termasuk port jika lokal.)**
4. Simpan → SSO memberikan:
   - **Client ID** (format UUID, misal `589e2a98-...`)
   - **Client Secret** (40 karakter) — **hanya ditampilkan sekali**. Jika hilang, gunakan fitur **Regenerate Secret**.

Simpan keduanya di `.env` aplikasi Anda dan **jangan pernah dibagikan / dimasukkan ke kode frontend**.

---

## 5. Alur Integrasi (Authorization Code Grant)

```
[User]  →  [Aplikasi Anda]  ──redirect──▶  [SSO /authorize]
                                              (user login SSO + approve)
                                              ◀──redirect balik──
        [Aplikasi Anda]  ◀──?code=...───
        POST /oauth/token (tukar code → access_token)
        GET  /api/user (Bearer) → { id, nik, name, email }
        Pencocokan Manual Provisioning (by NIK)
```

Detail langkah:

1. **Arahkan user ke halaman authorize:**
   ```
   https://sekalilogin.gotechdynamics.com/oauth/authorize
       ?client_id={CLIENT_ID}
       &redirect_uri={REDIRECT_URI}
       &response_type=code
       &scope=
   ```
2. User login di SSO (jika belum) dan **menyetujui (approve)** pemberian akses.
3. SSO mengarahkan kembali ke `redirect_uri` Anda dengan `?code={AUTH_CODE}`.
4. Aplikasi menukar code:
   ```
   POST https://sekalilogin.gotechdynamics.com/oauth/token
   Content-Type: application/x-www-form-urlencoded

   grant_type=authorization_code
   &client_id={CLIENT_ID}
   &client_secret={CLIENT_SECRET}
   &redirect_uri={REDIRECT_URI}
   &code={AUTH_CODE}
   ```
   → respons berisi `access_token`, `refresh_token`, `expires_in`.
5. Ambil data user:
   ```
   GET https://sekalilogin.gotechdynamics.com/api/user
   Authorization: Bearer {ACCESS_TOKEN}
   ```
   → mengembalikan JSON:
   ```json
   { "id": 1, "nik": "KD220004", "name": "Ricky Yacob", "email": "ricky@company.com" }
   ```
6. **Manual Provisioning:** cocokkan `nik` ke user lokal aplikasi Anda (lihat contoh di bawah).
7. Saat `access_token` kedaluwarsa, gunakan `refresh_token`:
   ```
   POST https://sekalilogin.gotechdynamics.com/oauth/token
   grant_type=refresh_token
   &client_id={CLIENT_ID}
   &client_secret={CLIENT_SECRET}
   &refresh_token={REFRESH_TOKEN}
   ```

---

## 6. Endpoint SSO

| Keterangan | Method & URL |
|---|---|
| Halaman authorize (login SSO) | `GET https://sekalilogin.gotechdynamics.com/oauth/authorize` |
| Tukar code → token | `POST https://sekalilogin.gotechdynamics.com/oauth/token` |
| Ambil data user | `GET https://sekalilogin.gotechdynamics.com/api/user` (Bearer) |
| Refresh token | `POST https://sekalilogin.gotechdynamics.com/oauth/token` |

`/api/user` hanya berisi: `id`, `nik`, `name`, `email`.

---

## 7. Contoh Implementasi (PHP / Laravel)

Contoh di bawah meniru pola yang dipakai **SUKIRMAN** (sudah berjalan di produksi).

### Konfigurasi `.env`
```env
SSO_BASE_URL=https://sekalilogin.gotechdynamics.com
SSO_CLIENT_ID=589e2a98-78e6-4109-8f7c-6059e677d3a3
SSO_CLIENT_SECRET=rb0H...
SSO_REDIRECT_URI=https://app-anda.com/auth/callback
```

### a. Redirect ke SSO
```php
public function redirectSso()
{
    $query = http_build_query([
        'client_id'     => config('services.sso.client_id'),
        'redirect_uri'  => config('services.sso.redirect_uri'),
        'response_type' => 'code',
        'scope'         => '',
    ]);
    return redirect(config('services.sso.base_url') . '/oauth/authorize?' . $query);
}
```

### b. Callback — tukar code & ambil user
```php
public function callbackSso(Request $request)
{
    $code = $request->query('code');
    if (!$code) {
        return redirect()->route('login')->withErrors(['message' => 'Gagal autentikasi SSO']);
    }

    // 1. Tukar code -> access_token
    $tokenRes = Http::asForm()->post(config('services.sso.base_url') . '/oauth/token', [
        'grant_type'    => 'authorization_code',
        'client_id'     => config('services.sso.client_id'),
        'client_secret' => config('services.sso.client_secret'),
        'redirect_uri'  => config('services.sso.redirect_uri'),
        'code'          => $code,
    ]);

    if ($tokenRes->failed()) {
        return redirect()->route('login')->withErrors(['message' => 'Gagal mendapatkan token SSO']);
    }

    $accessToken = $tokenRes->json('access_token');

    // 2. Ambil identitas user
    $userRes = Http::withToken($accessToken)->get(config('services.sso.base_url') . '/api/user');
    if ($userRes->failed()) {
        return redirect()->route('login')->withErrors(['message' => 'Gagal mengambil data user']);
    }

    $ssoUser = $userRes->json();
    $nik = $ssoUser['nik'] ?? null;
    if (!$nik) {
        return redirect()->route('login')->withErrors(['message' => 'Data NIK tidak ditemukan']);
    }

    // 3. MANUAL PROVISIONING — cocokkan NIK ke user lokal
    $user = User::where('nik', $nik)->first();

    if (!$user) {
        // Kebijakan aplikasi:
        return redirect()->route('login')
            ->withErrors(['message' => 'NIK tidak terdaftar di aplikasi ini. Hubungi admin.']);
        // ATAU buat user baru (perlu aktivasi admin):
        // $user = User::create([
        //     'nik'         => $nik,
        //     'name'        => $ssoUser['name']  ?? 'User ' . $nik,
        //     'email'       => $ssoUser['email'] ?? $nik . '@sso',
        //     'password'    => Hash::make(Str::random(32)),
        //     'is_approved' => false, // tunggu aktivasi admin aplikasi
        // ]);
    }

    if (!$user->is_approved) {
        return redirect()->route('login')->withErrors(['message' => 'Akun Anda belum diaktifkan admin aplikasi']);
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
}
```

> Catatan: jika tidak memakai `config/services.sso.*`, Anda bisa langsung membaca dari `env('SSO_BASE_URL')` dsb.

### c. Contoh dengan cURL (untuk pengujian)
```bash
# 1. Buka di browser:
# https://sekalilogin.gotechdynamics.com/oauth/authorize?client_id=... &redirect_uri=... &response_type=code&scope=

# 2. Tukar code:
curl -X POST "https://sekalilogin.gotechdynamics.com/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code&client_id=...&client_secret=...&redirect_uri=...&code=..."

# 3. Ambil user:
curl "https://sekalilogin.gotechdynamics.com/api/user" \
  -H "Authorization: Bearer {ACCESS_TOKEN}"
```

---

## 8. Keamanan & Best Practice

- **Client Secret** hanya di sisi server. **Jangan** taruh di JavaScript / frontend / repository publik.
- **Redirect URI** harus sesuai persis (termasuk `https`, host, port, path). SSO akan menolak jika tidak cocok.
- Simpan `access_token`/`refresh_token` secara aman (session server / storage terenkripsi), bukan di localStorage bila memungkinkan.
- Gunakan **NIK** sebagai kunci pencocokan akun (unik di seluruh entitas perusahaan).
- Refresh token juga rahasia — jangan bocorkan ke client-side.
- Logout SSO tidak wajib; aplikasi boleh logout sendiri (hapus session lokal). Untuk logout SSO menyeluruh, diskusikan dengan tim SSO.

---

## 9. FAQ / Troubleshooting

| Masalah | Penyebab & Solusi |
|---|---|
| `invalid_client` saat `/oauth/token` | `client_id`/`client_secret` salah. Cek `.env`, atau Regenerate Secret di panel SSO. |
| `invalid_grant` saat tukar code | Code sudah dipakai/kedaluwarsa. Kode hanya berlaku sekali — pastikan alur callback tidak dipanggil dua kali. |
| `redirect_uri_mismatch` | Redirect URI saat request tidak sama persis dengan yang didaftarkan di SSO. |
| `401 Unauthorized` di `/api/user` | `access_token` salah/kedaluwarsa. Ambil user segera setelah token didapat, atau refresh token. |
| NIK tidak dikenal di aplikasi saya | Normal (Manual Provisioning). Buat user lokal & aktivasi oleh admin aplikasi, atau ikuti kebijakan tolak akses. |
| Secret lupa | Tidak bisa dilihat ulang — gunakan **Regenerate Secret** di halaman Client SSO. |

---

### Akhir dokumen
Untuk pertanyaan teknis lanjutan, hubungi Tim IT (pengelola SSO).