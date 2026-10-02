# Fase 5 — Fitur Masa Depan (Backlog Terkendali)

> **Status:** CAMPURAN — sebagian item **disetujui (gelombang 1)**, sisanya **BACKLOG — tidak boleh dimulai.**
> **Ketergantungan:** Fase 1–4 selesai **dan** fitur produk terkait sudah **disetujui** tim.
> **Menambah fitur produk?** ✅ Ya — itulah alasan fase ini dipisahkan.
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)

> ✅ **PEMBARUAN 2026-09-30 — sebagian item dipromosikan ke gelombang 1.**
> Pemilik produk menyetujui **lima item** masuk gelombang 1 (target launching **1 Januari 2027**) dengan bantuan AI agent:
> **B-2** (SaaS subscription billing saja), **B-3** (WhatsApp bot **dua arah**), **B-5 Lite** (payment link/invoice — **bukan** e-commerce penuh), **B-6** (cashflow **minimalis**), dan **B-8** (SEO automation **dasar**).
> Item lain di dokumen ini **tetap DEFERRED**. Urutan & tanggal kerja: [`implementation-schedule.md`](implementation-schedule.md) v2.0.0.
> **Lingkup versi gelombang 1 sengaja dipersempit** — jangan melebar tanpa keputusan baru.

> ⚠️ **PERINGATAN PENTING**
> Dokumen ini **bukan** rencana kerja. Ini adalah **daftar tunggu** agar ide-ide produk tidak "bocor" ke fase fondasi.
> **Setiap** item di sini memerlukan **persetujuan produk** terpisah sebelum boleh dikerjakan. Tidak ada satu pun item yang boleh dikerjakan atas dasar "kelihatannya akan dibutuhkan".
> Item yang **sudah** disetujui dikerjakan **lewat `implementation-schedule.md`**, bukan langsung dari daftar ini.

> ⚠️ **Living document.** Setiap item yang disetujui harus dipecah menjadi fase/dokumen tersendiri, bukan dikerjakan langsung dari daftar ini.

---

## Objective

Menjaga jejak ide fitur SaaS **tanpa** mengimplementasikannya, sekaligus mencatat **apa yang sudah disiapkan** untuk masing-masing sehingga nanti tidak perlu membongkar fondasi.

Fase ini menjawab pertanyaan: *"Kalau fitur ini benar-benar disetujui, seberapa besar pekerjaannya, dan fondasi apa yang sudah ada?"*

---

## Scope

### Termasuk

- Daftar ide fitur beserta: kesiapan fondasi, pekerjaan yang tersisa, kebutuhan infrastruktur, risiko, dan prasyarat keputusan.
- Kriteria "kapan item boleh masuk fase nyata".
- **Pencatatan item yang telah dipromosikan** ke gelombang 1 (lihat blok pembaruan di atas).

### Tidak termasuk

- **Item yang masih berstatus DEFERRED** — implementasi apa pun atas item tersebut dilarang dari dokumen ini.
- Migrasi atau pembuatan tabel untuk item yang belum disetujui.
- Pengerjaan langsung dari daftar ini: item yang disetujui **wajib** dikerjakan lewat dokumen fase atau `implementation-schedule.md`.

---

## Prerequisites (untuk mengaktifkan item apa pun)

| Prasyarat | Keterangan |
|---|---|
| Persetujuan produk tertulis | Nama fitur, tier, dan perilaku harus jelas |
| Keputusan billing (D-05) | Bila fitur berkaitan dengan penagihan |
| Entitlement aktif (Fase 4) | Fitur berbayar harus dapat dibatasi |
| Isolasi tenant terjamin (Fase 1–2) | Fitur baru tidak boleh menambah jalur kebocoran |
| Pemecahan menjadi fase baru | Item tidak boleh dikerjakan langsung dari dokumen ini |

**Kriteria kelayakan masuk fase nyata (wajib semuanya):**

