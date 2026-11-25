# Lumina API

Backend untuk aplikasi Flutter **Lumina** (`../lumina_bank`). Dibangun dengan Laravel 13, Sanctum (token), dan MySQL (MAMP).

## Instalasi dari hasil clone

1. Nyalakan MAMP (Apache + MySQL), lalu buat database **`lumina`** (dan **`lumina_test`** untuk tes), mis. lewat phpMyAdmin.
2. Di folder proyek (Command Prompt):

   ```bat
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   php artisan filament:assets
   ```

`.env.example` sudah memakai MySQL MAMP (`root` / `root`, database `lumina`). Jika password MySQL Anda berbeda, ubah `DB_PASSWORD` di `.env`. Error `could not find driver` berarti `.env` masih memakai `DB_CONNECTION=sqlite`; error `Unknown database 'lumina'` berarti database belum dibuat.

## Menjalankan

API dilayani langsung oleh **Apache MAMP** di <http://localhost/lumina>, seperti web app lain di `htdocs`. Selama MAMP menyala (Apache + MySQL), API ikut hidup; tidak perlu menjalankan perintah apa pun.

- Cek di browser: <http://localhost/lumina/api/health>
- `D:\MAMP\htdocs\lumina` adalah *junction* (tautan folder) ke `lumina_api\public`, jadi kode tetap di folder proyek ini dan perubahan langsung berlaku. Hanya folder `public` yang terekspos; `.env` dan kode lain tidak bisa diakses dari browser.
- Membuat ulang junction bila terhapus (Command Prompt):

  ```bat
  mklink /J "D:\MAMP\htdocs\lumina" "D:\WORK\PROJECT\My BECA\lumina_api\public"
  ```

- Setelah mengubah `.env`, jalankan `php artisan config:clear`.

Struktur ini sama dengan deploy di hosting cPanel: isi folder `public` ditaruh di `public_html` (atau subfolder), sisanya di luar folder web.

Prasyarat: MySQL MAMP di `127.0.0.1:3306` (user `root` / `root`) dan database `lumina` sudah ada.

Mereset database ke data demo:

```bat
php artisan migrate:fresh --seed
```

## Akun demo

| Nama | User ID | PIN | No. rekening | Saldo awal |
|---|---|---|---|---|
| Andra Saputra | `lumina` | `123456` | 8820031475 | Rp 12.750.000 |
| Bunga Lestari | `bunga` | `654321` | 8820049912 | Rp 3.200.000 |

Transfer ke bank `Lumina` dengan nomor rekening yang terdaftar langsung menambah saldo rekening tujuan. Transfer ke bank lain hanya memotong saldo pengirim.

## Panel admin (kelola data)

Buka <http://localhost/lumina/admin>. Login: `admin@lumina.test` / `admin12345` (ganti sebelum dipakai di luar laptop).

Dibangun dengan Filament 5, bahasa Indonesia.

Menu di sidebar: **Dashboard**, **Nasabah**, dan **Banner**. Semua data dibuka per nasabah: pilih nasabah di daftar, lalu di halaman detailnya ada header (nama, User ID, email, no. rekening, jenis, saldo) dan tab di bawahnya, jadi data antar nasabah tidak bercampur.

| Bagian | Isi |
|---|---|
| Dashboard | Jumlah nasabah, total saldo, transaksi hari ini |
| Nasabah (daftar) | Cari, tambah, dan ubah nasabah; reset PIN. Membuat nasabah sekaligus membuat rekening dan setoran awal. Klik nasabah untuk membuka detailnya. |
| Tab Rekening | Rekening dan saldo nasabah. Saldo **tidak bisa diketik langsung**; ubah lewat aksi **Setor / Tarik**, yang selalu mencatat mutasi dan menolak saldo minus. Saldo di header ikut diperbarui. |
| Tab Mutasi | Transaksi nasabah itu, dengan filter arah, kategori, dan tanggal. **Hanya baca**: koreksi dilakukan dengan transaksi baru. |
| Tab e-Statement | Rekening koran PDF per periode (bulan/tahun). **Sumber**: *Buat dari mutasi* (PDF dibuat otomatis, bisa banyak halaman) atau *Unggah file PDF* (lampiran wajib, maks. 10 MB). Saat mengunggah PDF ada centang **Isi mutasi otomatis dari PDF ini**: baris transaksi di PDF dibaca (`app/Support/StatementParser.php`) dan ditambahkan ke tab Mutasi; hanya periode yang dipilih, mutasi yang sudah ada dilewati, saldo tidak berubah. PDF hasil scan/gambar tidak bisa dibaca. Satu PDF per periode; periode masa depan ditolak. Bisa dilihat, diganti/diperbarui, atau dihapus. File disimpan privat di `storage/app/private/statements`. |
| Tab Penerima tersimpan | Daftar penerima transfer cepat nasabah. |
| Banner | Banner promo aplikasi untuk **halaman login** (sebelum masuk, 1200×500 px) dan **beranda** (1200×240 px). Unggah gambar, tautan opsional, periode tayang opsional, aktif/nonaktif, urutan diatur dengan seret. Aplikasi mengambilnya dari `GET /api/banners?placement=welcome|home` (publik); bila kosong, aplikasi memakai banner bawaan. Banner contoh: `php artisan db:seed --class=BannerSeeder`. |
| Identitas Aplikasi | **Nama aplikasi** (mis. "Lumina"), **nama perusahaan**, dan **logo** (opsional, persegi 512×512 px, maks. 1 MB). Dipakai aplikasi mobile (judul, halaman login, beranda, "… ID", "Kartu …", dsb.), nama panel admin, dan e-statement PDF. Nama aplikasi juga menjadi **nama bank internal** untuk transfer sesama; saat diganti, penerima tersimpan ikut berganti. Satu kode bisa dipakai banyak perusahaan: tiap server punya identitasnya sendiri, dan aplikasi mengikuti server yang dituju. Teks di dalam gambar banner tidak ikut berganti; ganti lewat menu Banner. |

