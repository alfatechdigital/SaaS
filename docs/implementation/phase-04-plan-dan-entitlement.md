# Fase 4 — Plan & Feature Entitlement

> **Status:** RENCANA — belum dimulai.
> **Ketergantungan:** Fase 2 selesai (tenant context & isolasi). Bisa paralel dengan Fase 3.
> **Menambah fitur produk?** ❌ Tidak. Fase ini membangun **mekanisme**, bukan fitur.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ✅ **Pembaruan 2026-09-30.** Pemilik produk menyetujui **SaaS billing masuk gelombang 1** (target 1 Jan 2027). Konsekuensinya:
> **batas fase ini tetap sama** — Fase 4 hanya membangun `Feature` enum + entitlement service; **implementasi billing** dikerjakan sebagai workstream terpisah **setelah Fase 4**, dijadwalkan di [`implementation-schedule.md`](implementation-schedule.md) v2.0.0.
> **D-05 (provider pembayaran) wajib diputuskan Oktober 2026** dan menjadi pemblokir billing — tapi **bukan** pemblokir Fase 4.

> ⚠️ **Living document.** Perbarui saat pengerjaan.

---

## Objective

Menyediakan **satu cara** untuk bertanya "apakah tenant ini boleh memakai fitur X?", sehingga saat paket Basic/Plus/Pro akhirnya difinalkan, penambahannya tidak memerlukan perubahan tersebar di seluruh aplikasi.

Fase ini juga menyiapkan **desain** langganan, tanpa mengimplementasikan billing.

---

## Scope

### Termasuk

- `Feature` enum sebagai satu sumber kebenaran daftar fitur.
- Definisi plan (Basic/Plus/Pro) di **config**, bukan kode domain.
- Service entitlement: `$tenant->can(Feature::X)`.
- Sharing entitlement ke frontend (composable mirip `usePermission`).
- Otorisasi backend berbasis entitlement (middleware/policy helper).
- **Desain** tabel `plans` dan `subscriptions` (dokumen).
- Desain kuota/usage limit (dokumen, belum implementasi).

### Tidak termasuk (di dalam fase ini)

- **Implementasi billing itu sendiri** → dikerjakan sebagai workstream **gelombang 1** setelah Fase 4 selesai.
- UI upgrade/downgrade paket → **gelombang 2**.
- Add-on dalam bentuk transaksi nyata → **gelombang 2**.
- Kuota yang benar-benar dihitung (selain kuota dasar) → **gelombang 2**.
- Fitur produk apa pun (custom domain, katalog, AI) → **gelombang 2 / Fase 5**.

> ⚠️ **Peringatan (diperbarui 2026-09-30):** fase ini tetap berhenti pada **abstraksi akses**. Namun billing **kini disetujui masuk gelombang 1**, sehingga billing **akan** ditulis — **setelah** Fase 4 selesai dan **setelah D-05 diputuskan (Oktober 2026)**. **Jangan** menulis billing di dalam Fase 4; selesaikan dulu `Feature` enum + entitlement service.

---

## Prerequisites

| Prasyarat                                                            | Status                                                  |
| -------------------------------------------------------------------- | ------------------------------------------------------- |
| Fase 2 selesai                                                       | ❌                                                      |
| **D-06** (definisi plan: config vs tabel) diputuskan                 | ❌ **Wajib**                                            |
| **ADR-06** & **ADR-14** disetujui                                    | ❌ **Wajib**                                            |
| **PDR-01** (isi tiap paket) minimal punya daftar _draft_ fitur       | ❌ Disarankan — tanpa ini, enum fitur bisa salah bentuk |
| **D-05** (provider pembayaran) — **tidak** diperlukan untuk fase ini | ✅ Tidak menghalangi                                    |

> Bila PDR-01 benar-benar belum ada, fase ini tetap dapat dikerjakan hingga tahap "enum + service + config" menggunakan fitur yang **sudah ada** (mis. `company_profile`, `content_management`). Fitur yang tidak mungkin ada sebelum dirilis tidak perlu dimasukkan enum sekarang.

---

## Current State

Setelah Fase 1–3:

- Otorisasi hanya berbasis **peran** (`TeamRole` → `TeamPermission` → prop `teamPermissions` → `usePermission()`).
- Tidak ada konsep plan/langganan/entitlement apa pun.
- Tidak ada kolom plan di `teams`.
- Belum ada UI yang menanyakan "fitur apa yang tersedia untuk tenant ini".
- Semua fitur yang ada (proyek, konten, lead, keuangan, portofolio, profil, log) dapat dipakai oleh tenant mana pun — sesuai karena belum ada paket.

**Titik penting:** karena belum ada fitur berbayar nyata, fase ini menghasilkan **mekanisme yang belum "terasa"**. Ini disengaja dan benar — nilainya baru terasa saat fitur berbayar pertama dirilis.

---

## Tasks

### 4.1 `Feature` Enum

| #     | Tugas                                                                     | Catatan                                                            |
| ----- | ------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| 4.1.1 | Buat `App\Enums\Feature`                                                  | Nilai string, mis. `'company_profile'`                             |
| 4.1.2 | Isi **hanya** fitur yang sudah ada atau yang sudah pasti akan ada         | Jangan daftarkan ide yang belum disetujui sebagai "fitur tersedia" |
| 4.1.3 | Beri label/metode bantu seperlunya (`label()`)                            | Konsisten dengan enum proyek ini                                   |
| 4.1.4 | Dokumentasikan bahwa penambahan kasus ke enum ≠ fitur sudah boleh dirilis | Mencegah kesalahpahaman                                            |

**Usulan isi awal (perlu konfirmasi PDR-01):**

| Fitur                                                                                      | Alasan ada di enum sekarang                                                               |
| ------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- |
| `CompanyProfile`                                                                           | Sudah ada di aplikasi                                                                     |
| `ContentManagement`                                                                        | Sudah ada (kalender konten)                                                               |
| `LeadManagement`                                                                           | Sudah ada (CRM)                                                                           |
| `ProjectManagement`                                                                        | Sudah ada                                                                                 |
| `FinanceOverview`                                                                          | Sudah ada                                                                                 |
| `CustomDomain`                                                                             | Ide tier (belum disetujui) — daftarkan **hanya** bila PDR-04 sudah mengarah ke "akan ada" |
| `WhatsAppIntegration`, `Catalog`, `PaymentGateway`, `AdvancedSeo`, `AiAssist`, `Analytics` | Ide tier — daftarkan **hanya** bila sudah ada arah jelas                                  |

> **Rekomendasi:** untuk ide yang belum disetujui, **jangan** masukkan ke enum. Cukup catat di dokumen. Enum yang berisi fitur hantu akan membingungkan developer dan menciptakan ekspektasi palsu.

### 4.2 Definisi Plan (Config — ADR-14)

| #     | Tugas                                                                           | Catatan                                                                                                                                                 |
| ----- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 4.2.1 | Buat `config/plans.php` berisi mapping plan → daftar fitur                      | Murni data                                                                                                                                              |
| 4.2.2 | Definisikan `basic`, `plus`, `pro` sesuai _draft_ PDR-01                        | Boleh berisi hanya fitur yang sudah ada                                                                                                                 |
| 4.2.3 | Tambahkan kunci `default_plan`                                                  | Tenant tanpa langganan → default                                                                                                                        |
| 4.2.4 | Sertakan duplikasi fitur tingkat bawah ke atas (atau sediakan resolusi warisan) | Pilih satu pola: duplikasi eksplisit atau resolusi berjenjang. **Rekomendasi:** resolusi berjenjang agar tidak ada daftar ganda yang bisa tidak sinkron |
| 4.2.5 | Test: config valid, tidak ada nama fitur yang salah ketik                       | Validasi enum                                                                                                                                           |

### 4.3 Service Entitlement (ADR-06)

