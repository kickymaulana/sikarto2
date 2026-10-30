<script setup lang="ts">
import { computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ChevronRight } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { searchState } from '../../composables/search';

defineOptions({ layout: AppLayout });
const props = defineProps<{ instruments: { data: Array<any>; current_page: number; last_page: number; total: number; per_page: number } }>();
const currentPage = computed(() => props.instruments.current_page);
watch(searchState, (val) => router.get(route('instruments.index'), { search: val || undefined, per_page: props.instruments.per_page }, { preserveState: true, preserveScroll: true }));
const onPageChange = (page: number) => router.get(route('instruments.index'), { page, per_page: props.instruments.per_page, search: searchState.value || undefined }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <div class="mx-auto max-w-5xl"><div v-if="instruments.data.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Tidak ada alat ukur.</div><div v-else class="space-y-3"><Link v-for="i in instruments.data" :key="i.id" :href="route('instruments.edit', { instrument: i.id })" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-orange-200 hover:shadow-md"><div class="min-w-0"><div class="font-mono text-sm font-bold text-slate-900">{{ i.code }}</div><div class="mt-1 truncate text-xs text-slate-500">{{ i.type?.name }} · {{ i.brand?.name }} · {{ i.capacity?.name }} {{ i.specification?.name ? '· '+i.specification.name : '' }}</div><span v-if="i.deleted_at || !i.is_active" class="mt-2 inline-flex rounded-full bg-red-50 px-2 py-1 text-[10px] font-bold text-red-600">NONAKTIF</span></div><ChevronRight :size="18" class="shrink-0 text-slate-400" /></Link></div><div v-if="instruments.last_page > 1" class="mt-6 flex items-center justify-center gap-3"><button class="page-button" :disabled="currentPage <= 1" @click="onPageChange(currentPage - 1)">Sebelumnya</button><span class="text-sm text-slate-500">{{ currentPage }} / {{ instruments.last_page }}</span><button class="page-button" :disabled="currentPage >= instruments.last_page" @click="onPageChange(currentPage + 1)">Selanjutnya</button></div></div>
</template>

<style scoped>.page-button{border:1px solid #e2e8f0;border-radius:.625rem;background:#fff;padding:.55rem .8rem;font-size:.75rem;font-weight:600;color:#c2410c}.page-button:disabled{cursor:not-allowed;opacity:.4}</style>
