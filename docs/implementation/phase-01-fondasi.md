# Fase 1 — Fondasi & Titik Aman

> **Status:** RENCANA — belum dimulai.
> **Ketergantungan:** tidak ada. **Fase ini wajib dikerjakan lebih dulu.**
> **Menambah fitur produk?** ❌ Tidak. Fase ini murni arsitektur, keamanan, dan dokumentasi.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ⚠️ **Dokumen ini adalah _living document_.** Agen/developer yang mengerjakan fase ini **wajib** memperbarui bagian [Discovered During Implementation](#discovered-during-implementation), [Decisions Made During Implementation](#decisions-made-during-implementation), [Technical Debt](#technical-debt), dan [Post-Implementation Notes](#post-implementation-notes) setiap kali menemukan hal baru.

---

## Objective

Menutup celah keamanan dan pengetahuan yang membuat aplikasi berisiko saat berevolusi menjadi SaaS, **tanpa** mengubah perilaku produk yang sudah berjalan.

Fase ini menjawab tiga pertanyaan:

1. Apakah isolasi tenant saat ini benar-benar aman? (belum dapat dibuktikan secara otomatis)
2. Apakah jalur aman untuk upload file, resolusi tenant, dan SEO sudah ada? (belum ada)
3. Apakah keputusan arsitektur sudah tercatat dan dapat ditemukan? (dokumen lama hilang)

---

## Scope

### Termasuk

- Pemulihan & penomoran ulang dokumentasi arsitektur (dokumen ini + master plan).
- Test isolasi tenant otomatis (regresi kebocoran data).
- Penetapan konvensi storage tenant-aware + seam (tanpa fitur upload).
- Persiapan konfigurasi produksi (tanpa deploy).
- Fondasi SEO: build SSR + meta dasar.
- Pembersihan inkonsistensi dokumentasi.

### Tidak termasuk

- Global scope isolasi tenant → **Fase 2**.
- `TenantContext` service → **Fase 2**.
- Tenant settings, audit platform → **Fase 2**.
- Abstraksi resolver host/domain → **Fase 3**.
- Entitlement/plan → **Fase 4**.
- Fitur apa pun dari ide Basic/Plus/Pro → **Fase 5 (deferred)**, kecuali lima integrasi yang disetujui gelombang 1 (lihat `implementation-schedule.md`).

---

## Prerequisites

| Prasyarat                                                                 | Status                                                                                                                |
| ------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| D-01 (penanganan referensi ADR/PDR lama) diputuskan                       | ❌ Belum — **wajib** sebelum menyentuh komentar kode                                                                  |
| D-02 (model tenancy: shared DB) disetujui                                 | ❌ Belum                                                                                                              |
| D-03 (penerapan global scope) diketahui arahnya                           | ❌ Belum — hanya perlu _arah_, karena implementasinya di Fase 2                                                       |
| D-09 (infrastruktur produksi) minimal arahnya jelas                       | ⚠️ Sebagian — tugas konfigurasi dikerjakan tanpa memilih engine (temuan 2026-10-14); engine final tetap menunggu D-09 |
| `npm install` & `composer install` berhasil di mesin developer            | ✅ Diasumsikan                                                                                                        |
| `database/database.sqlite` sudah dibuat + `migrate:fresh --seed` berjalan | ✅ Diasumsikan                                                                                                        |

> **Catatan:** beberapa tugas di fase ini **tidak** bergantung pada keputusan yang belum tuntas (mis. test isolasi, konvensi storage). Tugas yang bergantung keputusan harus ditandai dan boleh ditunda sampai keputusan keluar.

---

## Current State

Ringkasan kondisi saat fase ini direncanakan (detail di [master plan §2–§5](../IMPLEMENTATION_PLAN.md#2-penilaian-arsitektur-saat-ini-current)):

> **Diperbarui 2026-10-14.** Butir yang sudah berubah dari kondisi awal ditandai _(kini)_.

- Multi-tenancy **sudah berjalan** lewat `team_id` + trait `BelongsToTeam` + middleware `EnsureTeamMembership`.
- Isolasi tenant bergantung pada **scoping manual** — tidak ada global scope. Risiko tertinggi (RF-1).
- `docs/IMPLEMENTATION_PLAN.md` **hilang**; ±50 komentar kode menunjuk ke sana.
- Storage **kosong** — belum ada satu pun upload; `photo_path`, `media_url`, `image_url` hanya string.
- SSR **aktif di config tetapi bundle tidak dibuild** (`bootstrap/ssr` tidak ada) → SEO publik lemah.
- SQLite dipakai; cache/queue/session di database; mail `log`. _(kini)_ cache, session, dan queue memakai Redis; SQLite tinggal untuk lokal & test, dan `ProductionConfigServiceProvider` menolak boot produksi di atasnya.
- Test per modul sudah ada (`tests/Feature/Domain/*`), termasuk `AuthorizationTest`, tetapi **belum ada** test isolasi lintas-tenant.

---

## Tasks

Checklist tugas. Kolom **Bergantung** menandakan keputusan yang harus ada sebelum tugas dapat dikerjakan.

### 1.1 Dokumentasi & Jejak Keputusan

| #     | Tugas                                                                             | Bergantung | Catatan                                                                                         |
| ----- | --------------------------------------------------------------------------------- | ---------- | ----------------------------------------------------------------------------------------------- |
| 1.1.1 | Buat `docs/IMPLEMENTATION_PLAN.md` (master, penomoran baru)                       | —          | Sudah dikerjakan pada tahap perencanaan                                                         |
| 1.1.2 | Buat 5 dokumen fase di `docs/implementation/`                                     | —          | Sudah dikerjakan pada tahap perencanaan                                                         |
| 1.1.3 | Putuskan penanganan referensi lama (perbarui komentar vs tabel pemetaan permanen) | **D-01**   | Lampiran C master plan                                                                          |
| 1.1.4 | Terapkan hasil 1.1.3 pada berkas di Lampiran D                                    | 1.1.3      | ±45 berkas                                                                                      |
| 1.1.5 | Perbarui `README.md` (inkonsistensi E-1, E-2, E-3)                                | —          | Lihat [Lampiran E](../IMPLEMENTATION_PLAN.md#lampiran-e--temuan-inkonsistensi-dokumentasi-lain) |
| 1.1.6 | Pastikan `docs/` ikut terlacak version control                                    | —          | RSK-02                                                                                          |

### 1.2 Test Isolasi Tenant (Prioritas Utama)

| #     | Tugas                                                                                | Bergantung | Catatan                                                                                   |
| ----- | ------------------------------------------------------------------------------------ | ---------- | ----------------------------------------------------------------------------------------- |
| 1.2.1 | Buat helper test dua tenant dengan data "mirip" (nama sama, hanya `team_id` berbeda) | —          | Mis. `tests/Feature/Domain/TenantIsolationTestCase.php` atau trait                        |
| 1.2.2 | Test: `index` setiap modul hanya mengembalikan data tenant aktif                     | —          | projects, tasks, leads, contents, transactions, portfolio, activity-logs, company-profile |
| 1.2.3 | Test: `update`/`delete` terhadap ID milik tenant lain → 404/403                      | —          | Menutup IDOR (R-4)                                                                        |
| 1.2.4 | Test: user non-anggota tidak bisa membuka `{current_team}/dashboard` → 403           | —          | Menutup celah keanggotaan                                                                 |
| 1.2.5 | Test: halaman publik tenant B tidak membocorkan data tenant A                        | —          | `CurrentTeam::activate()` + global scope di `PublicCompanyProfileController`              |
| 1.2.6 | Test: form konsultasi tenant B tidak menulis lead ke tenant A                        | —          | `PublicLeadController`                                                                    |
| 1.2.7 | Jadikan kelompok test ini **wajib di CI**                                            | —          | Jangan pernah di-_skip_                                                                   |

> **Penting:** test ini adalah jaring pengaman untuk **semua** fase berikutnya. Bila Fase 2 memasang global scope, test ini yang membuktikan scaffold-nya bekerja.

### 1.3 Storage Seam (tanpa fitur upload)

| #     | Tugas                                                                                 | Bergantung       | Catatan                              |
| ----- | ------------------------------------------------------------------------------------- | ---------------- | ------------------------------------ |
| 1.3.1 | Tetapkan bentuk seam: disk khusus tenant di `config/filesystems.php`                  | D-07 (arah saja) | Medan: `tenants`                     |
| 1.3.2 | Buat helper `App\Support\TenantStorage` yang otomatis memberi prefix `tenants/{id}/`  | 1.3.1            | Belum ada UI, belum ada upload       |
| 1.3.3 | Test helper: path selalu berawalan `tenants/{id}/`; helper menolak path keluar prefix | 1.3.2            | Mencegah path traversal sejak awal   |
| 1.3.4 | Dokumentasikan konvensi path + aturan validasi upload untuk pemakaian mendatang       | —                | ADR-05                               |
| 1.3.5 | Pastikan helper **tidak** bergantung pada `storage_path()` mentah                     | 1.3.2            | Agar migrasi ke object storage mulus |

> **Jangan** membuat: tabel media, UI upload, thumbnail, atau penghapusan file otomatis. Belum ada fitur yang membutuhkannya.

### 1.4 Konfigurasi & Environment

| #     | Tugas                                                                            | Bergantung | Catatan                                               |
| ----- | -------------------------------------------------------------------------------- | ---------- | ----------------------------------------------------- |
| 1.4.1 | Rapikan `.env.example` sebagai acuan nilai yang benar untuk produksi             | D-09       | Jangan sertakan secret nyata                          |
| 1.4.2 | Tambahkan kunci config `tenancy.*` (`base_domain`, dsb.) dengan default `null`   | —          | Tanpa perilaku yang bergantung padanya                |
| 1.4.3 | Pastikan `APP_ENV=production` tidak memakai SQLite (guard/validasi)              | D-09, D-13 | Bisa lewat validasi boot atau dokumentasi operasional |
| 1.4.4 | Tinjau `.github/` dan tetapkan CI: pint + phpstan + phpunit                      | —          | Test isolasi (1.2.7) masuk ke sini                    |
| 1.4.5 | Pastikan CI menjalankan migrasi pada MySQL **dan** Postgres (bukan hanya SQLite) | D-09       | Deteksi dini perbedaan perilaku (RSK-06)              |

### 1.5 Fondasi SEO

| #     | Tugas                                                                 | Bergantung | Catatan                        |
| ----- | --------------------------------------------------------------------- | ---------- | ------------------------------ |
| 1.5.1 | Build SSR: `npm run build:ssr` → pastikan `bootstrap/ssr` terbentuk   | D-16       | ADR-16                         |
| 1.5.2 | Siapkan cara menjalankan proses SSR (dokumentasi/langkah operasional) | D-09       | Jangan menyentuh fitur         |
| 1.5.3 | Meta dasar halaman publik dari data profil tenant (title/description) | —          | Data sudah tersedia            |
| 1.5.4 | Verifikasi output SSR benar-benar berisi HTML halaman publik          | 1.5.1      | Bandingkan dengan render klien |

> Detail SEO lanjutan (OG, canonical, sitemap, robots, structured data) → **Fase 3**. Jangan dikerjakan sekarang.

### 1.6 Keamanan Tambahan

| #     | Tugas                                                                          | Bergantung | Catatan                             |
| ----- | ------------------------------------------------------------------------------ | ---------- | ----------------------------------- |
| 1.6.1 | Audit: apakah ada jalur yang bisa mengeset `is_platform_admin` dari input user | —          | Harus **tidak ada** (R-2)           |
| 1.6.2 | Audit: `team_id` tidak pernah masuk daftar `#[Fillable]` dari input request    | —          | R-5                                 |
| 1.6.3 | Validasi skema URL pada `media_url`/`image_url` (`http`/`https` saja)          | —          | R-8 — perubahan kecil, aman         |
| 1.6.4 | Tinjau kebutuhan security header (CSP/X-Frame-Options) — **hanya rekomendasi** | —          | Jangan implementasi tanpa keputusan |
| 1.6.5 | Konfirmasi `Password::defaults` benar-benar aktif di produksi                  | —          | R-12                                |

---

## Database Changes

**Tidak ada perubahan skema pada fase ini.**

Alasan: fase ini bertujuan membuat sistem **aman dan terverifikasi**, bukan menambah kemampuan. Setiap tabel baru (tenant settings, audit platform, domains) memerlukan keputusan yang belum tuntas dan itu ada di Fase 2–4.

Satu-satunya pekerjaan terkait database adalah **pengujian lintas engine** (tugas 1.4.5) — memastikan migrasi yang ada berjalan di MySQL/Postgres.

---

## Backend Changes

| Area                                                 | Perubahan                                         | Risiko                       |
| ---------------------------------------------------- | ------------------------------------------------- | ---------------------------- |
| `config/filesystems.php`                             | Tambah disk/seam tenant                           | Rendah                       |
| `App\Support\TenantStorage`                          | Kelas baru                                        | Rendah                       |
| `config/tenancy.php`                                 | File config baru (default `null`)                 | Rendah                       |
| `app/Http/Requests/ContentItem/*`, `PortfolioItem/*` | Tambah validasi skema URL                         | Rendah                       |
| Test                                                 | Berkas test baru (isolasi tenant, storage helper) | Rendah                       |
| Komentar kode                                        | Menyesuaikan referensi ADR/PDR (bergantung D-01)  | Rendah, tetapi banyak berkas |

**Tidak ada** perubahan pada controller domain, model, atau route.

---

## Frontend Changes

| Area                                            | Perubahan                                        |
| ----------------------------------------------- | ------------------------------------------------ |
| `resources/js/pages/public/company-profile.vue` | Meta title/description dinamis dari props profil |
| `resources/js/app.ts`                           | Tidak berubah (layout mapping sudah benar)       |

**Tidak ada** perubahan UI fungsional, tidak ada halaman baru, tidak ada menu baru.

---

## Infrastructure Changes

| Kebutuhan                                           | Status fase ini                                |
| --------------------------------------------------- | ---------------------------------------------- |
| CI (pint, phpstan, phpunit, cross-engine migration) | Disiapkan di repository                        |
| Proses SSR Node                                     | Dokumentasi cara jalan; **belum** deploy       |
| Database produksi                                   | Hanya persiapan config & uji; **belum** deploy |
| Queue worker / scheduler                            | Hanya dokumentasi; **belum** deploy            |
| Backup                                              | Hanya rekomendasi; **belum** diterapkan        |

> Fase ini **tidak** melakukan deployment. Semua butir infrastruktur di atas adalah persiapan agar Fase 2+ tidak terhambat.

---

## Security Considerations

| ID   | Risiko                                       | Tindakan di fase ini                                         |
| ---- | -------------------------------------------- | ------------------------------------------------------------ |
| RF-1 | Kebocoran data antar-tenant (scoping manual) | **Buktikan lewat test** (1.2); perbaikan definitif di Fase 2 |
| RF-2 | Eskalasi `is_platform_admin`                 | Audit (1.6.1)                                                |
| RF-3 | Mass assignment `team_id`                    | Audit (1.6.2)                                                |
| RF-4 | URL eksternal tidak tervalidasi              | Validasi skema (1.6.3)                                       |
| RF-5 | Path traversal pada storage                  | Test helper (1.3.3)                                          |
| RF-6 | Password policy tidak aktif di produksi      | Konfirmasi (1.6.5)                                           |

**Prinsip:** fase ini **menemukan dan membuktikan**, bukan menambal secara luas. Penambalan arsitektural (global scope) sengaja ditunda ke Fase 2 agar fasenya tetap kecil dan dapat diuji.

---

## Testing Requirements

### Test baru

1. **Test isolasi tenant** (`tests/Feature/Domain/TenantIsolation*`) — wajib, mencakup seluruh modul + halaman publik + form konsultasi.
2. **Test helper storage** — prefix path & penolakan path di luar prefix.
3. **Test meta SEO** — halaman publik memuat title/description dari data tenant.
4. **Test guard produksi** (`tests/Feature/ProductionConfigGuardTest`) — `APP_ENV=production` menolak `sqlite`; lokal, testing, MySQL, dan PostgreSQL lolos.

### Test yang harus tetap hijau

- Seluruh `tests/Feature/**` yang sudah ada.
- `composer test` (pint + phpstan level 7 + phpunit).
- `npm run types:check`.

### Perintah

```bash
npm run build          # wajib sebelum php artisan test
composer test
npm run check          # termasuk format tabel Markdown di docs/
```

---

## Migration Considerations

- **Tidak ada migrasi data** pada fase ini.
- Bila D-01 memutuskan "perbarui komentar kode", pekerjaannya bersifat **teks saja** — tidak mengubah perilaku. Tetap wajib menjalankan `composer test` setelahnya.
- Bila D-09 memutuskan engine produksi, pengujian lintas engine dilakukan **sekarang** agar kejutan muncul di Fase 1, bukan saat cutover produksi.

---

## Acceptance Criteria

Fase dianggap selesai bila **semua** terpenuhi:

1. ✅ `docs/IMPLEMENTATION_PLAN.md` dan 5 dokumen fase ada, dapat ditemukan, dan ikut terlacak version control.
2. ✅ Ada test isolasi tenant yang mencakup seluruh modul domain dan halaman publik, dan **hijau**.
3. ✅ Test isolasi terbukti _gagal_ bila scoping sengaja dinonaktifkan (uji jaring pengaman benar-benar menangkap bug). _(Bukti bahwa test tidak palsu.)_
4. ✅ `App\Support\TenantStorage` ada, punya test, dan konvensi `tenants/{id}/…` terdokumentasi.
5. ✅ `npm run build:ssr` menghasilkan `bootstrap/ssr` dan halaman publik punya meta dasar.
6. ✅ `composer test` dan `npm run types:check` hijau.
7. ✅ CI menjalankan pint, phpstan, phpunit, dan migrasi di MySQL/Postgres.
8. ✅ Tidak ada fitur produk baru, tabel baru, atau halaman baru.
9. ✅ Referensi ADR/PDR lama ditangani sesuai D-01.
10. ✅ Inkonsistensi `README.md` (E-1 s/d E-3) diperbaiki.

---

## Risks

| ID   | Risiko                                                                          | Dampak | Mitigasi                                                             |
| ---- | ------------------------------------------------------------------------------- | ------ | -------------------------------------------------------------------- |
| RF-A | Test isolasi "palsu" (selalu hijau, tidak menangkap bug nyata)                  | Tinggi | Acceptance criteria #3: verifikasi test gagal saat scoping dimatikan |
| RF-B | Perubahan komentar kode (D-01) menimbulkan konflik besar                        | Rendah | Kerjakan dalam satu commit khusus, tanpa perubahan logika            |
| RF-C | Build SSR gagal di lingkungan developer                                         | Sedang | Dokumentasikan prasyarat; jangan jadikan blocker Fase 2              |
| RF-D | CI cross-engine memperlambat pipeline                                           | Rendah | Jalankan job database sebagai matriks terpisah                       |
| RF-E | `tenant_id`/scoping yang ternyata perlu diubah membuat test perlu ditulis ulang | Sedang | Fase 2 sengaja tidak dijalankan bersamaan                            |

---

## Open Questions

| ID      | Pertanyaan                                                                                                                   |
| ------- | ---------------------------------------------------------------------------------------------------------------------------- |
| OQ-F1-1 | Apakah D-01 memilih "perbarui komentar" atau "tabel pemetaan permanen"? Menentukan besar-kecilnya pekerjaan 1.1.4.           |
| OQ-F1-2 | Apakah proses SSR Node akan dijalankan di host yang sama atau terpisah? (menentukan instruksi operasional)                   |
| OQ-F1-3 | Apakah ada alasan bisnis untuk tetap memakai SQLite di produksi? (mis. hosting murah) — bila ya, RSK-04 perlu ditinjau ulang |
| OQ-F1-4 | Apakah CSP benar-benar dibutuhkan sekarang, atau cukup dicatat?                                                              |

---

## Known Issues

Kondisi yang sudah diketahui **sebelum** fase dimulai:

| #    | Isu                                                                                    | Status                                                     |
| ---- | -------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| KI-1 | `docs/IMPLEMENTATION_PLAN.md` hilang; ±50 komentar kode menunjuk ke sana               | Sebagian ditangani (dokumen dibuat); sisanya menunggu D-01 |
| KI-2 | Tidak ada global scope isolasi tenant                                                  | **Diketahui**, perbaikan di Fase 2                         |
| KI-3 | `users.photo_path` ada di skema tetapi tidak pernah diisi                              | Diketahui; biarkan, jangan dihapus tanpa keputusan         |
| KI-4 | `leads.next_follow_up` & `content_items.scheduled_at` adalah teks bebas, bukan tanggal | Technical debt warisan                                     |
| KI-5 | `README.md` menyebut `/settings/teams` dan `/register` yang sudah tidak sesuai         | Akan diperbaiki (1.1.5)                                    |
| KI-6 | SSR produksi tidak aktif                                                               | Akan disiapkan (1.5)                                       |

---

## Discovered During Implementation

> **Diisi oleh agen/developer saat pengerjaan.** Setiap temuan baru dicatat di sini dengan tanggal, deskripsi, dampak, dan tindakan.

_Belum ada entri._

| Tanggal    | Temuan                                                                                                                                                                                                                                                                                                                                                 | Dampak                                                        | Tindakan                                                                                                                                                                                                                                                                                                               |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-14 | **D-09 belum diputuskan**, padahal tugas 1.4.1/1.4.3/1.4.5 ditandai bergantung padanya.                                                                                                                                                                                                                                                                | Tugas konfigurasi terancam ditunda                            | Dikerjakan **tanpa menebak**: CI menguji MySQL **dan** PostgreSQL sekaligus (jadi tidak perlu memilih), guard-nya netral engine (hanya melarang `sqlite`), dan `DB_CONNECTION` di `.env.example` dibiarkan `sqlite`, sementara panduan nilai produksi ditaruh di README. Pembuatan rencana cutover tetap menunggu D-09 |
| 2026-10-14 | `npm run check` (bagian dari `composer ci:check`) **juga memformat `.md`** (perataan tabel dokumen fase), dan `npm run check:fix` **menghapus komentar di `.env.example`** tanpa mengubah nilainya.                                                                                                                                                    | Perubahan tabel di dokumen bisa memerahkan CI                 | Jalankan `npm run check:fix` setiap kali menyunting tabel Markdown; karena `--fix` menghapus komentar di `.env.example`, panduan nilainya ditaruh di README. Dicatat juga di README bagian alur kerja                                                                                                                  |
| 2026-10-14 | `.env.example` kini menyetel `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` ke `redis`, sehingga clone baru butuh Redis untuk menjalankan aplikasi (test tidak terdampak karena `phpunit.xml` menimpanya dengan `array`/`sync`).                                                                                                                    | Developer tanpa Redis akan menemui error saat menjalankan app | README diberi catatan fallback ke `database`/`file` sekaligus panduan nilai produksi                                                                                                                                                                                                                                   |
| 2026-10-14 | **Guard produksi memerahkan CI pada push pertama.** `composer install` menjalankan `php artisan package:discover`, yang memboot aplikasi **sebelum** `.env` dibuat. Tanpa `APP_ENV`, Laravel menyebut dirinya `production`, sehingga guard langsung melempar dan `composer install` gagal — seluruh job CI (termasuk matriks) mati di langkah pertama. | CI merah, walaupun kode aplikasinya sendiri benar             | Guard hanya berlaku setelah environment benar-benar dideklarasikan (`.env` ada, atau `APP_ENV` ada di environment proses). Aturannya dijadikan fungsi murni `refusesToBoot()` supaya kasus "fresh clone" ikut teruji, bukan hanya kasus produksi                                                                       |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.** Keputusan teknis kecil yang dibuat di lapangan (tidak memerlukan persetujuan tim) dicatat di sini agar jejaknya tidak hilang.

_Belum ada entri._

| Tanggal    | Keputusan                                                                                                                                                             | Alasan                                                                                                                                                        |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-14 | Guard produksi ditaruh di provider sendiri (`ProductionConfigServiceProvider`) + exception bernama (`UnsafeProductionDatabase`), bukan inline di `AppServiceProvider` | Bisa diuji apa adanya (memanggil `boot()` provider) tanpa memboot aplikasi dua kali — yang akan mendaftarkan `ActivityObserver` dua kali dan menggandakan log |
| 2026-10-14 | Job CI baru (`database`) sebagai matriks terpisah, job `ci` tidak diubah                                                                                              | Jalur cepat SQLite tetap murah, dan nama check lama tidak berubah sehingga branch protection tidak rusak                                                      |
| 2026-10-14 | Image uji: `mysql:8.0` dan `postgres:16`                                                                                                                              | MySQL 8 sesuai ADR-13; PostgreSQL 16 dipilih sebagai versi uji yang masih didukung — bukan keputusan engine produksi (itu D-09)                               |

---

## Deferred

Berasal dari fase ini, tetapi sengaja **tidak** dikerjakan:

| Item                                                   | Alasan                                                     | Fase tujuan                              |
| ------------------------------------------------------ | ---------------------------------------------------------- | ---------------------------------------- |
| Global scope isolasi tenant                            | Menyentuh hampir semua query; butuh fase tersendiri + test | Fase 2                                   |
| `TenantContext` service                                | Bergantung global scope                                    | Fase 2                                   |
| Tenant settings & audit platform                       | Butuh tabel baru + keputusan D-04                          | Fase 2                                   |
| Abstraksi `TenantResolver` (host)                      | Butuh desain domain                                        | Fase 3                                   |
| SEO lanjutan (OG, canonical, sitemap, robots, JSON-LD) | Bukan fondasi minimum                                      | Fase 3                                   |
| Entitlement/plan                                       | Butuh keputusan produk                                     | Fase 4                                   |
| Semua fitur Basic/Plus/Pro                             | Belum disetujui                                            | Fase 5 (kecuali 5 integrasi gelombang 1) |
| Object storage (S3)                                    | Belum ada upload nyata                                     | Fase 5                                   |

---

## Technical Debt

Utang teknis yang **diketahui** dan diterima untuk sementara (dicatat, bukan dibiarkan tanpa jejak):

| ID   | Utang                                                 | Alasan diterima                                    | Kapan ditinjau                 |
| ---- | ----------------------------------------------------- | -------------------------------------------------- | ------------------------------ |
| TD-1 | Isolasi tenant masih manual setelah Fase 1            | Perbaikan definitif di Fase 2                      | Fase 2                         |
| TD-2 | `photo_path` tidak terpakai                           | Belum ada kebutuhan                                | Saat fitur upload dibuat       |
| TD-3 | `next_follow_up`/`scheduled_at` berupa teks           | Perubahan perilaku berisiko tanpa keputusan produk | Saat reminder otomatis diminta |
| TD-4 | Referensi ADR/PDR lama mungkin masih ada setelah D-01 | Bergantung keputusan                               | Setelah D-01                   |
| TD-5 | Cache/queue belum tenant-aware                        | Belum ada pemakaian nyata                          | Fase 2                         |
| TD-6 | Tidak ada security header eksplisit                   | Belum diputuskan                                   | Setelah 1.6.4                  |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.** Ringkasan hasil, penyimpangan dari rencana, dan pelajaran yang didapat.

_Belum ada entri._

---

## Implementation Notes

Catatan praktis untuk pelaksana:

1. **Mulai dari test isolasi (1.2).** Ini pekerjaan dengan nilai tertinggi dan tidak bergantung keputusan apa pun.
2. **Jangan sekaligus** memasang global scope di fase ini. Itu mengubah perilaku query secara luas dan akan mengaburkan hasil test.
3. **Perhatikan urutan build:** `npm run build` **wajib** sebelum `php artisan test`.
4. Untuk 1.2.1, buat **dua** tenant di dalam satu test dengan data yang sengaja mirip (nama perusahaan sama, `team_id` berbeda) — inilah yang membuktikan isolasi, bukan dua tenant dengan data yang jelas berbeda.
5. Untuk memverifikasi test isolasi benar-benar bekerja (acceptance #3): sementara nonaktifkan penjaganya, jalankan test, pastikan **gagal**, lalu kembalikan. Sebelum P-2, penjaganya adalah pemanggilan `->forTeam($team)` di controller; sejak tugas 2.2.6 penjaganya adalah global scope, jadi mutasinya ikut pindah ke `static::addGlobalScope(new TeamScope);` di `app/Concerns/BelongsToTeam.php`. Catat hasilnya di [Post-Implementation Notes](#post-implementation-notes).
6. Tugas yang bergantung pada D-01/D-09 boleh ditunda; jangan menebak keputusan.
