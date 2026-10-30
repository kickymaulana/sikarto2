<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ShieldCheck, CheckCircle2 } from '@lucide/vue';

const page = usePage();
const errorMessage = computed(() => (page.props as any).errors?.message || '');
const successMessage = computed(() => (page.props as any)?.flash?.success ?? '');
</script>

<template>
    <Head title="Masuk" />
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10"><div class="w-full max-w-md"><div class="mb-8 text-center"><div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-3xl bg-orange-100 text-orange-500"><ShieldCheck :size="48" /></div><h1 class="text-3xl font-extrabold tracking-tight text-orange-600">SI KARTO</h1><p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Sistem Kalibrasi Toleransi Operasional Alat Ukur Rutin Bulanan</p></div><div class="mb-5 space-y-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex gap-2 text-sm text-slate-600"><CheckCircle2 :size="18" class="shrink-0 text-emerald-500" />Kalibrasi rutin bulanan tanpa kertas</div><div class="flex gap-2 text-sm text-slate-600"><CheckCircle2 :size="18" class="shrink-0 text-emerald-500" />Status otomatis per titik uji</div><div class="flex gap-2 text-sm text-slate-600"><CheckCircle2 :size="18" class="shrink-0 text-emerald-500" />Jadwal uji dan riwayat untuk audit</div></div><div v-if="errorMessage" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ errorMessage }}</div><div v-if="successMessage" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMessage }}</div><a :href="route('sso.redirect')" class="flex w-full items-center justify-center rounded-xl bg-orange-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600">Masuk dengan SSO Perusahaan</a><p class="mt-4 text-center text-xs leading-5 text-slate-400">Gunakan akun perusahaan (NIK &amp; Password SSO) yang terdaftar.<br>Akun baru akan disetujui terlebih dahulu oleh Admin.</p><p class="mt-10 text-center text-xs text-slate-400">© 2026 SI KARTO · Departemen QA</p></div></div>
</template>