- Hanya user dengan `is_admin = true` yang bisa masuk panel. Akun admin tidak bisa login di aplikasi, dan nasabah tidak bisa masuk panel.
- Semua perubahan saldo dari panel lewat `app/Services/LedgerService.php`.

## Test

```bat
php artisan test
```

Test memakai database terpisah `lumina_test` (lihat `phpunit.xml`), jadi data di `lumina` tidak ikut terhapus. Database itu harus sudah dibuat di MySQL.

## Endpoint

Semua endpoint berawalan `/api`. Selain login dan health, wajib header `Authorization: Bearer <token>`.

| Method | Path | Keterangan |
|---|---|---|
| GET | `/health` | Cek server hidup |
| POST | `/auth/login` | `{username, pin}` → `{token, user}`. Dibatasi 10x/menit |
| GET | `/app-config` | Publik. Identitas aplikasi `{data: {name, company, logo}}`; `logo` path relatif atau null |
| GET | `/app-config/logo` | Publik. Gambar logo (404 bila belum diatur) |
| POST | `/auth/logout` | Mencabut token |
| GET | `/accounts/me` | `{data: {name, number, type, balance}}` |
| GET | `/payees` | Daftar penerima tersimpan |
| GET | `/transactions` | Mutasi terbaru (maks. 100) |
| GET | `/statements` | Daftar e-statement milik nasabah (terbaru dulu) |
| GET | `/statements/{id}/file` | PDF e-statement; milik orang lain → 404 |
| POST | `/transfers` | `{payee_name, bank, number, amount, note?, pin}` → 201 + transaksi |

Error dikembalikan sebagai `{message: "..."}`: `401` (belum login / PIN login salah), `422` (PIN transfer salah, saldo tidak cukup, data tidak valid), `429` (terlalu banyak percobaan).

## Menghubungkan aplikasi Flutter

Alamat server diatur **dari dalam aplikasi**: layar Login → ikon server (kanan atas) atau teks "Server: ..." di bawah. Isi IP saja (mis. `192.168.1.5`); path `/lumina/api` ditambahkan otomatis. Tekan **Tes koneksi**, lalu **Simpan**. Pengaturan tersimpan di HP.

- **Emulator Android:** default `http://10.0.2.2/lumina/api` (alamat laptop dari dalam emulator).
- **HP fisik:** HP dan laptop di Wi-Fi yang sama; isi IP laptop (cek `ipconfig`). Izinkan Apache (port 80) di Windows Firewall bila diminta.
- **Hosting:** isi alamat lengkap, mis. `https://domain-anda.id/api`.
- Default saat build tetap bisa diganti: `flutter run --dart-define=API_BASE_URL=http://192.168.x.x/lumina/api`.
- **Tanpa server:** `flutter run --dart-define=USE_MOCK_API=true` memakai data tiruan di memori.

Login aplikasi: **User ID + PIN** nasabah (dibuat admin di panel). User ID terakhir diingat; "Ganti akun" untuk nasabah lain.

**Bahasa:** aplikasi mengikuti bahasa HP: Indonesia bila HP berbahasa Indonesia, selain itu English (default). Aplikasi mengirim header `Accept-Language`, dan pesan error API ikut berbahasa sama (terjemahan Inggris di `lang/en.json`, middleware `SetLocaleFromHeader`). Tanpa header, API memakai `APP_LOCALE` (id).

## Catatan keamanan

Setup ini untuk pengembangan lokal. Sebelum dipakai di luar laptop: pakai HTTPS dan hapus `usesCleartextTraffic` di `AndroidManifest.xml`, set `APP_DEBUG=false`, dan ganti password MySQL `root`.

## Struktur penting

- `routes/api.php`: daftar route
- `app/Http/Controllers/Api/`: `AuthController`, `AccountController`, `TransferController`
- `app/Http/Resources/`: bentuk JSON (sama dengan model di Flutter)
- `database/migrations/`, `database/seeders/DatabaseSeeder.php`: skema dan data demo
