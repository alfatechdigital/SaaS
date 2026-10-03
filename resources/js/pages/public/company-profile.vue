<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    Building2,
    Check,
    CheckCircle2,
    ChevronDown,
    Clock,
    Cloud,
    Code2,
    Cpu,
    ExternalLink,
    Layers,
    LogIn,
    Mail,
    MapPin,
    MessageSquare,
    Moon,
    Palette,
    Phone,
    Send,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Sun,
    Zap,
} from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { store as consultationStore } from '@/routes/public/consultation';
import type { CompanyProfile, PortfolioItem } from '@/types';

/**
 * Public marketing page for a team's company profile.
 *
 * Ported from `PublicCompanyProfilePage.tsx`. Differences from the template:
 * - Data comes from Inertia props (`profile`, `portfolio`) instead of Firestore.
 * - The consultation form posts to `PublicLeadController`, which files a Lead.
 * - When a team has no services / portfolio / FAQ yet, the section shows an
 *   empty state instead of the template's hardcoded Alfatech fallback content
 *   (this app is multi-tenant, so another team's copy would be wrong).
 * - Only `published` portfolio items are sent by the controller.
 */
const props = defineProps<{
    team: { name: string; slug: string };
    profile: CompanyProfile | null;
    portfolio: PortfolioItem[];
}>();

const { updateAppearance } = useAppearance();

const selectedCategory = ref('all');
const selectedPortfolioModal = ref<PortfolioItem | null>(null);
const openFaqIndex = ref<number | null>(0);
const isMobileNavOpen = ref(false);
const leadSubmittedSuccess = ref(false);

const form = useForm({
    contact_name: '',
    company_name: '',
    email: '',
    phone: '',
    service_type: 'Custom ERP & Web Platform',
    budget_estimate: 35_000_000,
    notes: '',
});

const companyName = computed(
    () => props.profile?.companyName || props.team.name,
);

const aboutText = computed(
    () =>
        props.profile?.about ??
        'Kami adalah mitra rekayasa perangkat lunak yang membantu perusahaan merancang, membangun, dan merawat sistem digital yang andal.',
);

const services = computed(() => props.profile?.services ?? []);
const faqList = computed(() => props.profile?.faq ?? []);

const categories = computed(() => [
    'all',
    ...Array.from(
        new Set(
            props.portfolio
                .map((item) => item.category)
                .filter((category): category is string => !!category),
        ),
    ),
]);

const filteredPortfolio = computed(() => {
    if (selectedCategory.value === 'all') {
        return props.portfolio;
    }

    const wanted = selectedCategory.value.toLowerCase();

    return props.portfolio.filter((item) =>
        (item.category ?? '').toLowerCase().includes(wanted),
    );
});

const whatsappLink = computed(() => {
    const digits = (props.profile?.phone ?? '').replace(/[^0-9]/g, '');

    return digits ? `https://wa.me/${digits}` : null;
});

const currentYear = new Date().getFullYear();

const navLinks = [
    { href: '#beranda', label: 'Beranda' },
    { href: '#layanan', label: 'Layanan' },
    { href: '#portofolio', label: 'Portofolio' },
    { href: '#keunggulan', label: 'Keunggulan' },
    { href: '#metodologi', label: 'Metodologi' },
    { href: '#faq', label: 'FAQ' },
    { href: '#konsultasi', label: 'Kontak' },
];

const advantages = [
    {
        icon: Clock,
        color: 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300',
        title: 'Agile Sprint & On-Time',
        desc: 'Pengembangan bertahap dengan demo berkala setiap pekan sehingga Anda selalu tahu progres nyata proyek.',
    },
    {
        icon: Code2,
        color: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300',
        title: 'Clean Code & Scalable',
        desc: 'Struktur kode bersih dengan standard linting ketat, dokumentasi API lengkap, dan kemudahan ekspansi fitur.',
    },
    {
        icon: ShieldCheck,
        color: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300',
        title: '100% Hak Milik Source Code',
        desc: 'Tidak ada biaya sewa lisensi tersembunyi. Source code diserahkan penuh kepada perusahaan Anda.',
    },
    {
        icon: Zap,
        color: 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300',
        title: 'Garansi & SLA Maintenance',
        desc: 'Jaminan perbaikan bug gratis 3-6 bulan setelah peluncuran untuk memastikan aplikasi berjalan tanpa kendala.',
    },
];

