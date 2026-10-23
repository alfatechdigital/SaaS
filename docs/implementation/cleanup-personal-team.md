# Rencana: Menghapus Konsep "Personal Team"

> **Status:** ✅ **SELESAI — dieksekusi 2026-10-23.** Lihat [§13 Hasil Eksekusi](#13-hasil-eksekusi).
> **Tanggal disusun:** 2026-10-21. **Dasar:** diskusi 2026-10-21 setelah tugas 2.3 (suspend tenant).
> **Dokumen induk:** [`../IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md) · **Terkait:** [PDR-10](../IMPLEMENTATION_PLAN.md#b2-product-decision-record-pdr), [PDR-06](../IMPLEMENTATION_PLAN.md#b2-product-decision-record-pdr), [ADR-02](../IMPLEMENTATION_PLAN.md#b1-architecture-decision-record-adr)

---

## 1. Latar Belakang

`is_personal` adalah sisa asumsi **starter kit** (setiap user punya "personal team" saat mendaftar). Aplikasi ini sudah memakai **registrasi invitation-only** ([PDR-06](../IMPLEMENTATION_PLAN.md#b2-product-decision-record-pdr)), sehingga:

- Tenant = perusahaan/tim, anggotanya boleh satu orang atau lebih. Tidak ada "tenant pribadi" dalam model bisnis.
- Jalur produksi **tidak pernah** membuat personal team:
    - `App\Actions\Fortify\CreateNewUser` tidak membuat tim apa pun.
    - `PlatformTenantController::store` memanggil `CreateTeam` dengan `isPersonal` default `false`.
- Personal team hanya lahir dari **factory & seeder** (`UserFactory::configure()` membuat tim `is_personal = true`, menempel user sebagai Owner, lalu `switchTeam()`), sehingga muncul sebagai "X's Team \[Personal]" pada data demo.
- Kode sendiri sudah menyebutnya warisan: `HasTeams::belongsToAnotherTeam()` berkomentar _"Personal teams are leftovers from the old self-service signup flow"_.

**Kesimpulan:** di produksi nanti kolom ini tidak akan berisi `true` sama sekali. Yang tersisa hanyalah percabangan kode, guard yang tidak relevan, dan data demo yang membingungkan operator.

## 2. Ruang Lingkup

**Termasuk**

- Menghapus kolom `teams.is_personal` beserta seluruh percabangan kode, prop frontend, dan guard yang bergantung padanya.
- Menyesuaikan factory, seeder, dan test agar model "satu jenis tenant" berlaku konsisten.

**Tidak termasuk**

- Fitur baru apa pun (tidak ada UI/route baru).
- Penghapusan konsep tim secara umum; `Team` tetap = tenant ([ADR-02](../IMPLEMENTATION_PLAN.md#b1-architecture-decision-record-adr)).
- Penegakan `users.is_active` (tetap catatan terpisah; lihat §8).

## 3. Keputusan yang Harus Diambil Sebelum Eksekusi

| ID       | Pertanyaan                                                                         | Rekomendasi                                                                                                                                                                                                                                                                              | Alternatif                                                                                                                                                                                                                                   |
| -------- | ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **PT-1** | Bagaimana menangani kolom & baris personal saat migrasi?                           | **Jalur ringkas (disarankan):** migrasi hanya `dropColumn('is_personal')`, tanpa logika hapus data. DB dev dibersihkan dengan `php artisan migrate:fresh --seed`.                                                                                                                        | **Jalur hati-hati** — hanya bila ada environment non-dev yang sudah berisi personal team: repoint `current_team_id`, hapus membership/invitation, hapus timnya, lalu drop kolom (berhenti dengan error bila tim personal punya data domain). |
| **PT-2** | Setelah personal team hilang, ke mana user tanpa tim diarahkan?                    | `RedirectsToCurrentTeam`: `currentTeam ?? fallbackTeam()`; bila benar-benar tidak ada → `403` (perilaku sekarang ketika personal juga tidak ada).                                                                                                                                        | Redirect ke halaman statis/undangan.                                                                                                                                                                                                         |
| **PT-3** | Aturan `TeamPolicy::leave()` / `delete()` setelah `is_personal` hilang?            | Hapus kondisi `is_personal`. `leave()` tetap melarang Owner keluar; `delete()` tetap butuh `TeamPermission::DeleteTeam`. Catatan: `DeleteTeamRequest` & ability `delete` **tidak punya pemanggil** saat ini (rute tim starter kit sudah dihapus) — boleh ikut dihapus sebagai dead code. | Biarkan policy apa adanya sampai ada rute penghapusan tim dari sisi tenant.                                                                                                                                                                  |
| **PT-4** | Bagaimana `UserFactory` berperilaku setelah perubahan?                             | **Factory tidak lagi membuat tim.** Test yang butuh user ber-tenant membuat tim eksplisit (helper kecil di `tests/TestCase.php`, mis. `createTeamFor(User $user, TeamRole $role = Owner)`).                                                                                              | Factory membuat tim non-personal → setiap user test jadi owner sebuah tenant; merusak arti test undangan (`belongsToAnotherTeam`) dan PDR-10.                                                                                                |
| **PT-5** | Prop/DTO yang tidak lagi dipakai (`isPersonal` di controller, `UserTeam`, tipe TS) | Hapus seluruhnya; `TenantTeamController@index` berhenti mengirim `isPersonal` (halaman `admin/team/Index.vue` memang tidak memakainya).                                                                                                                                                  | Biarkan sebagai `false` permanen (membingungkan).                                                                                                                                                                                            |

## 4. Langkah Eksekusi (urutan yang disarankan)

> **Ringkasnya:** hapus konsepnya → satu migrasi `dropColumn` → `migrate:fresh --seed` untuk membersihkan DB dev. Migrasi tetap "hati-hati" hanya karena ia berjalan di **setiap** environment saat deploy, bukan karena data dev perlu diselamatkan.

1. **Baseline.** Pastikan `composer test` hijau (saat ini 312 test). Tidak perlu backup: `migrate:fresh` memang akan menghapus seluruh data dev, termasuk dua baris audit hasil uji manual suspend.
2. **Migrasi** `database/migrations/2026_10_XX_000001_remove_personal_teams.php`:
    - `up()`: `Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('is_personal'));` — **tanpa** logika hapus/pindah data.
    - `down()`: tambahkan kembali `boolean('is_personal')->default(false)`.
    - Komentar di migrasi mencatat: bila kelak ada environment yang masih berisi baris personal, bersihkan manual dulu (jalur hati-hati di PT-1), karena setelah kolom hilang baris itu otomatis dianggap tenant biasa.
3. **Domain model:** `Team` (properti/fillable/cast), `HasTeams` (`personalTeam()`, filter `belongsToAnotherTeam()`, `toUserTeam()`), `CreateTeam` (parameter `isPersonal`), `UserTeam` (field `isPersonal`).
4. **Controller & policy:** `PlatformTenantController` (prop daftar, dua guard, fallback saat hapus tenant), `TenantTeamController` (prop + fallback anggota dikeluarkan), `RedirectsToCurrentTeam`, `TeamPolicy`, `InviteeHasNoOtherTeam` (verifikasi tetap benar setelah penyederhanaan).
5. **Factory & seeder:** `TeamFactory` (hapus default & state `personal()`), `UserFactory` (hapus pembuatan tim), cek `AlfatechDemoSeeder` tetap memberi membership ke tim demo.
6. **Frontend:** `resources/js/types/teams.ts`, `resources/js/pages/platform/tenants/Index.vue` (tipe, badge "Personal", dua `v-if`).
7. **Test:** sesuaikan/ hapus test yang menguji konsep lama; tambahkan test baru (§6).
8. **Verifikasi:** `php artisan migrate:fresh --seed`, `composer test`, `npm run types:check`, `npm run check`, `npm run build:ssr`, lalu cek manual panel Tenant (semua baris punya tombol suspend & hapus).
9. **Dokumentasi:** perbarui README, [phase-02](phase-02-tenant-context.md), [IMPLEMENTATION_PLAN](../IMPLEMENTATION_PLAN.md) (kolom `teams`, Lampiran A, riwayat), dan tandai dokumen ini **SELESAI** dengan tanggal.

## 5. Daftar Berkas Terdampak

### Backend (`app/`)

| Berkas                                                                                                                   | Perubahan                                                                                                                                         | Risiko |
| ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| [`app/Models/Team.php`](../../app/Models/Team.php)                                                                       | Hapus `@property`, `is_personal` dari `#[Fillable]`, dan cast                                                                                     | Rendah |
| [`app/Concerns/HasTeams.php`](../../app/Concerns/HasTeams.php)                                                           | Hapus `personalTeam()`; sederhanakan `belongsToAnotherTeam()`; hapus `isPersonal:` di `toUserTeam()`                                              | Sedang |
| [`app/Actions/Teams/CreateTeam.php`](../../app/Actions/Teams/CreateTeam.php)                                             | Hapus parameter `$isPersonal` dan kolom pada `Team::create()`                                                                                     | Rendah |
| [`app/Data/UserTeam.php`](../../app/Data/UserTeam.php)                                                                   | Hapus properti `isPersonal`                                                                                                                       | Rendah |
| [`app/Http/Controllers/PlatformTenantController.php`](../../app/Http/Controllers/PlatformTenantController.php)           | Hapus `isPersonal` dari payload daftar; hapus guard di `updateSuspension` & `destroy`; `destroy` memakai `fallbackTeam()`, bukan `personalTeam()` | Sedang |
| [`app/Http/Controllers/TenantTeamController.php`](../../app/Http/Controllers/TenantTeamController.php)                   | Hapus prop `isPersonal`; fallback anggota dikeluarkan cukup `fallbackTeam()`                                                                      | Rendah |
| [`app/Http/Responses/Concerns/RedirectsToCurrentTeam.php`](../../app/Http/Responses/Concerns/RedirectsToCurrentTeam.php) | `currentTeam ?? fallbackTeam()`                                                                                                                   | Rendah |
| [`app/Policies/TeamPolicy.php`](../../app/Policies/TeamPolicy.php)                                                       | Hapus kondisi `is_personal` pada `leave()` dan `delete()`; putuskan nasib `delete()` (PT-3)                                                       | Sedang |
| [`app/Rules/InviteeHasNoOtherTeam.php`](../../app/Rules/InviteeHasNoOtherTeam.php)                                       | Tidak berubah kode; pastikan semantik masih benar setelah penyederhanaan                                                                          | Rendah |

### Database

| Berkas                                                                                                                               | Perubahan                                                                                                                                    |
| ------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `database/migrations/2026_10_XX_000001_remove_personal_teams.php`                                                                    | **Baru** — `dropColumn('is_personal')` saja (tanpa logika data)                                                                              |
| [`database/factories/TeamFactory.php`](../../database/factories/TeamFactory.php)                                                     | Hapus `is_personal` default dan state `personal()`                                                                                           |
| [`database/factories/UserFactory.php`](../../database/factories/UserFactory.php)                                                     | Hapus `afterCreating` yang membuat tim personal                                                                                              |
| [`database/seeders/AlfatechDemoSeeder.php`](../../database/seeders/AlfatechDemoSeeder.php)                                           | Verifikasi saja (tidak ada `is_personal` eksplisit); pastikan anggota tetap masuk `alfatech`                                                 |
| [`database/migrations/2026_01_27_000001_create_teams_table.php`](../../database/migrations/2026_01_27_000001_create_teams_table.php) | **Jangan diubah** (historis). Catatan: `add_public_page_enabled...->after('is_personal')` tetap aman karena migrasi drop berjalan belakangan |

### Frontend

| Berkas                                                                                                 | Perubahan                                                                         |
| ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| [`resources/js/types/teams.ts`](../../resources/js/types/teams.ts)                                     | Hapus `isPersonal` dari tipe `Team`                                               |
| [`resources/js/pages/platform/tenants/Index.vue`](../../resources/js/pages/platform/tenants/Index.vue) | Hapus `isPersonal` dari `TenantRow`, badge "Personal", dan dua `v-if` pada tombol |
| `resources/js/pages/admin/team/Index.vue`                                                              | Tidak memakai `isPersonal`; cukup pastikan type-check tetap hijau                 |

### Dokumentasi (saat eksekusi, bukan sekarang)

| Dokumen                                                                        | Bagian                                                                     |
| ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| [`README.md`](../../README.md)                                                 | Bagian **Suspend tenant** (kalimat "Tenant personal tidak bisa disuspend") |
| [`docs/implementation/phase-02-tenant-context.md`](phase-02-tenant-context.md) | Post-Implementation Notes 2.3 + Decisions/Technical Debt                   |
| [`docs/IMPLEMENTATION_PLAN.md`](../IMPLEMENTATION_PLAN.md)                     | Kolom `teams` §3.1, Lampiran A, riwayat dokumen                            |

## 6. Daftar Test Terdampak

| Berkas                                                                                                     | Test / bagian                                                                         | Tindakan                                                                                                                        |
| ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [`tests/Feature/Teams/TeamTest.php`](../../tests/Feature/Teams/TeamTest.php)                               | `test_personal_team_returns_the_team_owned_by_the_user`                               | **Hapus** — menguji method yang hilang                                                                                          |
| [`tests/Feature/Teams/TeamMemberTest.php`](../../tests/Feature/Teams/TeamMemberTest.php)                   | `test_removed_member_current_team_is_set_to_personal_team`                            | Ubah menjadi "… diset ke tim lain bila ada, atau `null` bila tidak ada"                                                         |
| [`tests/Feature/Teams/TeamInvitationTest.php`](../../tests/Feature/Teams/TeamInvitationTest.php)           | `test_owning_a_personal_team_does_not_block_accepting_an_invitation`                  | Ubah menjadi "user tanpa tim bisa menerima undangan"; hapus `assertNotNull(personalTeam())`                                     |
| [`tests/Feature/Platform/PlatformTenantTest.php`](../../tests/Feature/Platform/PlatformTenantTest.php)     | `test_personal_teams_cannot_be_deleted_from_the_platform_panel`                       | **Hapus**; `test_deleting_a_tenant_clears_the_current_team_of_members_with_nowhere_to_go` disesuaikan (member dibuat tanpa tim) |
| [`tests/Feature/Platform/TenantSuspensionTest.php`](../../tests/Feature/Platform/TenantSuspensionTest.php) | `test_a_personal_team_cannot_be_suspended`                                            | **Hapus**                                                                                                                       |
| [`tests/Feature/Auth/RegistrationTest.php`](../../tests/Feature/Auth/RegistrationTest.php)                 | `assertNull($user->personalTeam())`, `assertDatabaseMissing(['is_personal' => true])` | Ganti menjadi "registrasi tidak membuat tim sama sekali" (`assertDatabaseCount('teams', 0)`)                                    |
| [`tests/Feature/Auth/EmailVerificationTest.php`](../../tests/Feature/Auth/EmailVerificationTest.php)       | 2 tempat memakai `$user->personalTeam()` untuk URL/route                              | Buat tim eksplisit lewat helper test                                                                                            |
| [`tests/Feature/Auth/AuthenticationTest.php`](../../tests/Feature/Auth/AuthenticationTest.php)             | `test_passkey_login_response_redirects_to_the_current_team_dashboard`                 | Buat tim eksplisit, jangan bergantung pada factory                                                                              |

**Audit menyeluruh wajib:** test lain yang mengandalkan efek samping `UserFactory` (user otomatis punya `current_team_id`) akan muncul sebagai kegagalan saat suite dijalankan — daftar final ditentukan dari hasil `php artisan test`, bukan dari tebakan. Kandidat yang perlu diperiksa: `DashboardTest`, `SettingsOverviewTest`, `Tenancy/TenantContextTest`, `Security/MassAssignmentTest`.

### Test baru yang disarankan

1. **Skema:** `Schema::hasColumn('teams', 'is_personal')` bernilai `false` (menjaga kolom tidak muncul lagi).
2. **Seed bersih:** setelah `migrate:fresh --seed`, hanya ada satu tenant demo (`alfatech`) dan tidak ada user yang memiliki lebih dari satu tim (PDR-10).
3. **Hapus tenant:** anggota yang kehilangan tenant dipindah ke membership lain bila ada, dan `null` bila tidak ada (menggantikan test personal).
4. **Panel platform:** payload daftar tenant tidak lagi memuat `isPersonal`, dan setiap tenant (tanpa kecuali) punya aksi suspend/hapus.
5. **Factory default:** `User::factory()->create()` tidak memiliki tim — mengunci keputusan PT-4.

## 7. Risiko & Mitigasi

| ID    | Risiko                                                                             | Dampak | Mitigasi                                                                                                                               |
| ----- | ---------------------------------------------------------------------------------- | ------ | -------------------------------------------------------------------------------------------------------------------------------------- |
| PT-R1 | Perubahan factory memecahkan banyak test sekaligus                                 | Sedang | Kerjakan dalam satu commit khusus; jangan mengubah ekspektasi test yang tidak terkait                                                  |
| PT-R2 | `migrate:fresh` menghapus seluruh data dev (termasuk data demo & audit uji manual) | Rendah | Memang disengaja untuk "bersih total"; jalankan saat data dev tidak dibutuhkan                                                         |
| PT-R3 | Baris personal di environment lain berubah jadi tenant biasa setelah kolom di-drop | Rendah | Cek `SELECT COUNT(*) FROM teams WHERE is_personal = 1` = `0` di setiap environment sebelum deploy                                      |
| PT-R4 | Ada deployment lain yang sudah punya personal team                                 | Rendah | Sebelum deploy: `SELECT COUNT(*) FROM teams WHERE is_personal = 1` harus `0`; bila tidak, bersihkan manual dulu (PT-1 jalur hati-hati) |
| PT-R5 | Menghapus kondisi `is_personal` di policy membuka jalur hapus yang tak diinginkan  | Sedang | `delete()` hanya dipakai `DeleteTeamRequest` yang tidak punya route; putuskan PT-3 sebelum merge                                       |
| PT-R6 | `dropColumn` di SQLite                                                             | Rendah | Didukung Laravel 13; dibuktikan oleh suite + CI MySQL/PostgreSQL                                                                       |

## 8. Catatan Terkait (di luar lingkup)

- `users.is_active` sudah ada dan bisa diubah Owner/Admin, tetapi **belum ditegakkan** di login/middleware. Kalau tujuannya memblokir individu, penegakannya di sana — bukan lewat suspend tenant.
- `DeleteTeamRequest` dan `TeamPolicy::delete()` saat ini tidak punya pemanggil (rute tim starter kit sudah dihapus, lihat `TeamTest::test_the_starter_kit_team_routes_are_gone`).

## 9. Rollback

- Kode: `git revert` commit pembersihan.
- Skema: `migrate:rollback` mengembalikan kolom `is_personal` (default `false`).
- Data: tidak ada migrasi data, jadi tidak ada data yang perlu dipulihkan. Satu-satunya kehilangan data adalah efek `migrate:fresh` di DB dev, yang memang disengaja.

## 10. Definition of Done

1. Tidak ada referensi `is_personal`/`personalTeam`/`isPersonal` di `app/`, `database/`, `resources/js/`, dan `tests/` (kecuali migrasi historis).
2. `composer test` hijau tanpa mengubah ekspektasi test yang tidak terkait.
3. `npm run types:check`, `npm run check`, `npm run build:ssr` hijau.
4. `php artisan migrate:fresh --seed` menghasilkan satu tenant demo (`alfatech`) tanpa badge Personal.
5. Panel Tenant: semua baris punya tombol suspend & hapus.
6. Living documents (§5) diperbarui dan dokumen ini ditandai selesai.

## 11. Estimasi & Saran Penjadwalan

- **Cakupan:** ±14 berkas produksi, 8 berkas test, 1 migrasi, 3 dokumen.
- **Estimasi:** sekitar satu hari kerja, dengan risiko terkonsentrasi pada fixture test.
- **Saran:** jadwalkan sebagai tugas tersendiri **setelah Gate Fase 2 (27 Okt)** atau di hari ringan sebelum cutover produksi. Jangan digabung dengan 26/27 Oktober yang sudah memuat settings, cache/log, dan gate.

## 12. Checklist Persetujuan

- [x] **PT-1** Setuju jalur ringkas: migrasi hanya `dropColumn`, DB dev dibersihkan dengan `migrate:fresh --seed` (tanpa migrasi data).
- [x] **PT-2** Setuju `fallbackTeam()` lalu `403` bila user benar-benar tanpa tim.
- [x] **PT-3** Setuju menghapus kondisi `is_personal` di policy. `DeleteTeamRequest` + ability `delete` **dibiarkan** (tidak punya route, tapi belum perlu dihapus).
- [x] **PT-4** Setuju factory tidak lagi membuat tim.
- [x] **PT-5** Setuju menghapus `isPersonal` dari controller/DTO/tipe frontend.
- [x] Dieksekusi sebagai tugas tersendiri, di luar slot 26/27 Oktober.

---

## 13. Hasil Eksekusi

**2026-10-23.** Semua langkah §4 dikerjakan dalam satu rangkaian perubahan.

| Item                   | Hasil                                                                                                                                                             |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Migrasi                | `2026_10_23_000001_remove_personal_teams.php` — `dropColumn` saja. `rollback` + `migrate` diuji bolak-balik, lalu DB dibangun ulang dengan `migrate:fresh --seed` |
| Skema akhir            | `teams` tanpa `is_personal`; hasil seed = **1 tenant** (`alfatech`), **6 user**, **0 user** dengan lebih dari satu tim                                            |
| Berkas produksi diubah | 9 berkas `app/`, 2 factory, 1 migrasi, 2 berkas frontend                                                                                                          |
| Test diubah            | 10 berkas; 2 test diganti maknanya, 3 test dihapus karena menguji konsep yang hilang                                                                              |
| Test baru              | `tests/Feature/Tenancy/NoPersonalTeamTest.php` (4 test)                                                                                                           |
| Suite                  | **315 test / 1250 assertion** hijau                                                                                                                               |
| Frontend               | `npm run types:check`, `npm run check`, `npm run build` + `build:ssr` hijau                                                                                       |

### Penyimpangan dari rencana

1. **`ownedTeams()` tidak dihapus.** Relation ini jadi tidak terpakai, tetapi masih benar secara semantik; dicatat sebagai TD2-6 di [phase-02](phase-02-tenant-context.md#technical-debt).
2. **`DeleteTeamRequest` + `TeamPolicy::delete()` dibiarkan.** Keduanya sudah tidak punya route sejak lama; menghapusnya memperluas lingkup tanpa manfaat. `delete()` kini setara dengan `TeamPermission::DeleteTeam`.
3. **`DashboardTest` diberi tenant eksplisit** lewat `createTeamFor()`, bukan dihapus — perilaku yang diuji (dashboard per tenant) tetap relevan.
4. **Test "removed member" dipecah dua**: fallback ke tenant lain, dan `null` bila tidak ada tenant lain. Rencana hanya menyebut satu test.

### Catatan untuk masa depan

- `UserFactory` **tidak** membuat tenant. Test yang butuh user ber-tenant wajib memanggil `$this->createTeamFor($user)`. Ini disengaja: fixture yang "diam-diam" punya tenant menyembunyikan asumsi PDR-10.
- Bila suatu saat ada environment lama yang masih punya baris personal, bersihkan **sebelum** migrasi ini berjalan (lihat komentar di migrasi).

---

## Riwayat Dokumen

| Versi | Tanggal    | Perubahan                                                                                                                             | Penulis            |
| ----- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| 0.1.0 | 2026-10-21 | Rencana awal, belum dieksekusi                                                                                                        | Software Architect |
| 0.2.0 | 2026-10-21 | Migrasi disederhanakan: `dropColumn` saja + `migrate:fresh --seed` untuk DB dev; jalur migrasi data dipindah menjadi opsi kondisional | Software Architect |
| 1.0.0 | 2026-10-23 | Dieksekusi penuh; hasil dan penyimpangan dicatat di §13                                                                               | Software Architect |
