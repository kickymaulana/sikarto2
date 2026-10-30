<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '../../Layouts/AppLayout.vue';
import LaporanSidebar from './Sidebar.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    year: number;
    factoryId: number | null;
    typeId: number | null;
    factories: Array<{ id: number; name: string }>;
    types: Array<{ id: number; name: string }>;
    tests: Array<{
        id: number;
        code?: string;
        location?: string;
        type?: string;
        brand?: string;
        capacity?: string;
        avg_correction?: number | null;
        acceptable_limit?: string | null;
        test_date?: string;
        status: string;
        next_test_date?: string;
    }>;
}>();

const years = Array.from({ length: 11 }, (_, index) => ({ label: String(2020 + index), value: String(2020 + index) }));
const factoryOptions = [{ label: 'Semua Pabrik', value: '' }, ...props.factories.map((factory) => ({ label: factory.name, value: String(factory.id) }))];
const typeOptions = [{ label: 'Semua Jenis', value: '' }, ...props.types.map((type) => ({ label: type.name, value: String(type.id) }))];

const apply = (params: Record<string, string | undefined>) => {
    router.get(route('laporan.internal-kalibrasi', params), {}, { preserveState: true, preserveScroll: true });
};

const statusLabel = (status: string) => status === 'OK' ? 'Accepted' : status === 'NG' ? 'Not Accepted' : status;
const statusType = (status: string) => status === 'OK' ? 'success' : status === 'NG' ? 'danger' : 'warning';
</script>

<template>
    <div>
        <div class="content-head">
            <LaporanSidebar active="internal" />
            <h2 class="page-title">Internal Kalibrasi Record</h2>
        </div>

        <div class="toolbar">
            <select class="control" :value="String(year)" @change="apply({ year: ($event.target as HTMLSelectElement).value, factory_id: factoryId?.toString(), type_id: typeId?.toString() })"><option v-for="option in years" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(factoryId ?? '')" @change="apply({ year: String(year), factory_id: ($event.target as HTMLSelectElement).value || undefined, type_id: typeId?.toString() })"><option v-for="option in factoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(typeId ?? '')" @change="apply({ year: String(year), factory_id: factoryId?.toString(), type_id: ($event.target as HTMLSelectElement).value || undefined })"><option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <a class="export-btn" :href="route('laporan.internal-kalibrasi.export', { year, factory_id: factoryId ?? undefined, type_id: typeId ?? undefined })">
                <span class="export-button">Export Excel</span>
            </a>
        </div>

        <div class="white-card table-wrap">
            <div class="table-scroll">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Kode Alat</th><th>Lokasi</th><th>Jenis Alat</th><th>Merek</th><th>Kapasitas</th><th>Avg Correction</th><th>Reference Document</th><th>Acceptable Limit</th><th>Tgl Kalibrasi</th><th>Frek Kalibrasi</th><th>Hasil Kalibrasi</th><th>Next Kalibrasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="test in tests" :key="test.id">
                            <td class="code">{{ test.code ?? '—' }}</td><td>{{ test.location ?? '—' }}</td><td>{{ test.type ?? '—' }}</td><td>{{ test.brand ?? '—' }}</td><td>{{ test.capacity ?? '—' }}</td><td>{{ test.avg_correction ?? '—' }}</td><td>MDDWI-QA22</td><td>{{ test.acceptable_limit ?? '—' }}</td><td>{{ test.test_date ?? '—' }}</td><td>PER 1 BULAN</td><td><span class="status-badge" :class="test.status === 'OK' ? 'ok' : test.status === 'NG' ? 'ng' : 'warning'">{{ statusLabel(test.status) }}</span></td><td>{{ test.next_test_date ?? '—' }}</td>
                        </tr>
                        <tr v-if="tests.length === 0"><td colspan="12" class="empty">Tidak ada data kalibrasi.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.content-head { display: flex; align-items: center; gap: 4px; margin-bottom: 12px; }
.page-title { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
.toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.filter-field { width: 200px; }
.table-wrap { overflow: hidden; }
.table-scroll { overflow-x: auto; }
.record-table { width: 100%; min-width: 1500px; border-collapse: collapse; font-size: 12px; }
.record-table th { background: #fff7ed; color: #7c2d12; font-weight: 700; white-space: nowrap; }
.record-table th, .record-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; white-space: nowrap; }
.record-table tbody tr:hover { background: #fffaf5; }
.code { font-weight: 700; font-family: monospace; }
.empty { text-align: center !important; color: #64748b; }
</style>
