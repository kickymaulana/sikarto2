<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, ref, watch } from 'vue';
import {
    ArrowLeft,
    BarChart3,
    ChevronDown,
    ClipboardCheck,
    Database,
    Home,
    LayoutDashboard,
    LogOut,
    Menu,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    Users,
    X,
} from '@lucide/vue';
import { searchState } from '../composables/search';

const page = usePage();
const auth = computed(() => page.props.auth);
const can = (permission: string) => !!auth.value?.user && auth.value.user.permissions.includes(permission);
const currentRoute = ref('');
const currentParams = ref<Record<string, any>>({});
const mobileMenuOpen = ref(false);
const masterOpen = ref(false);
const adminOpen = ref(false);
const searching = ref(false);

watch(() => page.url, () => {
    currentRoute.value = route().current() ?? '';
    currentParams.value = { ...(route().params ?? {}) };
    if (currentRoute.value.startsWith('masters.')) masterOpen.value = true;
    if (currentRoute.value.startsWith('users.')) adminOpen.value = true;
}, { immediate: true });

const entityLabels: Record<string, string> = {
    factories: 'Factory', departments: 'Departemen', types: 'Jenis Alat', brands: 'Merk', capacities: 'Kapasitas',
    limits: 'Acceptable Limit', specifications: 'Spesifikasi',
};

const pageTitle = computed(() => {
    const current = currentRoute.value;
    if (current === 'dashboard') return auth.value?.user?.name ?? 'SI KARTO';
    if (current === 'masters.index') return entityLabels[currentParams.value.entity] ?? 'Master Data';
    if (current === 'masters.create') return `Tambah ${entityLabels[currentParams.value.entity] ?? ''}`;
    if (current === 'masters.edit') return `Edit ${entityLabels[currentParams.value.entity] ?? ''}`;
    if (current === 'instruments.index') return 'Alat Ukur';
    if (current === 'instruments.create') return 'Tambah Alat Ukur';
    if (current === 'instruments.edit') return 'Edit Alat Ukur';
    if (current === 'tests.index') return 'Riwayat Pengujian';
    if (current === 'tests.create') return 'Entry Pengujian';
    if (current === 'tests.show') return 'Detail Pengujian';
    if (current === 'tests.edit') return 'Edit Pengujian';
    if (current === 'reports.index') return 'Laporan & Rekapitulasi';
    if (current.startsWith('laporan.')) return current === 'laporan.matrix' ? 'Matriks Uji Bulanan' : 'Laporan';
    if (current === 'users.index') return 'Kelola Pengguna';
    return 'SI KARTO';
});

const isSearchable = computed(() => ['masters.index', 'instruments.index', 'tests.index'].includes(currentRoute.value));
const showAdd = computed(() => ['masters.index', 'instruments.index', 'tests.index'].includes(currentRoute.value));
const isDashboard = computed(() => currentRoute.value === 'dashboard');
const showBack = computed(() => !isDashboard.value);

const masterItems = [
    ['factories', 'Factory'], ['departments', 'Departemen'], ['types', 'Jenis Alat'], ['brands', 'Merk'],
    ['capacities', 'Kapasitas'], ['limits', 'Acceptable Limit'], ['specifications', 'Spesifikasi'],
];

const go = (name: string, params?: Record<string, any>) => {
    mobileMenuOpen.value = false;
    router.get(route(name, params));
};

const startSearch = () => { searching.value = true; };
const exitSearch = () => { searching.value = false; searchState.value = ''; };
const logout = () => router.post(route('logout'));

const goCreate = () => {
    if (currentRoute.value === 'masters.index') go('masters.create', { entity: currentParams.value.entity });
    else if (currentRoute.value === 'instruments.index') go('instruments.create');
    else if (currentRoute.value === 'tests.index') go('tests.create');
};

const goBack = () => {
    const current = currentRoute.value;
    if (current === 'masters.create' || current === 'masters.edit' || current === 'masters.index') {
        if (current === 'masters.index') go('dashboard');
        else go('masters.index', { entity: currentParams.value.entity });
    } else if (current.startsWith('instruments.')) {
        if (current === 'instruments.index') go('dashboard');
        else go('instruments.index');
    } else if (current.startsWith('tests.')) {
        if (current === 'tests.index') go('dashboard');
        else go('tests.index');
    } else if (current.startsWith('laporan.') || current === 'reports.index') {
        go('laporan.index');
    } else if (current === 'users.index') {
        go('dashboard');
    } else {
        go('dashboard');
    }
};