| #     | Tugas                                                                             | Catatan                                          |
| ----- | --------------------------------------------------------------------------------- | ------------------------------------------------ |
| 4.3.1 | Buat service/`Entitlements` yang menyelesaikan fitur efektif untuk sebuah tenant  | Sumber: plan tenant + (nanti) add-on             |
| 4.3.2 | Tambahkan `Team::can(Feature $feature): bool`                                     | API utama yang dipakai di seluruh aplikasi       |
| 4.3.3 | Tambahkan `Team::features(): array` untuk kebutuhan UI                            |                                                  |
| 4.3.4 | Sediakan helper otorisasi backend, mis. `abort_unless($tenant->can(...), 403)`    | Untuk dipakai di controller/policy               |
| 4.3.5 | **Larangan tegas:** tidak boleh ada `if ($team->plan === 'pro')` di kode mana pun | Sertakan aturan di dokumentasi & cek code review |
| 4.3.6 | Test: tenant pada plan A tidak dapat mengakses fitur eksklusif plan B             |                                                  |
| 4.3.7 | Test: plan default terpakai bila tenant belum punya langganan                     |                                                  |

### 4.4 Integrasi ke Frontend

| #     | Tugas                                                                                                   | Catatan                               |
| ----- | ------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| 4.4.1 | Bagikan `features` dari `HandleInertiaRequests` (pola sama dengan `teamPermissions`)                    | Server tetap otoritas                 |
| 4.4.2 | Buat `useFeatures()` dengan `can(feature)` (pola sama dengan `usePermission()`)                         | Konsisten                             |
| 4.4.3 | Tambahkan tipe TypeScript di `resources/js/types/`                                                      |                                       |
| 4.4.4 | Pastikan prop lama `teamPermissions` **tidak** dihapus                                                  | Menghindari regresi besar             |
| 4.4.5 | Dokumentasikan perbedaan `can('canManageLeads')` (permission) vs `can('lead_management')` (entitlement) | Ini sumber kebingungan paling mungkin |

### 4.5 Desain Subscription (dokumen di fase ini — implementasi menyusul di gelombang 1)

| #     | Tugas                                                                                                            | Catatan                                                                                                                                                                                   |
| ----- | ---------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 4.5.1 | Tetapkan desain `plans` (bila kelak dipromosikan dari config)                                                    | `code` unique, `name`, `description`, `price`, `interval`, `is_active`                                                                                                                    |
| 4.5.2 | Tetapkan desain `subscriptions`                                                                                  | `team_id` (unique untuk langganan aktif), `plan_code`/`plan_id`, `status`, `trial_ends_at`, `current_period_start`, `current_period_end`, `canceled_at`, `provider`, `provider_reference` |
| 4.5.3 | Tetapkan mesin status                                                                                            | `trialing`/`active`/`past_due`/`canceled`/`expired`/`suspended`                                                                                                                           |
| 4.5.4 | Tulis implikasi provider Indonesia                                                                               | Midtrans/Xendit tidak mengelola siklus langganan penuh → status harus dikelola sendiri (job pengecekan periode)                                                                           |
| 4.5.5 | Tetapkan desain quota/usage                                                                                      | `usage_counters`: `team_id`, `metric`, `period`, `used`, `limit`                                                                                                                          |
| 4.5.6 | Tetapkan desain add-on                                                                                           | Tabel `tenant_addons`: `team_id`, `feature`, `starts_at`, `ends_at`, `status`                                                                                                             |
| 4.5.7 | **Jangan** membuat migrasi/tabel di dalam fase ini — implementasinya adalah workstream gelombang 1 yang terpisah | Batas scope Fase 4 = abstraksi akses                                                                                                                                                      |

### 4.6 Aturan Akses pada Fitur yang Sudah Ada

| #     | Tugas                                                                                      | Catatan               |
| ----- | ------------------------------------------------------------------------------------------ | --------------------- |
| 4.6.1 | **Jangan** langsung membatasi fitur yang sudah jalan (proyek, lead, dsb.) pada fase ini    | Itu perubahan produk! |
| 4.6.2 | Bila kelak dibatasi, lakukan bertahap + komunikasi ke tenant                               | Catat sebagai FUTURE  |
| 4.6.3 | Sediakan pola: entitlement diperiksa di controller/policy, bukan disembunyikan hanya di UI |                       |

> **Penting:** membatasi fitur yang saat ini bebas dipakai adalah **keputusan produk**, bukan keputusan teknis. Fase ini hanya menyiapkan alatnya.

---

## Database Changes

**Tidak ada perubahan skema wajib pada fase ini.**

