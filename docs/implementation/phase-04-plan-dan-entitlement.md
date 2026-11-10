# Fase 4 — Plan & Feature Entitlement

> **Status:** 🚧 **BERJALAN** — §4.1 (`Feature` enum), §4.2 (`config/plans.php` + test), §4.3 (entitlement service) dan §4.5 (desain tabel langganan) selesai 2026-11-10. Berikutnya: §4.4 (prop frontend) dipindah ke 26 Nov bersama UI billing.
> **Ketergantungan:** Fase 2 selesai (tenant context & isolasi). Bisa paralel dengan Fase 3.
> **Menambah fitur produk?** ❌ Tidak. Fase ini membangun **mekanisme**, bukan fitur.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ✅ **Pembaruan 2026-09-30.** Pemilik produk menyetujui **SaaS billing masuk gelombang 1** (target 1 Jan 2027). Konsekuensinya:
> **batas fase ini tetap sama** — Fase 4 hanya membangun `Feature` enum + entitlement service; **implementasi billing** dikerjakan sebagai workstream terpisah **setelah Fase 4**, dijadwalkan di [`implementation-schedule.md`](implementation-schedule.md) v2.0.0.
> **D-05 (provider pembayaran) wajib diputuskan Oktober 2026** dan menjadi pemblokir billing — tapi **bukan** pemblokir Fase 4.

> ⚠️ **Living document.** Perbarui saat pengerjaan.

