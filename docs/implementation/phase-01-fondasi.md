# Fase 1 — Fondasi & Titik Aman

> **Status:** ✅ **SELESAI** — Gate Fase 1 lolos di mesin lokal pada 2026-10-21 (lihat [Post-Implementation Notes](#post-implementation-notes)).
> **Ketergantungan:** tidak ada. **Fase ini wajib dikerjakan lebih dulu.**
> **Menambah fitur produk?** ❌ Tidak. Fase ini murni arsitektur, keamanan, dan dokumentasi.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ⚠️ **Dokumen ini adalah _living document_.** Agen/developer yang mengerjakan fase ini **wajib** memperbarui bagian [Discovered During Implementation](#discovered-during-implementation), [Decisions Made During Implementation](#decisions-made-during-implementation), [Technical Debt](#technical-debt), dan [Post-Implementation Notes](#post-implementation-notes) setiap kali menemukan hal baru.

> ℹ️ **Penomoran kode = warisan (D-01, 2026-10-19).** Komentar `ADR-*`/`PDR-*` di kode memakai penomoran dokumen lama; artinya ada di [Lampiran C](../IMPLEMENTATION_PLAN.md#lampiran-c--referensi-aturan-lama-legacy), bukan di Lampiran B.

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
| D-01 (penanganan referensi ADR/PDR lama) diputuskan                       | ✅ **Diputuskan 2026-10-19** — Lampiran C dipertahankan permanen; komentar kode **tidak** diubah                      |
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

| #     | Tugas                                                                            | Bergantung           | Catatan                                               |
| ----- | -------------------------------------------------------------------------------- | -------------------- | ----------------------------------------------------- |
| 1.4.1 | Rapikan `.env.example` sebagai acuan nilai yang benar untuk produksi             | D-09                 | Jangan sertakan secret nyata                          |
| 1.4.2 | Tambahkan kunci config `tenancy.*` (`base_domain`, dsb.) dengan default `null`   | —                    | Tanpa perilaku yang bergantung padanya                |
| 1.4.3 | Pastikan `APP_ENV=production` tidak memakai SQLite (guard/validasi)              | D-09 (adopsi ADR-13) | Bisa lewat validasi boot atau dokumentasi operasional |
| 1.4.4 | Tinjau `.github/` dan tetapkan CI: pint + phpstan + phpunit                      | —                    | Test isolasi (1.2.7) masuk ke sini                    |
| 1.4.5 | Pastikan CI menjalankan migrasi pada MySQL **dan** Postgres (bukan hanya SQLite) | D-09                 | Deteksi dini perbedaan perilaku (RSK-06)              |

### 1.5 Fondasi SEO

| #     | Tugas                                                                 | Bergantung                    | Catatan                        |
| ----- | --------------------------------------------------------------------- | ----------------------------- | ------------------------------ |
| 1.5.1 | Build SSR: `npm run build:ssr` → pastikan `bootstrap/ssr` terbentuk   | ADR-16 (disetujui 2026-10-20) | ADR-16                         |
| 1.5.2 | Siapkan cara menjalankan proses SSR (dokumentasi/langkah operasional) | D-09                          | Jangan menyentuh fitur         |
| 1.5.3 | Meta dasar halaman publik dari data profil tenant (title/description) | —                             | Data sudah tersedia            |
| 1.5.4 | Verifikasi output SSR benar-benar berisi HTML halaman publik          | 1.5.1                         | Bandingkan dengan render klien |

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

| ID   | Risiko                                       | Tindakan di fase ini                                                       |
| ---- | -------------------------------------------- | -------------------------------------------------------------------------- |
| RF-1 | Kebocoran data antar-tenant (scoping manual) | **Buktikan lewat test** (1.2); perbaikan definitif di Fase 2               |
| RF-2 | Eskalasi `is_platform_admin`                 | Audit (1.6.1) — **selesai 2026-10-21**, dikunci test mass-assignment       |
| RF-3 | Mass assignment `team_id`                    | Audit (1.6.2) — **selesai 2026-10-21**, `team_id` selalu dari tenant aktif |
| RF-4 | URL eksternal tidak tervalidasi              | Validasi skema (1.6.3) — **selesai 2026-10-21**, `url:http,https`          |
| RF-5 | Path traversal pada storage                  | Test helper (1.3.3) — **selesai 2026-10-19**                               |
| RF-6 | Password policy tidak aktif di produksi      | Konfirmasi (1.6.5) — **selesai 2026-10-21**                                |

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

| ID      | Pertanyaan                                                                                                                                                                          |
| ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| OQ-F1-1 | ~~Apakah D-01 memilih "perbarui komentar" atau "tabel pemetaan permanen"?~~ **Terjawab 2026-10-19: tabel pemetaan permanen**, jadi 1.1.4 hanya berupa catatan di tiap dokumen fase. |
| OQ-F1-2 | Apakah proses SSR Node akan dijalankan di host yang sama atau terpisah? (menentukan instruksi operasional)                                                                          |
| OQ-F1-3 | Apakah ada alasan bisnis untuk tetap memakai SQLite di produksi? (mis. hosting murah) — bila ya, RSK-04 perlu ditinjau ulang                                                        |
| OQ-F1-4 | Apakah CSP benar-benar dibutuhkan sekarang, atau cukup dicatat?                                                                                                                     |

---

## Known Issues

Kondisi yang sudah diketahui **sebelum** fase dimulai:

| #    | Isu                                                                                    | Status                                                                                                   |
| ---- | -------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| KI-1 | `docs/IMPLEMENTATION_PLAN.md` hilang; ±50 komentar kode menunjuk ke sana               | **Ditutup 2026-10-19 (D-01)** — dokumen ada, rujukan lama dipetakan di Lampiran C                        |
| KI-2 | Tidak ada global scope isolasi tenant                                                  | **Diketahui**, perbaikan di Fase 2                                                                       |
| KI-3 | `users.photo_path` ada di skema tetapi tidak pernah diisi                              | Diketahui; biarkan, jangan dihapus tanpa keputusan                                                       |
| KI-4 | `leads.next_follow_up` & `content_items.scheduled_at` adalah teks bebas, bukan tanggal | Technical debt warisan                                                                                   |
| KI-5 | `README.md` menyebut `/settings/teams` dan `/register` yang sudah tidak sesuai         | **Ditutup 2026-10-19 (1.1.5)**                                                                           |
| KI-6 | SSR produksi tidak aktif                                                               | **Sebagian 2026-10-20** — bundle `bootstrap/ssr` sudah bisa dibangun; proses Node belum di-deploy (D-09) |

---

## Discovered During Implementation

> **Diisi oleh agen/developer saat pengerjaan.** Setiap temuan baru dicatat di sini dengan tanggal, deskripsi, dampak, dan tindakan.

_Belum ada entri._

| Tanggal    | Temuan                                                                                                                                                                                                                                                                                                                                                 | Dampak                                                                 | Tindakan                                                                                                                                                                                                                                                                                                               |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-14 | **D-09 belum diputuskan**, padahal tugas 1.4.1/1.4.3/1.4.5 ditandai bergantung padanya.                                                                                                                                                                                                                                                                | Tugas konfigurasi terancam ditunda                                     | Dikerjakan **tanpa menebak**: CI menguji MySQL **dan** PostgreSQL sekaligus (jadi tidak perlu memilih), guard-nya netral engine (hanya melarang `sqlite`), dan `DB_CONNECTION` di `.env.example` dibiarkan `sqlite`, sementara panduan nilai produksi ditaruh di README. Pembuatan rencana cutover tetap menunggu D-09 |
| 2026-10-14 | `npm run check` (bagian dari `composer ci:check`) **juga memformat `.md`** (perataan tabel dokumen fase), dan `npm run check:fix` **menghapus komentar di `.env.example`** tanpa mengubah nilainya.                                                                                                                                                    | Perubahan tabel di dokumen bisa memerahkan CI                          | Jalankan `npm run check:fix` setiap kali menyunting tabel Markdown; karena `--fix` menghapus komentar di `.env.example`, panduan nilainya ditaruh di README. Dicatat juga di README bagian alur kerja                                                                                                                  |
| 2026-10-14 | `.env.example` kini menyetel `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` ke `redis`, sehingga clone baru butuh Redis untuk menjalankan aplikasi (test tidak terdampak karena `phpunit.xml` menimpanya dengan `array`/`sync`).                                                                                                                    | Developer tanpa Redis akan menemui error saat menjalankan app          | README diberi catatan fallback ke `database`/`file` sekaligus panduan nilai produksi                                                                                                                                                                                                                                   |
| 2026-10-14 | **Guard produksi memerahkan CI pada push pertama.** `composer install` menjalankan `php artisan package:discover`, yang memboot aplikasi **sebelum** `.env` dibuat. Tanpa `APP_ENV`, Laravel menyebut dirinya `production`, sehingga guard langsung melempar dan `composer install` gagal — seluruh job CI (termasuk matriks) mati di langkah pertama. | CI merah, walaupun kode aplikasinya sendiri benar                      | Guard hanya berlaku setelah environment benar-benar dideklarasikan (`.env` ada, atau `APP_ENV` ada di environment proses). Aturannya dijadikan fungsi murni `refusesToBoot()` supaya kasus "fresh clone" ikut teruji, bukan hanya kasus produksi                                                                       |
| 2026-10-20 | **Rujukan keputusan `D-13` dan `D-16` tidak ada di register §21** (yang ada hanya `D-01` s/d `D-12`), padahal jadwal dan tabel tugas memakainya sebagai prasyarat 1.4.3 dan 1.5.1.                                                                                                                                                                     | Prasyarat tidak bisa dicek; tugas bisa tertahan tanpa alasan           | Dibaca sebagai rujukan ke **ADR-13** dan **ADR-16** (dua ADR yang memang masih "perlu persetujuan"), lalu rujukannya dirapikan di jadwal + tabel tugas fase ini. Tidak ada nomor keputusan baru yang dikarang.                                                                                                         |
| 2026-10-22 | **`vp check` menghapus baris `.env.example` yang nilainya kosong** (mis. `TENANT_STORAGE_ROOT=`) — terjadi pada `npm run check` **maupun** `check:fix`, jadi bukan hanya soal komentar seperti catatan 2026-10-14.                                                                                                                                     | Variabel opsional bisa hilang dari contoh env tanpa terlihat di review | Jangan menaruh variabel opsional tanpa nilai di `.env.example`; dokumentasikan di README (contoh: `TENANT_STORAGE_ROOT`). Baris kosong itu kini dihapus permanen dari `.env.example`.                                                                                                                                  |

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

| ID   | Utang                                                 | Alasan diterima                                                                              | Kapan ditinjau                 |
| ---- | ----------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------ |
| TD-1 | Isolasi tenant masih manual setelah Fase 1            | Perbaikan definitif di Fase 2                                                                | Fase 2                         |
| TD-2 | `photo_path` tidak terpakai                           | Belum ada kebutuhan                                                                          | Saat fitur upload dibuat       |
| TD-3 | `next_follow_up`/`scheduled_at` berupa teks           | Perubahan perilaku berisiko tanpa keputusan produk                                           | Saat reminder otomatis diminta |
| TD-4 | Referensi ADR/PDR lama mungkin masih ada setelah D-01 | **Ditutup 2026-10-19** — Lampiran C permanen, komentar kode tetap warisan                    | —                              |
| TD-5 | Cache/queue belum tenant-aware                        | Belum ada pemakaian nyata                                                                    | Fase 2                         |
| TD-6 | Tidak ada security header eksplisit                   | Rekomendasi 2026-10-21: X-Frame-Options/nosniff/Referrer-Policy dulu, CSP setelah nonce siap | Butuh keputusan                |
| TD-7 | `social_links` profil belum divalidasi skema URL      | Belum dirender di halaman publik; risiko nol sampai fitur itu dibuat                         | Saat tautan sosial ditampilkan |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.** Ringkasan hasil, penyimpangan dari rencana, dan pelajaran yang didapat.

**2026-10-19 — tugas 1.1.3–1.1.5 (dokumentasi + D-01) dan 1.3 (storage seam).**

- **D-01 (1.1.3/1.1.4).** Dipilih **opsi 2**: [Lampiran C](../IMPLEMENTATION_PLAN.md#lampiran-c--referensi-aturan-lama-legacy) dipertahankan permanen sebagai tabel pemetaan, dan kelima dokumen fase diberi catatan "penomoran kode = warisan". Tidak ada komentar kode yang disentuh. Alasan: ±80 baris komentar di 61 berkas tidak mengubah perilaku, sedangkan risiko konfliknya nyata (RF-B).
- **README (1.1.5).** Tiga temuan Lampiran E ditutup. **E-1:** `/settings/teams` dihapus dari daftar halaman starter kit (rutenya memang sudah dihapus di `routes/settings.php`, PDR-07) dan diganti bagian **Storage Tenant** yang menyebut `/{slug-tim}/team`. **E-2:** baris `/register` diganti penjelasan bahwa registrasi **hanya** lewat tautan undangan (`FortifyServiceProvider::registerView` mengalihkan ke login tanpa undangan valid). **E-3:** diverifikasi — tombol "Buka Website Publik" masih ada di `resources/js/pages/admin/company-profile/Edit.vue`.
- **Storage seam (1.3.1–1.3.3).** Disk `tenants` di `config/filesystems.php` dengan root terparameterisasi (`TENANT_STORAGE_ROOT`, default `storage/app`), lalu `App\Support\TenantStorage` yang selalu memakai prefix `tenants/{team_id}/` dari `TenantContext`. Path absolut, separator `\`, segmen `..`/`.`, segmen kosong, dan null byte ditolak dengan `InvalidArgumentException`; tanpa tenant aktif helper melempar `MissingTenantContext::forStorage()` alih-alih menebak folder. Test: `tests/Feature/Storage/TenantStorageTest.php` (16 test).
- **Konvensi path (1.3.4).** Didokumentasikan di README bagian **Storage Tenant**, termasuk aturan validasi upload untuk pemakaian mendatang (whitelist mime, batas ukuran, nama acak, tidak di `public/`, hapus lewat job).
- **1.3.5 tidak butuh test terpisah.** Helper hanya memakai `FilesystemManager`, dan test penulisan berjalan di atas `Storage::fake('tenants')` — root sementara di luar `storage/app` — sehingga terbukti helper tidak bergantung pada `storage_path()`.
- **Belum dikerjakan (sengaja).** Tidak ada fitur upload, UI, tabel media, maupun kuota tenant — Fase 1 hanya memasang seam.

_Belum ada entri lain._

**2026-10-20 — tugas 1.4.2 (config tenancy) + 1.5.1/1.5.2 (SSR).**

- **1.4.2 `config/tenancy.*`.** Kunci `base_domain` dan `subdomain_enabled` ditambahkan dengan default `null`, tanpa perilaku yang membacanya (ADR-04 masih ditunda). Dijaga oleh `tests/Feature/Configuration/TenancyConfigTest.php`, yang gagal bila default berubah menjadi nilai nyata tanpa keputusan.
- **`.env.example` (1.4.1).** Tetap menjadi acuan nilai produksi (Redis, MySQL/Postgres, penjelasan di README). Dua kunci `TENANCY_*` **sengaja tidak** dicantumkan: nilainya belum diputuskan, dan menulisnya kosong akan mengubah default `null` menjadi string kosong pada salinan `.env` baru.
- **1.5.1 build SSR.** Entry `resources/js/ssr.ts` ditambahkan dan didaftarkan sebagai `ssr` pada plugin `laravel()` di `vite.config.ts`. `npm run build:ssr` menghasilkan `bootstrap/ssr/ssr.js` (±337 kB) beserta aset per halaman; direktori itu sudah di-`gitignore`. Judul halaman dan pemetaan layout dipindah ke `resources/js/app-config.ts` supaya render klien dan server memakai sumber yang sama — kalau tidak, `<title>`/layout bisa berbeda antara HTML SSR dan hidrasi klien.
- **Bukti proses hidup.** `node bootstrap/ssr/ssr.js` dijalankan, `php artisan inertia:check-ssr` menjawab "Inertia SSR server is running.", lalu proses dihentikan kembali. Jadi bundle tidak hanya terbentuk, tetapi bisa dirender.
- **1.5.2 dokumentasi.** README mendapat bagian **SSR produksi** (build, `inertia:start-ssr`, `inertia:check-ssr`, alamat/port, rebuild setelah UI berubah) dan catatan bahwa pemilihan host/proses manager menunggu D-09. Butir **Catatan & Keterbatasan** soal SSR diperbarui: bundle ada, proses belum di-deploy.
- **Catatan perilaku.** `bootstrap/ssr` hanya berisi bundle; **tidak ada** yang otomatis menjalankan proses Node. Selama proses itu mati, Inertia tetap _fallback_ ke klien, jadi tidak ada regresi bila bundle belum dibangun di server.
- **Belum dikerjakan (memang tugas 21 Oktober).** 1.5.3 (meta title/description dari profil tenant) dan 1.5.4 (verifikasi HTML SSR berisi konten halaman publik).

**2026-10-21 — tugas 1.5.3, 1.5.4, 1.6 (audit keamanan) + Gate Fase 1.**

- **1.5.3 meta dari data tenant.** `PublicCompanyProfileController` kini mengirim prop `meta`: `title` = `company_profiles.company_name` (fallback nama tim) dan `description` = `description` profil, fallback ke `about` yang sudah di-`strip_tags`, dipotong `Str::limit(…, 160)`, dan bila keduanya kosong memakai kalimat netral berisi nama tim. Halaman publik memakai prop itu di `<Head>`; teks marketing hardcoded ("Software House & IT Consulting", "aplikasi mobile, sistem ERP…") **dihapus** karena itu copy milik satu tenant dan salah untuk tenant lain.
- **1.5.4 verifikasi HTML mentah.** Dengan `node bootstrap/ssr/ssr.js` + `php artisan serve`, `GET /p/alfatech` mengembalikan HTML jadi: `<title data-inertia="">PT Alfatech Digital Solutions - Laravel</title>`, `<meta name="description" content="Software house and tech consulting agency based in Jakarta, Indonesia." data-inertia="description">`, dan `<div data-server-rendered="true" id="app">` berisi konten halaman (nama perusahaan, "Portofolio", "Kirim"). Artinya meta **dan** body benar-benar dirender server, bukan hanya dikirim sebagai props.
- **1.6.1 `is_platform_admin`.** Audit: kolom itu hanya disentuh seeder, factory, dan `platform:promote-admin`; `CreateNewUser` hanya menulis `name`/`email`/`password`, dan `ProfileUpdateRequest` hanya memvalidasi `name`/`email` sehingga `fill($request->validated())` membuang kunci lain. Dikunci oleh `tests/Feature/Security/MassAssignmentTest.php`.
- **1.6.2 `team_id`.** `team_id` tetap fillable (dibutuhkan seeder/factory), tetapi **tidak pernah** berasal dari request: setiap controller menulisnya dari `$request->team()` dan menyebar `$request->validated()` yang sudah tersaring. Test mengirim `team_id` tenant lain saat membuat portfolio dan membuktikan barisnya tetap masuk ke tenant aktif.
- **1.6.3 validasi skema URL (R-8).** `media_url` (content), `image_url`, dan `project_url` (portfolio) kini memakai `url:http,https`. `project_url` ikut divalidasi karena dirender sebagai `href` di halaman publik — `javascript:` di situ bisa dieksekusi saat diklik, bukan sekadar masalah tampilan.
- **1.6.4 security header — rekomendasi, belum diterapkan.** Lihat [Technical Debt](#technical-debt) TD-6. Ringkas: CSP ketat belum bisa dipasang sebelum audit inline script/style (`app.blade.php` memuat dua blok inline) dan sumber font eksternal; urutan aman adalah `X-Frame-Options: DENY` + `X-Content-Type-Options: nosniff` + `Referrer-Policy: strict-origin-when-cross-origin` dulu, CSP menyusul setelah nonce/hash disiapkan. Butuh keputusan, jadi tidak dikerjakan di fase ini.
- **1.6.5 `Password::defaults`.** Dikonfirmasi aktif di `AppServiceProvider::configureDefaults()`: produksi menuntut minimal 12 karakter, huruf besar/kecil, angka, simbol, dan `uncompromised`. Dikunci `tests/Feature/Security/PasswordDefaultsTest.php` (produksi ketat, lokal tetap default framework).
- **Gate Fase 1 — lolos di mesin lokal.** `composer test` (pint + phpstan level 7 + phpunit **303 test / 1204 assertion**), `npm run types:check`, `npm run check`, dan `npm run build:ssr` semuanya hijau. CI menjalankan rangkaian yang sama (job `ci` + matriks MySQL/PostgreSQL), jadi hasil pipeline tetap otoritatif untuk push berikutnya.

---

## Implementation Notes

Catatan praktis untuk pelaksana:

1. **Mulai dari test isolasi (1.2).** Ini pekerjaan dengan nilai tertinggi dan tidak bergantung keputusan apa pun.
2. **Jangan sekaligus** memasang global scope di fase ini. Itu mengubah perilaku query secara luas dan akan mengaburkan hasil test.
3. **Perhatikan urutan build:** `npm run build` **wajib** sebelum `php artisan test`.
4. Untuk 1.2.1, buat **dua** tenant di dalam satu test dengan data yang sengaja mirip (nama perusahaan sama, `team_id` berbeda) — inilah yang membuktikan isolasi, bukan dua tenant dengan data yang jelas berbeda.
5. Untuk memverifikasi test isolasi benar-benar bekerja (acceptance #3): sementara nonaktifkan penjaganya, jalankan test, pastikan **gagal**, lalu kembalikan. Sebelum P-2, penjaganya adalah pemanggilan `->forTeam($team)` di controller; sejak tugas 2.2.6 penjaganya adalah global scope, jadi mutasinya ikut pindah ke `static::addGlobalScope(new TeamScope);` di `app/Concerns/BelongsToTeam.php`. Catat hasilnya di [Post-Implementation Notes](#post-implementation-notes).
6. Tugas yang bergantung pada D-01/D-09 boleh ditunda; jangan menebak keputusan.