| Perubahan               | Status                                                                                         |
| ----------------------- | ---------------------------------------------------------------------------------------------- |
| `config/plans.php`      | Bukan database                                                                                 |
| `plans`                 | **Desain dokumen.** Boleh dibuat hanya bila D-06 memilih tabel                                 |
| `subscriptions`         | **Desain dokumen di fase ini.** Tabel dibuat di **gelombang 1** (disetujui 2026-09-30)         |
| `usage_counters`        | **Desain dokumen.** Tidak dibuat                                                               |
| `tenant_addons`         | **Desain dokumen.** Tidak dibuat                                                               |
| Kolom `plan` di `teams` | **Jangan** tambahkan kolom plan mentah — itu jalan pintas yang melahirkan `if ($plan === ...)` |

**Bila D-06 memilih tabel `plans`:** tabel berisi **definisi** paket, sedangkan relasi tenant→paket tetap lewat `subscriptions` (yang belum dibuat). Untuk sementara, tenant mengambil paket dari config default. **Hindari** menambahkan kolom `teams.plan` sebagai solusi cepat.

---

## Backend Changes

| Area                                                          | Perubahan                                                                              |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `App\Enums\Feature`                                           | **Baru**                                                                               |
| `config/plans.php`                                            | **Baru**                                                                               |
| `App\Support\Entitlements` (atau `App\Services\Entitlements`) | **Baru**                                                                               |
| `App\Models\Team`                                             | Tambah `can()`, `features()`                                                           |
| `App\Http\Middleware\HandleInertiaRequests`                   | Bagikan `features`                                                                     |
| Helper otorisasi                                              | **Baru** (mis. `App\Concerns\AuthorizesFeature` atau perluasan `AuthorizesTeamModule`) |
| Controller domain                                             | **Tidak** diubah (belum ada pembatasan)                                                |
| Test                                                          | Suite entitlement                                                                      |

---

## Frontend Changes

| Area                                      | Perubahan                                                                |
| ----------------------------------------- | ------------------------------------------------------------------------ |
| `resources/js/composables/useFeatures.ts` | **Baru**                                                                 |
| `resources/js/types/`                     | Tipe fitur                                                               |
| `resources/js/app.ts`                     | Tidak berubah                                                            |
| Halaman internal                          | **Tidak** diubah (belum ada pembatasan)                                  |
| Halaman "paket saya"                      | **Tidak dibuat di fase ini** — dikerjakan di gelombang 1 bersama billing |

---

## Infrastructure Changes

Tidak ada.

Fase ini murni kode aplikasi (enum, config, service, composable). Tidak ada dampak deployment, database engine, atau layanan eksternal.

---

## Security Considerations

| ID    | Risiko                                                                  | Tindakan                                                                  |
| ----- | ----------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| RF4-1 | Entitlement hanya ditegakkan di UI → bisa dilewati via request langsung | **Wajib** ditegakkan di backend (controller/policy), UI hanya lapis kedua |
| RF4-2 | Pencampuran `TeamPermission` dan `Feature` → kebingungan otorisasi      | Pisahkan namespace & dokumentasikan; test keduanya secara terpisah        |
| RF4-3 | Plan diubah dari input user (mis. request manipulasi)                   | Plan **tidak boleh** berasal dari input request                           |
| RF4-4 | Entitlement di-cache tanpa invalidasi saat plan berubah                 | Bila kelak ada cache, invalidasi wajib; catat sebagai risiko              |
| RF4-5 | Error message membocorkan daftar fitur plan lain                        | Pesan 403 generik                                                         |

**Prinsip:** `TeamPermission` (siapa) × `Feature` (apa) — akses diberikan **hanya** bila keduanya terpenuhi.

---

## Testing Requirements

| Test                 | Cakupan                                                         |
| -------------------- | --------------------------------------------------------------- |
| Resolusi entitlement | Plan dasar → hanya fitur dasarnya                               |
| Warisan plan         | Plus mencakup fitur Basic (bila memakai resolusi berjenjang)    |
| Default plan         | Tenant tanpa langganan memakai plan default                     |
| Penolakan akses      | Endpoint ber-entitlement menolak tenant yang tidak berhak (403) |
| Validasi config      | Semua nama fitur di `config/plans.php` ada di enum `Feature`    |
| Frontend prop        | `features` terbagikan & bertipe benar                           |
| Regresi              | `teamPermissions` masih bekerja seperti sebelumnya              |