const methodology = [
    {
        step: '01',
        title: 'Discovery & Consultation',
        desc: 'Riset kebutuhan, analisis proses bisnis, dan pembuatan dokumen spesifikasi (SRS & Scope).',
    },
    {
        step: '02',
        title: 'UI/UX Prototyping',
        desc: 'Perancangan wireframe interaktif di Figma dan pengujian alur pengguna sebelum penulisan kode.',
    },
    {
        step: '03',
        title: 'Sprint Development',
        desc: 'Pengembangan backend & frontend secara berkala dengan code review dan automated testing.',
    },
    {
        step: '04',
        title: 'UAT & QA Security',
        desc: 'Pengujian performa, audit celah keamanan, dan verifikasi langsung bersama tim Anda.',
    },
    {
        step: '05',
        title: 'Deployment & SLA',
        desc: 'Rilis ke server produksi atau App Store / Play Store, handover dokumentasi, dan garansi pemeliharaan.',
    },
];

const serviceIcons: Record<string, Component> = {
    smartphone: Smartphone,
    cloud: Cloud,
    palette: Palette,
    cpu: Cpu,
    layers: Layers,
    database: Code2,
    code: Code2,
};

function serviceIcon(name: string): Component {
    return serviceIcons[name] ?? Code2;
}

function submitConsultation(): void {
    form.post(consultationStore.url({ team: props.team.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            leadSubmittedSuccess.value = true;
            form.reset();
        },
    });
}

function resetConsultationForm(): void {
    leadSubmittedSuccess.value = false;
    form.clearErrors();
}

function useServiceForConsultation(name: string): void {
    form.service_type = name;
    isMobileNavOpen.value = false;
}