const isActive = (prefix: string) => currentRoute.value === prefix || currentRoute.value.startsWith(`${prefix}.`);
</script>

<template>
    <div class="min-h-screen bg-[var(--background)] text-[var(--foreground)]">
        <aside class="sidebar-shell fixed inset-y-0 left-0 z-40 hidden w-72 lg:flex lg:flex-col">
            <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                <div class="brand-mark"><ShieldCheck :size="20" /></div>
                <div><div class="font-extrabold tracking-tight text-white">SI KARTO</div><div class="text-[10px] uppercase tracking-[0.18em] text-orange-200/60">Quality Operations</div></div>
            </div>
            <div class="px-6 pb-2 pt-6 text-[10px] font-bold uppercase tracking-[0.18em] text-orange-200/45">Workspace</div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-4">
                <button v-if="can('master.read') || can('report.read')" class="nav-group" :class="{ active: isActive('dashboard') }" @click="go('dashboard')"><LayoutDashboard :size="18" /> Beranda</button>
                <button v-if="can('test.read') || can('test.create')" class="nav-group" :class="{ active: isActive('tests') }" @click="go('tests.index')"><ClipboardCheck :size="18" /> Pengujian</button>
                <button v-if="can('report.read') || can('master.read')" class="nav-group" :class="{ active: isActive('reports') || isActive('laporan') }" @click="go('laporan.index')"><BarChart3 :size="18" /> Laporan</button>
                <div v-if="can('master.read')" class="pt-5"><button class="nav-group justify-between" @click="masterOpen = !masterOpen"><span class="flex items-center gap-3"><Database :size="18" /> Master Data</span><ChevronDown :size="16" :class="{ 'rotate-180': masterOpen }" /></button><div v-show="masterOpen" class="ml-5 mt-1 space-y-1 border-l border-white/10 pl-3"><button v-for="item in masterItems" :key="item[0]" class="nav-sub" :class="{ active: currentParams.entity === item[0] && isActive('masters') }" @click="go('masters.index', { entity: item[0] })">{{ item[1] }}</button><button class="nav-sub" :class="{ active: isActive('instruments') }" @click="go('instruments.index')">Alat Ukur</button></div></div>
                <div v-if="can('user.manage')" class="pt-5"><button class="nav-group justify-between" @click="adminOpen = !adminOpen"><span class="flex items-center gap-3"><Settings :size="18" /> Administrasi</span><ChevronDown :size="16" :class="{ 'rotate-180': adminOpen }" /></button><div v-show="adminOpen" class="ml-5 mt-1 space-y-1 border-l border-white/10 pl-3"><button class="nav-sub" :class="{ active: isActive('users') }" @click="go('users.index')">Pengguna & Persetujuan</button></div></div>
            </nav>
            <div class="border-t border-white/10 p-4"><button class="nav-group nav-logout" @click="logout"><LogOut :size="18" /> Keluar</button></div>
        </aside>

        <div class="lg:pl-72">
            <header class="top-app-bar sticky top-0 z-30">
                <div v-if="!searching" class="flex h-[4.5rem] items-center justify-between gap-3 px-4 sm:px-7">
                    <div class="flex min-w-0 items-center gap-3"><button v-if="showBack" class="icon-button" @click="goBack"><ArrowLeft :size="20" /></button><button class="icon-button lg:hidden" @click="mobileMenuOpen = true"><Menu :size="20" /></button><div class="min-w-0"><div class="truncate text-sm font-extrabold sm:text-base">{{ pageTitle }}</div><div v-if="!isDashboard" class="hidden truncate text-xs text-[var(--muted-foreground)] sm:block">SI KARTO · Sistem Kalibrasi Toleransi Operasional</div></div></div>
                    <div class="flex items-center gap-1"><button v-if="isSearchable" class="icon-button" @click="startSearch"><Search :size="19" /></button><button class="icon-button lg:hidden" @click="logout"><LogOut :size="19" /></button></div>
                </div>
                <div v-else class="flex h-[4.5rem] items-center gap-2 px-4"><button class="icon-button" @click="exitSearch"><ArrowLeft :size="20" /></button><input v-model="searchState" autofocus class="h-11 min-w-0 flex-1 rounded-xl border border-[var(--input)] bg-white px-3 text-sm outline-none focus:border-orange-400" placeholder="Cari..." /><button class="icon-button" @click="exitSearch"><X :size="19" /></button></div>
            </header>
            <main class="android-content"><div v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ page.props.flash.success }}</div><div v-if="page.props.flash?.error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ page.props.flash.error }}</div><slot /></main>
        </div>

        <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 lg:hidden"><button class="absolute inset-0 bg-[#241b16]/55" aria-label="Tutup menu" @click="mobileMenuOpen = false" /><aside class="mobile-drawer relative flex h-full w-[min(88vw,340px)] flex-col"><div class="flex h-20 items-center justify-between border-b border-white/10 px-5"><div class="flex items-center gap-3"><div class="brand-mark"><ShieldCheck :size="19" /></div><div><div class="font-extrabold text-white">SI KARTO</div><div class="text-[10px] uppercase tracking-widest text-orange-200/60">Menu utama</div></div></div><button class="icon-button dark-icon" @click="mobileMenuOpen = false"><X :size="20" /></button></div><nav class="flex-1 space-y-1 overflow-y-auto p-4"><button class="nav-group" :class="{ active: isActive('dashboard') }" @click="go('dashboard')"><Home :size="18" /> Beranda</button><button v-if="can('test.read') || can('test.create')" class="nav-group" :class="{ active: isActive('tests') }" @click="go('tests.index')"><ClipboardCheck :size="18" /> Pengujian</button><button v-if="can('report.read') || can('master.read')" class="nav-group" :class="{ active: isActive('laporan') || isActive('reports') }" @click="go('laporan.index')"><BarChart3 :size="18" /> Laporan</button><div v-if="can('master.read')" class="mt-5 border-t border-white/10 pt-4"><div class="nav-caption">MASTER DATA</div><button v-for="item in masterItems" :key="item[0]" class="nav-sub" @click="go('masters.index', { entity: item[0] })">{{ item[1] }}</button><button class="nav-sub" @click="go('instruments.index')">Alat Ukur</button></div><div v-if="can('user.manage')" class="mt-5 border-t border-white/10 pt-4"><div class="nav-caption">ADMINISTRASI</div><button class="nav-sub" @click="go('users.index')"><Users :size="16" /> Pengguna & Persetujuan</button></div></nav><div class="border-t border-white/10 p-4"><button class="nav-group nav-logout" @click="logout"><LogOut :size="18" /> Keluar</button></div></aside></div>

        <button v-if="showAdd" class="fab-button" aria-label="Tambah" @click="goCreate"><Plus :size="24" /></button>
    </div>
</template>

<style scoped>
.sidebar-shell,.mobile-drawer{background:linear-gradient(165deg,#2b231e 0%,#241c18 100%);color:#fff}.brand-mark{display:flex;height:2.5rem;width:2.5rem;align-items:center;justify-content:center;border-radius:.85rem;background:linear-gradient(135deg,#f59a3d,#d85d12);color:#fff;box-shadow:0 8px 18px rgba(216,93,18,.25)}.nav-group,.nav-sub{display:flex;width:100%;align-items:center;gap:.75rem;border-radius:.8rem;padding:.72rem .8rem;text-align:left;font-size:.875rem;font-weight:600;color:rgba(255,245,235,.67);transition:background .15s,color .15s,transform .15s}.nav-group:hover,.nav-sub:hover{background:rgba(255,255,255,.08);color:#fff}.nav-group.active,.nav-sub.active{background:linear-gradient(90deg,rgba(232,117,24,.9),rgba(189,77,13,.72));color:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12)}.nav-sub{font-size:.8125rem;font-weight:500}.nav-caption{padding:0 .8rem .55rem;color:rgba(255,225,199,.45);font-size:.65rem;font-weight:800;letter-spacing:.14em}.nav-logout{color:#ffb39e}.nav-logout:hover{background:rgba(194,65,45,.18);color:#ffd4c8}.icon-button{display:inline-flex;height:2.5rem;width:2.5rem;align-items:center;justify-content:center;border-radius:.8rem;color:var(--muted-foreground);transition:background .15s,color .15s}.icon-button:hover{background:var(--secondary);color:var(--primary-strong)}.dark-icon{color:rgba(255,245,235,.7)}.dark-icon:hover{background:rgba(255,255,255,.08);color:#fff}.rotate-180{transform:rotate(180deg)}.fab-button{position:fixed;bottom:1.5rem;right:1.25rem;z-index:30;display:flex;height:3.25rem;width:3.25rem;align-items:center;justify-content:center;border:0;border-radius:1.1rem;background:linear-gradient(135deg,#f59a3d,#d85d12);color:#fff;box-shadow:0 12px 24px rgba(189,77,13,.28);transition:transform .15s,box-shadow .15s}.fab-button:hover{transform:translateY(-2px);box-shadow:0 16px 28px rgba(189,77,13,.35)}
</style>