1. Ada pihak yang menyetujui dan bertanggung jawab atas fitur tersebut.
2. Ada definisi perilaku yang cukup untuk ditulis sebagai acceptance criteria.
3. Ada jawaban atas pertanyaan "bagaimana fitur ini dibatasi antar-tenant?".
4. Ada perkiraan dampak biaya operasional (bila ada layanan pihak ketiga).
5. Fondasi yang dibutuhkan (dari Fase 1–4) sudah ada.

---

## Current State

Setelah Fase 1–4 (yang direncanakan):

| Fondasi | Kondisi |
|---|---|
| Isolasi tenant | Terjamin lewat global scope + test |
| Konteks tenant | Tersedia untuk request, job, command |
| Storage | Konvensi `tenants/{id}/…` + seam siap (belum ada upload) |
| Domain | Resolusi tenant terabstraksi; tabel `domains` masih desain |
| SEO | Meta/OG/canonical/sitemap/robots per tenant |
| Entitlement | `Feature` enum + plan di config + `Team::can()` |
| Billing | **Belum ada** |
| Audit platform | Tersedia |

Idiom fitur yang belum ada: katalog/produk, pesanan, keranjang, pembayaran, WhatsApp, AI, analitik, kuota.

---

## Tasks

> **Tidak ada tugas yang boleh dikerjakan langsung dari dokumen ini.** Setiap item harus dijadwalkan lebih dulu (lihat `implementation-schedule.md`). Item yang masih **DEFERRED** tidak boleh dikerjakan sama sekali.

### Item B-1 — Custom Domain (Penuh)

| Aspek | Keterangan |
|---|---|
| **Status** | ❌ Belum disetujui (PDR-04) |
| **Tier terkait** | Plus / Pro (ide) |
| **Fondasi siap** | ✅ `TenantResolver` (Fase 3), `config/tenancy.php`, route publik terpisah, kebijakan keamanan Host header tertulis |
| **Sisa pekerjaan** | Tabel `domains` + migrasi; CRUD domain untuk tenant; verifikasi kepemilikan (TXT/CNAME); `ResolveTenantFromHost` middleware; redirect domain non-primer; SSL otomatis; DNS wildcard |
| **Infrastruktur** | Wildcard DNS, reverse proxy meneruskan `Host`, ACME/Let's Encrypt (Caddy) atau Cloudflare for SaaS, domain staging |
| **Risiko utama** | Host header injection, domain takeover, redirect loop, cookie lintas domain |
| **Prasyarat keputusan** | PDR-04, D-08 |
| **Estimasi besar** | Tinggi — melibatkan aplikasi + infra + dukungan pelanggan |

### Item B-2 — Billing & Subscription ✅ **GELOMBANG 1**

| Aspek | Keterangan |
|---|---|
| **Status** | ✅ **Disetujui 2026-09-30** — gelombang 1, **hanya sisi langganan SaaS** (tenant → platform). UI upgrade/downgrade tetap gelombang 2. |
| **Fondasi siap** | ✅ Entitlement (Fase 4), desain `plans`/`subscriptions` (Fase 4) |
| **Sisa pekerjaan** | Tabel `plans`/`subscriptions`; integrasi provider; webhook; status siklus langganan; trial; invoice; dunning; UI upgrade/downgrade |
| **Catatan Indonesia** | Midtrans/Xendit **bukan** subscription billing penuh — status langganan kemungkinan harus dikelola sendiri (job pengecekan periode) |
| **Risiko utama** | Uang nyata: kesalahan status = kerugian finansial; webhook tidak idempoten; penagihan ganda |
| **Prasyarat keputusan** | D-05, PDR-05 |
| **Estimasi besar** | Tinggi |

### Item B-3 — WhatsApp Integration ✅ **GELOMBANG 1**

