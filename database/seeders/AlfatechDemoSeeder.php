<?php

namespace Database\Seeders;

use App\Enums\ActivityEntityType;
use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TransactionCategory;
use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the "PT Alfatech Digital Solutions" demo team.
 *
 * Data is ported from the React template's `src/services/seedData.ts`.
 * All records are scoped to a single team, per ADR-03.
 */
class AlfatechDemoSeeder extends Seeder
{
    private Team $team;

    /** @var array<string, User> */
    private array $users = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedUsersAndTeam();
        $this->seedCompanyProfile();
        $this->seedProjects();
        $this->seedTasks();
        $this->seedLeads();
        $this->seedContentItems();
        $this->seedTransactions();
        $this->seedPortfolioItems();
        $this->seedActivityLogs();
    }

    /**
     * Create the demo team and its members.
     */
    private function seedUsersAndTeam(): void
    {
        $this->team = Team::factory()->create([
            'name' => 'PT Alfatech Digital Solutions',
            'slug' => 'alfatech',
        ]);

        $members = [
            'rian' => [
                'name' => 'Rian Setiawan',
                'email' => 'digitalalfatech@gmail.com',
                'job_title' => 'CEO & Founder',
                'phone' => '+62 811-9988-1122',
                'skills' => ['Executive Leadership', 'Software Architecture', 'Client Relations'],
                'role' => TeamRole::Owner,
                // The installation operator. Platform rights are cross-tenant and
                // deliberately separate from TeamRole (PDR-03, PDR-05).
                'is_platform_admin' => true,
            ],
            'aditya' => [
                'name' => 'Aditya Pratama',
                'email' => 'aditya@alfatech.id',
                'job_title' => 'Technical Lead',
                'phone' => '+62 812-3344-5566',
                'skills' => ['Node.js', 'React', 'Cloud Architecture', 'PostgreSQL'],
                'role' => TeamRole::Member,
            ],
            'maya' => [
                'name' => 'Maya Lestari',
                'email' => 'maya@alfatech.id',
                'job_title' => 'UI/UX & FE Specialist',
                'phone' => '+62 813-7788-9900',
                'skills' => ['Figma', 'Tailwind CSS', 'Next.js', 'User Research'],
                'role' => TeamRole::Member,
            ],
            'budi' => [
                'name' => 'Budi Wicaksono',
                'email' => 'budi@alfatech.id',
                'job_title' => 'Senior Fullstack Dev',
                'phone' => '+62 815-6677-8899',
                'skills' => ['Go', 'TypeScript', 'Docker', 'Kubernetes'],
                'role' => TeamRole::Member,
            ],
            'sarah' => [
                'name' => 'Sarah Anggraeni',
                'email' => 'sarah@alfatech.id',
                'job_title' => 'Backend & Business Analyst',
                'phone' => '+62 817-2233-4455',
                'skills' => ['Python', 'FastAPI', 'System Analysis', 'MongoDB'],
                'role' => TeamRole::Member,
            ],
            'reza' => [
                'name' => 'Reza Kurniawan',
                'email' => 'reza@alfatech.id',
                'job_title' => 'Mobile Developer',
                'phone' => '+62 819-1122-3344',
                'skills' => ['Flutter', 'React Native', 'Swift', 'Kotlin'],
                'role' => TeamRole::Member,
            ],
        ];

        foreach ($members as $slug => $attributes) {
            $user = User::factory()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'job_title' => $attributes['job_title'],
                'phone' => $attributes['phone'],
                'skills' => $attributes['skills'],
                'is_platform_admin' => $attributes['is_platform_admin'] ?? false,
            ]);

            $this->team->members()->attach($user, ['role' => $attributes['role']->value]);
            $user->switchTeam($this->team);

            $this->users[$slug] = $user;
        }
    }

    /**
     * Seed the team's company profile.
     */
    private function seedCompanyProfile(): void
    {
        CompanyProfile::create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Alfatech Digital Solutions',
            'description' => 'Software house and tech consulting agency based in Jakarta, Indonesia.',
            'about' => 'PT Alfatech Digital Solutions adalah perusahaan rekayasa perangkat lunak (software house) terpercaya yang berbasis di Jakarta Selatan. Kami mengkhususkan diri dalam pengembangan sistem ERP kustom, platform web skala enterprise, aplikasi mobile iOS & Android, serta arsitektur cloud modern untuk mempercepat efisiensi bisnis klien di berbagai sektor industri.',
            'services' => [
                ['id' => 'srv-1', 'name' => 'Custom ERP & Web Platform', 'desc' => 'Pengembangan sistem informasi terintegrasi, pergudangan, keuangan, dan otomasi operasional bisnis.', 'icon' => 'database'],
                ['id' => 'srv-2', 'name' => 'Mobile App Development', 'desc' => 'Aplikasi mobile berperforma tinggi berbasis React Native dan Flutter untuk iOS dan Android.', 'icon' => 'smartphone'],
                ['id' => 'srv-3', 'name' => 'Cloud & DevOps Engineering', 'desc' => 'Perancangan arsitektur server, containerization Docker/Kubernetes, CI/CD pipeline, dan migrasi cloud AWS/GCP.', 'icon' => 'cloud'],
                ['id' => 'srv-4', 'name' => 'UI/UX Product Design', 'desc' => 'Riset pengguna, wireframing interaktif, dan visual design system modern yang fokus pada konversi bisnis.', 'icon' => 'palette'],
            ],
            'products' => [
                ['id' => 'prod-1', 'name' => 'Alfatech WMS Barcode', 'desc' => 'Modul plug-and-play pergudangan dengan integrasi scanner barcode nirkabel.'],
                ['id' => 'prod-2', 'name' => 'Alfatech Omnichannel POS', 'desc' => 'Sistem kasir multi-cabang terpusat untuk ritel modern dan rantai F&B.'],
            ],
            'contact' => 'Rian Setiawan',
            'email' => 'contact@alfatech.id',
            'phone' => '+62 812-8899-7700',
            'address' => 'Alfatech Tower Lt. 8, TB Simatupang No. 42, Cilandak, Jakarta Selatan 12430',
            'social_links' => [
                'website' => 'https://alfatech.id',
                'instagram' => 'https://instagram.com/alfatech.digital',
                'linkedin' => 'https://linkedin.com/company/alfatech-digital',
                'github' => 'https://github.com/alfatech-digital',
            ],
            'faq' => [
                [
                    'question' => 'Berapa rata-rata durasi pengerjaan proyek software di Alfatech?',
                    'answer' => 'Tergantung pada ruang lingkup proyek. Untuk aplikasi MVP atau web portal biasanya membutuhkan waktu 4-8 pekan, sedangkan sistem enterprise atau ERP skala penuh berkisar antara 3-6 bulan dengan pendekatan sprint mingguan.',
                ],
                [
                    'question' => 'Bagaimana skema pembayaran proyek di Alfatech?',
                    'answer' => 'Kami menerapkan sistem pembayaran berbasis milestone rilis, umumnya diawali Down Payment (DP) 30%-50%, termin pengembangan berkala, dan pelunasan setelah User Acceptance Testing (UAT) serta handover source code.',
                ],
                [
                    'question' => 'Apakah Alfatech menyediakan garansi dan pemeliharaan (maintenance)?',
                    'answer' => 'Ya, seluruh proyek yang kami serah-terimakan mendapatkan garansi bug-free gratis selama 3-6 bulan serta opsi perpanjangan Service Level Agreement (SLA) untuk pemeliharaan berkala.',
                ],
            ],
        ]);
    }

    /**
     * Seed the demo projects.
     */
    private function seedProjects(): void
    {
        $projects = [
            [
                'key' => 'proj-1',
                'name' => 'Sistem ERP Pergudangan Terpadu',
                'client_name' => 'PT Logistik Nusantara Mandiri',
                'description' => 'Aplikasi manajemen stok pergudangan multi-gudang, integrasi barcode scanner nirkabel, dan otomasi audit inventaris.',
                'status' => ProjectStatus::Development,
                'progress' => 70,
                'start_date' => '2024-08-01',
                'deadline' => '2024-11-15',
                'project_value' => 75_000_000,
                'pic' => 'aditya',
                'technologies' => ['React', 'Node.js', 'PostgreSQL', 'Docker', 'Redis'],
                'notes' => 'Klien meminta penambahan modul reporting per batch tanggal.',
            ],
            [
                'key' => 'proj-2',
                'name' => 'Revamp Web E-Commerce & Payment Gateway',
                'client_name' => 'CV Maju Makmur',
                'description' => 'Redesain storefront toko daring dengan optimasi performa checkout dan integrasi gateway QRIS, VA Bank BCA/Mandiri.',
                'status' => ProjectStatus::Review,
                'progress' => 90,
                'start_date' => '2024-09-01',
                'deadline' => '2024-10-30',
                'project_value' => 45_000_000,
                'pic' => 'maya',
                'technologies' => ['Next.js', 'Tailwind CSS', 'Midtrans API', 'Supabase'],
                'notes' => 'Tahap UAT akhir bersama tim marketing klien.',
            ],
            [
                'key' => 'proj-3',
                'name' => 'Aplikasi Telemedicine Pasien & Dokter',
                'client_name' => 'RS Medika Utama',
                'description' => 'Aplikasi konsultasi kesehatan daring dengan video call real-time WebRTC, resep digital, dan sinkronisasi rekam medis.',
                'status' => ProjectStatus::Development,
                'progress' => 45,
                'start_date' => '2024-09-15',
                'deadline' => '2024-12-10',
                'project_value' => 85_000_000,
                'pic' => 'budi',
                'technologies' => ['React Native', 'WebRTC', 'Go', 'PostgreSQL'],
                'notes' => 'Integrasi video call telah lolos load test 500 concurrent sessions.',
            ],
            [
                'key' => 'proj-4',
                'name' => 'Portal Pendaftaran Siswa & LMS Online',
                'client_name' => 'Yayasan Pendidikan Cendekia',
                'description' => 'Sistem PPDB online dengan verifikasi berkas otomatis dan modul LMS ujian daring anti-cheat untuk 3 sekolah yayasan.',
                'status' => ProjectStatus::Deal,
                'progress' => 10,
                'start_date' => '2024-10-15',
                'deadline' => '2024-12-20',
                'project_value' => 28_000_000,
                'pic' => 'sarah',
                'technologies' => ['Laravel', 'Vue.js', 'MySQL', 'AWS S3'],
                'notes' => 'Kickoff meeting dijadwalkan pekan depan untuk finalisasi SOW.',
            ],
            [
                'key' => 'proj-5',
                'name' => 'Mobile Attendance & Geofencing GPS',
                'client_name' => 'PT Agro Makmur Raya',
                'description' => 'Presensi karyawan lapangan berbasis titik koordinat GPS dan validasi biometrik wajah anti fake-GPS untuk 1.200 pekerja.',
                'status' => ProjectStatus::Development,
                'progress' => 55,
                'start_date' => '2024-09-20',
                'deadline' => '2024-11-28',
                'project_value' => 35_000_000,
                'pic' => 'reza',
                'technologies' => ['Flutter', 'Node.js', 'Google Maps API', 'Firebase'],
                'notes' => 'Modul offline-first caching sedang dalam tahap pengujian.',
            ],
            [
                'key' => 'proj-6',
                'name' => 'Website Korporat & SEO Engine',
                'client_name' => 'PT Buana Konstruksi',
                'description' => 'Website profil perusahaan konstruksi modern dengan showcase portofolio 3D, formulir tender, dan struktur SEO page-1.',
                'status' => ProjectStatus::Review,
                'progress' => 95,
                'start_date' => '2024-09-05',
                'deadline' => '2024-10-25',
                'project_value' => 18_000_000,
                'pic' => 'maya',
                'technologies' => ['Astro', 'Tailwind CSS', 'Cloudflare Pages'],
                'notes' => 'Menunggu approval domain DNS dari pihak klien.',
            ],
        ];

        foreach ($projects as $project) {
            Project::create([
                'team_id' => $this->team->id,
                'name' => $project['name'],
                'client_name' => $project['client_name'],
                'description' => $project['description'],
                'status' => $project['status'],
                'progress' => $project['progress'],
                'start_date' => Carbon::parse($project['start_date']),
                'deadline' => Carbon::parse($project['deadline']),
                'project_value' => $project['project_value'],
                'pic_id' => $this->users[$project['pic']]->id,
                'technologies' => $project['technologies'],
                'notes' => $project['notes'],
            ]);
        }
    }

    /**
     * Seed the demo tasks.
     */
    private function seedTasks(): void
    {
        $tasks = [
            [
                'project' => 'Sistem ERP Pergudangan Terpadu',
                'title' => 'Implementasi Modul Barcode Scanner Zebra',
                'description' => 'Koneksikan scanner via web serial API dan parsing kode format Code128.',
                'assignee' => 'aditya',
                'priority' => TaskPriority::High,
                'status' => TaskStatus::InProgress,
                'due_date' => '2024-10-28',
            ],
            [
                'project' => 'Sistem ERP Pergudangan Terpadu',
                'title' => 'Migrasi Schema Database Stok Multi-Gudang',
                'description' => 'Buat migration script dan seed data untuk 4 lokasi gudang.',
                'assignee' => 'aditya',
                'priority' => TaskPriority::High,
                'status' => TaskStatus::Done,
                'due_date' => '2024-10-15',
            ],
            [
                'project' => 'Revamp Web E-Commerce & Payment Gateway',
                'title' => 'Pengujian Callback Webhook Midtrans',
                'description' => 'Simulasi notifikasi pembayaran sukses, pending, dan expired.',
                'assignee' => 'maya',
                'priority' => TaskPriority::Medium,
                'status' => TaskStatus::Review,
                'due_date' => '2024-10-26',
            ],
            [
                'project' => 'Aplikasi Telemedicine Pasien & Dokter',
                'title' => 'Setup Signaling Server WebRTC & Coturn',
                'description' => 'Konfigurasi STUN/TURN server di VPS DigitalOcean.',
                'assignee' => 'budi',
                'priority' => TaskPriority::High,
                'status' => TaskStatus::InProgress,
                'due_date' => '2024-11-05',
            ],
            [
                'project' => 'Mobile Attendance & Geofencing GPS',
                'title' => 'Geofencing Polygon Validator',
                'description' => 'Algoritma Ray-casting untuk mengecek apakah titik GPS berada di dalam area perkebunan.',
                'assignee' => 'reza',
                'priority' => TaskPriority::Medium,
                'status' => TaskStatus::Todo,
                'due_date' => '2024-11-10',
            ],
        ];

        $projectIds = Project::query()->forTeam($this->team)->pluck('id', 'name');

        foreach ($tasks as $task) {
            Task::create([
                'team_id' => $this->team->id,
                'project_id' => $projectIds[$task['project']],
                'title' => $task['title'],
                'description' => $task['description'],
                'assignee_id' => $this->users[$task['assignee']]->id,
                'priority' => $task['priority'],
                'status' => $task['status'],
                'due_date' => Carbon::parse($task['due_date']),
            ]);
        }
    }

    /**
     * Seed the demo leads.
     */
    private function seedLeads(): void
    {
        $leads = [
            ['PT Graha Finansial', 'Pak Hendra', '0812-8899-1029', 'hendra@grahafinansial.co.id', 'Website Alfatech', 'Web Portal Investasi & Portofolio', 30_000_000, LeadStatus::New, 'Hari ini', 'Masuk melalui form kontak website paket korporat.'],
            ['Koperasi Karya Bersama', 'Ibu Siti', '0813-9922-3841', 'koperasikarya@gmail.com', 'WhatsApp Inbound', 'Sistem Simpan Pinjam Digital', 25_000_000, LeadStatus::New, 'Besok pagi', 'Membutuhkan cetak struk tabungan dan simulasi bunga flat.'],
            ['PT Fast Logistic Cargo', 'Bpk. Rudi Hartono', '0811-2244-6688', 'rudi.h@fastlogistic.id', 'Rekomendasi / Network', 'Dashboard Tracking Resi & Driver App', 45_000_000, LeadStatus::Contacted, 'Besok, 10:00 WIB', 'Sudah dihubungi via telepon, tertarik dengan modul GPS fleet Alfatech.'],
            ['Klinik Estetika Glow', 'Dr. Amanda', '0812-4455-7799', 'amanda@glowclinic.id', 'Instagram Ads', 'Aplikasi Booking & CRM Pasien', 45_000_000, LeadStatus::Contacted, 'WhatsApp Call Lanjutan', 'Kebutuhan integrasi rekam medis kecantikan dan katalog treatment.'],
            ['CV Mitra Sukses', 'Pak Danu', '0815-7788-9911', 'danu@mitrasukses.co.id', 'Website Alfatech', 'Demo Aplikasi POS Kasir Multi-Cabang', 35_000_000, LeadStatus::Meeting, 'Kamis, 24 Okt (Zoom)', 'Jadwal presentasi online demo produk POS Alfatech.'],
            ['Sekolah Alam Ceria', 'Ibu Ratna', '0817-6655-4433', 'ratna@alamceria.sch.id', 'Rekomendasi / Network', 'Custom Sistem Informasi Akademik', 40_000_000, LeadStatus::Meeting, 'Kantor Alfatech (Offline)', 'Pertemuan tatap muka membahas kurikulum & rapor digital.'],
            ['PT Borneo Mining Solusi', 'Bpk. Bambang', '0818-3322-1100', 'bambang@borneomining.com', 'Rekomendasi / Network', 'Sistem IoT Monitoring Armada & BBM', 85_000_000, LeadStatus::Proposal, 'Review Direksi Pekan Ini', 'Proposal teknis versi 2 dan penawaran komersial sudah terkirim.'],
            ['Yayasan Dharma Bangsa', 'Pak Aris', '0812-9900-1122', 'aris@dharmabangsa.ac.id', 'Rekomendasi / Network', 'LMS & Ujian Online CBT Kampus', 60_000_000, LeadStatus::Negotiation, 'Drafting SPK DP 40%', 'Negosiasi klausul garansi maintenance 6 bulan disetujui.'],
            ['PT Sinar Abadi Farm', 'Bpk. Iwan Suhendra', '0813-1122-3344', 'iwan@sinarabadi.farm', 'Website Alfatech', 'IoT Smart Green House & Dashboard Pertanian', 40_000_000, LeadStatus::Won, 'Kickoff Proyek', 'SPK telah ditandatangani dan DP termin 1 sudah masuk.'],
        ];

        foreach ($leads as $lead) {
            Lead::create([
                'team_id' => $this->team->id,
                'company_name' => $lead[0],
                'contact_name' => $lead[1],
                'phone' => $lead[2],
                'email' => $lead[3],
                'source' => $lead[4],
                'potential_project' => $lead[5],
                'estimated_value' => $lead[6],
                'status' => $lead[7],
                'next_follow_up' => $lead[8],
                'notes' => $lead[9],
            ]);
        }
    }

    /**
     * Seed the demo social media content items.
     */
    private function seedContentItems(): void
    {
        $contents = [
            [
                'title' => 'Tips Migrasi Cloud untuk UKM Indonesia',
                'caption' => 'Apakah server on-premise perusahaan Anda sering down saat peak traffic? Ini 4 strategi migrasi arsitektur cloud hemat biaya...',
                'platform' => ContentPlatform::Instagram,
                'content_type' => 'Reels / Carousel',
                'status' => ContentStatus::Scheduled,
                'scheduled_at' => 'Besok, 10:00 WIB',
                'assignee' => 'maya',
                'notes' => 'Asset visual telah disetujui, siap tayang di feed @alfatech.digital.',
            ],
            [
                'title' => 'Studi Kasus Efisiensi ERP Manufaktur',
                'caption' => 'Bagaimana Alfatech membantu PT Sinar Logistik memangkas waktu rekonsiliasi stok dari 3 hari menjadi 15 menit melalui implementasi barcode terintegrasi...',
                'platform' => ContentPlatform::Linkedin,
                'content_type' => 'Long-form Post + PDF Slide',
                'status' => ContentStatus::Review,
                'scheduled_at' => 'Kamis, 14:00 WIB',
                'assignee' => 'rian',
                'notes' => 'Draft review oleh CEO sebelum publikasi B2B di LinkedIn.',
            ],
            [
                'title' => 'Behind the Code: Debugging Sprint di Alfatech',
                'caption' => 'Intip suasana seru tim engineer Alfatech saat solving race-condition bug menjelang rilis staging! #LifeAtAlfatech #TechIndo',
                'platform' => ContentPlatform::Tiktok,
                'content_type' => 'Short Video Vlog',
                'status' => ContentStatus::Draft,
                'scheduled_at' => 'Jumat, 16:00 WIB',
                'assignee' => 'aditya',
                'notes' => 'Video klip 45 detik sedang diedit di CapCut.',
            ],
            [
                'title' => 'Mengenal Microservices vs Monolith untuk Startup',
                'caption' => 'Kapan saat yang tepat bagi produk Anda beralih ke arsitektur microservices? Simak checklist panduan arsitektur software dari Alfatech.',
                'platform' => ContentPlatform::Linkedin,
                'content_type' => 'Infografis Carousel',
                'status' => ContentStatus::Approved,
                'scheduled_at' => 'Senin, 09:00 WIB',
                'assignee' => 'budi',
                'notes' => 'Copywriting dan diagram arsitektur final.',
            ],
        ];

        foreach ($contents as $content) {
            ContentItem::create([
                'team_id' => $this->team->id,
                'title' => $content['title'],
                'caption' => $content['caption'],
                'platform' => $content['platform'],
                'content_type' => $content['content_type'],
                'status' => $content['status'],
                'scheduled_at' => $content['scheduled_at'],
                'assignee_id' => $this->users[$content['assignee']]->id,
                'notes' => $content['notes'],
            ]);
        }
    }

    /**
     * Seed the demo financial transactions.
     */
    private function seedTransactions(): void
    {
        $projectIds = Project::query()->forTeam($this->team)->pluck('id', 'name');

        $transactions = [
            ['project_income', 'Sistem ERP Pergudangan Terpadu', 'Pembayaran Termin 2 - Pengembangan Core Modul', 37_500_000, '2024-10-15', 'rian'],
            ['project_income', 'Revamp Web E-Commerce & Payment Gateway', 'Pelunasan 100% Final Delivery & Handover', 45_000_000, '2024-10-18', 'rian'],
            ['project_income', 'Aplikasi Telemedicine Pasien & Dokter', 'Uang Muka (DP 40%) Kontrak Pengembangan', 33_500_000, '2024-10-20', 'rian'],
            // No project yet: the underlying lead was just won.
            ['project_income', null, 'Pembayaran DP 50% IoT Smart Green House', 32_500_000, '2024-10-24', 'rian'],
            ['company_expense', null, 'Gaji Pokok & Tunjangan Tim Engineering (Oktober 2024)', 52_000_000, '2024-10-01', 'rian'],
            ['company_expense', null, 'Biaya Server AWS, GCP Cloud & Staging Clusters', 14_500_000, '2024-10-05', 'rian'],
            ['company_expense', null, 'Sewa Coworking Office Suite & Utilitas Internet', 12_000_000, '2024-10-02', 'rian'],
            ['project_expense', 'Sistem ERP Pergudangan Terpadu', 'Pembelian 2 Unit Hardware Scanner Barcode Testing', 4_600_000, '2024-10-12', 'aditya'],
            ['company_expense', null, 'Lisensi SaaS (GitHub Enterprise, Figma Org, Slack Pro)', 3_000_000, '2024-10-08', 'rian'],
        ];

        foreach ($transactions as $transaction) {
            $category = TransactionCategory::from($transaction[0]);

            Transaction::create([
                'team_id' => $this->team->id,
                'type' => $category->type(),
                'category' => $category,
                'project_id' => $transaction[1] === null ? null : $projectIds[$transaction[1]],
                'description' => $transaction[2],
                'amount' => $transaction[3],
                'date' => Carbon::parse($transaction[4]),
                'created_by_id' => $this->users[$transaction[5]]->id,
            ]);
        }
    }

    /**
     * Seed the demo portfolio items.
     */
    private function seedPortfolioItems(): void
    {
        $items = [
            [
                'title' => 'Sistem Logistik & Pergudangan Terpadu',
                'client' => 'PT Logistik Nusantara Mandiri',
                'category' => 'Enterprise ERP',
                'description' => 'Platform manajemen 4 pergudangan distribusi regional dengan pemindaian barcode nirkabel, pelacakan armada real-time, dan otomasi laporan stok harian.',
                'technologies' => ['React', 'Node.js', 'PostgreSQL', 'Docker', 'Tailwind CSS'],
                'image_url' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=800&auto=format&fit=crop&q=80',
                'project_url' => 'https://alfatech.id/case-study/logistik-nusantara',
                'completion_date' => '2024-09-15',
                'featured' => true,
            ],
            [
                'title' => 'Platform Telemedicine SehatPlus Medika',
                'client' => 'RS Medika Utama',
                'category' => 'Healthcare App',
                'description' => 'Aplikasi konsultasi kesehatan daring pasien & dokter dengan integrasi video call WebRTC terenkripsi, resep digital, dan sinkronisasi rekam medis BPJS.',
                'technologies' => ['React Native', 'WebRTC', 'Go', 'PostgreSQL'],
                'image_url' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&auto=format&fit=crop&q=80',
                'project_url' => 'https://alfatech.id/case-study/sehatplus-medika',
                'completion_date' => '2024-08-30',
                'featured' => true,
            ],
            [
                'title' => 'E-Commerce Marketplace TokoBahan.id',
                'client' => 'CV Maju Makmur',
                'category' => 'Web & E-Commerce',
                'description' => 'Toko daring B2B bahan baku manufaktur dengan fitur multi-gudang, perhitungan ongkos kirim kargo otomatis, dan multi-payment QRIS/Virtual Account.',
                'technologies' => ['Next.js', 'Tailwind CSS', 'Midtrans API', 'Supabase'],
                'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=80',
                'project_url' => 'https://alfatech.id/case-study/tokobahan-id',
                'completion_date' => '2024-10-01',
                'featured' => false,
            ],
            [
                'title' => 'IoT Smart Green House & Dashboard Tanaman',
                'client' => 'PT Sinar Abadi Farm',
                'category' => 'IoT & Dashboard',
                'description' => 'Dashboard telemetri sensor kelembaban tanah, suhu, dan otomasi penyiraman nutrisi hidroponik berbasis mikrokontroler ESP32.',
                'technologies' => ['Vue.js', 'MQTT', 'Node.js', 'InfluxDB'],
                'image_url' => 'https://images.unsplash.com/photo-1530836369250-ef72a3f5cda8?w=800&auto=format&fit=crop&q=80',
                'project_url' => 'https://alfatech.id/case-study/sinar-abadi-iot',
                'completion_date' => '2024-07-20',
                'featured' => true,
            ],
        ];

        foreach ($items as $item) {
            PortfolioItem::create([
                'team_id' => $this->team->id,
                'title' => $item['title'],
                'client' => $item['client'],
                'category' => $item['category'],
                'description' => $item['description'],
                'technologies' => $item['technologies'],
                'image_url' => $item['image_url'],
                'project_url' => $item['project_url'],
                'completion_date' => Carbon::parse($item['completion_date']),
                'featured' => $item['featured'],
                'published' => true,
            ]);
        }
    }

    /**
     * Seed the demo activity logs.
     */
    private function seedActivityLogs(): void
    {
        $logs = [
            ['Pembayaran DP 50% Diterima', ActivityEntityType::Transaction, 'PT Sinar Logistik telah menyelesaikan transfer termin 1 senilai Rp 32.500.000 ke rekening operasional BCA.', 'rian', '2024-10-24T08:20:00Z'],
            ['Proposal Terkirim ke Klien', ActivityEntityType::Lead, 'Dokumen penawaran teknis & SOW dikirimkan kepada calon mitra PT Borneo Mining Solusi (Rp 85 Jt).', 'rian', '2024-10-24T07:30:00Z'],
            ['Deploy Staging Berhasil', ActivityEntityType::Project, 'Aditya Pratama melakukan push commit v1.2.4-rc untuk modul inventaris TokoBahan.id ke server testing AWS.', 'aditya', '2024-10-24T05:30:00Z'],
            ['Prospek Baru Masuk', ActivityEntityType::Lead, 'Leads baru PT Graha Finansial masuk melalui formulir website Alfatech (estimasi Rp 30.000.000).', null, '2024-10-24T06:00:00Z'],
        ];

        foreach ($logs as $log) {
            ActivityLog::create([
                'team_id' => $this->team->id,
                'action' => $log[0],
                'entity_type' => $log[1],
                'entity_id' => null,
                'details' => $log[2],
                'performed_by_id' => $log[3] === null ? null : $this->users[$log[3]]->id,
                'created_at' => Carbon::parse($log[4]),
                'updated_at' => Carbon::parse($log[4]),
            ]);
        }
    }
}