function toggleTheme(): void {
    // Read the applied theme from the DOM so this stays SSR-safe (ADR-19).
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <Head :title="`${companyName} — Software House & IT Consulting`">
        <meta
            head-key="description"
            name="description"
            :content="`${companyName}: layanan pengembangan software kustom, aplikasi mobile, sistem ERP, dan arsitektur cloud untuk bisnis Anda.`"
        />
    </Head>

    <div
        class="flex min-h-screen flex-col bg-white font-sans text-slate-900 selection:bg-blue-600 selection:text-white dark:bg-slate-950 dark:text-slate-100"
    >
        <!-- ==================== 1. ANNOUNCEMENT + NAVBAR ==================== -->
        <div
            class="border-b border-slate-800 bg-slate-900 px-4 py-2 text-xs text-slate-300"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-400"
                    ></span>
                    <span class="font-medium text-slate-200">
                        Menerima Proyek Baru: Konsultasi Arsitektur &amp;
                        Penawaran Gratis
                    </span>
                </div>
                <div
                    class="hidden items-center gap-4 text-[11px] text-slate-400 sm:flex"
                >
                    <span>{{ profile?.address ?? 'Indonesia' }}</span>
                    <span v-if="profile?.email">&bull;</span>
                    <a
                        v-if="profile?.email"
                        :href="`mailto:${profile.email}`"
                        class="transition-colors hover:text-white"
                    >
                        {{ profile.email }}
                    </a>
                </div>
            </div>
        </div>

        <header
            class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 shadow-xs backdrop-blur-md transition-all dark:border-slate-800 dark:bg-slate-950/95"
        >
            <div
                class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-700 to-indigo-600 text-white shadow-md shadow-blue-700/20"
                    >
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div>
                        <div
                            class="flex items-center gap-1.5 text-base font-extrabold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            <span>{{ companyName }}</span>
                        </div>
                        <div
                            class="text-[10px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Software House &amp; IT Consulting
                        </div>
                    </div>
                </div>

                <nav
                    class="hidden items-center gap-7 text-xs font-semibold text-slate-600 md:flex dark:text-slate-300"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="transition-colors hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Theme toggle: both variants rendered, CSS picks one (ADR-19). -->
                    <button
                        type="button"
                        aria-label="Ganti tema tampilan"
                        class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2 text-xs font-semibold text-slate-700 transition-all hover:border-slate-300 hover:text-blue-600 sm:px-3 sm:py-2 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:text-blue-400"
                        @click="toggleTheme"
                    >
                        <span class="dark:hidden">
                            <Moon class="h-4 w-4 text-slate-600" />
                        </span>
                        <span class="hidden dark:inline">
                            <Sun class="h-4 w-4 text-amber-400" />
                        </span>
                        <span class="hidden sm:inline dark:hidden">Gelap</span>
                        <span class="hidden dark:sm:inline">Terang</span>
                    </button>

                    <a
                        href="#konsultasi"
                        class="hidden cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs transition-all hover:bg-blue-700 hover:shadow-md lg:inline-flex"
                    >
                        <span>Konsultasi Gratis</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </a>

                    <a
                        href="/login"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white shadow-xs transition-all hover:bg-slate-800 sm:px-4 sm:py-2.5 dark:bg-slate-800 dark:hover:bg-slate-700"
                    >
                        <LogIn class="h-4 w-4" />
                        <span class="hidden sm:inline">Login Admin</span>
                        <span class="sm:hidden">Login</span>
                    </a>

                    <button
                        type="button"
                        aria-label="Buka menu"
                        class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 md:hidden dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="isMobileNavOpen = !isMobileNavOpen"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <div
                v-if="isMobileNavOpen"
                class="animate-in slide-in-from-top space-y-3 border-b border-slate-200 bg-white px-4 py-4 duration-150 md:hidden dark:border-slate-800 dark:bg-slate-950"
            >
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="block py-1 text-sm font-semibold text-slate-700 dark:text-slate-300"
                    @click="isMobileNavOpen = false"
                >
                    {{ link.label }}
                </a>
            </div>
        </header>

        <!-- ==================== 2. HERO ==================== -->
        <section
            id="beranda"
            class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-b from-slate-50 via-white to-slate-50/50 py-16 sm:py-24 dark:border-slate-800 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950"
        >
            <div
                class="pointer-events-none absolute top-0 right-0 -mt-20 -mr-20 h-96 w-96 rounded-full bg-blue-100/60 blur-3xl dark:bg-blue-950/40"
            ></div>
            <div
                class="pointer-events-none absolute bottom-0 left-0 -mb-20 -ml-20 h-80 w-80 rounded-full bg-indigo-100/50 blur-3xl dark:bg-indigo-950/40"
            ></div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12"
                >
                    <div class="space-y-6 text-left lg:col-span-7">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-blue-200/60 bg-blue-50 px-3.5 py-1.5 text-xs font-bold text-blue-700 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300"
                        >
                            <Sparkles
                                class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400"
                            />
                            <span
                                >SOFTWARE HOUSE &amp; DIGITAL CONSULTANCY</span
                            >
                        </div>

                        <h1
                            class="text-3xl leading-[1.15] font-black tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100"
                        >
                            Partner Strategis Pembuatan
                            <span class="text-blue-600 dark:text-blue-400"
                                >Software Kustom</span
                            >, Aplikasi Mobile &amp; Sistem ERP di Indonesia
                        </h1>

                        <p
                            class="max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base dark:text-slate-300"
                        >
                            {{ aboutText }}
                        </p>

                        <div
                            class="grid grid-cols-1 gap-2.5 pt-2 sm:grid-cols-2"
                        >
                            <div
                                class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span>100% Hak Milik Source Code</span>
                            </div>
                            <div
                                class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span
                                    >Garansi Bug-Free &amp; SLA
                                    Maintenance</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span>Arsitektur Skalabilitas Tinggi</span>
                            </div>
                            <div
                                class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span
                                    >Sprint Mingguan dengan Progres
                                    Transparan</span
                                >
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-4">
                            <a
                                href="#konsultasi"
                                class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3.5 text-xs font-bold text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 hover:shadow-lg sm:text-sm"
                            >
                                <span>Mulai Konsultasi Gratis</span>
                                <ArrowRight class="h-4 w-4" />
                            </a>
                            <a
                                href="#portofolio"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3.5 text-xs font-bold text-slate-700 shadow-xs transition-all hover:bg-slate-50 sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <span>Lihat Portofolio Karya</span>
                            </a>
                        </div>

                        <div
                            class="grid grid-cols-3 gap-4 border-t border-slate-200/80 pt-6 text-left dark:border-slate-800"
                        >
                            <div>
                                <div
                                    class="text-xl font-black text-slate-900 sm:text-2xl dark:text-slate-100"
                                >
                                    120+
                                </div>
                                <div
                                    class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Proyek Terselesaikan
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xl font-black text-slate-900 sm:text-2xl dark:text-slate-100"
                                >
                                    98.9%
                                </div>
                                <div
                                    class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Kepuasan Klien
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xl font-black text-slate-900 sm:text-2xl dark:text-slate-100"
                                >
                                    3-6 Bln
                                </div>
                                <div
                                    class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Garansi Pasca Rilis
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative lg:col-span-5">
                        <div
                            class="relative space-y-5 rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-6 text-white shadow-2xl sm:p-7"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-800 pb-4"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-3 w-3 rounded-full bg-rose-500"
                                    ></span>
                                    <span
                                        class="inline-block h-3 w-3 rounded-full bg-amber-500"
                                    ></span>
                                    <span
                                        class="inline-block h-3 w-3 rounded-full bg-emerald-500"
                                    ></span>
                                </div>
                                <span
                                    class="font-mono text-[11px] text-slate-400"
                                    >production-stack</span
                                >
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="font-mono text-xs font-semibold text-blue-400"
                                >
                                    // Arsitektur Terpadu
                                </div>
                                <div class="text-lg font-bold text-slate-100">
                                    Sistem ERP &amp; Mobile Application Skala
                                    Korporat
                                </div>
                                <p
                                    class="text-xs leading-relaxed text-slate-400"
                                >
                                    Dirancang dengan framework modern dan
                                    database relasional serta caching
                                    terdistribusi untuk jutaan transaksi harian.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <div
                                    class="rounded-2xl border border-slate-700/60 bg-slate-800/80 p-3.5"
                                >
                                    <div class="text-[10px] text-slate-400">
                                        Response Latency
                                    </div>
                                    <div
                                        class="mt-0.5 text-sm font-bold text-emerald-400"
                                    >
                                        &lt; 45 ms
                                    </div>
                                    <div class="mt-1 text-[9px] text-slate-400">
                                        High-throughput APIs
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-slate-700/60 bg-slate-800/80 p-3.5"
                                >
                                    <div class="text-[10px] text-slate-400">
                                        Keamanan Data
                                    </div>
                                    <div
                                        class="mt-0.5 text-sm font-bold text-blue-400"
                                    >
                                        Terenkripsi
                                    </div>
                                    <div class="mt-1 text-[9px] text-slate-400">
                                        Audit &amp; backup berkala
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-3 rounded-2xl border border-blue-500/30 bg-blue-600/10 p-3.5"
                            >
                                <Award class="h-5 w-5 shrink-0 text-blue-400" />
                                <div class="text-xs text-slate-300">
                                    Didukung sertifikasi arsitektur cloud
                                    terstandarisasi industri.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 3. LAYANAN ==================== -->
        <section
            v-if="services.length > 0"
            id="layanan"
            class="border-b border-slate-100 bg-slate-50/60 py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-900/40"
        >
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl space-y-3 text-center">
                    <div
                        class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                    >
                        Layanan &amp; Solusi Digital
                    </div>
                    <h2
                        class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100"
                    >
                        Solusi Lengkap Pengembangan Software Skala Bisnis
                    </h2>
                    <p
                        class="text-xs leading-relaxed text-slate-500 sm:text-sm dark:text-slate-400"
                    >
                        Mulai dari konsep desain interaktif hingga implementasi
                        kode enterprise yang siap pakai.
                    </p>
                </div>

                <div
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="srv in services"
                        :key="srv.id"
                        class="group space-y-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs transition-all hover:border-blue-200 hover:shadow-md sm:p-7 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-800"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-950/60 dark:text-blue-300"
                        >
                            <component
                                :is="serviceIcon(srv.icon)"
                                class="h-6 w-6"
                            />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 transition-colors group-hover:text-blue-600 dark:text-slate-100 dark:group-hover:text-blue-400"
                        >
                            {{ srv.name }}
                        </h3>
                        <p
                            class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ srv.desc }}
                        </p>
                        <div class="pt-2">
                            <a
                                href="#konsultasi"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                @click="useServiceForConsultation(srv.name)"
                            >
                                <span>Konsultasikan Kebutuhan</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 4. PORTOFOLIO ==================== -->
        <section
            id="portofolio"
            class="border-b border-slate-100 bg-white py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col justify-between gap-6 md:flex-row md:items-end"
                >
                    <div class="space-y-2 text-left">
                        <div
                            class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                        >
                            Portofolio Pilihan
                        </div>
                        <h2
                            class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100"
                        >
                            Karya Nyata yang Telah Berdampak
                        </h2>
                        <p
                            class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                        >
                            Studi kasus proyek pengembangan software yang kami
                            bangun bersama para mitra.
                        </p>
                    </div>

                    <div
                        v-if="categories.length > 1"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            type="button"
                            :class="[
                                'rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all',
                                selectedCategory === cat
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                            ]"
                            @click="selectedCategory = cat"
                        >
                            {{ cat === 'all' ? 'Semua Kategori' : cat }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="filteredPortfolio.length === 0"
                    class="rounded-3xl border border-dashed border-slate-200 p-12 text-center dark:border-slate-700"
                >
                    <p
                        class="text-sm font-semibold text-slate-500 dark:text-slate-400"
                    >
                        Portofolio sedang diperbarui. Silakan hubungi kami untuk
                        studi kasus terkait.
                    </p>
                </div>

                <div v-else class="grid grid-cols-1 gap-8 md:grid-cols-2">
                    <div
                        v-for="item in filteredPortfolio"
                        :key="item.id"
                        class="group flex flex-col justify-between overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-xs transition-all hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="relative aspect-video overflow-hidden bg-slate-100 dark:bg-slate-800"
                        >
                            <img
                                v-if="item.imageUrl"
                                :src="item.imageUrl"
                                :alt="item.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div class="absolute top-4 left-4">
                                <span
                                    class="rounded-full bg-slate-900/80 px-3 py-1 text-[11px] font-bold text-white backdrop-blur-md"
                                >
                                    {{ item.category }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex flex-1 flex-col justify-between space-y-4 p-6 sm:p-7"
                        >
                            <div class="space-y-2">
                                <div
                                    class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                                >
                                    {{ item.client }}
                                </div>
                                <h3
                                    class="text-lg font-bold text-slate-900 transition-colors group-hover:text-blue-600 sm:text-xl dark:text-slate-100 dark:group-hover:text-blue-400"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="line-clamp-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.description }}
                                </p>
                            </div>

                            <div
                                class="space-y-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                            >
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tech in item.technologies"
                                        :key="tech"
                                        class="rounded-md bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ tech }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-slate-100 py-2.5 text-xs font-bold text-slate-700 transition-all hover:bg-blue-50 hover:text-blue-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-blue-300"
                                    @click="selectedPortfolioModal = item"
                                >
                                    <span>Lihat Detail Spesifikasi</span>
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Portfolio detail modal (inline, no Teleport → SSR-safe) -->
        <div
            v-if="selectedPortfolioModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[90vh] w-full max-w-2xl space-y-6 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl sm:p-8 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800 dark:bg-blue-950 dark:text-blue-300"
                    >
                        {{ selectedPortfolioModal.category }}
                    </span>
                    <button
                        type="button"
                        class="p-1 text-xl font-bold text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                        @click="selectedPortfolioModal = null"
                    >
                        &times;
                    </button>
                </div>

                <div
                    v-if="selectedPortfolioModal.imageUrl"
                    class="aspect-video overflow-hidden rounded-2xl bg-slate-100 dark:bg-slate-800"
                >
                    <img
                        :src="selectedPortfolioModal.imageUrl"
                        :alt="selectedPortfolioModal.title"
                        class="h-full w-full object-cover"
                    />
                </div>

                <div class="space-y-2">
                    <div
                        class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        Klien: {{ selectedPortfolioModal.client }}
                    </div>
                    <h3
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ selectedPortfolioModal.title }}
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-slate-600 sm:text-sm dark:text-slate-300"
                    >
                        {{ selectedPortfolioModal.description }}
                    </p>
                </div>

                <div class="space-y-2">
                    <div
                        class="text-xs font-bold text-slate-900 dark:text-slate-100"
                    >
                        Teknologi &amp; Tools:
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="tech in selectedPortfolioModal.technologies"
                            :key="tech"
                            class="rounded-lg bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                        >
                            {{ tech }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-4 border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Selesai pada:
                        {{ selectedPortfolioModal.completionDate }}
                    </span>
                    <a
                        v-if="selectedPortfolioModal.projectUrl"
                        :href="selectedPortfolioModal.projectUrl"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        Buka Proyek
                    </a>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white dark:bg-slate-700"
                        @click="selectedPortfolioModal = null"
                    >
                        Tutup Pratinjau
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== 5. KEUNGGULAN ==================== -->
        <section
            id="keunggulan"
            class="border-b border-slate-100 bg-slate-50/80 py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-900/40"
        >
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl space-y-3 text-center">
                    <div
                        class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                    >
                        Keunggulan Kami
                    </div>
                    <h2
                        class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100"
                    >
                        Mengapa Klien Memilih Kami?
                    </h2>
                    <p
                        class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                    >
                        Kualitas rekayasa software tingkat enterprise dengan
                        komunikasi proaktif dan transparansi penuh.
                    </p>
                </div>

                <div
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="item in advantages"
                        :key="item.title"
                        class="space-y-3 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-xl font-bold',
                                item.color,
                            ]"
                        >
                            <component :is="item.icon" class="h-5 w-5" />
                        </div>
                        <h3
                            class="text-sm font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ item.title }}
                        </h3>
                        <p
                            class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ item.desc }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 6. METODOLOGI ==================== -->
        <section
            id="metodologi"
            class="border-b border-slate-100 bg-white py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl space-y-3 text-center">
                    <div
                        class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                    >
                        Metodologi Kerja
                    </div>
                    <h2
                        class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100"
                    >
                        5 Tahap Terstruktur Menuju Keberhasilan Produk
                    </h2>
                    <p
                        class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                    >
                        Proses teruji untuk memastikan aplikasi selesai tepat
                        waktu, sesuai anggaran, dan memenuhi target bisnis Anda.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div
                        v-for="item in methodology"
                        :key="item.step"
                        class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-6 text-left dark:border-slate-800 dark:bg-slate-900/60"
                    >
                        <div
                            class="font-mono text-2xl font-black text-blue-600 dark:text-blue-400"
                        >
                            {{ item.step }}
                        </div>
                        <h3
                            class="text-sm font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ item.title }}
                        </h3>
                        <p
                            class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ item.desc }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 7. FAQ ==================== -->
        <section
            v-if="faqList.length > 0"
            id="faq"
            class="border-b border-slate-100 bg-slate-50/60 py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-900/40"
        >
            <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6">
                <div class="space-y-3 text-center">
                    <div
                        class="text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                    >
                        FAQ
                    </div>
                    <h2
                        class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100"
                    >
                        Pertanyaan yang Sering Diajukan
                    </h2>
                    <p
                        class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                    >
                        Jawaban cepat seputar pengerjaan proyek software, biaya,
                        dan kerja sama.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(faq, index) in faqList"
                        :key="index"
                        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-xs font-bold text-slate-900 transition-colors hover:text-blue-600 sm:text-sm dark:text-slate-100 dark:hover:text-blue-400"
                            @click="
                                openFaqIndex =
                                    openFaqIndex === index ? null : index
                            "
                        >
                            <span>{{ faq.question }}</span>
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200',
                                    openFaqIndex === index
                                        ? 'rotate-180 text-blue-600'
                                        : '',
                                ]"
                            />
                        </button>
                        <div
                            v-if="openFaqIndex === index"
                            class="border-t border-slate-100 bg-slate-50/50 px-5 pt-1 pb-5 text-xs leading-relaxed text-slate-600 sm:text-sm dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 8. FORMULIR KONSULTASI ==================== -->
        <section
            id="konsultasi"
            class="border-b border-slate-100 bg-white py-16 sm:py-24 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div
                    class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 p-8 text-white shadow-2xl sm:p-12"
                >
                    <div
                        class="pointer-events-none absolute top-0 right-0 h-80 w-80 rounded-full bg-white/10 blur-2xl"
                    ></div>

                    <div
                        class="relative z-10 grid grid-cols-1 gap-10 lg:grid-cols-12"
                    >
                        <div class="space-y-6 text-left lg:col-span-5">
                            <div
                                class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-200"
                            >
                                <MessageSquare class="h-3.5 w-3.5" />
                                <span>KONSULTASI TANPA BIAYA</span>
                            </div>

                            <h2
                                class="text-2xl leading-tight font-black text-white sm:text-4xl"
                            >
                                Punya Rencana Proyek Digital? Mari Diskusikan!
                            </h2>

                            <p
                                class="text-xs leading-relaxed text-blue-100/90 sm:text-sm"
                            >
                                Ceritakan ide atau tantangan operasional
                                perusahaan Anda. Tim kami siap memberikan
                                estimasi biaya, timeline, dan rekomendasi
                                teknologi terbaik.
                            </p>

                            <div class="space-y-3 pt-4 text-xs text-blue-100">
                                <div
                                    v-if="profile?.phone"
                                    class="flex items-center gap-3"
                                >
                                    <Phone
                                        class="h-4 w-4 shrink-0 text-blue-300"
                                    />
                                    <span
                                        >WhatsApp Bisnis:
                                        {{ profile.phone }}</span
                                    >
                                </div>
                                <div
                                    v-if="profile?.email"
                                    class="flex items-center gap-3"
                                >
                                    <Mail
                                        class="h-4 w-4 shrink-0 text-blue-300"
                                    />
                                    <span
                                        >Email Resmi: {{ profile.email }}</span
                                    >
                                </div>
                                <div
                                    v-if="profile?.address"
                                    class="flex items-center gap-3"
                                >
                                    <MapPin
                                        class="h-4 w-4 shrink-0 text-blue-300"
                                    />
                                    <span>{{ profile.address }}</span>
                                </div>
                                <a
                                    v-if="whatsappLink"
                                    :href="whatsappLink"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="mt-2 inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-xs font-bold text-white transition-colors hover:bg-white/25"
                                >
                                    <MessageSquare class="h-4 w-4" />
                                    <span>Chat via WhatsApp</span>
                                </a>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl bg-white p-6 text-slate-900 shadow-xl sm:p-7 lg:col-span-7 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <div
                                v-if="leadSubmittedSuccess"
                                class="animate-in fade-in space-y-4 py-8 text-center"
                            >
                                <div
                                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
                                >
                                    <Check class="h-8 w-8" />
                                </div>
                                <h3
                                    class="text-xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    Terima Kasih! Formulir Berhasil Dikirim
                                </h3>
                                <p
                                    class="mx-auto max-w-md text-xs text-slate-600 sm:text-sm dark:text-slate-300"
                                >
                                    Data Anda telah tersimpan di sistem CRM
                                    kami. Konsultan IT kami akan menghubungi
                                    Anda melalui WhatsApp / Email dalam 1x24 jam
                                    kerja.
                                </p>
                                <button
                                    type="button"
                                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white"
                                    @click="resetConsultationForm"
                                >
                                    Kirim Pesan Lainnya
                                </button>
                            </div>

                            <form
                                v-else
                                class="space-y-4 text-left"
                                @submit.prevent="submitConsultation"
                            >
                                <h3
                                    class="mb-2 text-base font-bold text-slate-900 dark:text-slate-100"
                                >
                                    Formulir Konsultasi Proyek
                                </h3>

                                <div
                                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Nama Lengkap *
                                        </label>
                                        <input
                                            v-model="form.contact_name"
                                            type="text"
                                            required
                                            placeholder="Misal: Hendra Setiawan"
                                            class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        />
                                        <p
                                            v-if="form.errors.contact_name"
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {{ form.errors.contact_name }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Nama Perusahaan / Bisnis
                                        </label>
                                        <input
                                            v-model="form.company_name"
                                            type="text"
                                            placeholder="Misal: PT Logistik Nusantara"
                                            class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Nomor WhatsApp / Telepon *
                                        </label>
                                        <input
                                            v-model="form.phone"
                                            type="tel"
                                            required
                                            placeholder="0812-xxxx-xxxx"
                                            class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        />
                                        <p
                                            v-if="form.errors.phone"
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {{ form.errors.phone }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Alamat Email
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            placeholder="hendra@perusahaan.co.id"
                                            class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        />
                                        <p
                                            v-if="form.errors.email"
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {{ form.errors.email }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Kebutuhan Solusi
                                        </label>
                                        <select
                                            v-model="form.service_type"
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        >
                                            <option
                                                v-for="srv in services"
                                                :key="srv.id"
                                                :value="srv.name"
                                            >
                                                {{ srv.name }}
                                            </option>
                                            <option
                                                v-if="services.length === 0"
                                                value="Konsultasi Umum"
                                            >
                                                Konsultasi Umum
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            Alokasi Estimasi Budget
                                        </label>
                                        <select
                                            v-model.number="
                                                form.budget_estimate
                                            "
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        >
                                            <option :value="15000000">
                                                &lt; Rp 25 Juta
                                            </option>
                                            <option :value="35000000">
                                                Rp 25 Juta - Rp 50 Juta
                                            </option>
                                            <option :value="75000000">
                                                Rp 50 Juta - Rp 100 Juta
                                            </option>
                                            <option :value="150000000">
                                                &gt; Rp 100 Juta (Enterprise)
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Catatan Singkat Kebutuhan Proyek
                                    </label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="3"
                                        placeholder="Jelaskan kebutuhan fitur utama atau kendala yang ingin diselesaikan..."
                                        class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-blue-600 text-xs font-bold text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <Send
                                        v-if="!form.processing"
                                        class="h-4 w-4"
                                    />
                                    <span>{{
                                        form.processing
                                            ? 'Mengirim...'
                                            : 'Kirim Permintaan Konsultasi'
                                    }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== 9. FOOTER ==================== -->
        <footer
            class="border-t border-slate-800 bg-slate-900 py-12 text-slate-400"
        >
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
                    <div class="space-y-3">
                        <div
                            class="flex items-center gap-2 text-base font-bold text-white"
                        >
                            <Building2 class="h-5 w-5 text-blue-500" />
                            <span>{{ companyName }}</span>
                        </div>
                        <p class="text-xs leading-relaxed text-slate-400">
                            {{
                                profile?.description ??
                                'Mitra rekayasa perangkat lunak dan transformasi digital.'
                            }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div
                            class="text-xs font-bold tracking-wider text-white uppercase"
                        >
                            Layanan Utama
                        </div>
                        <ul class="space-y-1.5 text-xs text-slate-400">
                            <li
                                v-for="srv in services.slice(0, 4)"
                                :key="srv.id"
                            >
                                {{ srv.name }}
                            </li>
                            <li v-if="services.length === 0">
                                Konsultasi Teknologi
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <div
                            class="text-xs font-bold tracking-wider text-white uppercase"
                        >
                            Kontak Kantor
                        </div>
                        <p
                            v-if="profile?.address"
                            class="text-xs leading-relaxed text-slate-400"
                        >
                            {{ profile.address }}
                        </p>
                        <p v-if="profile?.email" class="text-xs text-slate-400">
                            Email: {{ profile.email }}
                        </p>
                        <p v-if="profile?.phone" class="text-xs text-slate-400">
                            Telp/WA: {{ profile.phone }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="text-xs font-bold tracking-wider text-white uppercase"
                        >
                            Akses Internal
                        </div>
                        <p class="text-xs text-slate-400">
                            Portal manajemen operasional tim dan administrator.
                        </p>
                        <a
                            href="/login"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-800 py-2.5 text-xs font-bold text-slate-200 transition-all hover:bg-slate-700 hover:text-white"
                        >
                            <LogIn class="h-3.5 w-3.5" />
                            <span>Masuk Portal Admin</span>
                        </a>
                    </div>
                </div>

                <div
                    class="flex flex-col items-center justify-between gap-4 border-t border-slate-800 pt-8 text-center text-xs text-slate-500 sm:flex-row"
                >
                    <div>
                        &copy; {{ currentYear }} {{ companyName }}. Seluruh hak
                        cipta dilindungi.
                    </div>
                    <div class="flex items-center gap-4 text-slate-400">
                        <a href="#beranda" class="hover:underline">Beranda</a>
                        <a href="#layanan" class="hover:underline">Layanan</a>
                        <a href="#portofolio" class="hover:underline"
                            >Portofolio</a
                        >
                        <a href="#faq" class="hover:underline">FAQ</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
