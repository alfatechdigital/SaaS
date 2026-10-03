# Fase 2 — Tenant Context & Isolasi Lanjutan

> **Status:** RENCANA — belum dimulai.
> **Ketergantungan:** Fase 1 selesai (khususnya test isolasi tenant).
> **Menambah fitur produk?** ❌ Tidak.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ⚠️ **Living document.** Perbarui bagian temuan/utang teknis saat pengerjaan berlangsung.

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

> Diperbarui 2026-10-12. Sebelumnya bagian ini menggambarkan kondisi sebelum 2.1–2.2.1 dikerjakan.

- Isolasi tenant kini **dijamin arsitektural**: `App\Scopes\TeamScope` memfilter setiap query model tenant-scoped, dan query tanpa tenant aktif **gagal keras** (`App\Exceptions\MissingTenantContext`).
- `App\Support\TenantContext` adalah satu sumber tenant aktif per request/job. `CurrentTeam::from()`/`activate()` menulisnya; `ResetTenantContext` (per request) dan listener `JobProcessing` (per job) membersihkannya.
- Seluruh model tenant-scoped sudah memakai scope: `company_profiles`, `projects`, `tasks`, `leads`, `content_items`, `transactions`, `portfolio_items`, `activity_logs`.
- `->forTeam()` di controller masih ada dan kini **redundan** — scope adalah otoritasnya. Penghapusan menyusul di 2.2.6.
- Route publik (`p/{team}`) tidak melewati segmen `{current_team}`, jadi controller-nya mengaktifkan tenant secara eksplisit.
- Belum ada job sama sekali; pola konteks job sudah disiapkan (`runFor()` + reset per job) tetapi belum dipakai di produksi.
- Cache: belum dipakai secara eksplisit; kunci cache belum ber-namespace.
- Log: belum membawa `team_id`.
- `teams` belum punya status selain soft delete; `public_page_enabled` adalah satu-satunya saklar moderasi.
- `activity_logs` mencatat aksi **di dalam** tenant; aksi **lintas tenant** oleh platform admin tidak tercatat.

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

---

## Database Changes

| Perubahan                                                         | Tipe             | Fase |
| ----------------------------------------------------------------- | ---------------- | ---- |
| `teams.suspended_at` (nullable timestamp) **atau** `teams.status` | Aditif, nullable | 2    |
| `teams.settings` (JSON, nullable)                                 | Aditif, nullable | 2    |
| `platform_audit_logs` (tabel baru)                                | Baru             | 2    |
| `cache`/`jobs` — tanpa perubahan skema                            | —                | 2    |

Semua bersifat **aditif dan backward compatible** (ADR-17). Tidak ada kolom dihapus, tidak ada tabel di-_rename_.

---

## Backend Changes

| Area                                            | Perubahan                                             |
| ----------------------------------------------- | ----------------------------------------------------- |
| `App\Support\TenantContext`                     | **Baru**                                              |
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

| Kebutuhan                                      | Status                            |
| ---------------------------------------------- | --------------------------------- |
| Queue worker berjalan (untuk pola konteks job) | Diperlukan bila job mulai dibuat  |
| Log aggregation (opsional)                     | Disarankan agar `team_id` berguna |
| Tidak ada perubahan infrastruktur lain         | —                                 |

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

| Test                 | Cakupan                                                            |
| -------------------- | ------------------------------------------------------------------ |
| Suite isolasi Fase 1 | Harus **tetap hijau** setelah global scope aktif — ini bukti utama |
| Konteks tenant       | Konteks tidak bocor antar request; `runFor()` bekerja              |
| Fail-loud            | Query model tenant-scoped tanpa konteks → exception                |
| Escape hatch         | `withoutTeamScope()` benar-benar melewati scope                    |
| Status tenant        | Suspend memblokir shell + halaman publik                           |
| Audit platform       | Setiap aksi platform tercatat                                      |
| Settings             | Default & override                                                 |
| Cache namespace      | Tidak bertabrakan antar-tenant                                     |

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

| ID    | Risiko                                                                       | Dampak     | Mitigasi                                                                 |
| ----- | ---------------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------ |
| RF2-A | Global scope memecah query platform/seeder/command                           | **Tinggi** | Kerjakan per model; test setiap langkah; escape hatch eksplisit (RSK-01) |
| RF2-B | Stale tenant context pada antrian panjang                                    | Tinggi     | Bersihkan konteks; test khusus                                           |
| RF2-C | Scope ganda (global + `forTeam()`) membingungkan developer                   | Rendah     | Dokumentasikan; perlahan rapikan yang redundan                           |
| RF2-D | Fail-loud memicu exception di tempat tak terduga (mis. Inertia shared props) | Sedang     | Uji semua halaman; tangani jalur konsol secara eksplisit                 |
| RF2-E | Suspend tenant memutus akses tanpa peringatan                                | Sedang     | UI konfirmasi + audit log                                                |

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