Perintah: `composer test` dan `npm run types:check`.

---

## Migration Considerations

- **Tidak ada migrasi** pada fase ini.
- Karena plan diambil dari config, **tidak ada data tenant yang perlu diubah**.
- Bila kelak plan dipromosikan dari config ke tabel `plans`, sediakan seeder yang memuat isi config ke tabel, plus tugas migrasi kecil. Catat sebagai FUTURE.
- **Jangan** menulis migrasi `subscriptions` sebelum D-05 tuntas.

---

## Acceptance Criteria

1. ✅ `Feature` enum ada dan berisi **hanya** fitur yang sudah ada atau sudah disetujui arahnya.
2. ✅ Plan didefinisikan di config, dengan test validasi terhadap enum.
3. ✅ `$team->can(Feature::X)` bekerja dan menjadi satu-satunya cara memeriksa akses fitur.
4. ✅ **Tidak ada** satu pun `if ($plan === ...)` di seluruh kode (diverifikasi dengan pencarian teks).
5. ✅ Prop `features` terbagikan ke frontend; `useFeatures()` tersedia.
6. ✅ `teamPermissions` lama tidak berubah perilakunya.
7. ✅ Ada test: tenant plan A tidak bisa mengakses fitur plan B (di level backend).
8. ✅ **Tidak ada** tabel `plans`/`subscriptions`/`usage_counters`/`tenant_addons` yang dibuat **di dalam fase ini** (implementasinya adalah workstream gelombang 1 yang terpisah).
9. ✅ **Tidak ada** fitur yang sudah berjalan menjadi terbatas (tidak ada perubahan produk).
10. ✅ Desain `subscriptions`/quota/add-on terdokumentasi, termasuk catatan provider Indonesia.
11. ✅ `composer test` dan `npm run types:check` hijau.

---

## Risks

| ID    | Risiko                                                                             | Dampak     | Mitigasi                                               |
| ----- | ---------------------------------------------------------------------------------- | ---------- | ------------------------------------------------------ |
| RF4-A | Fase meluas menjadi pembangunan billing                                            | **Tinggi** | Batas scope tegas: berhenti di abstraksi akses         |
| RF4-B | Enum fitur mengeras sebelum PDR-01 final → perlu diubah total                      | Sedang     | Hanya masukkan fitur yang sudah pasti                  |
| RF4-C | Entitlement hanya di UI → celah keamanan                                           | Tinggi     | Acceptance criteria #7; tegakkan di backend            |
| RF4-D | `TeamPermission` dan `Feature` tertukar pemakaiannya                               | Sedang     | Dokumentasi + penamaan jelas + test terpisah           |
| RF4-E | Plan di config sulit diubah saat runtime, sehingga bisnis memaksa tabel lebih awal | Rendah     | D-06 sudah memutuskan; promosi ke tabel terdokumentasi |
| RF4-F | Membatasi fitur yang tadinya gratis memicu keluhan tenant                          | Sedang     | Bukan keputusan teknis → libatkan produk (task 4.6.1)  |

---

## Open Questions

| ID      | Pertanyaan                                                                                                                                                           |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| OQ-F4-1 | Apakah fitur yang sudah ada (proyek, lead, keuangan) akan dibagi ke paket, atau tetap gratis untuk semua? Ini menentukan apakah entitlement punya "gigi" sejak awal. |
| OQ-F4-2 | Apakah warisan fitur berjenjang (Plus ⊇ Basic) atau tiap paket mendaftar ulang?                                                                                      |
| OQ-F4-3 | Apakah tenant bisa menurunkan paket kapan saja? (berdampak pada validasi data saat downgrade)                                                                        |
| OQ-F4-4 | Apakah fitur dasar wajib selalu ada di plan default, agar tenant baru tidak kosong melompong?                                                                        |
| OQ-F4-5 | Apakah kuota perlu ditampilkan ke tenant (mis. "37/100 konten")? Bila ya, kapan?                                                                                     |

---

## Known Issues