| Aspek | Keterangan |
|---|---|
| **Status** | ✅ **Disetujui 2026-09-30** — gelombang 1 sebagai **bot dua arah** (pengiriman + webhook masuk + balasan otomatis). |
| **Fondasi siap** | ⚠️ Sebagian: entitlement ✅, pola job konteks tenant ✅ (Fase 2) |
| **Sisa pekerjaan** | Integrasi penyedia (mis. WhatsApp Business API), template pesan, antrian pengiriman, log pengiriman, kuota |
| **Risiko utama** | Biaya per pesan, aturan kebijakan penyedia, penyalahgunaan (spam) oleh tenant |
| **Prasyarat keputusan** | Persetujuan produk + keputusan penyedia |
| **Estimasi besar** | Sedang–Tinggi |

### Item B-4 — Katalog / Pesanan / Keranjang

| Aspek | Keterangan |
|---|---|
| **Status** | ⏸️ **GELOMBANG 2 (2026-09-30)** — keputusan pemilik produk: *"Lite dulu"*. E-commerce penuh menyusul. |
| **Fondasi siap** | ✅ Isolasi tenant & storage seam |
| **Sisa pekerjaan** | Produk, varian, stok, pesanan, keranjang, ongkir, status pesanan, UI toko publik |
| **Risiko utama** | Ini **produk baru** (e-commerce), bukan sekadar penambahan modul; beban dukungan tinggi |
| **Prasyarat keputusan** | Persetujuan produk yang jelas |
| **Estimasi besar** | Sangat tinggi — pertimbangkan apakah ini benar-benar arah perusahaan |

### Item B-5 — Payment Gateway (untuk pelanggan tenant) — ⚠️ **GELOMBANG 1 versi LITE**

| Aspek | Keterangan |
|---|---|
| **Status** | ✅ **Disetujui 2026-09-30** — namun **hanya versi Lite** (payment link/invoice). Versi penuh (untuk pesanan) tetap menunggu B-4. |
| **Versi gelombang 1 (Lite)** | Tenant membuat tagihan → pelanggan membayar via link (Midtrans Snap / Xendit Invoice) → webhook + tanda terima + reminder. **Tanpa** katalog, keranjang, atau ongkir. |
| **Ketergantungan** | Versi **Lite**: tidak bergantung B-4. Versi **penuh**: B-4 (katalog/pesanan) |
| **Sisa pekerjaan** | Integrasi gateway, webhook pembayaran, rekonsiliasi, refund, status pesanan |
| **Risiko utama** | Sangat tinggi (transaksi finansial pelanggan tenant) |
| **Prasyarat keputusan** | B-4 disetujui lebih dulu |
| **Estimasi besar** | Tinggi |

### Item B-6 — Keuangan/Cashflow Lanjutan — ⚠️ **GELOMBANG 1 versi MINIMALIS**

| Aspek | Keterangan |
|---|---|
| **Status** | ✅ **Disetujui 2026-09-30** — gelombang 1 hanya versi **minimalis**: laporan periode/kategori, ringkasan, dashboard, ekspor. Fitur lanjutan tetap gelombang 2. |
| **Fondasi siap** | ✅ Modul transaksi + isolasi tenant |
| **Sisa pekerjaan** | Laporan, periode, kategori lanjutan, ekspor, dashboard |
| **Catatan** | Ini **penyempurnaan** modul yang ada, bukan modul baru. Bila disetujui, pecah menjadi fase UI/reporting |
| **Risiko utama** | Rendah–Sedang |
| **Prasyarat keputusan** | Persetujuan produk |

### Item B-7 — AI / Automation

| Aspek | Keterangan |
|---|---|
| **Status** | ❌ Belum disetujui (PDR-07) |
| **Fondasi siap** | ⚠️ Sebagian: pola job konteks tenant (Fase 2), entitlement (Fase 4) |
| **Sisa pekerjaan** | Provider abstraction (jangan panggil SDK langsung dari controller), penyimpanan API key di secret manager, job asinkron idempoten, pencatatan pemakaian, kuota, rate limit, kontrol biaya |
| **Risiko utama** | **Biaya tidak terkendali** (RSK-08); kebocoran data tenant ke provider pihak ketiga; isi prompt masuk log |
| **Prasyarat keputusan** | PDR-07 + keputusan kuota/biaya |
| **Estimasi besar** | Sedang (fitur sederhana) hingga Tinggi (otomasi kompleks) |