| Tanggal    | Temuan                                                                                                                                                                   | Dampak                                                                   | Tindakan                                                                                                                                                                             |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 2026-10-08 | **Suite isolasi tetap hijau meski `TeamScope` sengaja dirusak**, karena controller masih memanggil `->forTeam()`. Jadi suite isolasi belum menjadi penjaga global scope. | Global scope bisa rusak tanpa terdeteksi                                 | Tambah `tests/Feature/Tenancy/TeamGlobalScopeTest.php` (fail-loud + confinement + escape hatch per model). Suite isolasi baru efektif setelah `->forTeam()` redundan dihapus (2.2.6) |
| 2026-10-08 | Route publik (`p/{team}`) tidak melewati segmen `{current_team}`, sehingga tidak punya tenant aktif dan query ber-scope gagal.                                           | Halaman publik 500 begitu `company_profiles` ber-scope                   | `CurrentTeam::activate()` dipanggil di `PublicCompanyProfileController` dan `PublicLeadController`                                                                                   |
| 2026-10-08 | Query dari relasi yang di-_lazy load_ di dalam fixture test berjalan tanpa konteks tenant (`$task->project`).                                                            | Test gagal dengan exception, bukan assertion — membingungkan saat dibaca | Fixture memakai instance yang sudah dibuat. Catatan: `Model::fresh()` aman (`newQueryWithoutScopes`) dan `Rule::exists(Model::class)` aman (hanya nama tabel, tanpa scope)           |
| 2026-10-08 | `AlfatechDemoSeeder` melakukan query pada model ber-scope tanpa konteks.                                                                                                 | `PlatformSeederTest` gagal                                               | Seeder dibungkus `TenantContext::runFor()`. Bagian dari 2.2.5, dikerjakan lebih awal karena scope langsung memblokirnya                                                              |
| 2026-10-12 | `activity_logs` ikut ber-scope pada hari yang sama dengan model lain, bukan menyusul.                                                                                    | —                                                                        | Tidak ada penyesuaian khusus; `ActivityObserver` hanya menulis (`create`), tidak pernah query                                                                                        |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.**

| Tanggal    | Keputusan                                                                                                                              | Alasan                                                                                                                             |
| ---------- | -------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-08 | **D-03 disetujui** — global scope diterapkan pada seluruh model tenant-scoped                                                          | Isolasi tidak lagi bergantung pada ingatan developer; jaring pengaman P-1 sudah hijau dan wajib di CI                              |
| 2026-10-08 | Scope diaktifkan **per model** lewat saklar sementara `usesTeamScope()`, lalu saklar dihapus setelah semua model tercakup (2026-10-12) | Agar setiap kegagalan dapat diatribusikan ke satu model (RSK-01); scaffolding sementara dibuang setelah tidak dipakai              |
| 2026-10-08 | Scope di `BelongsToTeam` dibuat **unconditional** setelah seluruh model tercakup                                                       | Model yang memakai trait itu memang tenant-owned; satu-satunya jalan keluar adalah `withoutTeamScope()` yang eksplisit             |
| 2026-10-08 | Jalur khusus yang dibereskan lebih awal: route publik + seeder                                                                         | Keduanya langsung gagal begitu scope aktif; menundanya hanya membuat suite merah                                                   |
| 2026-10-08 | Fail-loud memakai exception khusus yang menyebut nama model                                                                            | Penyebab langsung terlihat dari stack trace, tanpa perlu debug tambahan                                                            |
| 2026-10-08 | `runFor()` **memulihkan** konteks sebelumnya (bukan sekadar menghapusnya)                                                              | `runFor()` bersarang tidak boleh melebarkan scope luar secara diam-diam, dan konteks tetap bersih bila callback melempar exception |

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

## Technical Debt

| ID    | Utang                                                                              | Alasan diterima                             | Kapan ditinjau               |
| ----- | ---------------------------------------------------------------------------------- | ------------------------------------------- | ---------------------------- |
| TD2-1 | Sebagian `->forTeam()` masih tersisa setelah global scope (redundan)               | Menghapusnya berisiko; lebih aman dibiarkan | Saat refactor modul terkait  |
| TD2-2 | Tidak ada audit historis aksi platform sebelum fase ini                            | Tidak dapat direkonstruksi                  | —                            |
| TD2-3 | `suspended_at` mungkin belum menangani kasus "tenant disuspend karena gagal bayar" | Billing baru masuk gelombang 1              | Gelombang 1                  |
| TD2-4 | Log aggregation belum disiapkan; `team_id` di log belum dimanfaatkan penuh         | Belum ada tooling                           | Fase 5 / DevOps              |
| TD2-5 | Belum ada pemantauan performa query setelah global scope                           | Belum ada beban                             | Setelah data uji lebih besar |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.**

_Belum ada entri._

---

## Implementation Notes

1. **Jangan mengerjakan 2.2 (global scope) dan 2.2.6 (hapus `forTeam`) dalam satu perubahan.** Pasang dulu, buktikan test hijau, baru rapikan.
2. **Verifikasi fail-loud benar-benar bekerja:** buat test yang menjalankan query model tenant-scoped tanpa konteks dan pastikan melempar exception.
3. **Waspadai jalur konsol.** Seeder & command berjalan tanpa request; mereka **wajib** memakai `TenantContext::runFor()` atau `withoutTeamScope()` secara eksplisit.
4. **Platform layer adalah kasus khusus** — operasinya memang lintas tenant. Jangan "memperbaiki"-nya dengan memberi konteks tenant yang salah.
5. Bila `withoutTeamScope()` muncul lebih dari 2–3 kali di kode produksi, hentikan dan tinjau ulang desainnya.
6. Perbarui `docs/IMPLEMENTATION_PLAN.md` Lampiran A bila kolom `settings`/`suspended_at` benar-benar dibuat.
