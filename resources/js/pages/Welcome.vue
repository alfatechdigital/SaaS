<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  Building2,
  Smartphone,
  Cloud,
  Palette,
  Cpu,
  Layers,
  CheckCircle2,
  ArrowRight,
  ChevronDown,
  Sparkles,
  Sun,
  Moon,
  Send
} from 'lucide-vue-next';

interface PortfolioItem {
  id: string;
  title: string;
  client: string;
  category: string;
  description: string;
  technologies: string[];
  imageUrl: string;
  projectUrl: string;
  completionDate: string;
  featured: boolean;
}

const selectedCategory = ref('all');
const isMobileNavOpen = ref(false);
const isDarkMode = ref(false);

const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
};

const formState = ref({
  contactName: '',
  companyName: '',
  email: '',
  phone: '',
  serviceType: 'Custom ERP & Web Platform',
  budgetEstimate: 35000000,
  notes: ''
});

const isSubmittingLead = ref(false);
const leadSubmittedSuccess = ref(false);

const servicesList = [
  { id: 'srv-1', name: 'Custom ERP & Web Platform', desc: 'Pengembangan sistem informasi terintegrasi, pergudangan, keuangan, dan otomasi operasional bisnis berskala besar.', icon: Building2 },
  { id: 'srv-2', name: 'Mobile App Development', desc: 'Aplikasi mobile berperforma tinggi berbasis React Native dan Flutter untuk platform iOS dan Android.', icon: Smartphone },
  { id: 'srv-3', name: 'Cloud & DevOps Engineering', desc: 'Perancangan arsitektur server, containerization Docker/Kubernetes, CI/CD pipeline, dan migrasi cloud AWS/GCP.', icon: Cloud },
  { id: 'srv-4', name: 'UI/UX Product Design', desc: 'Riset pengguna, wireframing interaktif, dan visual design system modern yang fokus pada konversi bisnis.', icon: Palette },
  { id: 'srv-5', name: 'IoT & Hardware Integration', desc: 'Integrasi mikrokontroler, telemetri sensor suhu/kelembaban, barcode scanner, dan dashboard data real-time.', icon: Cpu },
  { id: 'srv-6', name: 'API & Payment Gateway', desc: 'Integrasi gerbang pembayaran QRIS, Virtual Account bank nasional, dan konektivitas API pihak ketiga.', icon: Layers }
];

const portfolioItems: PortfolioItem[] = [
  {
    id: 'port-1',
    title: 'Sistem Logistik & Pergudangan Terpadu',
    client: 'PT Logistik Nusantara Mandiri',
    category: 'Enterprise ERP',
    description: 'Platform manajemen 4 pergudangan distribusi regional dengan pemindaian barcode nirkabel, pelacakan armada real-time, dan otomasi laporan stok harian.',
    technologies: ['React', 'Node.js', 'PostgreSQL', 'Docker'],
    imageUrl: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=800&auto=format&fit=crop&q=80',
    projectUrl: 'https://alfatech.id/case-study/logistik-nusantara',
    completionDate: '2024-09-15',
    featured: true
  },
  {
    id: 'port-2',
    title: 'Platform Telemedicine SehatPlus Medika',
    client: 'RS Medika Utama',
    category: 'Mobile App',
    description: 'Aplikasi konsultasi kesehatan daring pasien & dokter dengan integrasi video call WebRTC terenkripsi, resep digital, dan sinkronisasi rekam medis BPJS.',
    technologies: ['React Native', 'WebRTC', 'Go', 'PostgreSQL'],
    imageUrl: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&auto=format&fit=crop&q=80',
    projectUrl: 'https://alfatech.id/case-study/sehatplus-medika',
    completionDate: '2024-08-30',
    featured: true
  },
  {
    id: 'port-3',
    title: 'E-Commerce Marketplace TokoBahan.id',
    client: 'CV Maju Makmur',
    category: 'Web & E-Commerce',
    description: 'Toko daring B2B bahan baku manufaktur dengan fitur multi-gudang, perhitungan ongkos kirim kargo otomatis, dan multi-payment QRIS/Virtual Account.',
    technologies: ['Next.js', 'Tailwind CSS', 'Midtrans API', 'Supabase'],
    imageUrl: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=80',
    projectUrl: 'https://alfatech.id/case-study/tokobahan-id',
    completionDate: '2024-10-01',
    featured: false
  },
  {
    id: 'port-4',
    title: 'IoT Smart Green House & Dashboard Tanaman',
    client: 'PT Sinar Abadi Farm',
    category: 'IoT & Dashboard',
    description: 'Dashboard telemetri sensor kelembaban tanah, suhu, dan otomasi penyiraman nutrisi hidroponik berbasis mikrokontroler ESP32.',
    technologies: ['Vue.js', 'MQTT', 'Node.js', 'InfluxDB'],
    imageUrl: 'https://images.unsplash.com/photo-1530836369250-ef72a3f5cda8?w=800&auto=format&fit=crop&q=80',
    projectUrl: 'https://alfatech.id/case-study/sinar-abadi-iot',
    completionDate: '2024-07-20',
    featured: true
  }
];