> **Kuota wajib lebih dulu.** Jangan pernah merilis fitur AI tanpa batas pemakaian per tenant.

### Item B-8 — SEO & Content Tooling Lanjutan — ⚠️ **GELOMBANG 1 versi DASAR**

| Aspek | Keterangan |
|---|---|
| **Status** | ✅ **Disetujui 2026-09-30** — gelombang 1 hanya **otomasi dasar** (otomasi meta/schema, ping sitemap). Skor SEO, audit on-page, Search Console, hreflang, dan redirect manager tetap gelombang 2. |
| **Fondasi siap** | ✅ Meta/OG/canonical/sitemap/robots/structured data per tenant |
| **Sisa pekerjaan** | Skor SEO konten, audit on-page, saran internal linking, integrasi Search Console, hreflang, redirect manager |
| **Risiko utama** | Sedang — bermanfaat tetapi bukan kebutuhan mendasar |
| **Prasyarat keputusan** | Persetujuan produk |

### Item B-9 — Analytics & Reporting

| Aspek | Keterangan |
|---|---|
| **Status** | ❌ Belum disetujui |
| **Fondasi siap** | ⚠️ Sebagian: `activity_logs` per tenant & audit platform |
| **Sisa pekerjaan** | Agregasi metrik, dashboard, penyimpanan data historis, kemungkinan job agregasi berkala |
| **Risiko utama** | Kinerja query lintas tenant dalam jumlah besar; privasi data |
| **Prasyarat keputusan** | Persetujuan produk + definisi metrik |
| **Estimasi besar** | Sedang–Tinggi |

### Item B-10 — Quota, Usage Limit & Add-on

| Aspek | Keterangan |
|---|---|
| **Status** | ⚠️ **Sebagian gelombang 1** — hanya *kuota/usage dasar* yang menyertai SaaS billing. Kuota lanjutan & add-on: ❌ belum disetujui (PDR-06) |
| **Fondasi siap** | ✅ Entitlement (Fase 4) |
| **Sisa pekerjaan** | Tabel `usage_counters`/`tenant_addons`, penghitungan pemakaian, penegakan kuota, UI |
| **Risiko utama** | Penghitungan yang salah → tagihan/limit keliru |
| **Prasyarat keputusan** | PDR-06, D-05 |
| **Estimasi besar** | Sedang |

### Item B-11 — Onboarding Self-Service

| Aspek | Keterangan |
|---|---|
| **Status** | ❓ Terbuka (PDR-03, D-10) — saat ini invitation-only |
| **Fondasi siap** | ✅ `CreateTeam` action, alur invitation |
| **Sisa pekerjaan** | Registrasi publik, pembuatan tenant otomatis, verifikasi email, onboarding wizard, anti-abuse (rate limit/CAPTCHA) |
| **Risiko utama** | **Tinggi**: spam tenant, penyalahgunaan, beban moderasi; juga membatalkan aturan "satu user = satu tenant" |
| **Prasyarat keputusan** | PDR-03, D-10, D-11 |
| **Estimasi besar** | Sedang secara kode, tinggi secara operasional |

### Item B-12 — Object Storage & Media Manager

| Aspek | Keterangan |
|---|---|
| **Status** | ⚠️ Fondasi sudah ada (Fase 1: seam + konvensi) |
| **Sisa pekerjaan** | Upload UI, validasi tipe/ukuran, thumbnail/optimasi, migrasi `local` → S3, kuota penyimpanan, penghapusan file saat tenant dihapus |
| **Risiko utama** | Biaya penyimpanan/egress, file yatim, isolasi file salah |
| **Prasyarat keputusan** | D-07 |
| **Estimasi besar** | Sedang |

### Item B-13 — Multi-Domain / Subdomain per Tenant

| Aspek | Keterangan |
|---|---|
| **Status** | ❓ Terbuka (D-08) |
| **Ketergantungan** | B-1 |
| **Sisa pekerjaan** | Wildcard DNS, penentuan domain utama, redirect |
| **Risiko utama** | Sama seperti B-1 |
| **Estimasi besar** | Sedang (bila B-1 dikerjakan) |

