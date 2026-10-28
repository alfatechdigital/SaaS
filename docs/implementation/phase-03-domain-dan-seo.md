# Fase 3 — Domain & SEO

> **Status:** ✅ **SELESAI (gelombang 1 — SEO).** Tugas 3.5.1–3.5.10 selesai 2026-10-28/29; **Gate Fase 3 lolos di mesin lokal** pada 2026-10-29 (lihat [Post-Implementation Notes](#post-implementation-notes)). Tugas domain 3.1–3.4 tetap di **gelombang 2**.
> **Ketergantungan:** Fase 2 selesai (`TenantContext` + isolasi tenant terjamin).
> **Menambah fitur produk?** ❌ Tidak untuk bagian persiapan; bagian SEO publik adalah penyempurnaan halaman yang **sudah ada**.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ⚠️ **Living document.** Perbarui saat pengerjaan. Jadwal: [`implementation-schedule.md`](implementation-schedule.md) v2.0.0.

> ℹ️ **Penomoran kode = warisan (D-01, 2026-10-19).** Komentar `ADR-*`/`PDR-*` di kode memakai penomoran dokumen lama; artinya ada di [Lampiran C](../IMPLEMENTATION_PLAN.md#lampiran-c--referensi-aturan-lama-legacy), bukan di Lampiran B.

---

## Objective

> ✅ **Pembaruan 2026-09-30:** bagian domain (tugas 3.1–3.4) **dipindahkan ke gelombang 2**. Gelombang 1 hanya mengerjakan **bagian SEO (3.5)**.

1. ⏸️ _(Gelombang 2)_ Membuat **resolusi tenant tidak lagi terikat pada slug di URL**, sehingga custom domain dapat ditambahkan nanti sebagai _penambahan_, bukan _penulisan ulang_.
2. ✅ _(Gelombang 1)_ Membangun fondasi **SEO per tenant** untuk halaman publik yang sudah ada.

Fase ini **menyiapkan** custom domain, tetapi **tidak** mengimplementasikannya.

---

## Scope

### Termasuk (gelombang 1 — **hanya bagian SEO**)

- Fondasi SEO: SSR produksi (dari Fase 1) → meta dinamis, OG, Twitter card, canonical, structured data `LocalBusiness`, `robots.txt`, dan `sitemap.xml` per tenant.
- Menghormati `public_page_enabled` di semua jalur SEO.

### Dipindahkan ke **gelombang 2** (2026-09-30)

- Abstraksi `TenantResolver` (tugas 3.1).
- Konfigurasi `config/tenancy.php` untuk domain (tugas 3.2).
- Pemisahan route publik (tugas 3.3).
- **Desain** tabel `domains` (tugas 3.4).

> Keputusan ini diambil karena gelombang 1 menambahkan lima integrasi pihak ketiga. Karena custom domain (PDR-04) juga belum disetujui, penundaan ini **tidak menunda nilai bisnis apa pun**.

### Tidak termasuk (→ gelombang 2 / Fase 5)

- Tabel `domains` + CRUD domain untuk tenant.
- Verifikasi kepemilikan domain.
- Resolusi host yang aktif.
- Sertifikat SSL per domain, konfigurasi DNS, reverse proxy.
- Redirect domain non-primer.
- SEO lanjutan: skor SEO, audit on-page, Search Console, hreflang, redirect manager.

---

## Prerequisites

| Prasyarat                                                                  | Status                       |
| -------------------------------------------------------------------------- | ---------------------------- |
| Fase 2 selesai (`TenantContext` ada dan stabil)                            | ❌                           |
| Fase 1 selesai: SSR build berhasil                                         | ❌                           |
| **D-08** (strategi URL: path vs subdomain) disetujui                       | ❌ **Wajib**                 |
| **ADR-15** (pisahkan route publik) disetujui                               | ❌ Disarankan                |
| **PDR-04** (custom domain sebagai fitur tier) minimal berstatus _akan ada_ | ❓ Belum disetujui           |
| Infrastruktur DNS/reverse proxy belum diperlukan di fase ini               | ✅ Tidak diperlukan sekarang |

> **Catatan penting:** karena bagian domain (3.1–3.4) kini pindah ke gelombang 2, keputusan **D-08** dan **ADR-15** **tidak lagi menghambat** pekerjaan gelombang 1. Bagian SEO (3.5) **sama sekali tidak bergantung** pada PDR-04 maupun D-08.

---

## Current State

Setelah Fase 1 & 2:

- Tenant aktif tersedia lewat `TenantContext` (hasil Fase 2).
- Tenant masih di-resolve **hanya** dari slug di segmen URL: `/p/{team}` (publik) dan `/{current_team}/…` (internal).
- Belum ada konsep domain/host. `config/tenancy.php` (dari Fase 1) berisi `base_domain = null`.
- Halaman publik: `/p/{slug-tim}`, satu controller (`PublicCompanyProfileController`), satu halaman Inertia (`public/company-profile.vue`).
- `teams.public_page_enabled` sudah mematikan halaman publik dengan 404 (moderasi platform).
- SEO: hanya SSR + meta title/description dasar dari Fase 1. Belum ada OG, canonical, sitemap, robots, structured data.
- Data yang tersedia untuk SEO sudah cukup: `company_profiles` (nama, deskripsi, kontak, layanan, FAQ), `portfolio_items` (terbit).

---

## Tasks

> ✅ **Gelombang 1 hanya mengerjakan 3.5 (SEO).** Tugas 3.1–3.4 (domain) **dipindahkan ke gelombang 2** pada 2026-09-30.

### 3.1 Abstraksi `TenantResolver` (FOUNDATION — ADR-04) — ⏸️ **GELOMBANG 2**

| #     | Tugas                                                                   | Catatan                                         |
| ----- | ----------------------------------------------------------------------- | ----------------------------------------------- |
| 3.1.1 | Definisikan antarmuka `TenantResolver` (mis. `resolve(Request): ?Team`) | Tanpa efek samping                              |
| 3.1.2 | Implementasi `PathTenantResolver` (slug)                                | Harus mereproduksi perilaku sekarang **persis** |
| 3.1.3 | Registrasikan resolver di container; `TenantContext` memakainya         | Titik sambung tunggal                           |
| 3.1.4 | Test: seluruh pola URL lama tetap bekerja tanpa perubahan               | Ini kriteria "tidak ada regresi"                |
| 3.1.5 | Dokumentasikan slot implementasi host yang akan datang                  | Jangan tulis stub yang tidak dipakai            |

### 3.2 Konfigurasi Domain (FOUNDATION) — ⏸️ **GELOMBANG 2**

| #     | Tugas                                                                                      | Catatan                      |
| ----- | ------------------------------------------------------------------------------------------ | ---------------------------- |
| 3.2.1 | Lengkapi `config/tenancy.php`: `base_domain`, `subdomain_enabled`, `custom_domain_enabled` | Semua default `null`/`false` |
| 3.2.2 | Pastikan **tidak ada** kode yang berperilaku berbeda karena config ini                     | Verifikasi lewat test        |
| 3.2.3 | Dokumentasikan nilai yang dibutuhkan untuk lokal/staging/produksi                          | Belum diisi                  |
| 3.2.4 | Tetapkan pendekatan lokal (mis. `{slug}.localhost`) sebagai dokumentasi                    | Jangan di-hardcode           |

### 3.3 Pemisahan Route Publik (ADR-15) — ⏸️ **GELOMBANG 2**

| #     | Tugas                                                                    | Catatan                             |
| ----- | ------------------------------------------------------------------------ | ----------------------------------- |
| 3.3.1 | Kumpulkan route publik tenant ke satu grup yang jelas (`routes/web.php`) | `/p/{team}` + form konsultasi       |
| 3.3.2 | Pastikan grup publik tidak bergantung pada middleware tenant internal    | Sudah begitu; pertahankan           |
| 3.3.3 | Dokumentasikan bentuk route bila host/domain aktif nanti                 | Contoh: `/` di host tenant          |
| 3.3.4 | Test: route publik tetap berfungsi & tetap rate-limited                  | `throttle:5,1` pada form konsultasi |

### 3.4 Desain Domain (FOUNDATION — dokumen, belum implementasi) — ⏸️ **GELOMBANG 2**

| #     | Tugas                                          | Catatan                                                      |
| ----- | ---------------------------------------------- | ------------------------------------------------------------ |
| 3.4.1 | Tetapkan desain tabel `domains` di dokumen ini | Lihat pratinjau di bawah                                     |
| 3.4.2 | Tetapkan mesin status domain                   | `pending` → `verifying` → `active` → `failed` (+ `disabled`) |
| 3.4.3 | Tetapkan aturan domain primer per tenant       | `(team_id, is_primary)` unique parsial                       |
| 3.4.4 | Tulis analisis risiko keamanan Host header     | Lihat [Security Considerations](#security-considerations)    |
| 3.4.5 | **Jangan** membuat migrasi/tabel/CRUD          | Itu gelombang 2 / Fase 5                                     |

**Pratinjau desain `domains` (belum final, D-08/PDR-04):**

| Kolom                | Tipe                   | Catatan                                            |
| -------------------- | ---------------------- | -------------------------------------------------- |
| `id`                 | bigint PK              |                                                    |
| `team_id`            | FK → `teams`, cascade  | Pemilik                                            |
| `host`               | string, **unique**     | Tanpa skema, lowercase, tanpa trailing slash       |
| `is_primary`         | boolean, default false | Satu per tenant                                    |
| `status`             | string                 | `pending`/`verifying`/`active`/`failed`/`disabled` |
| `verification_token` | string, nullable       | Untuk TXT record                                   |
| `verified_at`        | timestamp, nullable    |                                                    |
| `last_checked_at`    | timestamp, nullable    |                                                    |
| `timestamps`         |                        |                                                    |

**Index yang direncanakan:** `unique(host)`, `index(team_id)`, `unique` parsial `(team_id)` where `is_primary = true`.

### 3.5 Fondasi SEO per Tenant — ✅ **GELOMBANG 1**

| #      | Tugas                                                                                    | Catatan                                                            |
| ------ | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| 3.5.1  | Pastikan SSR produksi berjalan stabil dari Fase 1                                        | Prasyarat; verifikasi ulang                                        |
| 3.5.2  | Meta dinamis: `title`, `description` dari `company_profiles`                             | Jangan hardcode                                                    |
| 3.5.3  | Open Graph dasar: `og:title`, `og:description`, `og:type`, `og:url`, `og:image`          | Gambar dari portofolio/logo tenant                                 |
| 3.5.4  | Twitter card dasar                                                                       | Minimal `summary_large_image`                                      |
| 3.5.5  | **Canonical URL** yang sadar domain                                                      | Sekarang: URL slug; nanti: domain primer                           |
| 3.5.6  | Structured data `LocalBusiness` (JSON-LD) dari profil tenant                             | Nama, alamat, telepon, email, URL                                  |
| 3.5.7  | `robots.txt` per tenant                                                                  | Hormati `public_page_enabled`; jangan indeks tenant yang dimatikan |
| 3.5.8  | `sitemap.xml` per tenant                                                                 | Isi: halaman profil + portofolio yang **terbit** saja              |
| 3.5.9  | Hormati moderasi: tenant `public_page_enabled = false` → tidak masuk sitemap & `noindex` | Test                                                               |
| 3.5.10 | Test: meta/OG/canonical/sitemap/robots benar untuk ≥2 tenant berbeda                     | —                                                                  |

> **Selesai 2026-10-28 — 3.5.2 s/d 3.5.6.** Semua nilai dibangun di `App\Support\PublicPageSeo` dari profil tenant; tidak ada kolom SEO baru (data yang ada sudah cukup). JSON-LD dikirim sebagai HTML jadi lewat `serverHead` Inertia karena template Vue menolak `<script>`.
>
> **Selesai 2026-10-29 — 3.5.7 s/d 3.5.10.** `robots.txt` dan `sitemap.xml` per tenant tersedia di `p/{slug}/robots.txt` dan `p/{slug}/sitemap.xml`; keduanya bertanya pada satu predicate yang sama dengan halaman (`Team::isPublicPageVisible()`), sehingga moderator dan suspensi berlaku serentak di ketiga jalur. Matriks ≥2 tenant ada di `PublicSeoAcrossTenantsTest`.

> **Jangan** mengerjakan: skor SEO, saran SEO otomatis, audit on-page, integrasi Search Console. Itu Fase 5 / DEFERRED. Pengecualian: **otomasi meta/schema + ping sitemap** (B-8 dasar) masuk **gelombang 1**.

---

## Database Changes

| Perubahan                                                                           | Status di fase ini                                                                                                                      |
| ----------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| `domains`                                                                           | **Desain dokumen saja.** Tidak dibuat.                                                                                                  |
| Kolom SEO di `company_profiles` (mis. `meta_title`, `meta_description`, `og_image`) | **Boleh** dibuat (aditif, nullable) **bila** 3.5.2 memerlukan override manual. Bila data yang ada sudah cukup, **jangan** tambah kolom. |
| Index tambahan                                                                      | Tidak ada                                                                                                                               |

**Prinsip:** jangan menambah kolom hanya karena "mungkin berguna nanti". Tambah bila ada kebutuhan nyata di 3.5.

---

## Backend Changes

| Area                                                  | Perubahan                                                                                                  |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `App\Support\TenantResolver` (interface)              | **Baru**                                                                                                   |
| `App\Support\Resolvers\PathTenantResolver`            | **Baru**                                                                                                   |
| `App\Support\TenantContext`                           | Memakai resolver                                                                                           |
| `app/Providers/AppServiceProvider.php`                | Bind resolver                                                                                              |
| `config/tenancy.php`                                  | Lengkapi kunci                                                                                             |
| `App\Http\Controllers\PublicCompanyProfileController` | Tambah data SEO ke props                                                                                   |
| `App\Support\PublicPageSeo`                           | **Baru** — semua meta publik (title, description, canonical, OG, Twitter, JSON-LD) dibangun di satu tempat |
| `App\Http\Controllers\PublicRobotsController`         | **Baru** — `p/{slug}/robots.txt` (tugas 3.5.7)                                                             |
| `App\Http\Controllers\PublicSitemapController`        | **Baru** — `p/{slug}/sitemap.xml` (tugas 3.5.8)                                                            |
| `resources/views/seo/sitemap.blade.php`               | **Baru** — template XML (escaping via Blade)                                                               |
| `App\Models\Team::isPublicPageVisible()`              | **Baru** — satu predicate untuk halaman, robots, dan sitemap                                               |
| Route sitemap/robots                                  | **Baru** (mis. `/p/{team}/sitemap.xml`, `/p/{team}/robots.txt`)                                            |
| `App\Support\Seo\*` (opsional)                        | Helper pembangun meta bila mulai kompleks                                                                  |
| Setiap controller domain                              | **Tidak** diubah                                                                                           |

---

## Frontend Changes

| Area                                            | Perubahan                                                 |
| ----------------------------------------------- | --------------------------------------------------------- |
| `resources/js/pages/public/company-profile.vue` | Terima & render meta/OG via head management Inertia       |
| `resources/js/types/domain.ts`                  | Tambah tipe data SEO publik (`PublicPageMeta`)            |
| `resources/js/app.ts`, `resources/js/ssr.ts`    | `serverHead: 'seoHead'` — jalur masuk JSON-LD dari server |
| Halaman internal                                | **Tidak** diubah                                          |

**Catatan SSR:** semua nilai yang bergantung pada `window`/DOM harus SSR-safe (pola yang sudah dipakai proyek ini). Meta **wajib** dirender di server agar crawler melihatnya.

---

## Infrastructure Changes

| Kebutuhan                       | Fase ini             | Fase 5           |
| ------------------------------- | -------------------- | ---------------- |
| Proses SSR Node berjalan        | **Ya** (dari Fase 1) | —                |
| DNS wildcard / record kustom    | Tidak                | Ya               |
| Reverse proxy meneruskan `Host` | Tidak                | Ya               |
| SSL per domain                  | Tidak                | Ya               |
| CDN                             | Tidak                | Belum diputuskan |

Fase ini **tidak** menuntut perubahan infrastruktur produksi. Ini sengaja: persiapan kode dulu, infrastruktur menyusul saat domain benar-benar disetujui.

---

## Security Considerations

| ID    | Risiko                                                             | Tindakan                                                                                                                                     |
| ----- | ------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- |
| RF3-1 | **Host header injection** — host dipercaya untuk menentukan tenant | Host **wajib** dicocokkan ke tabel `domains`; tidak ada fallback berbasis tebakan. Diterapkan di Fase 5, tetapi polanya ditetapkan sekarang. |
| RF3-2 | Domain takeover — domain tidak terverifikasi tetap aktif           | Status `active` hanya setelah `verified_at` terisi                                                                                           |
| RF3-3 | Redirect loop pada domain non-primer                               | Redirect sekali, tidak berantai                                                                                                              |
| RF3-4 | Cookie/session bocor antar domain tenant                           | Tinjau `SESSION_DOMAIN` sebelum domain aktif                                                                                                 |
| RF3-5 | Sitemap/robots membocorkan halaman non-publik                      | Hanya ekspos data terbit; hormati `public_page_enabled`                                                                                      |
| RF3-6 | Abuse pendaftaran domain / verifikasi                              | Rate limit (Fase 5)                                                                                                                          |
| RF3-7 | Canonical menunjuk URL salah → masalah duplikasi SEO               | Canonical dibangun dari satu sumber; test di ≥2 tenant                                                                                       |

**Penting:** Fase ini menetapkan **kebijakan**, bukan implementasinya. Yang berbahaya adalah mengambil jalan pintas (mis. `$request->host()` langsung menentukan tenant) — pola itu **dilarang** sejak sekarang agar tidak diwariskan ke Fase 5.

---

## Testing Requirements

| Test                            | Cakupan                                               |
| ------------------------------- | ----------------------------------------------------- |
| Resolver path                   | Semua pola URL lama tetap bekerja                     |
| Resolver tidak menemukan tenant | Slug tak dikenal → 404 (bukan 500, bukan tenant acak) |
| Route publik                    | Tetap berfungsi; form konsultasi tetap rate-limited   |
| Moderasi                        | Tenant dinonaktifkan → 404 di semua jalur publik      |
| Meta/OG/canonical               | Benar untuk ≥2 tenant dengan data berbeda             |
| Sitemap                         | Hanya berisi data terbit; bukan nol; valid XML        |
| Robots                          | Tenant dinonaktifkan → tidak boleh diindeks           |
| Structured data                 | JSON-LD valid & sesuai data tenant                    |

Perintah: `composer test` dan `npm run types:check`. Sertakan `npm run build` sebelum test.

---

## Migration Considerations

- **Tidak ada migrasi wajib** di fase ini. Bila 3.5.2 memerlukan kolom SEO, tambah aditif nullable.
- Desain `domains` **tidak** dijalankan sebagai migrasi.
- Bila nanti domain aktif (Fase 5), URL path lama **harus** tetap berfungsi (301 → domain primer) agar SEO tidak hilang. Ini prinsip yang sudah dicatat di master plan §13.3.

---

## Acceptance Criteria

**Berlaku untuk gelombang 1 (SEO):**

> **Status 2026-10-29 (Gate Fase 3).** Kesepuluh kriteria gelombang 1 terpenuhi dan diverifikasi; kriteria 3 dan 4 diperiksa dari **HTML/berkas mentah** (SSR + `curl`), bukan dari DOM.

1. ✅ Halaman publik punya meta title/description/OG/canonical dari data tenant.
2. ✅ Structured data `LocalBusiness` valid.
3. ✅ `robots.txt` dan `sitemap.xml` per tenant berfungsi dan menghormati `public_page_enabled`.
4. ✅ Output SSR benar-benar mengandung meta tersebut (diverifikasi dari HTML, bukan dari DOM).
5. ✅ `composer test` dan `npm run types:check` hijau.
6. ✅ Tidak ada fitur produk baru.

**Ditunda ke gelombang 2:**

7. ⏸️ Tidak ada lagi resolusi tenant yang tersebar; semua melalui `TenantResolver` + `TenantContext`.
8. ⏸️ Perilaku URL slug **identik** dengan sebelumnya (tidak ada regresi).
9. ⏸️ Desain tabel `domains` terdokumentasi; **tidak ada** tabel/migrasi/CRUD domain dibuat.
10. ⏸️ Tidak ada kode yang berperilaku berbeda karena `config/tenancy.php`.

---

## Risks

| ID    | Risiko                                                                                    | Dampak | Mitigasi                                                           |
| ----- | ----------------------------------------------------------------------------------------- | ------ | ------------------------------------------------------------------ |
| RF3-A | Abstraksi resolver menjadi _over-engineering_ bila custom domain akhirnya tidak disetujui | Sedang | Antarmuka dijaga kecil (satu method); manfaat decoupling tetap ada |
| RF3-B | Konfigurasi `tenancy.*` ditulis tetapi tidak dipakai → kode mati                          | Rendah | Semua default `null`; tidak ada cabang perilaku                    |
| RF3-C | SSR tidak stabil di produksi → meta tidak terlihat crawler                                | Tinggi | Verifikasi HTML SSR sebagai acceptance criteria                    |
| RF3-D | Sitemap membocorkan data yang belum boleh publik                                          | Sedang | Hanya data terbit; test                                            |
| RF3-E | Kolom SEO ditambahkan "sekadar jaga-jaga"                                                 | Rendah | Aturan: tambah hanya bila 3.5 membutuhkannya                       |
| RF3-F | Bisnis meminta custom domain langsung setelah fase ini                                    | Sedang | Fase 5 sudah dinakhodai; pastikan ekspektasi bisnis jelas          |

---

## Open Questions

| ID      | Pertanyaan                                                                                        |
| ------- | ------------------------------------------------------------------------------------------------- |
| OQ-F3-1 | Apakah tenant akan memakai subdomain (`{slug}.app.tld`) selain custom domain?                     |
| OQ-F3-2 | Apakah satu tenant boleh punya >1 domain aktif, dan berapa batasnya?                              |
| OQ-F3-3 | Apakah `sitemap.xml` perlu sitemap index per instalasi (banyak tenant)?                           |
| OQ-F3-4 | Untuk `og:image`, apakah diambil dari portofolio pertama, logo perusahaan, atau upload khusus?    |
| OQ-F3-5 | Apakah tenant boleh mengisi `meta_title`/`meta_description` manual, atau selalu turunan otomatis? |

---

## Known Issues

| #     | Isu                                                                                                                | Status                                                                                                                                                                                                                             |
| ----- | ------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| KI3-1 | SSR belum terbukti berjalan di produksi                                                                            | Diverifikasi di Fase 1                                                                                                                                                                                                             |
| KI3-2 | Belum ada data "logo perusahaan" yang terstruktur                                                                  | Bila SEO butuh `og:image`, perlu sumber gambar (lihat OQ-F3-4)                                                                                                                                                                     |
| KI3-3 | `company_profiles` belum punya kolom khusus SEO                                                                    | Keputusan bergantung OQ-F3-5                                                                                                                                                                                                       |
| KI3-4 | `sitemap.xml` per tenant hanya mencakup satu halaman + portofolio                                                  | **Diterima 2026-10-29:** portfolio adalah section di halaman itu, bukan URL terpisah; datanya masuk lewat `lastmod`. Perluas saat portofolio punya halaman sendiri                                                                 |
| KI3-5 | Per-tenant `robots.txt` hidup di `p/{slug}/robots.txt`, sedangkan crawler hanya membaca `/robots.txt` di root host | **Diterima** selama tenancy berbasis path: isinya tetap benar (termasuk `Disallow` untuk tenant tersembunyi) dan menjadi otoritatif begitu domain tenant aktif (gelombang 2, ADR-04). Root `public/robots.txt` dibiarkan allow-all |

---

## Discovered During Implementation

> **Diisi oleh agen/developer saat pengerjaan.**

| Tanggal    | Temuan                                                                                                                                                                                                                                                              | Dampak                                                                             | Tindakan                                                                                                                                                                                             |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-28 | **`<script>` tidak bisa ditulis di template Vue.** Compiler DOM menolak tag berefek samping (`Tags with side effect (<script> and <style>) are ignored in client component templates`), jadi JSON-LD tidak bisa menjadi anak `<Head>` seperti tag `<meta>` lainnya. | Structured data akan diam-diam tidak ada bila dipaksa lewat template               | Dipakai opsi `serverHead` milik Inertia v3: server mengirim HTML jadi di prop `seoHead`, Inertia menggabungkannya dengan output `<Head>` (dan menggantinya saat navigasi). Terbukti ada di HTML SSR. |
| 2026-10-28 | **`resolveServerHead` menyisipkan `data-inertia="server-head-{index}"` ke tag pertama.** Menambahkan atribut itu sendiri menghasilkan atribut ganda pada satu tag.                                                                                                  | Markup tidak valid bila atribut diisi manual                                       | Atribut tidak diisi manual; string hanya berisi `<script type="application/ld+json">…</script>`.                                                                                                     |
| 2026-10-28 | **`json_encode` tanpa `JSON_HEX_TAG` bisa ditembus `</script>`.** Nama perusahaan adalah input pengguna; `PT </script><script>alert(1)</script>` akan menutup tag JSON-LD dan menjalankan sisanya sebagai markup.                                                   | XSS tersimpan di halaman publik                                                    | Encoding memakai `JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT \| JSON_UNESCAPED_SLASHES \| JSON_UNESCAPED_UNICODE`; dikunci `PublicPageSeoTest`.                                  |
| 2026-10-29 | **Portofolio tidak punya URL sendiri**, jadi `sitemap.xml` tidak bisa mendaftarnya sebagai entri terpisah — `p/{slug}` adalah satu dokumen dan portofolio adalah section di dalamnya.                                                                               | Sitemap hanya berisi satu `<url>`; kontribusi portofolio hilang bila tidak diakali | Portofolio dipakai sebagai sumber `lastmod` (item terbit terbaru), sehingga perubahan portofolio tetap memicu crawl ulang. Didaftarkan sebagai KI3-4.                                                |
| 2026-10-29 | **`robots.txt` per tenant hidup di `p/{slug}/robots.txt`**, sedangkan crawler hanya membaca `/robots.txt` di root host.                                                                                                                                             | Isi per-tenant tidak pernah diambil otomatis selama tenancy berbasis path          | Diterima & didokumentasikan (KI3-5); file root `public/robots.txt` tetap allow-all. Menjadi otoritatif saat domain tenant aktif (ADR-04).                                                            |

---

## Decisions Made During Implementation

> **Diisi saat pengerjaan.**

| Tanggal    | Keputusan                                                                                                   | Alasan                                                                                                                                                                                       |
| ---------- | ----------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-28 | **Tidak menambah kolom SEO di `company_profiles`** (tidak ada `meta_title`/`og_image`)                      | Data yang ada (nama, deskripsi, telepon, email, alamat) sudah cukup untuk 3.5.2–3.5.6; kolom override baru berguna setelah ada tenant yang minta wording lain                                |
| 2026-10-28 | **Semua meta dibangun di `App\Support\PublicPageSeo`**, bukan di controller                                 | Sitemap (3.5.8) butuh canonical yang sama; satu sumber mencegah URL/title berbeda antar-jalur                                                                                                |
| 2026-10-28 | **`og:image` diambil dari portofolio terbit pertama** yang URL-nya absolut `http(s)`                        | Belum ada kolom logo; path relatif tidak berguna untuk crawler, jadi diabaikan                                                                                                               |
| 2026-10-28 | **`twitter:card` mengikuti ada/tidaknya gambar** (`summary` vs `summary_large_image`)                       | Kartu besar tanpa gambar tampil sebagai banner kosong                                                                                                                                        |
| 2026-10-28 | **JSON-LD dikirim lewat `serverHead`, bukan ditempel di Blade**                                             | Blade tidak ikut berubah saat navigasi Inertia; `serverHead` diganti per halaman oleh Inertia sendiri                                                                                        |
| 2026-10-28 | **`address` dipetakan ke `PostalAddress.streetAddress`** apa adanya                                         | Profil menyimpan alamat sebagai satu baris bebas; memecahnya menjadi komponen berarti menebak                                                                                                |
| 2026-10-29 | **Satu predicate `Team::isPublicPageVisible()`** untuk halaman, robots, dan sitemap                         | Tiga jalur yang menjawab pertanyaan sama dengan cara berbeda cepat atau lambat akan berbeda jawabannya — dan yang paling berbahaya adalah robots yang membocorkan sitemap tenant tersembunyi |
| 2026-10-29 | **Tenant tersembunyi tetap mendapat `robots.txt` yang bisa dibaca** (`Disallow: /`), tetapi sitemap-nya 404 | `robots.txt` yang hilang berarti "indeks semua" — kebalikan dari keputusan moderasi; sebaliknya sitemap yang 404 tidak mengiklankan URL yang sudah mati                                      |
| 2026-10-29 | **`X-Robots-Tag: noindex` pada respons 404 halaman publik**                                                 | Halaman yang sudah terindeks sebelumnya perlu instruksi eksplisit, bukan hanya 404 yang bisa ditafsirkan sebagai gangguan sementara                                                          |
| 2026-10-29 | **Sitemap dirender lewat Blade (`resources/views/seo/sitemap.blade.php`)**, bukan `sprintf` di controller   | Escaping `&` dan karakter XML lain diserahkan ke Blade; satu tempat untuk struktur XML saat entri bertambah                                                                                  |

---

## Deferred

| Item                                                  | Alasan                                                                     | Fase tujuan       |
| ----------------------------------------------------- | -------------------------------------------------------------------------- | ----------------- |
| **Abstraksi `TenantResolver` (3.1)**                  | Dipindah agar gelombang 1 muat; custom domain belum disetujui              | **Gelombang 2**   |
| **Konfigurasi `config/tenancy.php` (3.2)**            | Mengikuti 3.1                                                              | **Gelombang 2**   |
| **Pemisahan route publik (3.3)**                      | Mengikuti 3.1                                                              | **Gelombang 2**   |
| **Desain tabel `domains` (3.4)**                      | Mengikuti 3.1                                                              | **Gelombang 2**   |
| Tabel `domains` + migrasi                             | Fitur belum disetujui                                                      | Fase 5            |
| Verifikasi kepemilikan domain                         | Butuh DNS & UI                                                             | Fase 5            |
| `ResolveTenantFromHost` middleware                    | Butuh tabel domain                                                         | Fase 5            |
| Redirect domain non-primer                            | Butuh domain aktif                                                         | Fase 5            |
| SSL otomatis (ACME)                                   | Infrastruktur                                                              | Fase 5            |
| Sitemap index lintas tenant                           | Belum perlu                                                                | Fase 5            |
| SEO lanjutan (hreflang, breadcrumb, redirect manager) | Bukan fondasi                                                              | Fase 5            |
| Otomasi SEO **lanjutan** / skor konten berbasis AI    | Hanya otomasi dasar (meta/schema, ping sitemap) yang disetujui gelombang 1 | Fase 5 (DEFERRED) |

---

## Technical Debt

| ID    | Utang                                                         | Alasan diterima             | Kapan ditinjau               |
| ----- | ------------------------------------------------------------- | --------------------------- | ---------------------------- |
| TD3-1 | `domains` masih sebatas desain dokumen                        | Menunggu persetujuan produk | Fase 5                       |
| TD3-2 | Canonical masih memakai URL path (belum domain primer)        | Domain belum ada            | Fase 5                       |
| TD3-3 | Belum ada data logo/gambar merek yang terstruktur             | Belum ada fitur upload      | Saat media manager dibuat    |
| TD3-4 | `robots`/`sitemap` per tenant berarti satu request per tenant | Skala masih kecil           | Bila tenant bertambah banyak |
| TD3-5 | Belum ada pemantauan indeks search engine                     | Bukan prioritas             | Fase 5                       |

---

## Post-Implementation Notes

> **Diisi setelah fase selesai dieksekusi.**

**2026-10-28 — tugas 3.5.2 s/d 3.5.6 (meta, Open Graph, Twitter card, canonical, JSON-LD).**

- **Satu tempat untuk semua meta.** `App\Support\PublicPageSeo::for($team, $profile, $portfolio)` membangun `title`, `description`, `canonical`, `og`, `twitter`, dan `jsonLd`. Controller hanya meneruskannya sebagai prop `meta`; sitemap (3.5.8, besok) akan memakai `canonical()` yang sama sehingga URL tidak pernah berbeda antar-jalur. Tenant **dipass eksplisit**, bukan dibaca dari konteks, supaya kelas ini bisa dipakai untuk tenant yang tidak sedang aktif.
- **3.5.2 meta dinamis.** `title` = `company_profiles.company_name` (fallback nama tim); `description` = `description` → `about` yang di-`strip_tags` → kalimat netral bernama tenant, dipotong 160 karakter. Tidak ada copy marketing bersama.
- **3.5.3 Open Graph.** `og:type` `website`, `og:site_name` nama tim, `og:title`/`og:description`/`og:url` dari nilai yang sama dengan meta, `og:image` dari portofolio terbit pertama yang URL-nya absolut `http(s)` (tanpa itu tag tidak dirender).
- **3.5.4 Twitter card.** `summary_large_image` bila ada gambar, `summary` bila tidak; ikut mengirim `twitter:title`/`twitter:description`/`twitter:image`.
- **3.5.5 canonical.** `route('public.company-profile', ['team' => $team->slug])` — absolut dan sadar tenant. Method `canonical()` adalah satu-satunya tempat yang berubah saat domain primer masuk (TD3-2 tetap terbuka untuk itu).
- **3.5.6 JSON-LD `LocalBusiness`.** `@context`/`@type`, `name`, `description`, `url`, `telephone`, `email`, `image`, `address` (`PostalAddress.streetAddress`); kunci kosong dibuang, bukan dikirim sebagai `null`. Dikirim lewat `serverHead` Inertia karena template Vue menolak `<script>` (lihat [Discovered](#discovered-during-implementation)).
- **Escaping.** `JSON_HEX_TAG`/`JSON_HEX_AMP`/`JSON_HEX_APOS`/`JSON_HEX_QUOT` dipakai agar nama perusahaan berisi `</script>` tidak bisa keluar dari blok JSON-LD. Diuji dengan nama yang memang berisi payload.
- **Bukti (3.5.1 + 3.5.10 versi ringkas).** `node bootstrap/ssr/ssr.js` + `php artisan serve`, lalu `GET /p/alfatech` **HTML mentah** memuat: `<title data-inertia="">PT Alfatech Digital Solutions - Laravel</title>`, `meta[name=description]`, `link[rel=canonical]`, `og:type`/`og:site_name`/`og:title`/`og:description`/`og:url`/`og:image`, `twitter:card` = `summary_large_image` + `twitter:*`, dan `<script data-inertia="server-head-0" type="application/ld+json">` berisi `LocalBusiness` lengkap (telepon, email, alamat, gambar). `GET /login` tetap bersih: tanpa `seoHead` dan tanpa JSON-LD, jadi `serverHead` tidak bocor ke halaman lain.
- **Test.** `tests/Feature/Seo/PublicPageSeoTest.php` (10 test): canonical absolut & spesifik tenant, OG mencerminkan meta, gambar dari portofolio terbit, gambar draf/path relatif diabaikan, `LocalBusiness` lengkap, field kosong dibuang, bentuk payload `seoHead`, escaping `</script>`, dan dua tenant bernama hampir sama tetap menampilkan identitasnya sendiri.
- **Belum dikerjakan (memang tugas 29 Oktober).** 3.5.7 `robots.txt`, 3.5.8 `sitemap.xml`, 3.5.9 `noindex` untuk tenant yang dimatikan, dan matriks 3.5.10 penuh.

**2026-10-29 — tugas 3.5.7 s/d 3.5.10 + Gate Fase 3.**

- **Satu predicate untuk tiga jalur.** `Team::isPublicPageVisible()` = `public_page_enabled && ! isSuspended()`. Halaman publik, `robots.txt`, dan `sitemap.xml` semuanya bertanya ke situ, sehingga moderasi (PDR-12) dan suspensi (2.3) tidak bisa lagi berbeda jawaban antar-jalur.
- **3.5.7 robots.** `GET p/{slug}/robots.txt` → `text/plain`: `User-agent: *`, `Allow: /`, dan `Sitemap: {URL absolut}` untuk tenant yang tampil. Tenant tersembunyi mendapat `Disallow: /` **tanpa** baris `Sitemap` — tetap 200, karena berkas robots yang hilang berarti "indeks semua".
- **3.5.8 sitemap.** `GET p/{slug}/sitemap.xml` → `application/xml`, satu `<url>` berisi canonical tenant dan `<lastmod>` = perubahan terbaru antara profil dan portofolio **terbit** terbaru. Dirender dari `resources/views/seo/sitemap.blade.php` agar escaping XML diserahkan ke Blade.
- **3.5.9 moderasi.** Tenant tersembunyi: halaman **404 + `X-Robots-Tag: noindex`**, robots `Disallow: /`, sitemap **404**. Halaman yang dimatikan jadi tidak masuk sitemap, tidak boleh dirayapi, dan tidak diminta diindeks.
- **3.5.10 bukti dua tenant.** `tests/Feature/Seo/PublicSeoAcrossTenantsTest.php` (4 test) memakai dua tenant berdata berbeda dan memastikan setiap artefak hanya menyebut tenant-nya sendiri: title/OG/canonical/JSON-LD, baris `Sitemap` di robots, `loc` di sitemap; ditambah satu test bahwa menyembunyikan tenant A tidak mengubah status tenant B.
- **Bukti dari berkas mentah (Gate Fase 3).** Dengan `node bootstrap/ssr/ssr.js` + `php artisan serve`, tenant sementara `uji-seo` dibuat di DB dev dan hasilnya: `p/alfatech` & `p/uji-seo` masing-masing memiliki `<title>`, canonical, `og:url`, `og:title`, `twitter:card`, dan `<script type="application/ld+json">` miliknya sendiri; kedua `robots.txt` menunjuk sitemap tenant masing-masing; kedua `sitemap.xml` valid dan hanya memuat URL tenant sendiri. Setelah `public_page_enabled = false` pada tenant sementara: halaman **404 + `X-Robots-Tag: noindex`**, robots `Disallow: /`, sitemap **404**, sementara `p/alfatech` tetap 200. Tenant sementara lalu dihapus permanen (`teams=1`, `profiles=1`).
- **Gate Fase 3 — lolos di mesin lokal.** `composer test` hijau: pint bersih, phpstan level 7 nol error, **369 test / 1543 assertion**. `npm run types:check` dan `npm run check` hijau. Kriteria domain (7–10) tetap ditunda ke gelombang 2 sesuai keputusan 2026-09-30.

---

## Implementation Notes

1. ~~**Kerjakan 3.1 lebih dulu.** Abstraksi resolver adalah inti fase ini; SEO hanya bernilai bila URL publik sudah stabil bentuknya.~~ **Pembaruan 2026-09-30:** gelombang 1 **hanya** mengerjakan **3.5 (SEO)**; 3.1–3.4 pindah ke gelombang 2. SEO tetap bernilai meski resolver belum diabstraksi, karena bentuk URL belum berubah.
2. **Verifikasi meta dari HTML mentah**, bukan dari browser dev tools setelah hidrasi. Cara paling pasti: `curl` halaman publik dan pastikan tag meta ada di respons.
3. **Jangan menambahkan tabel `domains`** walau desainnya sudah ada di dokumen ini. Desain ≠ izin implementasi.
4. **Perhatikan `public_page_enabled`** di setiap jalur SEO — tenant yang dimoderasi tidak boleh muncul di sitemap maupun diindeks.
5. Untuk 3.5.10, buat dua tenant dengan data sangat berbeda agar salah satu tag yang keliru langsung terlihat.
6. Bila terpaksa menambah kolom SEO di `company_profiles`, tambahkan sebagai nullable dan catat di [Decisions Made During Implementation](#decisions-made-during-implementation).