const filteredPortfolio = computed(() => {
  if (selectedCategory.value === 'all') return portfolioItems;
  return portfolioItems.filter(item => item.category.toLowerCase().includes(selectedCategory.value.toLowerCase()));
});

const handleLeadSubmit = (e: Event) => {
  e.preventDefault();
  if (!formState.value.contactName || !formState.value.phone) return;
  isSubmittingLead.value = true;
  setTimeout(() => {
    isSubmittingLead.value = false;
    leadSubmittedSuccess.value = true;
  }, 800);
};

const formatRupiah = (val: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};
</script>

<template>
  <Head title="PT Alfatech Digital Solutions - Enterprise Software House & IT Partner" />
  
  <div class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 font-sans transition-colors duration-300">
    <!-- Navbar Top -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
            <Building2 class="w-6 h-6" />
          </div>
          <div>
            <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400">
              Alfatech
            </span>
            <span class="block text-[10px] font-semibold tracking-wider text-slate-500 uppercase">Software House</span>
          </div>
        </div>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
          <a href="#services" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Layanan</a>
          <a href="#portfolio" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Portofolio</a>
          <a href="#contact" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Konsultasi</a>
        </nav>

        <div class="hidden md:flex items-center gap-4">
          <button @click="toggleTheme" type="button" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
            <Sun v-if="isDarkMode" class="w-4 h-4 text-amber-400" />
            <Moon v-else class="w-4 h-4 text-slate-600" />
          </button>
          

        </div>

        <button @click="isMobileNavOpen = !isMobileNavOpen" type="button" class="md:hidden p-2 rounded-lg border border-slate-200 dark:border-slate-800">
          <ChevronDown class="w-6 h-6 transform transition-transform" :class="{ 'rotate-180': isMobileNavOpen }" />
        </button>
      </div>

      <!-- Mobile Nav -->
      <div v-if="isMobileNavOpen" class="md:hidden px-4 pt-2 pb-6 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="flex flex-col gap-4 text-sm font-medium">
          <a href="#services" @click="isMobileNavOpen = false">Layanan</a>
          <a href="#portfolio" @click="isMobileNavOpen = false">Portofolio</a>
          <a href="#contact" @click="isMobileNavOpen = false">Konsultasi Proyek</a>

        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <section class="relative py-20 lg:py-32 overflow-hidden bg-gradient-to-b from-blue-50/50 to-transparent dark:from-blue-950/20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-3xl mx-auto text-center space-y-8">
          <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold uppercase tracking-wider">
            <Sparkles class="w-4 h-4" /> Enterprise Software House Indonesia
          </div>
          
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight">
            Membangun Software Enterprise & Ecosystem Digital Masa Depan
          </h1>
          
          <p class="text-lg sm:text-xl text-slate-600 dark:text-slate-300 leading-relaxed">
            Mitra pengembangan sistem ERP kustom, aplikasi mobile Android & iOS, cloud architecture, dan solusi otomasi bisnis berskala tinggi untuk perusahaan modern.
          </p>

          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <a href="#contact" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-xl text-base font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-xl shadow-blue-600/30 transition-all hover:-translate-y-0.5">
              Konsultasi Proyek Gratis <ArrowRight class="w-5 h-5" />
            </a>
            <a href="#portfolio" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl text-base font-semibold border border-slate-300 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-900 transition-all">
              Lihat Hasil Kerja
            </a>
          </div>

          <!-- Quick Stats -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-12 border-t border-slate-200 dark:border-slate-800">
            <div>
              <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400">45+</p>
              <p class="text-xs text-slate-500 font-medium mt-1">Proyek Enterprise Selesai</p>
            </div>
            <div>
              <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400">99.8%</p>
              <p class="text-xs text-slate-500 font-medium mt-1">SLA Server & Uptime</p>
            </div>
            <div>
              <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400">30+</p>
              <p class="text-xs text-slate-500 font-medium mt-1">Klien Korporasi & B2B</p>
            </div>
            <div>
              <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400">100%</p>
              <p class="text-xs text-slate-500 font-medium mt-1">Hak Milik Source Code</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 bg-white dark:bg-slate-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <h2 class="text-xs font-bold tracking-widest text-blue-600 dark:text-blue-400 uppercase">Layanan Unggulan</h2>
          <p class="text-3xl sm:text-4xl font-bold">Solusi Pengembangan Software Terintegrasi</p>
          <p class="text-slate-600 dark:text-slate-400">Setiap produk dirancang sesuai dengan alur bisnis Anda tanpa ketergantungan paket kaku.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="service in servicesList" :key="service.id" class="p-8 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 hover:border-blue-500/50 transition-all hover:shadow-xl group">
            <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <component :is="service.icon" class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold mb-3">{{ service.name }}</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ service.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Portfolio Showcase -->
    <section id="portfolio" class="py-20 bg-slate-50 dark:bg-slate-950">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
          <div>
            <h2 class="text-xs font-bold tracking-widest text-blue-600 dark:text-blue-400 uppercase mb-2">Studi Kasus Proyek</h2>
            <p class="text-3xl font-bold">Portofolio Pekerjaan Terbaru</p>
          </div>

          <!-- Category Filter -->
          <div class="flex flex-wrap gap-2">
            <button 
              v-for="cat in ['all', 'Enterprise ERP', 'Mobile App', 'Web & E-Commerce', 'IoT & Dashboard']" 
              :key="cat"
              @click="selectedCategory = cat"
              type="button"
              class="px-4 py-2 rounded-xl text-xs font-semibold transition-all"
              :class="selectedCategory === cat ? 'bg-blue-600 text-white shadow-md' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-100'"
            >
              {{ cat === 'all' ? 'Semua Proyek' : cat }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div v-for="item in filteredPortfolio" :key="item.id" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-sm hover:shadow-xl transition-all">
            <div class="relative h-64 overflow-hidden">
              <img :src="item.imageUrl" :alt="item.title" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500" />
              <div class="absolute top-4 left-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/90 dark:bg-slate-900/90 text-blue-600 dark:text-blue-400 backdrop-blur-sm shadow-md">
                  {{ item.category }}
                </span>
              </div>
            </div>
            <div class="p-6 space-y-4">
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ item.client }}</p>
              <h3 class="text-xl font-bold leading-snug">{{ item.title }}</h3>
              <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">{{ item.description }}</p>
              <div class="flex flex-wrap gap-2 pt-2">
                <span v-for="tech in item.technologies" :key="tech" class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                  {{ tech }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Consultation Inbound Form -->
    <section id="contact" class="py-20 bg-white dark:bg-slate-900">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-8 sm:p-12 shadow-xl">
          <div class="text-center space-y-3 mb-10">
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Diskusi Bebas Biaya</span>
            <h2 class="text-3xl font-extrabold">Konsultasikan Kebutuhan Proyek Anda</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">Tim technical architect kami siap menganalisis kebutuhan sistem dan estimasi biaya pengembangan software Anda.</p>
          </div>

          <div v-if="leadSubmittedSuccess" class="p-8 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
              <CheckCircle2 class="w-8 h-8" />
            </div>
            <h3 class="text-xl font-bold text-emerald-900 dark:text-emerald-300">Permintaan Berhasil Dikirim!</h3>
            <p class="text-sm text-emerald-700 dark:text-emerald-400 max-w-md mx-auto">
              Terima kasih! Tim konsultan teknologi Alfatech akan langsung menghubungi nomor WhatsApp/telepon Anda dalam rentang 1x24 jam jam kerja.
            </p>
            <button @click="leadSubmittedSuccess = false" type="button" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Kirim Form Baru</button>
          </div>

          <form v-else @submit="handleLeadSubmit" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Lengkap *</label>
                <input v-model="formState.contactName" required type="text" placeholder="Budi Pratama" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Perusahaan / Organisasi</label>
                <input v-model="formState.companyName" type="text" placeholder="PT Maju Bersama" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nomor WhatsApp / Telepon *</label>
                <input v-model="formState.phone" required type="tel" placeholder="081234567890" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Email Bisnis</label>
                <input v-model="formState.email" type="email" placeholder="budi@perusahaan.com" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Jenis Layanan Yang Dibutuhkan</label>
              <select v-model="formState.serviceType" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="Custom ERP & Web Platform">Custom ERP & Web Platform Enterprise</option>
                <option value="Mobile App Development">Aplikasi Mobile iOS / Android</option>
                <option value="Cloud Architecture & DevOps">Cloud Server & DevOps Infrastructure</option>
                <option value="IoT & System Automation">IoT & Hardware Automation</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                Estimasi Anggaran Proyek: <span class="text-blue-600 font-bold">{{ formatRupiah(formState.budgetEstimate) }}</span>
              </label>
              <input v-model.number="formState.budgetEstimate" type="range" min="15000000" max="250000000" step="5000000" class="w-full accent-blue-600 h-2 bg-slate-200 dark:bg-slate-800 rounded-lg cursor-pointer" />
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Catatan Tambahan / Deskripsi Singkat Fitur</label>
              <textarea v-model="formState.notes" rows="4" placeholder="Jelaskan gambaran singkat kebutuhan software yang ingin dibuat..." class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
            </div>

            <button type="submit" :disabled="isSubmittingLead" class="w-full py-4 rounded-xl text-base font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-xl shadow-blue-600/30 transition-all flex items-center justify-center gap-2">
              <Send class="w-5 h-5" /> {{ isSubmittingLead ? 'Mengirim Data...' : 'Kirim Permintaan Konsultasi' }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800 text-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold">A</div>
          <span class="font-bold text-white text-base">PT Alfatech Digital Solutions</span>
        </div>
        <p>&copy; 2026 PT Alfatech Digital Solutions. Hak Cipta Dilindungi Undang-Undang.</p>
      </div>
    </footer>
  </div>
</template>
