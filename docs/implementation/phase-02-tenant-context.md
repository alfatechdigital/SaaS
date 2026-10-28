# Fase 2 — Tenant Context & Isolasi Lanjutan

> **Status:** ✅ **SELESAI** — Gate Fase 2 lolos di mesin lokal pada 2026-10-27 (lihat [Post-Implementation Notes](#post-implementation-notes)).
> **Ketergantungan:** Fase 1 selesai (khususnya test isolasi tenant).
> **Menambah fitur produk?** ❌ Tidak.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ⚠️ **Living document.** Perbarui bagian temuan/utang teknis saat pengerjaan berlangsung.

> ℹ️ **Penomoran kode = warisan (D-01, 2026-10-19).** Komentar `ADR-*`/`PDR-*` di kode memakai penomoran dokumen lama; artinya ada di [Lampiran C](../IMPLEMENTATION_PLAN.md#lampiran-c--referensi-aturan-lama-legacy), bukan di Lampiran B.

---

## Objective

Mengubah isolasi tenant dari **disiplin manual** menjadi **jaminan arsitektural**, dan menyediakan satu tempat terpusat untuk mengenali tenant aktif sehingga penyimpanan, cache, antrian, log, dan audit dapat mengikuti konteks tenant secara konsisten.

Singkatnya: _"Tidak mungkin lagi lupa memfilter `team_id`."_

---

## Scope

### Termasuk

- `TenantContext` service (satu sumber tenant aktif per request/job).
- Global scope untuk semua model tenant-scoped + escape hatch eksplisit.
- Ekstensi siklus hidup tenant (status: aktif/suspend) + audit aksi platform.
- Konfigurasi tenant (settings) — ringan.
- Cache, queue, dan log yang sadar tenant.
- Test anti-kebocoran diperluas (termasuk job & cache).

### Tidak termasuk

- Resolusi tenant dari host/domain → **Fase 3**.
- Entitlement/plan → **Fase 4**.
- Fitur produk apa pun.

---

## Prerequisites

| Prasyarat                                                         | Status                 |
| ----------------------------------------------------------------- | ---------------------- |
| Fase 1 selesai; test isolasi tenant hijau di CI                   | ✅ (2026-10-06)        |
| **D-03** (penerapan global scope) disetujui                       | ✅ (2026-10-08)        |
| **D-04** (tenant settings: tabel vs JSON) diputuskan              | ❌ **Wajib** untuk 2.5 |
| **ADR-09** (cache/queue/log tenant-aware) disetujui               | ❌ Disarankan          |
| Fase 1 selesai memisahkan config produksi (queue worker berjalan) | ❌                     |

---

## Current State

> Diperbarui 2026-10-26. Sebelumnya bagian ini menggambarkan kondisi sebelum 2.1–2.2.1 dikerjakan.

- Isolasi tenant kini **dijamin arsitektural**: `App\Scopes\TeamScope` memfilter setiap query model tenant-scoped, dan query tanpa tenant aktif **gagal keras** (`App\Exceptions\MissingTenantContext`).
- `App\Support\TenantContext` adalah satu sumber tenant aktif per request/job. `CurrentTeam::from()`/`activate()` menulisnya; `ResetTenantContext` (per request) dan listener `JobProcessing` (per job) membersihkannya.
- Seluruh model tenant-scoped sudah memakai scope: `company_profiles`, `projects`, `tasks`, `leads`, `content_items`, `transactions`, `portfolio_items`, `activity_logs`.
- **Tidak ada lagi `->forTeam()` di kode produksi** (2.2.6, 2026-10-13). Scope adalah otoritasnya; `forTeam()` hanya menyempitkan, bukan mengamankan. Pemakaian yang tersisa hanya di test.
- Escape hatch `withoutTeamScope()` **nol pemakaian di kode produksi**; tiga pemakaian yang ada semuanya di `tests/Feature/Tenancy/TeamGlobalScopeTest.php` dan masing-masing berkomentar alasan (2.2.4).
- Jalur khusus sudah diaudit (2.2.5): platform layer hanya menyentuh `teams`/`users`/`memberships`, seeder memakai `TenantContext::runFor()`, command hanya menyentuh `users`, scheduler menyentuh `team_invitations` yang memang tidak tenant-scoped.
- Route publik (`p/{team}`) tidak melewati segmen `{current_team}`, jadi controller-nya mengaktifkan tenant secara eksplisit.
- Belum ada job produksi, tetapi polanya sudah jadi: `App\Jobs\TenantJob` membawa `team_id` di payload dan memasang konteks tenant di `handle()` yang `final` (2.6.2, 2026-10-27). `runFor()` + reset per job tetap menjadi mekanisme di bawahnya.
- Scheduler ditinjau untuk 2.6.4: kedua tugas terjadwal (hapus undangan kedaluwarsa, `db:backup`) memang lintas tenant dan tidak butuh iterasi. Aturan untuk tugas berikutnya tercatat di `routes/console.php`.
- Cache: belum ada pemakaian eksplisit di kode produksi, tetapi kuncinya sudah ber-namespace lewat `App\Support\TenantCache` (`tenant:{id}:…`, 2.6.1) dan helper itu gagal keras tanpa tenant aktif.
- Log: setiap baris log yang ditulis saat tenant aktif membawa `team_id` di konteksnya; `forget()` membersihkannya lagi (2.6.3).
- `teams` punya `suspended_at` (siklus hidup tenant, 2.3) dan `settings` JSON (preferensi `locale`/`timezone`/`currency`, 2.5); `public_page_enabled` adalah saklar moderasi halaman publik.
- `activity_logs` mencatat aksi **di dalam** tenant; aksi **lintas tenant** oleh platform admin kini tercatat terpisah di `platform_audit_logs` (2.4, 2026-10-15).
- `platform_audit_logs` sengaja **tanpa `team_id`**, dan modelnya tidak memakai `BelongsToTeam` — isinya memang bukan milik satu tenant, sehingga pembacaan dari sisi platform tidak butuh escape hatch.
- Backup harian sudah terdaftar di penjadwal (`db:backup`, 02:00) dengan retensi dari `BACKUP_KEEP_DAYS`. Backup bukan bagian Fase 2, tetapi dikerjakan di baris yang sama karena satu database menampung seluruh tenant.

---

## Tasks

### 2.1 `TenantContext`

| #     | Tugas                                                                                                    | Catatan                                                       |
| ----- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| 2.1.1 | Buat `App\Support\TenantContext` (singleton per request/job) yang menyimpan tenant aktif + asal resolusi | Asal: `path` \| `host` \| `console` \| `platform`             |
| 2.1.2 | Migrasikan `CurrentTeam::from()` dan `$request->team()` agar memakai `TenantContext`                     | Perilaku eksternal tidak berubah                              |
| 2.1.3 | Sedikan `TenantContext::runFor($team, fn () => …)` untuk konteks eksplisit (job, command, seeder)        | Wajib untuk konsol                                            |
| 2.1.4 | Pastikan konteks **selalu dibersihkan** antar request/job                                                | Test: konteks tidak bocor antar request dalam antrian panjang |
| 2.1.5 | Bagikan tenant aktif + fitur (nanti) ke Inertia lewat `HandleInertiaRequests`                            | Saat ini `currentTeam` sudah dibagikan; gunakan service       |

### 2.2 Global Scope (ADR-03)

| #     | Tugas                                                                         | Catatan                                                                                                                      |
| ----- | ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| 2.2.1 | Tambah global scope di trait `BelongsToTeam` yang membaca `TenantContext`     | Model: `company_profiles`, `projects`, `tasks`, `leads`, `content_items`, `transactions`, `portfolio_items`, `activity_logs` |
| 2.2.2 | **Fail loudly**: query model tenant-scoped tanpa konteks tenant → exception   | Lebih baik gagal jelas daripada mengembalikan data salah                                                                     |
| 2.2.3 | Sediakan escape hatch eksplisit `withoutTeamScope()`                          | Nama harus jelas; mudah diaudit di code review                                                                               |
| 2.2.4 | Audit **setiap** pemakaian `withoutTeamScope()` yang ada                      | Harus punya komentar alasan                                                                                                  |
| 2.2.5 | Tangani jalur khusus: platform layer, seeder, command, test                   | Platform layer beroperasi lintas tenant → wajib konteks eksplisit                                                            |
| 2.2.6 | Hapus `->forTeam($team)` yang menjadi redundan — **hanya** bila terbukti aman | Jangan hapus massal tanpa alasan; scope eksplisit tetap sah                                                                  |
| 2.2.7 | Perluas test isolasi Fase 1: jalankan seluruh suite setelah scope aktif       | Ini yang membuktikan adopsi berhasil                                                                                         |

> ⚠️ **Risiko tertinggi fase ini (RSK-01).** Global scope menyentuh hampir semua query. Kerjakan per model, jalankan test setiap langkah, jangan dalam satu commit raksasa.

### 2.3 Siklus Hidup Tenant

| #     | Tugas                                                                                                                       | Catatan                                                          |
| ----- | --------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| 2.3.1 | Putuskan representasi status tenant: kolom `status` enum-string di `teams` (`active`/`suspended`) atau kolom `suspended_at` | Preferensi: `suspended_at` nullable (minimalis, mudah di-revert) |
| 2.3.2 | Middleware/guard menolak akses tenant yang disuspend dengan pesan jelas                                                     | Jangan menampilkan data apa pun                                  |
| 2.3.3 | Aksi platform: suspend/aktifkan tenant di `PlatformTenantController`                                                        | Konsisten dengan pola `updatePublicPage` yang sudah ada          |
| 2.3.4 | Tampilkan status di halaman `platform/tenants/Index.vue`                                                                    | Perubahan UI kecil, hanya untuk operator                         |
| 2.3.5 | Pastikan tenant disuspend: halaman publik mati & anggota tidak bisa login ke shell tenant                                   | Test                                                             |

### 2.4 Audit Platform (`platform_audit_logs`)

| #     | Tugas                                                                            | Catatan                                                                                                                                                                             |
| ----- | -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2.4.1 | Buat migrasi `platform_audit_logs`                                               | Kolom: `id`, `actor_id` (FK users, nullable), `action` (string), `target_type` (string), `target_id` (string, nullable), `details` (json/text nullable), `ip_address`, `created_at` |
| 2.4.2 | Catat: buat tenant, hapus tenant, toggle `public_page_enabled`, suspend/aktifkan | Target = team                                                                                                                                                                       |
| 2.4.3 | Jangan mencampur dengan `activity_logs` (yang tenant-scoped)                     | Pemisahan yang disengaja                                                                                                                                                            |
| 2.4.4 | Sedikan tampilan minimal (opsional) atau minimal query audit                     | Jangan bangun UI besar; ini untuk operator                                                                                                                                          |
| 2.4.5 | Test: aksi platform tercatat dengan aktor yang benar                             | —                                                                                                                                                                                   |

> **Selesai 2026-10-15 — kecuali bagian suspend.** 2.4.1–2.4.3 dan 2.4.5 dikerjakan; 2.4.4 memakai command `php artisan platform:audit-log` (20 catatan terbaru, bisa disaring `--action=`), bukan UI. Aksi yang tercatat: buat tenant, hapus tenant, dan toggle `public_page_enabled`. **Suspend/aktifkan tenant belum ada** karena tugas 2.3 belum dikerjakan — enum `App\Enums\PlatformAuditAction` tinggal menerima dua case baru saat itu tiba.

### 2.5 Tenant Settings

| #     | Tugas                                                                          | Catatan                                         |
| ----- | ------------------------------------------------------------------------------ | ----------------------------------------------- |
| 2.5.1 | Terapkan keputusan **D-04**                                                    | Rekomendasi: kolom `settings` (JSON) di `teams` |
| 2.5.2 | Bungkus akses lewat API sederhana (mis. `$team->setting('timezone', default)`) | Jangan biarkan kode menyentuh JSON mentah       |
| 2.5.3 | Isi awal yang wajar: `locale`, `timezone`, `currency`                          | Hanya yang benar-benar dibutuhkan               |
| 2.5.4 | Test: default terpakai; nilai tersimpan & terbaca                              | —                                               |

> **Jangan** mengisi settings dengan hal yang belum dibutuhkan (warna merek, logo, dsb.). Itu fitur produk.

### 2.6 Cache, Queue, dan Log Sadar Tenant (ADR-09)

| #     | Tugas                                                                                    | Catatan                                                           |
| ----- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| 2.6.1 | Helper kunci cache ber-namespace: `tenant:{id}:…`                                        | Mencegah tabrakan antar-tenant                                    |
| 2.6.2 | Bila/ketika job dibuat: bawa `team_id` + gunakan `TenantContext::runFor()` di `handle()` | Belum ada job; siapkan polanya + dokumentasi                      |
| 2.6.3 | Tambahkan `team_id` ke konteks log (`Log::withContext`)                                  | Memudahkan debugging multi-tenant                                 |
| 2.6.4 | Pastikan scheduler yang berjalan lintas tenant mengiterasi tenant dengan konteks benar   | Task hapus invitation kedaluwarsa: tinjau apakah perlu per-tenant |
| 2.6.5 | Test: kunci cache dua tenant berbeda tidak bertabrakan                                   | —                                                                 |

> **Selesai 2026-10-26 (2.6.1, 2.6.3, 2.6.5) dan 2026-10-27 (2.6.2, 2.6.4).** Cache lewat `App\Support\TenantCache`, log membawa `team_id`, dan pola job ada di `App\Jobs\TenantJob`. **Belum ada job produksi** — yang disiapkan adalah pola, bukan pekerjaan yang dijadwalkan. Tinjauan scheduler menyimpulkan tugas yang ada **tidak** perlu iterasi per tenant (lihat [Tinjauan Scheduler](#tinjauan-scheduler--tugas-264)).

---

## Database Changes

| Perubahan                                                         | Tipe             | Fase |
| ----------------------------------------------------------------- | ---------------- | ---- |
| `teams.suspended_at` (nullable timestamp) **atau** `teams.status` | Aditif, nullable | 2    |
| `teams.settings` (JSON, nullable)                                 | Aditif, nullable | 2    |     | `platform_audit_logs` (tabel baru, dibuat 2026-10-15) | Baru | 2   |
| `cache`/`jobs` — tanpa perubahan skema                            | —                | 2    |

Semua bersifat **aditif dan backward compatible** (ADR-17). Tidak ada kolom dihapus, tidak ada tabel di-_rename_.

---

## Backend Changes

| Area                                            | Perubahan                                             |
| ----------------------------------------------- | ----------------------------------------------------- |
| `App\Support\TenantContext`                     | **Baru**                                              |
| `App\Support\TenantCache`                       | **Baru** — kunci `tenant:{id}:…` (tugas 2.6.1)        |
| `App\Jobs\TenantJob`                            | **Baru** — base class job tenant-aware (tugas 2.6.2)  |
| `App\Scopes\TeamScope`                          | **Baru** — filter tenant + fail-loud                  |
| `App\Exceptions\MissingTenantContext`           | **Baru**                                              |
| `App\Http\Middleware\ResetTenantContext`        | **Baru** — bersihkan konteks per request              |
| `App\Concerns\BelongsToTeam`                    | Tambah global scope + `withoutTeamScope()`            |
| `App\Support\CurrentTeam`                       | Didelegasikan ke `TenantContext`; tambah `activate()` |
| `App\Models\Team`                               | Status + `setting()` + relasi audit                   |
| `App\Models\PlatformAuditLog`                   | **Baru**                                              |
| `App\Http\Middleware\EnsureTeamMembership`      | Cek status tenant                                     |
| `App\Http\Controllers\PlatformTenantController` | Aksi suspend/aktifkan + pencatatan audit              |
| `App\Http\Middleware\HandleInertiaRequests`     | Bagikan konteks tenant dari service                   |
| `app/Providers/AppServiceProvider.php`          | Bind `TenantContext` sebagai singleton                |
| `database/seeders/AlfatechDemoSeeder.php`       | Sesuaikan bila perlu konteks eksplisit                |
| Test                                            | Suite isolasi diperluas; test audit & settings        |

---

## Frontend Changes

| Area                                            | Perubahan                                                       |
| ----------------------------------------------- | --------------------------------------------------------------- |
| `resources/js/pages/platform/tenants/Index.vue` | Kolom status + tombol suspend/aktifkan                          |
| `resources/js/types/teams.ts`                   | Tambah tipe status bila ada                                     |
| Shell tenant                                    | Halaman "tenant disuspend" (atau gunakan error page yang jelas) |
| `usePermission.ts`                              | Tidak berubah                                                   |

Sangat minimal — tidak ada halaman tenant baru.

---

## Infrastructure Changes

| Kebutuhan                                      | Status                                                                                                         |
| ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Queue worker berjalan (untuk pola konteks job) | Diperlukan bila job mulai dibuat                                                                               |
| Cron `php artisan schedule:run` tiap menit     | Diperlukan agar `db:backup` harian benar-benar berjalan; tanpa itu jadwal yang terdaftar tidak berarti apa-apa |
| Log aggregation (opsional)                     | Disarankan agar `team_id` berguna                                                                              |
| Tidak ada perubahan infrastruktur lain         | —                                                                                                              |

---

## Security Considerations

| ID    | Risiko                                                                                           | Tindakan                                       |
| ----- | ------------------------------------------------------------------------------------------------ | ---------------------------------------------- |
| RF2-1 | Global scope memecah query platform → data lintas tenant muncul di shell tenant, atau sebaliknya | Escape hatch eksplisit + test menyeluruh       |
| RF2-2 | Konteks tenant bocor antar request/job (stale context)                                           | Bersihkan konteks; test antrian panjang        |
| RF2-3 | Tenant disuspend tetap bisa diakses lewat jalur tertentu (mis. halaman publik)                   | Test semua jalur                               |
| RF2-4 | Fail-loud exception membocorkan informasi internal                                               | Exception generik ke user, detail hanya di log |
| RF2-5 | Kunci cache bertabrakan → data tenant lain tersaji                                               | Namespace + test                               |

**Prinsip:** setelah fase ini, isolasi tenant **tidak lagi bergantung pada ingatan developer**.

---

## Testing Requirements

| Test                 | Cakupan                                                                                |
| -------------------- | -------------------------------------------------------------------------------------- |
| Suite isolasi Fase 1 | Harus **tetap hijau** setelah global scope aktif — ini bukti utama                     |
| Dashboard (2.2.7)    | Agregasi lima modul hanya memuat data tenant aktif                                     |
| Konteks tenant       | Konteks tidak bocor antar request; `runFor()` bekerja                                  |
| Fail-loud            | Query model tenant-scoped tanpa konteks → exception                                    |
| Escape hatch         | `withoutTeamScope()` benar-benar melewati scope                                        |
| Status tenant        | Suspend memblokir shell + halaman publik                                               |
| Audit platform       | Setiap aksi platform tercatat                                                          |
| Backup & restore     | Dump → ubah data → restore → data kembali seperti semula; retensi; driver tak didukung |
| Settings             | Default & override                                                                     |
| Cache namespace      | Tidak bertabrakan antar-tenant                                                         |

Perintah: `composer test` (pint + phpstan level 7 + phpunit) dan `npm run types:check`.

---

## Migration Considerations

- Semua perubahan aditif → aman dijalankan pada database berisi data.
- `teams.suspended_at` default `null` = semua tenant yang ada tetap aktif. Tidak ada data yang perlu di-_backfill_.
- `teams.settings` default `null` = perilaku lama (default aplikasi).
- `platform_audit_logs` kosong di awal; tidak perlu backfill historis (aksi platform sebelum fase ini tidak tercatat — catat di [Known Issues](#known-issues)).
- Aktifkan global scope **per model, satu per satu**, bukan sekaligus; jalankan test setiap langkah.

---

## Acceptance Criteria

> **Status 2026-10-27 (Gate Fase 2).** Kesepuluh kriteria terpenuhi dan diverifikasi: isolasi dijaga global scope, mutasi ulang membuktikan penjaganya, `forTeam()` tidak lagi dipakai di produksi, aksi platform tercatat (`platform_audit_logs`), tenant dapat disuspend (2.3, 2026-10-22), kunci cache ber-namespace tenant (2.6.1, 2026-10-26), dan pola job tenant-aware sudah tersedia + diuji (2.6.2, 2026-10-27). Bukti terakhir: `composer test` 345 test / 1320 assertion hijau, phpstan level 7 nol error.

1. ✅ `TenantContext` menjadi satu-satunya sumber tenant aktif; tidak ada controller yang resolve tenant sendiri.
2. ✅ Seluruh model tenant-scoped memakai global scope; query tanpa konteks gagal jelas.
3. ✅ `withoutTeamScope()` ada dan setiap pemakaiannya punya alasan tertulis.
4. ✅ Suite isolasi tenant (Fase 1) **tetap hijau** tanpa perubahan ekspektasi test.
5. ✅ Platform layer berfungsi penuh setelah global scope aktif (buat/hapus/moderasi tenant).
6. ✅ Tenant dapat disuspend; akses shell dan halaman publik diblokir.
7. ✅ Aksi platform tercatat di `platform_audit_logs`.
8. ✅ Kunci cache ber-namespace tenant.
9. ✅ `composer test` dan `npm run types:check` hijau.
10. ✅ Tidak ada fitur produk baru.

---

## Risks

| ID    | Risiko                                                                       | Dampak     | Mitigasi                                                                       |
| ----- | ---------------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------ |
| RF2-A | Global scope memecah query platform/seeder/command                           | **Tinggi** | Kerjakan per model; test setiap langkah; escape hatch eksplisit (RSK-01)       |
| RF2-B | Stale tenant context pada antrian panjang                                    | Tinggi     | Bersihkan konteks; test khusus                                                 |
| RF2-C | Scope ganda (global + `forTeam()`) membingungkan developer                   | Rendah     | Sudah dirapikan 2026-10-13 (2.2.6): `forTeam()` tidak lagi dipakai di produksi |
| RF2-D | Fail-loud memicu exception di tempat tak terduga (mis. Inertia shared props) | Sedang     | Uji semua halaman; tangani jalur konsol secara eksplisit                       |
| RF2-E | Suspend tenant memutus akses tanpa peringatan                                | Sedang     | UI konfirmasi + audit log                                                      |

---

## Open Questions

| ID      | Pertanyaan                                                                                                                       |
| ------- | -------------------------------------------------------------------------------------------------------------------------------- |
| OQ-F2-1 | Apakah tenant disuspend boleh tetap membaca data (read-only) atau harus nol akses?                                               |
| OQ-F2-2 | Apakah `activity_logs` perlu memuat aksi platform lintas tenant juga, atau pemisahan tabel dipertahankan? (rekomendasi: dipisah) |
| OQ-F2-3 | Apakah `TenantContext` perlu tersedia di blade/`app.blade.php` untuk kebutuhan SEO nanti?                                        |
| OQ-F2-4 | Apakah ada kebutuhan kuota/limit per tenant sekarang? (bila ya → Fase 4, jangan di sini)                                         |

---

## Known Issues

| #     | Isu                                                                                         | Status                                       |
| ----- | ------------------------------------------------------------------------------------------- | -------------------------------------------- |
| KI2-1 | Aksi platform sebelum Fase 2 tidak akan tercatat di `platform_audit_logs`                   | Diterima; tidak ada backfill                 |
| KI2-2 | Tidak ada job apa pun sehingga lebih sulit menguji sinkronisasi konteks tenant secara nyata | Uji dengan job dummy di test                 |
| KI2-3 | `EnsureTeamMembership` menjalankan query `exists()` per request                             | Terkait performa; bisa dioptimasi belakangan |

---

## Discovered During Implementation

> **Diisi oleh agen/developer saat pengerjaan.**

| Tanggal    | Temuan                                                                                                                                                                                                                                                                           | Dampak                                                                       | Tindakan                                                                                                                                                                                          |
| ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-08 | **Suite isolasi tetap hijau meski `TeamScope` sengaja dirusak**, karena controller masih memanggil `->forTeam()`. Jadi suite isolasi belum menjadi penjaga global scope.                                                                                                         | Global scope bisa rusak tanpa terdeteksi                                     | Tambah `tests/Feature/Tenancy/TeamGlobalScopeTest.php` (fail-loud + confinement + escape hatch per model). Suite isolasi baru efektif setelah `->forTeam()` redundan dihapus (2.2.6)              |
| 2026-10-08 | Route publik (`p/{team}`) tidak melewati segmen `{current_team}`, sehingga tidak punya tenant aktif dan query ber-scope gagal.                                                                                                                                                   | Halaman publik 500 begitu `company_profiles` ber-scope                       | `CurrentTeam::activate()` dipanggil di `PublicCompanyProfileController` dan `PublicLeadController`                                                                                                |
| 2026-10-08 | Query dari relasi yang di-_lazy load_ di dalam fixture test berjalan tanpa konteks tenant (`$task->project`).                                                                                                                                                                    | Test gagal dengan exception, bukan assertion — membingungkan saat dibaca     | Fixture memakai instance yang sudah dibuat. Catatan: `Model::fresh()` aman (`newQueryWithoutScopes`) dan `Rule::exists(Model::class)` aman (hanya nama tabel, tanpa scope)                        |
| 2026-10-08 | `AlfatechDemoSeeder` melakukan query pada model ber-scope tanpa konteks.                                                                                                                                                                                                         | `PlatformSeederTest` gagal                                                   | Seeder dibungkus `TenantContext::runFor()`. Bagian dari 2.2.5, dikerjakan lebih awal karena scope langsung memblokirnya                                                                           |
| 2026-10-12 | `activity_logs` ikut ber-scope pada hari yang sama dengan model lain, bukan menyusul.                                                                                                                                                                                            | —                                                                            | Tidak ada penyesuaian khusus; `ActivityObserver` hanya menulis (`create`), tidak pernah query                                                                                                     |
| 2026-10-13 | **Audit `withoutTeamScope()` (2.2.4):** nol pemakaian di kode produksi; hanya tiga pemakaian di `TeamGlobalScopeTest`, semuanya diberi komentar alasan.                                                                                                                          | Escape hatch mudah diaudit saat code review                                  | Quality gate tetap berlaku: setiap pemakaian baru wajib berkomentar alasan                                                                                                                        |
| 2026-10-13 | **Audit jalur khusus (2.2.5):** platform layer, seeder, command, scheduler, dan test tidak ada yang perlu `withoutTeamScope()` baru.                                                                                                                                             | Tidak ada konteks tenant palsu yang dipasang di jalur lintas tenant          | Rincian per jalur dicatat di bawah                                                                                                                                                                |
| 2026-10-13 | **Mutasi ulang untuk 2.2.7:** `addGlobalScope(new TeamScope)` dinonaktifkan → 45 test gagal, termasuk 20 dari `TenantIsolationTest`.                                                                                                                                             | Membuktikan suite isolasi kini menjaga **global scope**, bukan `->forTeam()` | Penghapusan `->forTeam()` (2.2.6) aman: penjaganya sudah pindah ke scope                                                                                                                          |
| 2026-10-13 | **Gerbang penuh 2.2.7:** `composer test` hijau setelah 2.2.4–2.2.6 — pint bersih, phpstan level 7 nol error, 249 test / 1052 assertion.                                                                                                                                          | Tidak ada ekspektasi test yang diubah untuk membuatnya hijau                 | Test baru: `test_dashboard_only_exposes_the_active_tenant_data`                                                                                                                                   |
| 2026-10-15 | **Restore SQLite senyap bila koneksi belum di-`purge`.** Dibuktikan dengan probe: berkas ditimpa lewat `copy()` lalu dibaca ulang — koneksi PDO yang sudah terbuka masih mengembalikan isi lama, sedangkan koneksi baru mengembalikan isi dump.                                  | Restore tampak "berhasil" tetapi aplikasi tetap menyajikan data lama         | `DB::purge()` dipanggil sebelum **dan** sesudah restore di `DatabaseBackup::restore()`, lalu diuji lewat putaran penuh di `DatabaseBackupTest`                                                    |
| 2026-10-15 | Mencatat audit **di dalam** transaksi hapus tenant berarti kegagalan hapus tidak meninggalkan catatan audit.                                                                                                                                                                     | Jejak audit tidak pernah mencatat aksi yang sebenarnya gagal                 | Disengaja: yang salah satu di antara "tercatat" dan "benar-benar terjadi", yang benar-benar terjadi lebih penting (2.4.2)                                                                         |
| 2026-10-15 | `Builder::soleOrFail()` tidak ada di Laravel 13; satu-satunya test yang memakainya gagal dengan `BadMethodCallException`.                                                                                                                                                        | Test baru gagal karena API yang salah, bukan karena perilaku aplikasi        | Diganti `sole()` (3 test `PlatformAuditLogTest` memverifikasi asumsi yang sama)                                                                                                                   |
| 2026-10-15 | **"Ada request?" bukan cara yang benar mendeteksi jalur console.** Console kernel Laravel tetap mengikat `request`, jadi `app()->bound('request')` mengembalikan `true` di `artisan`/`tinker` — alamat loopback pun tercatat seolah operator memakai panel dari sana.            | Audit bisa memuat alamat yang salah tanpa ada yang sadar                     | `RecordPlatformAudit` memakai `app()->runningInConsole()`; `ip_address` nullable, dan nilainya hanya diambil dari request HTTP                                                                    |
| 2026-10-22 | **Jadwal 22 Okt dan dokumen fase berbeda soal status HTTP tenant disuspend**: jadwal menulis 404 untuk shell **dan** publik, sedangkan 2.3.2 meminta "pesan jelas".                                                                                                              | Tanpa keputusan, implementasi bisa mengikuti salah satu dokumen secara acak  | Diputuskan pemilik produk: **403 + pesan** untuk shell, **404** untuk halaman publik & form konsultasi. Tercatat di [Decisions Made During Implementation](#decisions-made-during-implementation) |
| 2026-10-26 | **`Log::withContext()` lewat facade hanya menyentuh kanal default**, dan `Log::sharedContext()` tetap kosong. Pembersihan harus lewat `Log::withoutContext(['team_id'])` pada kanal yang sudah ter-resolve.                                                                      | Konteks tenant bisa tertinggal di proses panjang (queue worker, Octane)      | `Log::withoutContext(['team_id'])` dipanggil di `TenantContext::forget()`; diuji dengan `MessageLogged` (tugas 2.6.3)                                                                             |
| 2026-10-27 | **Properti `readonly` tidak bisa dipakai untuk field job.** `SerializesModels::__unserialize()` memulihkan properti lewat refleksi dari scope subclass, dan PHP menolak menginisialisasi ulang `readonly` dari scope itu (`Cannot initialize readonly property … from scope …`). | Pola job yang "terlihat modern" gagal tepat saat job dijalankan dari antrian | `TenantJob::$teamId` memakai properti biasa + komentar alasan; dikunci `test_the_job_payload_carries_the_tenant_id`                                                                               |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.**

| Tanggal    | Keputusan                                                                                                                                | Alasan                                                                                                                                                                                               |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-08 | **D-03 disetujui** — global scope diterapkan pada seluruh model tenant-scoped                                                            | Isolasi tidak lagi bergantung pada ingatan developer; jaring pengaman P-1 sudah hijau dan wajib di CI                                                                                                |
| 2026-10-08 | Scope diaktifkan **per model** lewat saklar sementara `usesTeamScope()`, lalu saklar dihapus setelah semua model tercakup (2026-10-12)   | Agar setiap kegagalan dapat diatribusikan ke satu model (RSK-01); scaffolding sementara dibuang setelah tidak dipakai                                                                                |
| 2026-10-08 | Scope di `BelongsToTeam` dibuat **unconditional** setelah seluruh model tercakup                                                         | Model yang memakai trait itu memang tenant-owned; satu-satunya jalan keluar adalah `withoutTeamScope()` yang eksplisit                                                                               |
| 2026-10-08 | Jalur khusus yang dibereskan lebih awal: route publik + seeder                                                                           | Keduanya langsung gagal begitu scope aktif; menundanya hanya membuat suite merah                                                                                                                     |
| 2026-10-08 | Fail-loud memakai exception khusus yang menyebut nama model                                                                              | Penyebab langsung terlihat dari stack trace, tanpa perlu debug tambahan                                                                                                                              |
| 2026-10-08 | `runFor()` **memulihkan** konteks sebelumnya (bukan sekadar menghapusnya)                                                                | `runFor()` bersarang tidak boleh melebarkan scope luar secara diam-diam, dan konteks tetap bersih bila callback melempar exception                                                                   |
| 2026-10-13 | **Hapus seluruh `->forTeam()` di kode produksi (2.2.6)**, bukan hanya sebagian                                                           | Dua sumber kebenaran untuk tenant selalu berakhir dengan yang salah satunya basi; `forTeam()` yang menunjuk tenant lain hanya mengembalikan nol baris, bukan error — jadi kegagalannya senyap        |
| 2026-10-13 | Tenant tetap diaktifkan lewat `CurrentTeam::from()` (`{current_team}`) / `TeamRequest::authorize()` / `activate()` (route publik)        | Middleware `ResolveTenant` (host-aware) baru masuk gelombang 2; memindahkannya sekarang berarti memperluas lingkup 2.2                                                                               |
| 2026-10-13 | Nota validasi `Rule::exists(Project::class, 'id')->where('team_id', …)` **dipertahankan**                                                | `Rule::exists()` hanya memakai nama tabel (tanpa scope), jadi `where('team_id')` di situ memang penjaga, bukan duplikasi                                                                             |
| 2026-10-15 | **`platform_audit_logs` tanpa `team_id`, dan modelnya tidak memakai `BelongsToTeam`**                                                    | Isinya memang bukan milik satu tenant; tanpa trait itu, jalur platform tidak perlu `withoutTeamScope()` sama sekali — escape hatch tetap nol pemakaian di produksi                                   |
| 2026-10-15 | Penulisan audit terjadi lewat **satu action** (`RecordPlatformAudit`), bukan panggilan model langsung di controller                      | Satu jalur berarti satu tempat untuk mengubah bentuk catatan, dan mudah dicari saat audit kode                                                                                                       |
| 2026-10-15 | Catatan audit hapus tenant dibuat **di dalam** transaksi penghapusan                                                                     | Menghindari catatan "tenant dihapus" untuk penghapusan yang sebenarnya gagal dan di-rollback                                                                                                         |
| 2026-10-15 | Backup dibuat **di dalam aplikasi** (`symfony/process` yang sudah jadi dependensi), bukan lewat paket pihak ketiga                       | Tanpa dependensi baru, perintahnya auditabel, dan hanya memakai dua biner klien yang memang ada di server database                                                                                   |
| 2026-10-15 | Retention dump dikonfigurasi di `config/backup.php` (`BACKUP_KEEP_DAYS`, default 7) dengan override `--keep-days`                        | Backup tanpa pembersihan akan memenuhi disk, dan itu baru terasa di produksi                                                                                                                         |
| 2026-10-15 | `db:restore` memakai `ConfirmableTrait` — tanya dulu saat produksi, `--force` untuk runbook                                              | Ini perintah paling destruktif di repo: satu salah ketik berarti seluruh pelanggan kembali ke titik tertentu                                                                                         |
| 2026-10-15 | **P-4: nama `team` dipertahankan**, ambigu hanya didokumentasikan (glosarium README + ADR-02)                                            | Rename menyentuh 11 tabel dan puluhan berkas demi nol manfaat bagi user; yang dibutuhkan hanya satu definisi tertulis yang jelas                                                                     |
| 2026-10-22 | **2.3.1: `suspended_at` nullable**, bukan kolom enum `status`                                                                            | Revert cukup mengosongkan satu kolom, tidak ada state machine yang harus dijaga, dan waktu suspend tetap terekam                                                                                     |
| 2026-10-22 | Tenant disuspend: shell **403 + pesan**, halaman publik **404**                                                                          | Anggota yang sudah login perlu tahu kenapa dashboard mati; pengunjung anonim tidak boleh tahu tenant itu ada. Jadwal menulis 404 untuk keduanya — deviasi dicatat di temuan 2026-10-22               |
| 2026-10-26 | **2.5: kolom JSON `teams.settings`** dengan default di `config/tenancy.php` (`locale` `id`, `timezone` `Asia/Jakarta`, `currency` `IDR`) | Tiga preferensi skalar belum butuh tabel + join; default tinggal di config supaya `$team->setting('locale')` selalu punya nilai tanpa backfill                                                       |
| 2026-10-26 | **Akses settings hanya lewat `Team::setting()` / `setSetting()`**, bukan JSON mentah                                                     | Satu tempat untuk urutan resolusi (nilai tersimpan → default pemanggil → default config), jadi tenant tanpa preferensi dan tenant yang menyimpan nilai default tidak bisa dibedakan perilakunya      |
| 2026-10-26 | **2.6.1: namespace cache ditegakkan lewat `App\Support\TenantCache`**, bukan konvensi penamaan string                                    | Konvensi bisa dilupakan; helper mengambil id dari `TenantContext` sehingga mustahil mem-prefix dengan tenant yang salah, dan gagal keras tanpa tenant aktif                                          |
| 2026-10-26 | **2.6.3: `team_id` dipasang di `TenantContext::set()/forget()`**, bukan middleware                                                       | Request, console command, job, dan seeder semuanya lewat jalur itu; `runFor()` juga memulihkan konteks lewat `set()`/`forget()` supaya `team_id` tenant dalam tidak tertinggal                       |
| 2026-10-27 | **2.6.2: base class `TenantJob` dengan `handle()` `final`**, bukan trait atau konvensi dokumentasi                                       | Job yang lupa memasang konteks akan gagal di dalam worker, jauh dari penyebabnya. `final` membuat kelalaian itu mustahil, bukan sekadar tidak disarankan                                             |
| 2026-10-27 | Job untuk tenant yang sudah dihapus **dilewati** (satu baris `warning`), bukan digagalkan                                                | Pekerjaannya memang tidak punya subjek lagi; melempar exception berarti retry berulang untuk sesuatu yang tidak akan pernah berhasil                                                                 |
| 2026-10-27 | **2.6.4: tidak ada tugas terjadwal yang perlu diubah**                                                                                   | Keduanya sudah lintas tenant secara benar: `team_invitations` sengaja tidak tenant-scoped, dan `db:backup` bekerja di level database. Yang ditambahkan adalah aturan tertulis untuk tugas berikutnya |

---

## Deferred

| Item                           | Alasan                    | Fase tujuan             |
| ------------------------------ | ------------------------- | ----------------------- |
| `TenantResolver` berbasis host | Butuh desain domain       | Fase 3                  |
| Entitlement/plan               | Butuh keputusan produk    | Fase 4                  |
| Kuota & usage counter          | Belum ada kuota disetujui | Fase 4                  |
| Pemisahan antrian per tenant   | Belum ada job nyata       | Setelah ada beban nyata |
| Fitur Basic/Plus/Pro           | Belum disetujui           | Fase 5                  |

---

## Audit 2.2.4–2.2.5 — Escape Hatch & Jalur Khusus

> Dijalankan 2026-10-13, setelah seluruh model tenant-scoped ber-scope.

### `withoutTeamScope()` — tugas 2.2.4

Pencarian di seluruh repo menemukan **nol pemakaian di kode produksi** (`app/`, `routes/`, `database/`).
Tiga pemakaian yang ada semuanya di test, masing-masing dengan komentar alasan:

| Lokasi                                                                     | Alasan yang dicatat                                                            |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `TeamGlobalScopeTest` — `test_without_team_scope_reaches_every_tenant`     | Membuktikan escape hatch benar-benar melepas scope saat tidak ada tenant aktif |
| `TeamGlobalScopeTest` — `test_without_team_scope_escapes_an_active_tenant` | Membuktikan escape hatch melebarkan query yang sedang terkunci ke satu tenant  |
| `TeamGlobalScopeTest` — assertion di test yang sama                        | Membuktikan baris tenant lain dapat dijangkau — dan itu harus sengaja          |

Quality gate `implementation-schedule.md` §9 (“Setiap `withoutTeamScope()` wajib berkomentar alasan”) tetap berlaku untuk pemakaian baru.

### Jalur khusus — tugas 2.2.5

| Jalur                                       | Cara menangani tenant                                                                                                           | Perlu `withoutTeamScope()`?                                                  |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Platform layer (`PlatformTenantController`) | Hanya menyentuh `teams`, `users`, `memberships`, `team_invitations` — tidak ada model ber-scope                                 | Tidak; memasang konteks tenant palsu justru dilarang (Implementation Note 4) |
| `AlfatechDemoSeeder`                        | Seluruh penulisan/query tenant dibungkus `TenantContext::runFor($this->team, …)`                                                | Tidak                                                                        |
| `platform:promote` (`PromotePlatformAdmin`) | Hanya menyentuh `users`                                                                                                         | Tidak                                                                        |
| Scheduler (`routes/console.php`)            | Menghapus `team_invitations` kedaluwarsa — model ini memang tidak tenant-scoped                                                 | Tidak                                                                        |
| Route publik (`p/{team}`)                   | `CurrentTeam::activate($team)` di `PublicCompanyProfileController` & `PublicLeadController`                                     | Tidak                                                                        |
| Test                                        | Fixture membuat baris dengan `team_id` eksplisit (`create()` tidak melewati scope); query yang butuh konteks memakai `runFor()` | Hanya di `TeamGlobalScopeTest`                                               |

Aturan validasi `Rule::exists(Project::class, 'id')->where('team_id', $teamId)` di `SaveTaskRequest` dan `SaveTransactionRequest` **dipertahankan**: `Rule::exists()` hanya memakai nama tabel tanpa scope, sehingga filter `team_id` di situ memang penjaga, bukan duplikasi.

---

## Tinjauan Scheduler — tugas 2.6.4

> Dijalankan 2026-10-27. Isi `routes/console.php` saat itu: dua tugas, keduanya global.

| Tugas                      | Menyentuh                           | Butuh konteks per tenant? | Alasan                                                                                                                                                                                                                                               |
| -------------------------- | ----------------------------------- | ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Hapus undangan kedaluwarsa | `team_invitations`                  | **Tidak**                 | Model ini sengaja tidak memakai `BelongsToTeam`: undangan dibaca **sebelum** user punya tenant (registrasi lewat undangan), jadi scope tenant akan menyembunyikan baris yang justru harus dihapus. Satu `DELETE` global = benar, bukan jalan pintas. |
| `db:backup`                | seluruh database (lewat dump biner) | **Tidak**                 | Backup bekerja di level database, bukan level model. Satu dump memang berisi semua tenant (P-5).                                                                                                                                                     |

**Bukti:** `schedule:run` dijalankan tanpa tenant aktif dan tetap berhasil. `PruneExpiredTeamInvitationsTest::test_the_scheduled_cleanup_reaches_every_tenant_at_once` mengunci sifat itu — dua tenant, satu putaran, dua undangan terhapus.

**Aturan untuk tugas terjadwal berikutnya** (ditulis juga sebagai komentar di `routes/console.php`):

1. Tugas yang menyentuh model ber-`BelongsToTeam` **wajib** iterasi tenant di dalam `TenantContext::runFor()`; tanpa itu `schedule:run` akan melempar `MissingTenantContext`.
2. Tugas yang butuh kerja berat per tenant sebaiknya **dispatch `App\Jobs\TenantJob`** alih-alih mengerjakannya inline — scheduler jadi cepat, dan tiap tenant mendapat konteks + retry-nya sendiri.
3. Jangan menambahkan `withoutTeamScope()` ke tugas terjadwal untuk "menghindari error" — itu menghapus isolasi tanpa terlihat.

---

## Technical Debt

| ID    | Utang                                                                                                   | Alasan diterima                          | Kapan ditinjau                                                                              |
| ----- | ------------------------------------------------------------------------------------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------------- |
| TD2-1 | ~~Sebagian `->forTeam()` masih tersisa setelah global scope (redundan)~~ **ditutup 2026-10-13 (2.2.6)** | —                                        | —                                                                                           |
| TD2-2 | Tidak ada audit historis aksi platform sebelum fase ini                                                 | Tidak dapat direkonstruksi               | —                                                                                           |
| TD2-3 | `suspended_at` mungkin belum menangani kasus "tenant disuspend karena gagal bayar"                      | Billing baru masuk gelombang 1           | Gelombang 1 — **mekanisme suspend sudah ada (2026-10-22); otomatisasinya menunggu billing** |
| TD2-4 | Log aggregation belum disiapkan; `team_id` di log belum dimanfaatkan penuh                              | Belum ada tooling                        | Fase 5 / DevOps                                                                             |
| TD2-5 | Belum ada pemantauan performa query setelah global scope                                                | Belum ada beban                          | Setelah data uji lebih besar                                                                |
| TD2-6 | `User::ownedTeams()` (relation "teams I own") kini tidak dipakai kode produksi                          | Tidak berbahaya, semantiknya masih benar | Saat ada fitur yang butuh daftar kepemilikan                                                |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.**

**2026-10-15 — tugas 2.4 (audit platform) + P-4 (glosarium) + P-5 (backup).**

- **2.4 audit platform.** Migrasi `2026_10_15_000001_create_platform_audit_logs_table.php` (`actor_id` nullable `nullOnDelete`, `action`, `target_type`, `target_id`, `details` JSON, `ip_address`, `created_at` saja), enum `App\Enums\PlatformAuditAction`, model + factory, dan satu jalur tulis `App\Actions\Platform\RecordPlatformAudit`. `PlatformTenantController` mencatat **tenant dibuat**, **halaman publik dinyalakan/dimatikan**, dan **tenant dihapus** (di dalam transaksi). Operator membaca lewat `php artisan platform:audit-log --limit= --action=`. Test: `tests/Feature/Platform/PlatformAuditLogTest.php` (7 test) — termasuk aksi yang **ditolak** tidak meninggalkan jejak, dan catatan tetap terbaca **tanpa** konteks tenant.
- **2.4 belum lengkap secara sengaja.** Suspend/aktifkan tenant (2.3, tugas 2.4.2 bagian kedua) belum ada, jadi belum dicatat. `PlatformAuditAction` tinggal menerima case baru saat 2.3 dikerjakan.
- **P-5 backup.** `config/backup.php` (`BACKUP_PATH`/`BACKUP_KEEP_DAYS`), `App\Support\DatabaseBackup`, command `db:backup` & `db:restore`, dan jadwal harian 02:00 di `routes/console.php`. SQLite disalin sebagai berkas; MySQL/MariaDB dan PostgreSQL memakai `mysqldump`/`mysql` dan `pg_dump`/`psql` dengan `--set=ON_ERROR_STOP=on`.
- **Bukti restore di lingkungan lokal.** Backup dijalankan atas salinan database dev, satu nama tenant diubah menjadi penanda, lalu `db:restore --force` dijalankan: nilai kembali seperti semula dan jumlah tenant tetap 9. Putaran itu juga ditulis sebagai test (`tests/Feature/Backup/DatabaseBackupTest.php`, 11 test) sehingga tidak bergantung pada ingatan. **Restore MySQL/PostgreSQL belum pernah diuji** — binernya tidak ada di mesin ini; itu sebabnya README menyebut prosedurnya harus dicoba di server tujuan sebelum dianggap berhasil.
- **P-4 glosarium.** `Team` = tenant ditulis di README (bagian Glosarium, dirujuk dari bagian Multi-Tenancy) dan ADR-02 di dokumen induk ditandai disetujui. **Tidak ada** tabel atau kolom yang di-rename.

**2026-10-22 — tugas 2.3 (siklus hidup tenant).**

- **2.3.1 `teams.suspended_at`.** Dipilih kolom timestamp nullable, bukan enum status: "tidak disuspend" adalah ketiadaan nilai, membatalkan suspend hanya mengosongkan satu kolom, dan waktu suspend tetap tersimpan untuk operator. Migrasi `2026_10_22_000001_add_suspended_at_to_teams_table.php`; `Team::isSuspended()` jadi satu-satunya cara bertanya.
- **2.3.2 guard.** Pemeriksaan diletakkan di `EnsureTeamMembership` **sebelum** cek role dan sebelum `switchTeam()`, supaya tenant disuspend tidak bisa membuka satu halaman shell pun dan konteks pengguna tidak ikut berpindah. Status **403 + pesan jelas** ("Tenant ini sedang disuspend…") untuk shell, sedangkan halaman publik **404** agar keberadaan tenant tidak dikonfirmasi. Ini **deviasi sadar** dari kalimat jadwal yang menulis 404 untuk keduanya — diputuskan pemilik produk 2026-10-22 karena anggota yang sudah login perlu tahu sebabnya, sementara pengunjung publik tidak.
- **2.3.3 aksi platform.** `PlatformTenantController::updateSuspension` mengikuti pola `updatePublicPage`; update kolom + catatan audit (`tenant.suspended` / `tenant.activated`) dibungkus **satu transaksi**, jadi tidak ada suspend tanpa jejak. ~~Tenant personal ditolak 403~~ — konsep personal team dihapus 2026-10-23 (lihat [cleanup-personal-team.md](cleanup-personal-team.md)), sehingga semua tenant kini setara.
- **2.3.4 UI.** Halaman `platform/tenants/Index.vue` mendapat kolom **Status** (badge Aktif/Disuspend dengan tooltip tanggal) dan tombol jeda/putar per tenant; ~~tombolnya disembunyikan untuk tenant personal~~ (dihapus 2026-10-23).
- **2.3.5 bukti.** `tests/Feature/Platform/TenantSuspensionTest.php` (9 test): suspend/aktivasi + audit, shell 403, akses pulih setelah aktivasi, halaman publik 404, form konsultasi 404 tanpa lead, ~~tenant personal ditolak tanpa jejak audit~~ (test dihapus 2026-10-23), owner tenant ditolak, validasi `suspended`, dan status tampil di daftar tenant.
- **Test lama disesuaikan.** `PlatformAuditLogTest` memakai `tenant.suspended` sebagai contoh "aksi tidak dikenal"; karena sekarang menjadi aksi nyata, contohnya diganti `tenant.unknown-action` — maksud test tidak berubah.
- **Belum dikerjakan (memang di luar 2.3).** Suspensi belum memutus sesi yang sedang berjalan (request berikutnya tetap ditolak), belum mengirim email ke owner tenant, dan belum otomatis dari billing (TD2-3).

**2026-10-23 — pembersihan konsep "personal team" (di luar penomoran fase).**

Rencana lengkap: [cleanup-personal-team.md](cleanup-personal-team.md).

- **Skema.** Migrasi `2026_10_23_000001_remove_personal_teams.php` hanya melakukan `dropColumn('is_personal')` (tanpa migrasi data), karena DB dev dibangun ulang dengan `migrate:fresh --seed`. Rollback mengembalikan kolom dengan default `false` — diuji bolak-balik di lokal.
- **Model & domain.** `Team` kehilangan properti/fillable/cast; `HasTeams::personalTeam()` dihapus; `belongsToAnotherTeam()` kini cukup membandingkan membership; `UserTeam` tidak lagi membawa `isPersonal`; `CreateTeam` kehilangan parameter `$isPersonal`.
- **Fallback.** `RedirectsToCurrentTeam`, `PlatformTenantController::destroy`, dan `TenantTeamController::destroyMember` memakai `fallbackTeam()` (bukan `personalTeam()`), sehingga user tanpa tenant lain berakhir dengan `current_team_id = null`.
- **Guard platform.** Dua `abort_if($team->is_personal, 403)` dihapus; UI `platform/tenants/Index.vue` tidak lagi menampilkan badge "Personal" dan tombol suspend/hapus muncul untuk semua tenant.
- **Factory (PT-4).** `UserFactory` tidak lagi membuat tim — fixture tenant dibuat eksplisit lewat `Tests\TestCase::createTeamFor()`. `TeamFactory::personal()` dihapus.
- **Test.** 24 test terdampak diperbaiki tanpa mengubah ekspektasi yang tidak terkait (dua di antaranya diganti maknanya: "owning a personal team…" → "user tanpa tenant bisa menerima undangan", dan test fallback anggota dikeluarkan kini menguji dua kasus: punya tenant lain vs tidak punya). Test baru: `tests/Feature/Tenancy/NoPersonalTeamTest.php` (kolom tidak ada, factory tidak membuat tim, payload platform tanpa `isPersonal`).
- **Hasil.** `migrate:fresh --seed` menyisakan satu tenant (`alfatech`) dengan 6 anggota, tidak ada user dengan lebih dari satu tim.

**2026-10-26 — tugas 2.5 (settings tenant) + 2.6.1, 2.6.3, 2.6.5 (cache & log sadar tenant).**

- **2.5.1 skema.** Migrasi `2026_10_26_000001_add_settings_to_teams_table.php` menambah `teams.settings` (JSON, nullable, `after('suspended_at')`). Aditif: tenant lama tetap memakai default, tidak ada backfill. Rollback diuji bolak-balik di SQLite lokal.
- **2.5.2 API.** `Team::setting(string $key, mixed $default = null)` membungkus seluruh akses baca; `Team::setSetting()` untuk sisi tulis. Urutan resolusi: nilai tersimpan → default pemanggil → `config('tenancy.defaults.{key}')`. `settings` di-cast `array` dan masuk `#[Fillable]` (mengikuti preseden `is_platform_admin`/`team_id`: factory & seeder butuh, tapi tidak ada form request yang memvalidasinya sehingga payload HTTP tidak bisa menyentuhnya).
- **2.5.3 default.** `config/tenancy.php` mendapat blok `defaults`: `locale` = `id`, `timezone` = `Asia/Jakarta`, `currency` = `IDR`. Ketiganya sengaja berbeda dari `config/app.php` (`en`/`UTC`) supaya default produk tidak bergantung pada default framework.
- **2.6.1 cache.** `App\Support\TenantCache` (`key/get/put/remember/forget`) mem-prefix setiap kunci dengan `tenant:{id}:` dan mengambil id dari `TenantContext`; tanpa tenant aktif ia melempar `MissingTenantContext::forCache()`. Tidak ada pemakaian cache produksi baru — ini seam, seperti `TenantStorage`.
- **2.6.3 log.** `TenantContext::set()` menambahkan `Log::withContext(['team_id' => …])`, `forget()` membersihkannya dengan `Log::withoutContext(['team_id'])`. Pemulihan di `runFor()` kini lewat `set()`/`forget()`, bukan menulis properti langsung — kalau tidak, `team_id` tenant dalam akan tertinggal. Efeknya: `ResetTenantContext` (per request) dan listener `JobProcessing` (per job) otomatis membersihkan konteks log juga.
- **2.6.5 bukti.** Tiga test baru: `TenantSettingsTest` (7), `TenantCacheTest` (9), `TenantLogContextTest` (6). Konteks log dibaca dari event `MessageLogged`, bukan dari berkas log, karena hanya itu yang benar-benar sampai ke handler. Termasuk dua test kebocoran: nilai cache tenant A tidak terbaca tenant B, dan job baru tidak mewarisi `team_id` job sebelumnya.
- **Belum dikerjakan (di luar 26 Okt).** 2.6.2 (pola job tenant-aware) dan 2.6.4 (tinjauan scheduler) menyusul 27 Okt bersama Gate Fase 2. Settings belum dibaca fitur mana pun — belum ada fitur yang membutuhkannya.

**2026-10-27 — tugas 2.6.2 (pola job) + 2.6.4 (tinjauan scheduler) + Gate Fase 2.**

- **2.6.2 pola job.** `App\Jobs\TenantJob` (abstract, `implements ShouldQueue`) membawa `public int $teamId` di payload, `handle()` **final**, dan subclass hanya mengisi `handleForTenant(Team $team)`. Di dalam `handle()`, tim di-`find` lalu `TenantContext::runFor()` dipakai membungkus pekerjaan. `final` adalah intinya: subclass tidak bisa lupa membungkus, sehingga kegagalan "query tanpa tenant" tidak mungkin berasal dari job baru.
- **Tenant yang sudah dihapus = bukan error.** Bila tim tidak ditemukan (termasuk soft-deleted, karena `SoftDeletes` menyaringnya), job menulis satu baris `warning` lalu berhenti. Alternatifnya (melempar) akan membuat job gagal dan di-retry untuk pekerjaan yang subjeknya sudah tidak ada.
- **`readonly` tidak bisa dipakai.** Percobaan pertama memakai `public readonly int $teamId`; `SerializesModels::__unserialize()` memulihkan properti lewat refleksi dari scope subclass, dan PHP menolak menginisialisasi ulang properti `readonly` di scope itu (`Cannot initialize readonly property … from scope …`). Karena itu properti biasa + komentar alasan.
- **Bukti 2.6.2.** `tests/Feature/Tenancy/TenantJobTest.php` (7 test): payload membawa `team_id` (serialize → unserialize), job berjalan dengan tenant-nya aktif (query `Project` ber-scope berhasil), jalur `dispatch()` sungguhan (queue `sync` di test) juga berhasil, konteks + `team_id` log bersih setelah selesai, konteks pemanggil dipulihkan (pola nesting `runFor()`), kunci cache di dalam job ber-namespace `tenant:{id}:…`, dan job untuk tenant terhapus dilewati tanpa exception.
- **2.6.4 tinjauan scheduler.** Dua tugas terjadwal ditinjau satu per satu: hapus undangan kedaluwarsa dan `db:backup`. Keduanya **tidak** butuh iterasi tenant — yang pertama karena `team_invitations` sengaja tidak tenant-scoped (undangan dibaca sebelum user punya tenant), yang kedua karena backup bekerja di level database. Kesimpulan + aturan untuk tugas berikutnya ditulis di `routes/console.php` dan di [Tinjauan Scheduler](#tinjauan-scheduler--tugas-264). Test tambahan: `test_the_scheduled_cleanup_reaches_every_tenant_at_once` (dua tenant, satu `schedule:run`, dua undangan terhapus, tanpa tenant aktif).
- **Gate Fase 2 — lolos di mesin lokal.** `composer test` hijau: pint bersih, phpstan level 7 nol error, **345 test / 1320 assertion**. `npm run types:check` dan `npm run check` hijau. Seluruh 10 acceptance criteria terverifikasi (lihat [Acceptance Criteria](#acceptance-criteria)); tidak ada ekspektasi test lama yang diubah untuk membuatnya hijau.
- **Tidak ada job produksi.** Yang ada hanya pola + test. Job pertama yang nyata (mis. pembersihan berkas tenant saat tenant dihapus) tinggal `extends TenantJob` dan mengisi `handleForTenant()`.

---

## Implementation Notes

1. **Jangan mengerjakan 2.2 (global scope) dan 2.2.6 (hapus `forTeam`) dalam satu perubahan.** Pasang dulu, buktikan test hijau, baru rapikan. _(Dipatuhi: 2.2 mendarat di `8d9f292`, penghapusan `forTeam` menyusul 2026-10-13.)_
2. **Verifikasi fail-loud benar-benar bekerja:** buat test yang menjalankan query model tenant-scoped tanpa konteks dan pastikan melempar exception.
3. **Waspadai jalur konsol.** Seeder & command berjalan tanpa request; mereka **wajib** memakai `TenantContext::runFor()` atau `withoutTeamScope()` secara eksplisit.
4. **Platform layer adalah kasus khusus** — operasinya memang lintas tenant. Jangan "memperbaiki"-nya dengan memberi konteks tenant yang salah.
5. Bila `withoutTeamScope()` muncul lebih dari 2–3 kali di kode produksi, hentikan dan tinjau ulang desainnya. _(Status 2026-10-13: nol pemakaian di kode produksi — lihat bagian Audit.)_
6. Perbarui `docs/IMPLEMENTATION_PLAN.md` Lampiran A bila kolom `settings`/`suspended_at` benar-benar dibuat.