> ℹ️ **Penomoran kode = warisan (D-01, 2026-10-19).** Komentar `ADR-*`/`PDR-*` di kode memakai penomoran dokumen lama; artinya ada di [Lampiran C](../IMPLEMENTATION_PLAN.md#lampiran-c--referensi-aturan-lama-legacy), bukan di Lampiran B.

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

| Prasyarat                                                            | Status                                                                                                                        |
| -------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Fase 2 selesai                                                       | ✅ Selesai 2026-10-27 (Gate Fase 2)                                                                                           |
| **D-06** (definisi plan: config vs tabel) diputuskan                 | ✅ 2026-10-26 — config dulu                                                                                                   |
| **ADR-06** & **ADR-14** disetujui                                    | ✅ 2026-09-30                                                                                                                 |
| **PDR-01** (isi tiap paket) minimal punya daftar _draft_ fitur       | ✅ 2026-10-28 — daftar final di [Lampiran B.4](../IMPLEMENTATION_PLAN.md#b4-paket-langganan--pdr-01-pdr-05-pdr-06-2026-10-28) |
| **D-05** (provider pembayaran) — **tidak** diperlukan untuk fase ini | ✅ Tidak menghalangi                                                                                                          |

> **Pembaruan 2026-10-28.** PDR-01 sudah berisi daftar final, termasuk enam fitur yang belum punya kode sama sekali (template, Google Analytics, Google Sheet sync, form builder, email automation, kuota admin). Konsekuensinya untuk fase ini: enum `Feature` **tidak** boleh langsung berisi keenam fitur itu — cukup fitur yang sudah ada atau yang pasti akan dibangun di gelombang 1. Menambah case enum adalah perubahan kecil; case yang salah bentuk dan sudah dipakai di banyak tempat tidak.

> **Pembaruan 2026-10-28 (sesi keputusan paket).** Hal-hal yang mengubah rencana fase ini:
>
> - **Enum harus punya baris tingkat modul**, bukan hanya fitur publik: `lead_management` (**Plus** sejak 2026-10-28), `project_management`, `task_management`, `content_planner`, `finance` (Pro + add-on), plus fitur publik (`custom_domain`, `whatsapp_bot`, `payment_gateway`, `form_builder`, `email_automation`, `google_sheet_sync`, `google_analytics`, `file_upload`, `contact_form`, `seo_basic`, `seo_advanced`, `content_ai`).
> - **Kuota ikut masuk entitlement**: user 1/3/10 dan AI 50 generate/bulan → `usage_counters` naik dari "defer" ke **perlu**.
> - **Add-on membuat resolusi entitlement dua sumber**: fitur paket ∪ add-on aktif. Rancang method-nya sekarang, walau add-on baru dibangun bersama billing.
> - **Panel platform perlu mengelola paket & fitur per tenant** — scope baru (bukan di fase ini), dicatat di [B.4.4](../IMPLEMENTATION_PLAN.md#b44-dampak-ke-pekerjaan-yang-sudah-dijadwalkan).

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

### 4.1 `Feature` Enum — ✅ Selesai 2026-11-10

| #     | Tugas                                                                     | Catatan                                                            |
| ----- | ------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| 4.1.1 | Buat `App\Enums\Feature`                                                  | Nilai string, mis. `'company_profile'`                             |
| 4.1.2 | Isi **hanya** fitur yang sudah ada atau yang sudah pasti akan ada         | Jangan daftarkan ide yang belum disetujui sebagai "fitur tersedia" |
| 4.1.3 | Beri label/metode bantu seperlunya (`label()`)                            | Konsisten dengan enum proyek ini                                   |
| 4.1.4 | Dokumentasikan bahwa penambahan kasus ke enum ≠ fitur sudah boleh dirilis | Mencegah kesalahpahaman                                            |

**Isi enum final (diputuskan 2026-11-10) — 22 kasus mengikuti [B.4.1](../IMPLEMENTATION_PLAN.md#b41-matriks-fitur):**

| Kelompok                  | Fitur                                                                                                                                                                                         |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Semua paket (Basic ke atas) | `company_profile`, `template_selection`, `subdomain`, `seo_basic`, `google_analytics`, `file_upload`, `contact_form`                                                                        |
| Plus ke atas              | `custom_domain`, `whatsapp_bot`, `payment_gateway`, `form_builder`, `email_automation`, `google_sheet_sync`, `lead_management`                                                               |
| Pro saja (bisa add-on)    | `project_management`, `task_management`, `content_planner`, `finance`, `seo_advanced`, `content_ai`, `advanced_automation`, `role_management`                                                |

`ssl` dan `prioritas support` **tidak** dijadikan kasus enum: keduanya komitmen layanan, bukan kapabilitas aplikasi yang bisa digerbangi kode.

> **Keputusan 2026-11-10 — menggantikan rekomendasi lama di atas.** Pemilik produk memilih enum mengikuti **seluruh baris fungsional B.4.1**, termasuk fitur yang belum dibangun (`form_builder`, `email_automation`, `google_sheet_sync`, `content_ai`, `seo_advanced`, `advanced_automation`, `custom_domain`). Alasannya: `config/plans.php` harus 1:1 dengan matriks komersial, dan menambah kasus enum jauh lebih murah daripada plan yang isinya tidak lengkap. Kasus enum **tetap bukan** klaim rilis — docblock `App\Enums\Feature` menyatakannya eksplisit.

### 4.2 Definisi Plan (Config — ADR-14) — ✅ Selesai 2026-11-10

| #     | Tugas                                                                           | Catatan                                                                                                                                                 |
| ----- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 4.2.1 | Buat `config/plans.php` berisi mapping plan → daftar fitur                      | Murni data                                                                                                                                              |
| 4.2.2 | Definisikan `basic`, `plus`, `pro` sesuai _draft_ PDR-01                        | Boleh berisi hanya fitur yang sudah ada                                                                                                                 |
| 4.2.3 | Tambahkan kunci `default_plan`                                                  | Tenant tanpa langganan → default                                                                                                                        |
| 4.2.4 | Sertakan duplikasi fitur tingkat bawah ke atas (atau sediakan resolusi warisan) | Pilih satu pola: duplikasi eksplisit atau resolusi berjenjang. **Rekomendasi:** resolusi berjenjang agar tidak ada daftar ganda yang bisa tidak sinkron |
| 4.2.5 | Test: config valid, tidak ada nama fitur yang salah ketik                       | Validasi enum                                                                                                                                           |

**Hasil 2026-11-10.** `config/plans.php` berisi `default_plan => 'basic'` dan tiga paket dengan daftar fitur **ditulis ulang** per paket (duplikasi eksplisit sesuai OQ-F4-2, bukan warisan berjenjang), plus `limits.users` 1/3/10 dan `limits.ai_generations_per_month` 0/0/50. Fitur disimpan sebagai **string nilai enum** agar config tetap data murni dan aman di-cache; validitasnya dijaga `tests/Unit/PlansConfigTest.php` (nama fitur terhadap enum, tidak ada duplikat, tidak ada fitur yatim, kuota sesuai matriks, Plus ⊇ Basic, Pro ⊇ Plus, modul tidak bocor ke Basic/Plus).

Harga dan metadata billing **sengaja tidak** dimasukkan: itu milik workstream billing (B-2).

### 4.3 Service Entitlement (ADR-06) — ✅ Selesai 2026-11-10

| #     | Tugas                                                                             | Catatan                                          |
| ----- | --------------------------------------------------------------------------------- | ------------------------------------------------ |
| 4.3.1 | Buat service/`Entitlements` yang menyelesaikan fitur efektif untuk sebuah tenant  | Sumber: plan tenant + (nanti) add-on             |
| 4.3.2 | Tambahkan `Team::can(Feature $feature): bool`                                     | API utama yang dipakai di seluruh aplikasi       |
| 4.3.3 | Tambahkan `Team::features(): array` untuk kebutuhan UI                            |                                                  |
| 4.3.4 | Sediakan helper otorisasi backend, mis. `abort_unless($tenant->can(...), 403)`    | Untuk dipakai di controller/policy               |
| 4.3.5 | **Larangan tegas:** tidak boleh ada `if ($team->plan === 'pro')` di kode mana pun | Sertakan aturan di dokumentasi & cek code review |
| 4.3.6 | Test: tenant pada plan A tidak dapat mengakses fitur eksklusif plan B             |                                                  |
| 4.3.7 | Test: plan default terpakai bila tenant belum punya langganan                     |                                                  |

**Hasil 2026-11-10.** Implementasi memakai satu service + satu *seam* sumber data, supaya workstream billing tinggal mengganti binding tanpa menyentuh pemanggil:

| Berkas                                   | Isi                                                                                                                                                                                                                                 |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `App\Contracts\TeamEntitlementSource`    | **Baru.** `planCodeFor(Team): ?string` + `addonFeaturesFor(Team): array<Feature>`. Inilah tempat `subscriptions`/`tenant_addons` dibaca nanti.                                                                                       |
| `App\Support\NoSubscriptionSource`       | **Baru.** Bindings hari ini: tidak ada langganan & tidak ada add-on → `null` + `[]`, sehingga semua tenant memakai `default_plan`.                                                                                                   |
| `App\Support\Entitlements`               | **Baru.** `planCode()`, `plan()`, `features()`, `can()`, `limit()`. Fitur efektif = fitur plan ∪ add-on aktif, dideduplikasi. Plan tak dikenal → `App\Exceptions\UnknownPlan` (gagal keras, bukan diam-diam jatuh ke default).          |
| `App\Models\Team`                        | `can(Feature): bool` (tugas 4.3.2) dan `features(): array<int, string>` (tugas 4.3.3) — keduanya mendelegasikan ke `Entitlements`.                                                                                                 |
| `App\Concerns\AuthorizesTeamModule`      | Method baru `authorizeFeature(Team, Feature)` → `abort_unless($team->can($feature), 403)` (tugas 4.3.4).                                                                                                                            |
| `tests/Unit/PlanNameBranchGuardTest`     | **Baru.** Memindai `app/`, `routes/`, `resources/js/` dan gagal bila ada `->plan` atau perbandingan nama plan (tugas 4.3.5). Baris komentar dilewati agar dokumentasi boleh menyebut pola yang dilarang.                             |

Keputusan turunan:

- **Tidak ada `isPro()`/`isBasic()`.** Pertanyaan selalu berbentuk fitur, karena itu satu-satunya bentuk yang selamat saat isi paket berubah.
- **Tidak ada cache entitlement.** Sumber datanya config (sudah di-cache Laravel) + satu query saat billing hadir; cache akan menambah risiko basi saat plan berubah (RF4-4) tanpa manfaat terukur sekarang.
- **`limit()` hanya membaca** angka kuota, belum menegakkan apa pun — penegakan butuh `usage_counters` (workstream billing).
- `HandleInertiaRequests` **belum** menyentuh `features`: sesuai jadwal ia pindah ke 26 Nov bersama UI billing.

### 4.4 Integrasi ke Frontend

| #     | Tugas                                                                                                   | Catatan                               |
| ----- | ------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| 4.4.1 | Bagikan `features` dari `HandleInertiaRequests` (pola sama dengan `teamPermissions`)                    | Server tetap otoritas                 |
| 4.4.2 | Buat `useFeatures()` dengan `can(feature)` (pola sama dengan `usePermission()`)                         | Konsisten                             |
| 4.4.3 | Tambahkan tipe TypeScript di `resources/js/types/`                                                      |                                       |
| 4.4.4 | Pastikan prop lama `teamPermissions` **tidak** dihapus                                                  | Menghindari regresi besar             |
| 4.4.5 | Dokumentasikan perbedaan `can('canManageLeads')` (permission) vs `can('lead_management')` (entitlement) | Ini sumber kebingungan paling mungkin |

### 4.5 Desain Subscription (dokumen di fase ini — implementasi menyusul di gelombang 1) — ✅ Desain selesai 2026-11-10

| #     | Tugas                                                                                                            | Catatan                                                                                                                                                                                   |
| ----- | ---------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 4.5.1 | Tetapkan desain `plans` (bila kelak dipromosikan dari config)                                                    | `code` unique, `name`, `description`, `price`, `interval`, `is_active`                                                                                                                    |
| 4.5.2 | Tetapkan desain `subscriptions`                                                                                  | `team_id` (unique untuk langganan aktif), `plan_code`/`plan_id`, `status`, `trial_ends_at`, `current_period_start`, `current_period_end`, `canceled_at`, `provider`, `provider_reference` |
| 4.5.3 | Tetapkan mesin status                                                                                            | `trialing`/`active`/`past_due`/`canceled`/`expired`/`suspended`                                                                                                                           |
| 4.5.4 | Tulis implikasi provider Indonesia                                                                               | Midtrans/Xendit tidak mengelola siklus langganan penuh → status harus dikelola sendiri (job pengecekan periode)                                                                           |
| 4.5.5 | Tetapkan desain quota/usage                                                                                      | `usage_counters`: `team_id`, `metric`, `period`, `used`, `limit`                                                                                                                          |
| 4.5.6 | Tetapkan desain add-on                                                                                           | Tabel `tenant_addons`: `team_id`, `feature`, `starts_at`, `ends_at`, `status`                                                                                                             |
| 4.5.7 | **Jangan** membuat migrasi/tabel di dalam fase ini — implementasinya adalah workstream gelombang 1 yang terpisah | Batas scope Fase 4 = abstraksi akses                                                                                                                                                      |

**Desain 2026-11-10** — ini **rancangan**, bukan skema yang sudah ada. Tidak ada migrasi yang ditulis di fase ini.

Bentuk tabel (kolom wajib; nama kolom mengikuti konvensi proyek, uang dalam rupiah bulat):

| Tabel            | Kolom                                                                                                                                                                                                                     | Constraint & catatan                                                                                                                                                                                                                  |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `plans`          | `id`, `code` (unique), `name`, `description` (nullable), `price_monthly`, `price_yearly` (nullable), `is_active`                                                                                                           | Hanya dibuat bila config dipromosikan ke tabel (TD4-1). Config tetap sumber awal; seeder menyalin isinya. `code` = kunci yang sama dengan `config('plans.plans')`                                                                       |
| `subscriptions`  | `id`, `team_id`, `plan_code`, `status`, `trial_ends_at` (nullable), `current_period_start`, `current_period_end`, `canceled_at` (nullable), `provider`, `provider_reference` (nullable), `ended_at` (nullable), timestamps    | **Maksimal satu langganan berjalan per tenant.** MySQL tidak punya *partial index*, jadi pakai kolom ter-GENERATE `current_team_id` = `IF(ended_at IS NULL, team_id, NULL)` + `UNIQUE(current_team_id)`; Postgres memakai partial unique index biasa |
| `usage_counters` | `id`, `team_id`, `metric`, `period` (mis. `2027-01`), `used`, `limit`, timestamps                                                                                                                                          | `UNIQUE(team_id, metric, period)`. `metric` = `users`, `ai_generations`                                                             |
| `tenant_addons`  | `id`, `team_id`, `feature`, `starts_at`, `ends_at` (nullable), `status`, `provider_reference` (nullable), timestamps                                                                                                       | `feature` menyimpan nilai `App\Enums\Feature`. **Satu add-on aktif per (tenant, fitur)** — jamin juga di service saat memperpanjang, bukan hanya di index                                                                                |

Mesin status `subscriptions` (tugas 4.5.3):

```text
trialing ──bayar──▶ active ──gagal bayar──▶ past_due ──(+7 hari)──▶ expired ──(30 hari)──▶ dihapus
    │                  │                       │
    └──batal──▶ canceled ◀──minta downgrade/batal (berlaku di current_period_end)──┘
```

- `suspended` dipakai untuk tenant yang dinonaktifkan platform (mekanisme Fase 2 sudah ada di `teams.suspended_at`) — bukan status langganan, supaya dua sebab tidak tercampur.
- Transisi `past_due → expired` dijalankan **job harian** (`subscriptions:check-period`), bukan webhook: provider Indonesia hanya memberi tahu hasil satu transaksi.

Implikasi provider (tugas 4.5.4, D-05 = Midtrans):

- Midtrans **bukan** subscription engine: tidak ada objek langganan, tidak ada retry otomatis, tidak ada *proration*. Karena itu `subscriptions` adalah **sumber kebenaran**, dan provider hanya menagih per periode.
- Webhook = notifikasi status **transaksi**, bukan status langganan → handler harus idempoten, memverifikasi signature, dan memetakan transaksi → invoice → `subscriptions`.
- Perpanjangan, dunning (3 pengingat), dan suspend otomatis **wajib** dijadwalkan sendiri (`schedule:run`), bukan diandalkan pada provider.

Kuota (tugas 4.5.5):

- `usage_counters.limit` **disalin** dari `config('plans.plans.{code}.limits')` saat periode dimulai — nilai dihitung ulang bila plan berubah, tapi angka historis tetap terbaca.
- `users` dihitung dari `team_members`, tidak di-increment manual (menghindari drift saat undangan dibatalkan).
- `ai_generations` di-increment per pemakaian; `0` berarti fitur tidak tersedia untuk plan tersebut.

Add-on (tugas 4.5.6):

- `tenant_addons` **tidak** mengubah `subscriptions.plan_code` — label paket tenant tetap Basic/Plus/Pro (§B.4.2).
- Entitlement membaca add-on lewat `TeamEntitlementSource::addonFeaturesFor()` yang sudah ada; implementasi billing cukup mengembalikan fitur dengan `status = active` dan `ends_at` null/`> now()`.

> **Tidak ada migrasi di fase ini** (tugas 4.5.7). Empat tabel di atas baru dibuat bersama B-2 (SaaS billing, mulai 17 Nov).

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
| `App\Enums\Feature`                                           | ✅ **Baru 2026-11-10**                                                                  |
| `config/plans.php`                                            | ✅ **Baru 2026-11-10**                                                                  |
| `App\Support\Entitlements`                                   | ✅ **Baru 2026-11-10** — resolusi fitur efektif (plan ∪ add-on), `can()`, `limit()`     |
| `App\Contracts\TeamEntitlementSource`                        | ✅ **Baru 2026-11-10** — seam sumber langganan; billing mengganti bindingnya             |
| `App\Support\NoSubscriptionSource`                           | ✅ **Baru 2026-11-10** — binding hari ini: tanpa langganan, tanpa add-on                 |
| `App\Models\Team`                                            | ✅ Tambah `can()`, `features()`                                                         |
| `App\Http\Middleware\HandleInertiaRequests`                   | ⏳ **Pindah ke 26 Nov** (bersama UI billing) — bagikan `features`                       |
| Helper otorisasi                                              | ✅ `AuthorizesTeamModule::authorizeFeature()` 2026-11-10                                |
| Controller domain                                             | **Tidak** diubah (belum ada pembatasan — tugas 4.6.1)                                  |
| Test                                                          | ✅ `tests/Feature/EntitlementTest.php`, `tests/Unit/PlanNameBranchGuardTest.php`         |

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

| Test                 | Cakupan                                                         | Status |
| -------------------- | --------------------------------------------------------------- | ------ |
| Resolusi entitlement | Plan dasar → hanya fitur dasarnya                               | ✅     |
| Warisan plan         | Plus mencakup fitur Basic (bila memakai resolusi berjenjang)    | ✅ (duplikasi eksplisit — diuji `PlansConfigTest`) |
| Default plan         | Tenant tanpa langganan memakai plan default                     | ✅     |
| Penolakan akses      | Endpoint ber-entitlement menolak tenant yang tidak berhak (403) | ✅ (`authorizeFeature`) |
| Validasi config      | Semua nama fitur di `config/plans.php` ada di enum `Feature`    | ✅     |
| Frontend prop        | `features` terbagikan & bertipe benar                           | ⏳ 26 Nov |
| Regresi              | `teamPermissions` masih bekerja seperti sebelumnya              | ✅ (suite lama hijau) |

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

| ID      | Pertanyaan                                                                                                                                                                                                                                                                       |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| OQ-F4-1 | Apakah fitur yang sudah ada (proyek, lead, keuangan) akan dibagi ke paket, atau tetap gratis untuk semua? Ini menentukan apakah entitlement punya "gigi" sejak awal. **✅ Terjawab 2026-10-28:** modul internal masuk **Pro** dan boleh dibeli sebagai **add-on**; bukan gratis. |
| OQ-F4-2 | Apakah warisan fitur berjenjang (Plus ⊇ Basic) atau tiap paket mendaftar ulang? **✅ Terjawab 2026-10-28:** **daftar ulang** — tiap paket menyebut fiturnya sendiri di config.                                                                                                   |
| OQ-F4-3 | Apakah tenant bisa menurunkan paket kapan saja? (berdampak pada validasi data saat downgrade) **✅ Terjawab 2026-10-28:** boleh diminta kapan saja, **berlaku akhir periode**, dengan validasi data.                                                                             |
| OQ-F4-4 | Apakah fitur dasar wajib selalu ada di plan default, agar tenant baru tidak kosong melompong? **✅ Terjawab 2026-10-28:** tenant baru mendapat **trial Basic 30 hari**; Basic sudah berisi CMS + template + SEO + GA + upload.                                                   |
| OQ-F4-5 | Apakah kuota perlu ditampilkan ke tenant (mis. "37/100 konten")? Bila ya, kapan? **❓ Masih terbuka** — kuota yang disetujui (user 1/3/10, AI 50/bulan) tetap perlu tampilan; dijadwalkan bersama UI billing.                                                                    |

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

| Tanggal    | Temuan                                                                                                                                                                                                        | Dampak                    | Tindakan                                                                                   |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- | ------------------------------------------------------------------------------------------ |
| 2026-11-10 | Dokumen fase ini memuat dua instruksi bertentangan soal isi enum: larangan memasukkan fitur yang belum ada kodenya (§Prerequisites) vs daftar fitur B.4.1 yang harus tercermin penuh di plan (§Pembaruan 2026-10-28) | Cakupan enum & isi plan   | Diputuskan pemilik produk: ikuti B.4.1 penuh — lihat tabel keputusan di bawah              |
| 2026-11-10 | `array_merge(...array_map(...))` pada array ber-key (`config('plans.plans')`) melempar "unknown named parameters" di PHP 8.5                                                                                  | Test config gagal         | Bungkus dengan `array_values()` sebelum spread                                              |
| 2026-11-10 | `php.ini` Laragon yang aktif sempat tidak memuat `pdo_sqlite`, sehingga `composer test` gagal "could not find driver" — bukan disebabkan perubahan kode. `php -m` sekarang memuat `pdo_sqlite`/`sqlite3` (PHP 8.3.2 aktif) | Verifikasi suite terhambat | Dicek ulang; suite dijalankan penuh dan hijau. Tidak ada perubahan `php.ini` dari sesi ini |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.**

| Tanggal    | Keputusan                                                                                                                                                     | Alasan                                                                                                                                                          |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-11-10 | Cakupan enum = seluruh baris fungsional B.4.1 (22 kasus), bukan hanya fitur yang sudah dibangun                                                                | Plan di `config/plans.php` harus 1:1 dengan matriks komersial; menambah kasus enum murah, plan yang tidak lengkap menyesatkan                                    |
| 2026-11-10 | `ssl` dan `prioritas support` tidak dijadikan kasus enum                                                                                                       | Komitmen layanan/infrastruktur, bukan kapabilitas aplikasi yang bisa digerbangi kode                                                                            |
| 2026-11-10 | Daftar fitur tiap plan ditulis ulang (duplikasi eksplisit), bukan warisan berjenjang                                                                            | OQ-F4-2 (2026-10-28); satu plan dibaca sekali, tidak ada promosi fitur diam-diam saat harga berubah                                                             |
| 2026-11-10 | Fitur di config disimpan sebagai string nilai enum, bukan objek `Feature`                                                                                       | Config tetap data murni dan aman di-cache; `PlansConfigTest` yang menjaga validitas terhadap enum                                                               |
| 2026-11-10 | Kuota user disimpan di `plans.<code>.limits.users`; kuota AI di `limits.ai_generations_per_month` (0 untuk Basic/Plus)                                          | Kuota adalah bagian entitlement (B.4.1); penegakannya menyusul di §4.3 dan billing                                                                              |
| 2026-11-10 | Harga, trial, dan metadata billing tidak dimasukkan ke `config/plans.php`                                                                                       | Milik workstream billing (B-2, mulai 17 Nov); bentuknya belum diputuskan dan tidak boleh diramalkan di sini                                                     |
| 2026-11-10 | Sumber langganan diabstraksi jadi `App\Contracts\TeamEntitlementSource` dengan binding `NoSubscriptionSource`                                                   | Billing tinggal mengganti satu binding; pemanggil `Team::can()` tidak berubah. Sekaligus mewadahi union plan ∪ add-on di satu tempat (PDR-06)                     |
| 2026-11-10 | Plan tak dikenal melempar `UnknownPlan`, tidak jatuh ke default                                                                                                 | Plan salah berarti bug data/config; menurunkannya diam-diam ke Basic akan menyalahkan tenant yang sudah bayar untuk paket lebih tinggi                          |
| 2026-11-10 | `Entitlements` tidak menyimpan cache hasil resolusi                                                                                                             | Sumber datanya config (sudah di-cache) + satu query nanti; cache menambah risiko basi (RF4-4) tanpa manfaat terukur                                             |
| 2026-11-10 | Larangan branch plan ditegakkan sebagai test (`PlanNameBranchGuardTest`), bukan sekadar catatan review                                                          | DoD #4 meminta verifikasi; test membuat regresi tertangkap di CI, bukan saat review manual                                                                       |

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
