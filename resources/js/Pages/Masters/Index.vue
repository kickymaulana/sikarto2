<script setup lang="ts">
import { computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '../../Layouts/AppLayout.vue';
import { searchState } from '../../composables/search';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    entity: string;
    config: {
        label: string;
        columns: Array<{ key: string; label: string }>;
    };
    items: {
        data: Array<Record<string, any>>;
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
}>();

const currentPage = computed(() => props.items.current_page);

watch(searchState, (val) => {
    router.get(route('masters.index', { entity: props.entity }), {
        search: val || undefined,
        per_page: props.items.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
});

const onPageChange = (page: number, perPage: number) => {
    router.get(route('masters.index', { entity: props.entity }), {
        page,
        per_page: perPage,
        search: searchState.value || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const resolved = (item: Record<string, any>, key: string) => {
    if (key === 'factory' && item.factory) return item.factory.name;
    return item[key] ?? '';
};

const displayName = (item: Record<string, any>) => {
    const key = props.config.columns[0]?.key ?? 'name';
    return resolved(item, key);
};
</script>

<template>
    <div>
        <div>
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="page-title">{{ config.label }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Kelola data {{ config.label.toLowerCase() }} SI KARTO.</p>
                </div>
                <button class="add-button" @click="router.get(route('masters.create', { entity }))">Tambah {{ config.label }}</button>
            </div>
            <div class="min-w-0">
                <div v-if="items.data.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada data {{ config.label.toLowerCase() }}.</div>
                <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th v-for="c in config.columns" :key="c.key" class="whitespace-nowrap px-4 py-3 font-bold">{{ c.label }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in items.data" :key="item.id" class="cursor-pointer transition hover:bg-orange-50" @click="router.get(route('masters.edit', { entity, id: item.id }))"><td v-for="c in config.columns" :key="c.key" class="whitespace-nowrap px-4 py-3 text-slate-700">{{ resolved(item, c.key) }}</td></tr></tbody></table></div>
                </div>
                <div v-if="items.last_page > 1" class="mt-6 flex items-center justify-center gap-3"><button class="page-button" :disabled="currentPage <= 1" @click="onPageChange(currentPage - 1, items.per_page)">Sebelumnya</button><span class="text-sm text-slate-500">{{ currentPage }} / {{ items.last_page }}</span><button class="page-button" :disabled="currentPage >= items.last_page" @click="onPageChange(currentPage + 1, items.per_page)">Selanjutnya</button></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-title{font-size:1.125rem;font-weight:700;color:#172033}.add-button{border:0;border-radius:.625rem;background:#f97316;padding:.65rem .9rem;color:#fff;font-size:.8rem;font-weight:700}.page-button{border:1px solid #e2e8f0;border-radius:.625rem;background:#fff;padding:.55rem .8rem;font-size:.75rem;font-weight:600;color:#c2410c}.page-button:disabled{cursor:not-allowed;opacity:.4}
</style>
