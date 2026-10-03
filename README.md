# Alfatech Office

Sistem operasional internal **sekaligus** website company profile publik untuk perusahaan jasa pengembangan software. Satu aplikasi, dua wajah:

- **Portal internal** (butuh login) — manajemen proyek, CRM leads, kalender konten media sosial, keuangan, portofolio, profil perusahaan, dan log aktivitas.
- **Website publik** (tanpa login) — halaman profil perusahaan dengan layanan, portofolio, FAQ, dan formulir konsultasi yang langsung masuk ke pipeline CRM sebagai lead baru.

Aplikasi ini **multi-tenant**: satu instalasi bisa melayani banyak tim/perusahaan, dan seluruh data dipisahkan per tim.

---

## Daftar Isi

- [Teknologi](#teknologi)
- [Persyaratan](#persyaratan)
- [Instalasi](#instalasi)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Cara Mengakses](#cara-mengakses)
- [Akun Demo](#akun-demo)
- [Multi-Tenancy & Hak Akses](#multi-tenancy--hak-akses)
- [Perintah Penting](#perintah-penting)
- [Struktur Proyek](#struktur-proyek)
- [Alur Kerja Pengembangan](#alur-kerja-pengembangan)
- [Catatan & Keterbatasan](#catatan--keterbatasan)

---

## Teknologi

| Lapisan          | Teknologi                                                   |
| ---------------- | ----------------------------------------------------------- |
| Backend          | Laravel 13, PHP 8.3+                                        |
| Frontend         | Vue 3.5 (`<script setup lang="ts">`), TypeScript, Inertia 3 |
| Styling          | Tailwind CSS v4                                             |
| Build tool       | Vite 8 (`vite-plus`)                                        |
| Database         | SQLite                                                      |
| Autentikasi      | Laravel Fortify (login, 2FA, passkey)                       |
| Routing frontend | Wayfinder (helper rute di-generate otomatis)                |
| Komponen UI      | reka-ui / shadcn-vue                                        |
| Testing          | PHPUnit + Larastan (PHPStan level 7)                        |

Tidak ada Pinia. State global memakai composable + `provide`/`inject`.

---

## Persyaratan

Pastikan sudah terpasang:

- **PHP 8.3+**
- **Composer 2**
- **Node.js 20+** dan **npm** (proyek ini memakai **npm**, bukan pnpm/yarn)
- **Git**

Ekstensi PHP yang dibutuhkan: `pdo_sqlite` (paling sering terlewat), `mbstring`, `openssl`, `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `json`, `session`, `tokenizer`, `xml`. Sebagian besar sudah aktif secara bawaan; yang biasanya perlu diaktifkan manual di `php.ini` adalah `pdo_sqlite` dan `mbstring`.

Cek cepat:

```bash
php -v
composer -V
node -v
npm -v

# pastikan SQLite & mbstring tersedia (keduanya harus tercetak)
php -m | findstr /I "pdo_sqlite mbstring"     # Windows
php -m | grep -iE "pdo_sqlite|mbstring"       # macOS / Linux
```

---

## Instalasi

### 1. Clone repository

```bash
git clone <URL_REPOSITORY> compro
cd compro
```

> Ganti `<URL_REPOSITORY>` dengan URL repo GitHub-mu, mis. `https://github.com/username/compro.git`.

### 2. Install dependensi PHP

```bash
composer install
```

### 3. Siapkan file environment

```bash
cp .env.example .env      # macOS / Linux
```

```powershell
Copy-Item .env.example .env   # Windows PowerShell
```

Lalu generate application key:

```bash
php artisan key:generate
```

Buka `.env` bila perlu menyesuaikan nama aplikasi, URL, atau konfigurasi mail.

### 4. Buat file database SQLite

File database **tidak ikut di repository** (`database/.gitignore` mengecualikan `*.sqlite*`), jadi harus dibuat manual:

```bash
touch database/database.sqlite      # macOS / Linux
```

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force   # Windows PowerShell
```

### 5. Migrasi database + isi data contoh

```bash
php artisan migrate:fresh --seed
```

Perintah ini membuat seluruh tabel dan mengisi **data demo** (1 tim "PT Alfatech Digital Solutions", 6 anggota, 6 proyek, 9 lead, 4 konten, 9 transaksi, portofolio, profil perusahaan, dan log aktivitas).

### 6. Install dependensi frontend & build aset

```bash
npm install
npm run build
```

> `npm run build` wajib dijalankan sebelum `php artisan test`, karena test yang me-render halaman Inertia membutuhkan Vite manifest di `public/build`.

### Shortcut: `composer setup`

Proyek ini menyediakan skrip gabungan untuk langkah 2–3 dan 6:

```bash
composer setup
```

Perintah itu menjalankan: `composer install` → salin `.env` → `key:generate` → `migrate --force` → `npm install` → `npm run build`.

⚠️ **Dua hal yang tidak dilakukan `composer setup`:** membuat file `database/database.sqlite` dan mengisi data contoh. Jadi tetap lakukan langkah 4, lalu `php artisan migrate:fresh --seed`.

---

## Menjalankan Aplikasi

### Cara cepat (satu perintah)

```bash
composer dev
```

Perintah ini menjalankan tiga proses sekaligus dengan `concurrently`:

| Proses                     | Keterangan                                                   |
| -------------------------- | ------------------------------------------------------------ |
| `php artisan serve`        | server Laravel di `http://localhost:8000`                    |
| `php artisan queue:listen` | worker antrian                                               |
| `npm run dev`              | Vite dev server (hot reload, termasuk SSR saat pengembangan) |

### Cara manual (dua terminal)

**Terminal 1:**

```bash
php artisan serve
```

**Terminal 2:**

```bash
npm run dev
```

Untuk melihat versi produksi, gunakan `npm run build` dan akses lewat server Laravel — aset akan dilayani dari `public/build`.

---

## Cara Mengakses

Setelah aplikasi berjalan di `http://localhost:8000`:

| URL                                        | Untuk                                |
| ------------------------------------------ | ------------------------------------ |
| `http://localhost:8000/`                   | Halaman sambutan (Welcome)           |
| `http://localhost:8000/login`              | Login portal internal                |
| `http://localhost:8000/register`           | Registrasi akun baru                 |
| `http://localhost:8000/alfatech/dashboard` | Dashboard portal internal            |
| `http://localhost:8000/p/alfatech`         | **Website publik** profil perusahaan |
| `http://localhost:8000/settings/profile`   | Pengaturan profil akun               |

### URL portal internal selalu berawalan slug tim

Semua halaman internal mengikuti pola **`/{slug-tim}/...`**, contohnya:

```
/alfatech/dashboard
/alfatech/projects
/alfatech/leads
/alfatech/contents
/alfatech/transactions
/alfatech/portfolio
/alfatech/company-profile
/alfatech/activity-logs
/alfatech/settings
```

`alfatech` di situ adalah **slug tim**, bukan nama folder. Kalau kamu membuat tim baru, URL-nya ikut berubah sesuai slug tim tersebut.

Tombol **"Buka Website Publik"** di halaman Profil Perusahaan juga langsung mengarah ke `/p/{slug-tim}`.

---

## Akun Demo

Hasil `php artisan migrate:fresh --seed` membuat satu tim dengan enam anggota. **Semua akun memakai password: `password`**

| Email                       | Jabatan                    | Peran di Tim            |
| --------------------------- | -------------------------- | ----------------------- |
| `digitalalfatech@gmail.com` | CEO & Founder              | **Owner** (akses penuh) |
| `aditya@alfatech.id`        | Technical Lead             | Member                  |
| `maya@alfatech.id`          | UI/UX & FE Specialist      | Member                  |
| `budi@alfatech.id`          | Senior Fullstack Dev       | Member                  |
| `sarah@alfatech.id`         | Backend & Business Analyst | Member                  |
| `reza@alfatech.id`          | Mobile Developer           | Member                  |

> ⚠️ **Hanya untuk pengembangan lokal.** Akun demo ini berasal dari seeder — jangan pernah dipakai di produksi. Lihat [`database/seeders/AlfatechDemoSeeder.php`](database/seeders/AlfatechDemoSeeder.php).

Gunakan akun **Member** untuk menguji tampilan dengan hak akses terbatas: beberapa menu (Leads/CRM, Keuangan, Log Aktivitas) memang sengaja disembunyikan.

---

## Multi-Tenancy & Hak Akses

Seluruh data domain memiliki kolom `team_id`, dan setiap query wajib ter-scope ke tim yang sedang aktif. Model memakai trait `App\Concerns\BelongsToTeam` dengan query scope `forTeam()`.

**Peran tim** (`App\Enums\TeamRole`):

| Peran    | Akses                                                     |
| -------- | --------------------------------------------------------- |
| `Owner`  | Semua permission, termasuk menghapus tim                  |
| `Admin`  | Semua modul domain + kelola anggota (tanpa menghapus tim) |
| `Member` | Proyek, Konten Medsos, Profil Perusahaan, Portofolio      |

**Permission** (`App\Enums\TeamPermission`) memakai format `modul:aksi`, mis. `project:manage`, `lead:manage`, `finance:manage`, `activity-log:view`.

Di sisi frontend, permission dibagikan lewat prop `teamPermissions` dan dibaca dengan:

```ts
const { can } = usePermission();

if (can('canManageLeads')) {
    // ...
}
```

Cara ini dipakai menu Sidebar untuk menyembunyikan item yang tidak boleh diakses.

---

## Perintah Penting

### Pengembangan

| Perintah                           | Fungsi                                   |
| ---------------------------------- | ---------------------------------------- |
| `composer dev`                     | Jalankan server + queue + Vite sekaligus |
| `php artisan serve`                | Server Laravel saja                      |
| `npm run dev`                      | Vite dev server saja (hot reload)        |
| `npm run build`                    | Build aset produksi → `public/build`     |
| `php artisan migrate:fresh --seed` | Reset database + isi data demo           |

### Kualitas kode & test

| Perintah                             | Fungsi                                                 |
| ------------------------------------ | ------------------------------------------------------ |
| `composer test`                      | **Rangkaian lengkap**: pint --test → phpstan → phpunit |
| `php artisan test`                   | Test saja                                              |
| `php artisan test --filter=NamaTest` | Jalankan satu test tertentu                            |
| `vendor/bin/phpstan analyse`         | Analisis statis, level 7                               |
| `vendor/bin/pint`                    | Rapikan format kode                                    |
| `vendor/bin/pint --test`             | Cek format tanpa mengubah file                         |
| `npm run types:check`                | Cek tipe TypeScript (`vue-tsc`)                        |
| `npm run check`                      | Lint + format frontend                                 |

> **Jalankan `npm run build` sebelum `php artisan test`** bila `public/build` belum ada, agar test yang me-render halaman Inertia tidak gagal.

---

## Struktur Proyek

```
app/
├── Concerns/           Trait lintas model (BelongsToTeam, AuthorizesTeamModule)
├── Enums/              Enum domain (status, permission, role, kategori)
├── Http/
│   ├── Controllers/    Controller per modul
│   ├── Middleware/     Termasuk HandleInertiaRequests
│   ├── Requests/       Validasi + otorisasi (base: TeamRequest)
│   └── Resources/      Pemetaan snake_case → camelCase untuk frontend
├── Models/             Model Eloquent (+ trait BelongsToTeam)
├── Observers/          ActivityObserver — mencatat log aktivitas otomatis
└── Support/            Helper (CurrentTeam, TeamMembers)

database/
├── factories/          Factory untuk data uji
├── migrations/         Skema tabel
└── seeders/            AlfatechDemoSeeder — data demo

docs/
└── IMPLEMENTATION_PLAN.md   Dokumen rencana & keputusan arsitektur

resources/js/
├── components/         Komponen bersama (Sidebar, TopNavbar, Modal, dll.)
├── composables/        usePermission, useAppearance, useToast
├── layouts/            AdminLayout (shell portal internal) & layout starter kit
├── pages/
│   ├── admin/          Halaman portal internal
│   ├── public/         Website publik
│   ├── auth/           Halaman autentikasi
│   └── settings/       Pengaturan akun
├── routes/             Helper rute Wayfinder (di-generate, jangan diedit)
├── types/              Tipe TypeScript
└── utils/              formatters (rupiah, tanggal, badge status)
```

---

## Alur Kerja Pengembangan

1. **Baca dulu** [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md). Dokumen itu adalah sumber kebenaran tunggal untuk proyek ini — berisi keputusan arsitektur (ADR), peta model data, progres pengerjaan, dan catatan jebakan yang sudah pernah ditemui.
2. Jalankan `npm run dev` **dan** `php artisan serve` saat mengerjakan UI. `npm run dev` juga merender halaman di server (SSR), sehingga error SSR muncul di terminal — type-check saja tidak cukup untuk menangkap masalah UI.
3. Sebelum commit, jalankan `composer test` (sudah mencakup pint, phpstan, dan phpunit).
4. File di `resources/js/routes/` dan `resources/js/actions/` **di-generate Wayfinder** — jangan diedit manual; keduanya juga diabaikan git.

---

## Catatan & Keterbatasan

- **SSR produksi belum diaktifkan.** `config/inertia.php` sudah `ssr.enabled => true`, tetapi bundle `bootstrap/ssr` belum dibuild dan server SSR belum berjalan — sehingga Inertia _fallback_ diam-diam ke rendering di klien. Aplikasi tetap berjalan normal; yang terdampak hanya SEO halaman publik. Langkah mengaktifkannya ada di [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) §13. Saat pengembangan (`npm run dev`), SSR **sudah** aktif lewat plugin Vite.
- **Halaman `/settings/profile`, `/settings/security`, `/settings/teams`, dan `/settings/appearance`** masih memakai layout bawaan starter kit, sehingga sidebar-nya berbeda dari sidebar utama aplikasi. Ini keputusan yang disengaja (ADR-16).
- **Data uang disimpan sebagai integer rupiah** (SQLite tidak punya tipe desimal). Formatting hanya dilakukan di frontend lewat `resources/js/utils/formatters.ts`.
- **Tidak ada real-time push.** Perubahan data diterapkan lewat kunjungan Inertia biasa, bukan WebSocket (ADR-07).

---

## Lisensi

Proyek ini dibangun di atas [Laravel](https://laravel.com) dan [Laravel Vue Starter Kit](https://github.com/laravel/vue-starter-kit), keduanya berlisensi MIT.