---

## Database Changes

**Dokumen ini sendiri tidak mengizinkan perubahan skema.** Namun lima item gelombang 1 **sudah disetujui** lewat keputusan terpisah (2026-09-30), sehingga tabelnya **akan** dibuat sesuai jadwal:

| Tabel | Status |
|---|---|
| `subscriptions`, `invoices` | ✅ Gelombang 1 — SaaS billing + payment link |
| `whatsapp_*` | ✅ Gelombang 1 — bot dua arah |
| `plans` (config dulu) | ✅ Gelombang 1 |
| `usage_counters` (kuota dasar) | ✅ Gelombang 1 |
| `domains`, `products`, `orders`, `carts`, `tenant_addons`, `ai_*` | ❌ Tetap dilarang dari dokumen ini |

---

## Backend Changes

**Tidak ada dari dokumen ini.** Implementasi gelombang 1 dikerjakan lewat `implementation-schedule.md`.

---

## Frontend Changes

**Tidak ada dari dokumen ini.**

---

## Infrastructure Changes

**Tidak ada dari dokumen ini.** Kebutuhan infrastruktur gelombang 1 (Redis, worker antrian, proses SSR, kredensial provider) dicatat di master plan §17 dan `implementation-schedule.md`.

Bagian ini hanya mencatat kebutuhan infrastruktur yang **akan** muncul bila item disetujui — lihat tabel per item di [Tasks](#tasks), terutama B-1 (DNS, reverse proxy, SSL) dan B-12 (object storage).

---

## Security Considerations

Untuk setiap item, pertanyaan keamanan yang **wajib** dijawab sebelum implementasi:

| Pertanyaan | Berlaku untuk |
|---|---|
| Bagaimana fitur ini dibatasi antar-tenant? | Semua |
| Apakah menambah jalur baru untuk kebocoran data? | Semua |
| Apakah ada data tenant yang keluar ke pihak ketiga? | B-2, B-3, B-5, B-7, B-8, B-9 |
| Apakah ada biaya per pemakaian yang bisa disalahgunakan? | B-3, B-5, B-7, B-12 |
| Apakah webhook dari pihak ketiga idempoten dan terverifikasi? | B-2, B-3, B-5 |
| Apakah ada data keuangan/pribadi yang perlu retensi khusus? | B-2, B-4, B-5, B-9 |
| Apakah aturan "satu user = satu tenant" masih berlaku? | B-11 |

---

## Testing Requirements

Tidak ada test yang perlu ditulis **dari dokumen ini**. Pekerjaan gelombang 1 tetap tunduk pada aturan di bawah.

**Aturan tetap:** fitur apa pun yang kelak diimplementasikan **wajib**:
1. Menambah test isolasi tenant untuk endpoint/query baru.
2. Memperbarui test isolasi (Fase 1) agar mencakup jalur baru.
3. Lulus `composer test` dan `npm run types:check`.

---

## Migration Considerations

Tidak ada migrasi pada fase ini.

**Aturan tetap:** setiap item yang kelak dikerjakan harus mengikuti ADR-17 (perubahan aditif & *backward compatible*), dan menyediakan rencana migrasi data bila menyentuh data tenant yang sudah ada.

---

## Acceptance Criteria

Dokumen ini "selesai" bila:

1. ✅ Seluruh ide fitur produk tercatat di sini dan **tidak ada** yang bocor tanpa jejak ke Fase 1–4.
2. ✅ Setiap item punya status persetujuan yang jelas (belum disetujui / terbuka / **disetujui gelombang 1**).
3. ✅ Item yang **masih** DEFERRED tidak menghasilkan kode, migrasi, tabel, atau UI.
4. ✅ Setiap item mencantumkan fondasi yang sudah siap dan sisa pekerjaannya.
5. ✅ Item yang dipromosikan mencantumkan tanggal keputusan dan **lingkup versinya** (Lite / dasar / minimalis) agar tidak melebar diam-diam.

---

## Risks

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| RF5-A | Item di dokumen ini dikerjakan tanpa persetujuan (RSK-03) | **Tinggi** | Pernyataan tegas di bagian atas; review PR ketat |
| RF5-B | Ekspektasi bisnis menganggap item ini "sudah dikerjakan" | Sedang | Komunikasikan status backlog ke stakeholder |
| RF5-C | Item disetujui mendadak dan dianggap pekerjaan kecil (mis. B-1) | Tinggi | Tabel per item sudah menyebutkan kebutuhan infra & risiko |
| RF5-D | B-4/B-5 mengubah perusahaan menjadi bisnis e-commerce | Sangat tinggi | Keputusan strategis, bukan teknis |
| RF5-E | B-7 (AI) dirilis tanpa kuota → biaya meledak | Tinggi | Kuota wajib lebih dulu (RSK-08) |
| RF5-F | B-11 (self-service) membuka spam tenant | Tinggi | Anti-abuse + moderasi wajib |

---

## Open Questions

| ID | Pertanyaan |
|---|---|
| OQ-F5-1 | Mana dari item B-1…B-13 yang benar-benar ada di roadmap bisnis 12 bulan ke depan? |
| OQ-F5-2 | Apakah perusahaan serius mengarah ke e-commerce (B-4/B-5), atau lebih ke sisi company profile + CRM? |
| OQ-F5-3 | Apakah AI (B-7) akan dijual sebagai fitur berbayar atau sekadar nilai tambah? |
| OQ-F5-4 | Apakah tenant akan diizinkan melakukan self-service onboarding dalam waktu dekat? |
| OQ-F5-5 | Apakah ada kebutuhan data residency/ekspor data yang memaksa tinjauan ulang model tenancy (OQ-03)? |

---

## Known Issues

| # | Isu | Status |
|---|---|---|
| KI5-1 | Tidak ada item dari dokumen ini yang disetujui | Sesuai kondisi |
| KI5-2 | Estimasi besar bersifat kasar, belum dipecah menjadi story | Wajar untuk backlog |
| KI5-3 | Daftar ini bergantung pada PDR-01 yang belum final | Bisa berubah |

---

## Discovered During Implementation

> **Tidak relevan** untuk dokumen backlog — tidak ada implementasi yang berjalan di sini.
> Bila ada temuan, catat di dokumen fase yang **sedang** dikerjakan, bukan di sini.

---

## Decisions Made During Implementation

> **Tidak relevan** untuk dokumen backlog.

---

## Deferred

**Seluruh isi dokumen ini adalah DEFERRED.**

| Item | Status |
|---|---|
| B-1 Custom domain penuh | DEFERRED |
| B-2 Billing & subscription | ⚠️ **Sebagian gelombang 1** (langganan SaaS); UI upgrade/downgrade → gelombang 2 |
| B-3 WhatsApp integration | ⚠️ **Gelombang 1** (bot dua arah); fitur lanjutan → gelombang 2 |
| B-4 Katalog/pesanan/keranjang | ⏸️ **Gelombang 2** |
| B-5 Payment gateway | ⚠️ **Gelombang 1 versi Lite**; versi penuh → gelombang 2 |
| B-6 Keuangan lanjutan | ⚠️ **Gelombang 1 versi minimalis**; lanjutan → gelombang 2 |
| B-7 AI/automation | DEFERRED |
| B-8 SEO & content tooling lanjutan | ⚠️ **Gelombang 1 versi dasar**; lanjutan → gelombang 2 |
| B-9 Analytics & reporting | DEFERRED |
| B-10 Quota/usage/add-on | ⚠️ **Sebagian gelombang 1** (kuota dasar); sisanya DEFERRED |
| B-11 Onboarding self-service | DEFERRED |
| B-12 Object storage & media manager | DEFERRED |
| B-13 Multi-domain/subdomain | DEFERRED |

**DEFERRED tegas (dilarang keras pada tahap ini):**

- Implementasi **AI / automation** (B-7)
- Implementasi **custom domain lengkap** (B-1) dan **multi-domain/subdomain** (B-13)
- Implementasi **e-commerce penuh**: katalog/pesanan/keranjang (B-4) + payment gateway untuk pesanan
- **Billing lanjutan**: dunning otomatis, trial otomatis, UI upgrade/downgrade
- **Kuota/usage lanjutan** dan **add-on** (B-10)
- **Onboarding self-service** (B-11)
- **Object storage / media manager** (B-12)
- **Analytics & reporting** (B-9)
- **Otomasi SEO tingkat lanjut** (skor SEO, audit on-page, Search Console, hreflang, redirect manager)
- Ekspansi CMS lengkap

> **Catatan:** payment gateway (versi Lite) dan WhatsApp (bot dua arah) **bukan lagi deferred total** — versinya yang disetujui sudah tercatat sebagai gelombang 1 di atas.

---

## Technical Debt

Tidak ada utang teknis yang **dihasilkan** oleh dokumen ini, karena tidak ada kode yang ditulis.

Utang teknis **yang akan muncul** bila item dikerjakan terburu-buru (peringatan):

| ID | Utang potensial | Pemicu |
|---|---|---|
| TD5-1 | Kolom `teams.plan` ditambahkan sebagai jalan pintas | B-2 dikerjakan tanpa Fase 4 |
| TD5-2 | Domain dipercaya dari `Host` tanpa verifikasi | B-1 dikerjakan tanpa kebijakan Fase 3 |
| TD5-3 | AI dipanggil sinkron di dalam request | B-7 dikerjakan tanpa pola job Fase 2 |
| TD5-4 | File tenant disimpan tanpa prefix `tenants/{id}/` | B-12 dikerjakan tanpa seam Fase 1 |
| TD5-5 | Status langganan salah karena webhook tidak idempoten | B-2 tanpa desain state machine |

---

## Post-Implementation Notes

> **Diisi ketika item backlog dipromosikan menjadi fase nyata.** Catat di sini: item mana yang disetujui, kapan, oleh siapa, dan ke dokumen fase mana ia dipindahkan.

_Belum ada entri._

| Tanggal | Item | Disetujui oleh | Dipindahkan ke |
|---|---|---|---|
| 2026-09-30 | B-2 SaaS subscription billing | Pemilik produk | `implementation-schedule.md` v2.0.0 (gelombang 1) |
| 2026-09-30 | B-3 WhatsApp bot (dua arah) | Pemilik produk | `implementation-schedule.md` v2.0.0 (gelombang 1) |
| 2026-09-30 | B-5 Lite — payment link/invoice | Pemilik produk | `implementation-schedule.md` v2.0.0 (gelombang 1) |
| 2026-09-30 | B-6 Cashflow minimalis | Pemilik produk | `implementation-schedule.md` v2.0.0 (gelombang 1) |
| 2026-09-30 | B-8 SEO automation (dasar) | Pemilik produk | `implementation-schedule.md` v2.0.0 (gelombang 1) |

---

## Implementation Notes

1. **Jangan kerjakan item yang masih DEFERRED dari dokumen ini.** Tujuannya semata menjaga jejak ide. Item yang sudah dipromosikan dikerjakan **lewat jadwal** (`implementation-schedule.md`), bukan langsung dari daftar ini.
2. Bila seorang stakeholder meminta salah satu item, langkah pertamanya **bukan** menulis kode, melainkan: (a) dapatkan persetujuan tertulis, (b) pecah menjadi fase baru dengan struktur dokumen yang sama seperti Fase 1–4, (c) catat di [Post-Implementation Notes](#post-implementation-notes).
3. Urutan ketergantungan yang wajar bila kelak dikerjakan: **B-12** (storage) dan **B-2** (billing) lebih dulu sebelum item yang bergantung padanya; **B-1** sebelum **B-13**; **B-4** sebelum **B-5**.
4. Ingat prinsip utama proyek: **"Siapkan arsitektur, bukan produk."**
