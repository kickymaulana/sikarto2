<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { BarChart3, ChevronRight, ClipboardCheck, Database, FileText, Users, Wrench } from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ dueInstruments: Array<{ id: number; code: string; type?: string; factory?: string; department?: string; latest_test_date?: string; next_test_date?: string }> }>();
const page = usePage();
const user = page.props.auth?.user;
const can = (p: string) => !!user?.permissions.includes(p);
const isUserRole = !!user?.roles?.includes('user');
const goEntry = () => router.get(route('tests.create'));
const goTests = () => router.get(route('tests.index'));
const goMasters = () => router.get(route('masters.index', { entity: 'factories' }));
const goLaporan = () => router.get(route('laporan.index'));
const goUsers = () => router.get(route('users.index'));
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        <div class="rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-500 to-amber-500 p-6 text-white shadow-lg shadow-orange-500/15"><p class="mb-1 text-sm font-medium text-orange-100">Selamat datang di SI KARTO</p><h1 class="text-2xl font-extrabold tracking-tight">Operasional kalibrasi lebih teratur.</h1><p class="mt-2 max-w-xl text-sm text-orange-50">Kelola pengujian rutin, data alat ukur, dan laporan dalam satu tempat.</p></div>
        <div v-if="isUserRole" class="grid gap-4 sm:grid-cols-2"><button class="dashboard-card" @click="goLaporan"><span class="dashboard-icon bg-blue-50 text-blue-600"><BarChart3 :size="22" /></span><span><strong>Laporan</strong><small>Lihat matriks dan rekapitulasi</small></span><ChevronRight :size="18" /></button></div>
        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button v-if="can('test.create')" class="dashboard-card" @click="goEntry"><span class="dashboard-icon bg-orange-50 text-orange-600"><ClipboardCheck :size="22" /></span><span><strong>Entry Pengujian</strong><small>Input hasil kalibrasi</small></span><ChevronRight :size="18" /></button>
            <button v-if="can('test.read')" class="dashboard-card" @click="goTests"><span class="dashboard-icon bg-slate-100 text-slate-600"><FileText :size="22" /></span><span><strong>Riwayat Pengujian</strong><small>Lihat hasil pengujian</small></span><ChevronRight :size="18" /></button>
            <button v-if="can('master.read')" class="dashboard-card" @click="goMasters"><span class="dashboard-icon bg-amber-50 text-amber-600"><Database :size="22" /></span><span><strong>Master Data</strong><small>Kelola data operasional</small></span><ChevronRight :size="18" /></button>
            <button v-if="can('report.read') || can('master.read')" class="dashboard-card" @click="goLaporan"><span class="dashboard-icon bg-blue-50 text-blue-600"><BarChart3 :size="22" /></span><span><strong>Laporan</strong><small>Matriks dan rekapitulasi</small></span><ChevronRight :size="18" /></button>
            <button v-if="can('user.manage')" class="dashboard-card" @click="goUsers"><span class="dashboard-icon bg-violet-50 text-violet-600"><Users :size="22" /></span><span><strong>Administrasi</strong><small>Pengguna dan persetujuan</small></span><ChevronRight :size="18" /></button>
        </div>
        <section><div class="mb-3 flex items-center justify-between"><h2 class="text-base font-bold">Alat Perlu Uji</h2><Link :href="route('tests.index')" class="text-sm font-semibold text-orange-600">Lihat semua</Link></div><div v-if="dueInstruments.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Tidak ada alat yang jatuh tempo bulan ini.</div><div v-else class="grid gap-3 md:grid-cols-2"><button v-for="i in dueInstruments" :key="i.id" class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-orange-200 hover:shadow-md" @click="goEntry"><div class="flex items-center justify-between gap-3"><span class="font-mono text-sm font-bold">{{ i.code }}</span><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="i.next_test_date ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700'">{{ i.next_test_date ? 'OVERDUE' : 'BELUM UJI' }}</span></div><div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500"><span>{{ i.type }}</span><span>{{ i.factory }} / {{ i.department }}</span><span>Uji terakhir: {{ i.latest_test_date ?? '—' }}</span></div></button></div></section>
    </div>
</template>

<style scoped>
.dashboard-card{display:flex;align-items:center;gap:.75rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:1rem;text-align:left;box-shadow:0 4px 16px rgba(15,23,42,.04);transition:border-color .15s,box-shadow .15s}.dashboard-card:hover{border-color:#fdba74;box-shadow:0 8px 24px rgba(249,115,22,.1)}.dashboard-card span:nth-child(2){display:flex;min-width:0;flex:1;flex-direction:column;gap:.2rem}.dashboard-card strong{font-size:.875rem}.dashboard-card small{color:#64748b;font-size:.75rem}.dashboard-icon{display:flex;height:2.75rem;width:2.75rem;flex:none;align-items:center;justify-content:center;border-radius:.875rem}
</style>
