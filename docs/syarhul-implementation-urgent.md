# Syarhul Implementation Urgent

> **Status:** Panduan prioritas — berlaku **segera**, sebelum tim mulai bekerja.
> **Tujuan:** Menjelaskan urutan pengerjaan yang paling mendesak dan **mengapa** urutannya berbeda dari `docs/IMPLEMENTATION_PLAN.md`.
> **Hubungan dengan dokumen induk:** Dokumen ini **bukan pengganti** `docs/IMPLEMENTATION_PLAN.md`. Dokumen induk tetap menjadi peta jangka panjang. Dokumen ini hanya menetapkan **urutan eksekusi** yang berbeda (lihat [Bagian 4](#4-pemetaan-prioritas--fase-dokumen-induk)).
> **Bahasa:** Indonesia; istilah teknis dipertahankan dalam bahasa Inggris.

---

## 0. Kenapa Dokumen Ini Ada

Fase 1–5 di dokumen induk adalah **peta lengkap**, tetapi urutannya terlalu netral. Ada risiko nyata: pekerjaan yang mahal dan tidak mendesak dikerjakan lebih dulu, sementara **satu cacat keamanan** yang bisa membunuh produk justru tertunda.

Dokumen ini menjawab satu pertanyaan saja:

> **"Apa yang wajib beres sebelum tenant kedua masuk, atau sebelum deployment produksi pertama?"**

Sesudah itu, kembali ke dokumen induk.

---

## 1. Ringkasan (TL;DR)

| Urutan | Item | Sifat | Kenapa di sini |
|---|---|---|---|
| **P-0** | Version control | Prasyarat | Kamu akan upload ke GitHub — tanpa `.git`, tidak ada apa pun untuk di-review |
| **P-1** | Test isolasi tenant | **DEFECT** | Satu-satunya item yang merupakan cacat, bukan hygiene |
| **P-2** | Global scope isolasi tenant | **DEFECT** | Perbaikan definitif dari P-1 |
| **P-3** | MySQL + Redis | Infrastruktur | Wajib sebelum tenant kedua, tapi **setelah** P-1/P-2 |
| **P-4** | Keputusan istilah "team" | Naming | Murah, gratis, mencegah kebingungan tim |
| **P-5** | Audit platform + backup | Safety net | Sebelum ada tenant yang datanya berharga |
| **G-1…G-5** | **Integrasi gelombang 1** (WhatsApp bot, cashflow minimalis, SEO automation, SaaS billing, payment link Lite) | Fitur produk | ✅ **Disetujui 2026-09-30** — dikerjakan **setelah** P-0…P-5 |
| — | Custom domain, e-commerce penuh, AI, analytics, media manager | Fitur produk | **JANGAN dulu** |

**Prinsip pemisahnya sederhana:**
- **P-1 dan P-2 adalah cacat** → memperbaiki = menghilangkan risiko.
- **P-3 sampai P-5 adalah hygiene** → memperbaiki = memindahkan batas kapasitas.
- **Sisanya adalah produk** → memperbaiki = menambah nilai, tapi hanya setelah ada pelanggan.

---

## 2. Prioritas Rinci

### P-0. Version Control (Prasyarat Mutlak)

**Temuan:** `git log` di folder ini gagal dengan pesan `fatal: not a git repository (or any of the parent directories)`. Artinya, folder ini **bukan repository git**.

**Kenapa ini masuk daftar urgent:** rencanamu adalah meng-upload ke GitHub supaya tim bisa mulai bekerja. Tanpa `.git`:
- tidak ada history, sehingga tidak ada rollback;
- tim tidak bisa membuat pull request, sehingga tidak ada proses review;
- penyebab bug tidak dapat dilacak (`git blame` tidak tersedia).

**Tindakan:**
1. Pastikan repository di-*init* di lokasi yang benar, dan `.gitignore` sudah sesuai.
2. Pastikan folder `docs/` **ikut terlacak** (jangan di-*ignore*) — dokumen perencanaan ini harus ikut ke GitHub. Saat ini `.gitignore` tidak mengecualikan `docs/`, jadi ini aman.
3. **Perhatikan OneDrive.** Menyimpan `.git` di dalam folder yang disinkronkan OneDrive adalah kombinasi rawan: berkas `.git` dapat rusak atau hilang saat sinkronisasi, terutama ketika folder berisi `node_modules` dan `vendor` dalam jumlah besar. Pertimbangkan memindahkan proyek ke path lokal biasa, atau minimal pastikan repository remote (GitHub) selalu menjadi sumber kebenaran.

**Kriteria selesai:**
- `git status` berjalan tanpa error.
- `git ls-files docs` menampilkan `IMPLEMENTATION_PLAN.md`, `syarhul-implementation-urgent.md`, dan kelima dokumen fase.
- Riwayat commit pertama sudah ada.

**Risiko bila dilewat:** semua prioritas lain kehilangan jaring pengaman. Ini yang membuat P-0 selalu nomor satu, walaupun bukan pekerjaan teknis yang "sulit".

---

### P-1. Test Isolasi Tenant — Prioritas Teknis Nomor Satu

**Kategori: DEFECT (bukan hygiene).**

**Masalah.** Seluruh data domain dipisahkan oleh kolom `team_id`, tetapi pemisahan itu **diterapkan secara manual** di setiap controller dan FormRequest. Tidak ada global scope yang menjaminnya di level query.

**Kenapa ini yang paling urgent.** Ini satu-satunya item di seluruh rencana yang bisa **membunuh produk**. Skenarionya:

- Seorang developer membuat endpoint baru dan lupa menambahkan `->forTeam($team)`.
- Tidak ada test yang menangkapnya.
- Dashboard tenant A menampilkan leads tenant B.

Untuk produk SaaS, kebocoran data antar pelanggan bukan sekadar bug — ini insiden yang tidak dapat ditarik kembali, dan menyangkut kepercayaan serta potensi masalah hukum.

**Tindakan:**
1. Buat helper test dengan **dua tenant berisi data yang sengaja dibuat mirip** (nama perusahaan sama, hanya `team_id` berbeda). Dua tenant dengan data yang jelas berbeda tidak akan membuktikan apa pun.
2. Uji `index` untuk seluruh modul: proyek, task, lead, content, transaction, portfolio, activity-log, company-profile.
3. Uji `update`/`delete` terhadap ID milik tenant lain → harus 404/403.
4. Uji user non-anggota membuka `{current_team}/dashboard` → harus 403.
5. Uji halaman publik tenant B tidak membocorkan data tenant A.
6. Uji form konsultasi tenant B tidak menulis lead ke tenant A.
7. Jadikan kelompok test ini **wajib di CI** dan jangan pernah di-*skip*.

**Bukti bahwa test ini bukan test palsu (wajib dilakukan).**
Sementara hapus `->forTeam(...)` dari salah satu controller, jalankan test, dan pastikan test **GAGAL**. Lalu kembalikan. Test isolasi yang selalu hijau tidak membuktikan apa pun.

**Kriteria selesai:** seluruh test di atas hijau di CI, **dan** sudah dibuktikan gagal saat proteksi sengaja dimatikan.

**Rujukan:** Fase 1 (`phase-01-fondasi.md`), tugas 1.2.

---

### P-2. Global Scope Isolasi Tenant — Perbaikan Definitif

**Kategori: DEFECT (lanjutan dari P-1).**

**Kenapa urutannya setelah P-1, bukan bersamaan.** Global scope mengubah perilaku hampir semua query. Tanpa jaring pengaman dari P-1, kamu tidak akan bisa membedakan mana kegagalan akibat perubahan dan mana yang memang sudah bocor sebelumnya. **P-1 adalah alat ukurnya; P-2 adalah perbaikannya.**

**Tindakan:**
1. Buat `TenantContext` sebagai satu sumber tenant aktif.
2. Pasang global scope di trait `BelongsToTeam`, **per model, satu per satu** — jalankan test setiap langkah.
3. Buat query model tenant-scoped tanpa konteks **gagal keras** (exception), jangan mengembalikan data yang salah.
4. Sediakan escape hatch `withoutTeamScope()` yang namanya eksplisit, agar mudah diaudit saat code review.
5. Perhatikan khusus jalur **platform layer, seeder, dan command** — ketiganya memang beroperasi lintas tenant dan harus memakai konteks eksplisit, bukan "dipaksa" masuk ke satu tenant.

**Kriteria selesai:** suite isolasi P-1 tetap hijau **tanpa mengubah satu pun ekspektasi test**, dan platform layer tetap berfungsi penuh.

**Rujukan:** Fase 2 (`phase-02-tenant-context.md`), tugas 2.1 dan 2.2.

> ⚠️ **Risiko tertinggi di seluruh rencana.** Jangan dikerjakan sebagai satu commit besar.

---

### P-3. Database Produksi: MySQL + Redis

**Koreksi penting atas alasan.** Selama ini disebutkan alasannya adalah "karena multi-tenant, datanya bisa sangat banyak". Sebenarnya **bukan volume**, karena SQLite sanggup menangani data berukuran gigabyte tanpa masalah. Alasan sebenarnya ada dua:

1. **Write concurrency.** SQLite mengunci **seluruh berkas database** saat ada operasi tulis. Begitu beberapa tenant menulis pada saat yang sama, muncul error `database is locked`. Ini bukan soal jumlah data, melainkan soal jumlah penulis bersamaan.
2. **Tidak bisa multi app-server.** SQLite adalah berkas lokal, sehingga aplikasi tidak dapat dijalankan di lebih dari satu server. Ini memblokir horizontal scaling.

**Tindakan, bertahap dan berurutan:**

1. **CI cross-engine (kerjakan duluan — murah dan cepat).** Jalankan migrasi di MySQL **dan** Postgres di CI, bukan hanya SQLite. Ini menangkap lebih awal perbedaan yang mudah terlewat, misalnya perilaku index `unique` terhadap nilai `NULL` dan perbedaan kolasi string.
2. **Pindahkan cache, session, dan queue ke Redis.** Ini bagian yang sering terlupa: saat ini `CACHE_STORE`, `SESSION_DRIVER`, dan `QUEUE_CONNECTION` semuanya memakai driver `database`. Setelah pindah ke MySQL tanpa memindahkan ketiganya, cache, session, queue, dan query tenant akan berebut di satu database yang sama — bottleneck-nya hanya berpindah, bukan hilang.
3. **Cutover saat deployment produksi pertama — bukan sekarang.** Memigrasi database yang belum dipakai produksi tidak memberi nilai apa pun, dan justru menambah permukaan risiko sebelum isolasi (P-1/P-2) beres.
4. **Pastikan `APP_ENV=production` tidak memakai SQLite.** Bisa lewat guard saat boot atau prosedur operasional yang jelas.

**Kriteria selesai:** CI hijau di MySQL dan Postgres; Redis dipakai untuk cache/session/queue; tidak ada lagi ketergantungan pada `database` driver di konfigurasi produksi.

**Rujukan:** Fase 1 tugas 1.4.5, Fase 3 konfigurasi, master plan §8.3 dan §17.

---

### P-4. Istilah "team" — Putuskan dan Dokumentasikan, Jangan Di-rename

**Kategori: NAMING (bukan defect).**

**Setuju bahwa masalahnya nyata.** `Team` saat ini bermakna ganda: "tim internal" dan "tenant". Ini memang menimbulkan kebingungan (tercatat sebagai temuan A-3 di dokumen induk).

**Tetapi ini bukan yang urgent.** Alasannya:

- Ini masalah **penamaan**, bukan perilaku. Tidak ada user yang terdampak.
- `team_id` dipakai di **11 tabel** dan dirujuk di **puluhan berkas kode**. Rename atau introduksi tabel `tenants` adalah refactor besar.
- Nilainya bagi user: nol. Ini persis jenis pekerjaan yang perencanaan ini secara sengaja hindari ("jangan refactor hanya demi gaya").

**Tindakan (murah, selesai dalam hitungan menit):**
1. Tetapkan glosarium: **`Team` = Tenant** (satu perusahaan/organisasi yang berlangganan).
2. Tulis satu paragraf penjelasan di `README.md`.
3. Catat keputusannya di dokumen induk (ADR-02).

**Trigger yang akan mengubah keputusan ini.** Bila suatu saat tenant membutuhkan **sub-team atau departemen internal** (misalnya "Divisi Marketing" di dalam satu tenant), saat itu istilah "team" benar-benar bentrok dan pembahasan `tenants` harus dilakukan secara serius. **Sampai itu terjadi, jangan disentuh.**

**Kriteria selesai:** glosarium ada; tidak ada perubahan nama tabel atau kolom.

**Rujukan:** Fase 1 tugas 1.1.4, master plan ADR-02 dan temuan A-3.

---

### P-5. Audit Platform dan Backup

**Audit aksi platform.** Saat ini operator platform dapat membuat, menghapus, men-suspend, dan memoderasi tenant, tetapi **tidak ada satu pun catatan** mengenai siapa melakukan apa dan kapan. Untuk SaaS, aksi destruktif lintas tenant wajib dapat diaudit. Solusinya sudah dirancang: tabel `platform_audit_logs` (Fase 2, tugas 2.4).

**Backup.** Semua tenant berbagi satu database. Artinya, satu kegagalan restore berdampak ke **seluruh pelanggan sekaligus**. Ini perlu strategi backup harian yang **diuji restore-nya**, bukan sekadar backup yang diasumsikan berhasil.

**Kriteria selesai:** aksi platform tercatat; ada backup terjadwal dan bukti restore berhasil di lingkungan uji.

**Rujukan:** Fase 2 tugas 2.4, master plan §15 (R-10) dan §17.

---

## 3. Yang JANGAN Dikerjakan Dulu

> ✅ **PEMBARUAN 2026-09-30.** Sebagian item di bagian ini **tidak lagi dilarang**. Pemilik produk menyetujui **lima integrasi** masuk gelombang 1 (target 1 Jan 2027) dengan bantuan AI agent.
> Urutan & tanggal kerjanya ada di [`implementation/implementation-schedule.md`](implementation/implementation-schedule.md) v2.0.0. Yang **tetap** dilarang adalah versi penuh/lanjutan dari item tersebut, dan item di tabel 3.2.

### 3.1 Delegasi baru: integrasi gelombang 1

| Item | Lingkup yang **disetujui** | Lingkup yang **tetap dilarang** |
|---|---|---|
| **WhatsApp** | Bot **dua arah** (pengiriman + webhook masuk + balasan otomatis) | Kampanye massal, template lanjutan |
| **Cashflow** | **Minimalis**: laporan periode/kategori, dashboard, ekspor | Laporan & analitik lanjutan |
| **SEO automation** | **Dasar**: otomasi meta/schema, ping sitemap | Skor SEO, audit on-page, Search Console, hreflang, redirect manager |
| **SaaS billing** | Langganan tenant → platform, invoice, webhook | Dunning otomatis, trial otomatis, UI upgrade/downgrade |
| **Payment** | **Lite**: payment link/invoice untuk pelanggan tenant | **Full**: katalog, keranjang, pesanan, ongkir (butuh B-4) |

> Integrasi ini **tetap** dikerjakan **setelah** P-0…P-5. Jangan biarkan pekerjaan integrasi menunda perbaikan cacat isolasi tenant.

### 3.2 Yang tetap JANGAN dikerjakan

Semua ini tetap bernilai rendah atau berisiko tinggi untuk sekarang:

| Item | Alasan ditunda |
|---|---|
| Custom domain (penuh) | Fitur belum disetujui (PDR-04); butuh DNS, reverse proxy, dan SSL |
| Storage / media manager | Belum ada satu pun upload di aplikasi |
| AI / automation | Belum disetujui; berisiko biaya tak terkendali |
| Katalog / pesanan / keranjang (e-commerce penuh) | Ini bisnis baru, bukan sekadar modul — keputusan "Lite dulu" |
| Memperluas CMS | Belum ada kebutuhan nyata |
| Otomasi SEO tingkat lanjut | Fondasi SEO sudah cukup untuk sekarang |
| Analytics & reporting | Belum disetujui |

Rujukan: `phase-05-fitur-masa-depan.md`.

---

## 4. Pemetaan Prioritas ↔ Fase Dokumen Induk

Dokumen induk tetap berlaku sebagai peta jangka panjang. Yang berubah hanyalah **urutan eksekusi**.

| Prioritas di sini | Setara dengan | Catatan |
|---|---|---|
| P-0 Version control | Menyisipkan temuan baru | Belum ada di dokumen induk — perlu ditambahkan sebagai risiko |
| P-1 Test isolasi tenant | **Fase 1**, tugas 1.2 | Naik menjadi pekerjaan pertama |
| P-2 Global scope | **Fase 2**, tugas 2.1–2.2 | Tetap tepat setelah P-1 |
| P-3 MySQL + Redis | **Fase 1** tugas 1.4.5 + **Fase 3** + §17 | Dinaikkan prioritasnya menjadi "sebelum deploy pertama" |
| P-4 Istilah "team" | **Fase 1**, tugas 1.1.4 | Diturunkan menjadi keputusan dokumen saja |
| P-5 Audit + backup | **Fase 2** tugas 2.4 + §17 | Tepat setelah isolasi beres |
| — | **Fase 3** (SEO/domain) | Ditunda sampai P-1 s/d P-4 selesai |
| **Gelombang 1** (integrasi) | `phase-05` item **B-2, B-3, B-5 Lite, B-6, B-8** | Disetujui 2026-09-30; jadwal di `implementation-schedule.md` |
| — | **Fase 4** (entitlement) | **Gelombang 1** — dibutuhkan oleh SaaS billing (ADR-06/ADR-14 disetujui) |
| — | **Fase 5** (sisa backlog) | Tetap DILARANG |

**Satu-satunya perubahan urutan besar:** Fase 1 dipecah. Pekerjaan yang bersifat *defect* (P-1) didahulukan, sedangkan pekerjaan *persiapan* (storage seam, SEO, konfigurasi) menyusul. Global scope (Fase 2) naik menjadi pekerjaan ketiga.

---

## 5. Checklist Eksekusi

Bisa ditempel langsung sebagai issue atau GitHub Project.

**P-0 — Version control**
- [x] Repository git aktif di lokasi yang benar
- [x] `.gitignore` ditinjau; pastikan `docs/` **tidak** diabaikan
- [x] Commit pertama; repository sudah ter-*push* ke GitHub
- [ ] Pertimbangkan memindahkan proyek keluar dari folder OneDrive

**P-1 — Test isolasi tenant**
- [x] Helper test dua tenant berdata mirip
- [x] Test `index` untuk 8 modul
- [x] Test `update`/`delete` lintas tenant
- [x] Test akses non-anggota
- [x] Test halaman publik & form konsultasi
- [x] Test dijalankan wajib di CI
- [x] **Dibuktikan gagal** saat `forTeam()` sengaja dihapus

**P-2 — Global scope**
- [x] `TenantContext` dibuat
- [x] `TenantContext` menggantikan resolusi tenant yang tersebar
- [x] Global scope aktif per model
- [x] Query tanpa konteks gagal keras
- [x] `withoutTeamScope()` tersedia dan terdokumentasi
- [ ] Platform layer, seeder, dan command tetap berfungsi
- [ ] Suite isolasi P-1 tetap hijau tanpa mengubah ekspektasi

> **Status 2026-10-12.**
> - **P-0** selesai, kecuali memindahkan proyek keluar dari OneDrive (belum dilakukan).
> - **P-1** selesai, termasuk bukti mutation. Langkah gerbang CI sudah dipasang di `.github/workflows/tests.yml`, **tetapi belum pernah benar-benar berjalan**: workflow hanya trigger pada `push` ke `main` dan `pull_request`, sedangkan pekerjaan di-*push* ke branch `syahrul-dev`. Perlu diputuskan sebelum gate ini punya arti.
> - **P-2** — 2.1 dan 2.2.1–2.2.3 selesai (scope aktif di seluruh 8 model, fail-loud, `withoutTeamScope()`). Dua kotak terakhir sengaja belum dicentang: audit jalur khusus, penghapusan `->forTeam()` yang redundan, dan verifikasi ulang suite isolasi adalah 2.2.4–2.2.7 (Selasa 13 Okt). Platform layer + seeder sudah hijau, `command` belum diaudit.
> - **P-3**, **P-4**, **P-5** belum dimulai.

**P-3 — Database produksi**
- [ ] CI menjalankan migrasi di MySQL dan Postgres
- [ ] Cache, session, queue dipindahkan ke Redis
- [ ] Guard: `APP_ENV=production` tidak memakai SQLite
- [ ] Rencana cutover disiapkan untuk deployment pertama

**P-4 — Istilah "team"**
- [ ] Glosarium: `Team` = Tenant
- [ ] Catatan di `README.md`
- [ ] Dicatat sebagai ADR-02 di dokumen induk
- [ ] **Tidak ada** rename tabel/kolom

**P-5 — Audit & backup**
- [ ] `platform_audit_logs` dibuat
- [ ] Buat/hapus/suspend/moderasi tenant tercatat
- [ ] Backup terjadwal
- [ ] Restore diuji dan berhasil

**Quality gate sebelum setiap PR digabung**
- [ ] `composer test` hijau (pint + phpstan level 7 + phpunit)
- [ ] `npm run types:check` hijau
- [ ] `npm run build` dijalankan sebelum `php artisan test`
- [ ] Tidak ada fitur produk yang belum disetujui ikut masuk

---

## 6. Catatan Penutup

1. **Dokumen ini bersifat sementara.** Setelah P-0 sampai P-5 selesai, kembali ke `docs/IMPLEMENTATION_PLAN.md` sebagai acuan tunggal.
2. **Dokumen ini tidak menambah atau mengurangi ruang lingkup.** Ia hanya mengatur urutan. Perluasan lingkup gelombang 1 (lima integrasi) diputuskan **terpisah** pada 2026-09-30 dan dicatat di `implementation-schedule.md` serta `phase-05-fitur-masa-depan.md`.
3. **P-1 dan P-2 adalah satu-satunya pekerjaan yang benar-benar mendesak secara teknis.** Sisanya adalah hygiene dan keputusan. Jangan biarkan pekerjaan hygiene menunda pekerjaan cacat.
4. **Kalau waktunya terbatas, kerjakan P-0 dan P-1 saja.** Dua itu sudah menghilangkan risiko terbesar: kehilangan riwayat kode, dan kebocoran data antar-tenant.
5. Bila dokumen ini dianggap sudah tidak diperlukan lagi, hapus saja — tetapi pastikan temuannya (khususnya P-0 dan P-3) sudah tercatat di dokumen induk terlebih dahulu.

---

## Riwayat Perubahan

| Versi | Tanggal | Perubahan | Penulis |
|---|---|---|---|
| 1.0.0 | 2026-09-23 | Dibuat sebagai panduan prioritas sebelum tim mulai bekerja | Software Architect |
| 1.1.0 | 2026-09-30 | Lima integrasi (WhatsApp bot, cashflow minimalis, SEO automation, SaaS billing, payment link Lite) disetujui masuk gelombang 1; bagian "JANGAN dikerjakan dulu" dipisah menjadi lingkup yang disetujui vs tetap dilarang; rujukan ke `implementation-schedule.md` v2.0.0 | Software Architect |