| #     | Isu                                                                                                      | Status                                                                 |
| ----- | -------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| KI4-1 | Hingga ada fitur berbayar, entitlement tidak akan terasa efeknya                                         | Diterima — disengaja                                                   |
| KI4-2 | Belum ada UI untuk melihat/mengubah paket                                                                | Fase 5                                                                 |
| KI4-3 | Belum ada representasi data paket tenant (masih config)                                                  | Menunggu D-05/D-06                                                     |
| KI4-4 | Risiko penamaan ganda: `can()` di `usePermission` (permission) vs `can()` di `useFeatures` (entitlement) | Perlu dokumentasi jelas; pertimbangkan nama berbeda bila membingungkan |

---

## Discovered During Implementation

> **Diisi oleh agen/developer saat pengerjaan.**

_Belum ada entri._

| Tanggal | Temuan | Dampak | Tindakan |
| ------- | ------ | ------ | -------- |
| —       | —      | —      | —        |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.**

_Belum ada entri._

| Tanggal | Keputusan | Alasan |
| ------- | --------- | ------ |
| —       | —         | —      |

---

## Deferred

| Item                                   | Alasan                           | Fase tujuan                                        |
| -------------------------------------- | -------------------------------- | -------------------------------------------------- |
| Tabel `plans`                          | Config lebih sederhana sekarang  | Gelombang 1 (config dulu)                          |
| Tabel `subscriptions`                  | Butuh D-05                       | **Gelombang 1**                                    |
| Payment gateway (untuk pesanan)        | Butuh katalog (B-4)              | **Gelombang 2**                                    |
| Invoice, dunning, trial otomatis       | Bagian billing                   | **Gelombang 1** (dunning lanjutan → gelombang 2)   |
| `usage_counters` & kuota nyata         | Hanya kuota dasar yang disetujui | **Gelombang 1** (kuota dasar); sisanya gelombang 2 |
| `tenant_addons`                        | Add-on belum disetujui           | Gelombang 2                                        |
| UI paket/upgrade                       | Fitur produk                     | **Gelombang 2**                                    |
| Pembatasan fitur yang sudah gratis     | Keputusan produk                 | Setelah keputusan produk                           |
| Kuota AI (relevan saat fitur AI rilis) | AI belum ada                     | Fase 5                                             |

---

## Technical Debt

| ID    | Utang                                                          | Alasan diterima                   | Kapan ditinjau                        |
| ----- | -------------------------------------------------------------- | --------------------------------- | ------------------------------------- |
| TD4-1 | Plan masih di config, belum dapat diubah runtime               | D-06 memilih config               | Saat panel platform dibutuhkan        |
| TD4-2 | Belum ada representasi langganan di database                   | Menunggu billing                  | Fase 5                                |
| TD4-3 | Entitlement belum dipakai untuk membatasi apa pun              | Tidak ada fitur berbayar          | Saat fitur berbayar pertama disetujui |
| TD4-4 | Belum ada mekanisme downgrade (validasi data saat paket turun) | Belum relevan                     | Fase 5                                |
| TD4-5 | Belum ada telemetri pemakaian fitur                            | Butuh storage & keputusan privasi | Fase 5                                |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.**

_Belum ada entri._

---

## Implementation Notes

1. **Jaga batas scope.** Bila mulai muncul pembicaraan "provider pembayaran", "invoice", atau "webhook" — hentikan; itu Fase 5.
2. **Jangan** menambahkan kolom `plan` di `teams` sebagai jalan pintas. Itu akan melahirkan `if ($plan === ...)` yang justru ingin dihindari.
3. **Uji larangan** dengan pencarian teks sederhana untuk pola `=== 'pro'`, `== 'plus'`, `plan ===` di seluruh `app/` — harus nol hasil.
4. Bila PDR-01 belum ada, mulailah dari fitur yang **sudah nyata** di aplikasi, dan biarkan paket berisi subset dari fitur tersebut.
5. Perbarui master plan Lampiran B (ADR-06, ADR-14) bila keputusan berubah, dan catat perubahan itu di [Decisions Made During Implementation](#decisions-made-during-implementation).
6. Pertimbangkan memberi nama method yang berbeda dari `can()` bila kebingungan dengan `usePermission()` menjadi masalah nyata — mis. `hasFeature()` di sisi backend.
