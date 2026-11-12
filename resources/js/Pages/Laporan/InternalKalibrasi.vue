<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '../../Layouts/AppLayout.vue';
import LaporanSidebar from './Sidebar.vue';
import { ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    year: number;
    month: number | null;
    factoryId: number | null;
    typeId: number | null;
    dateFrom: string | null;
    dateTo: string | null;
    factories: Array<{ id: number; name: string }>;
    types: Array<{ id: number; name: string }>;
    tests: Array<{
        id: number;
        code?: string;
        location?: string;
        type?: string;
        brand?: string;
        capacity?: string;
        avg_correction?: string | null;
        acceptable_limit?: string | null;
        test_date?: string;
        status: string;
        next_test_date?: string;
        tester?: string;
    }>;
}>();

const years = Array.from({ length: 11 }, (_, index) => ({ label: String(2020 + index), value: String(2020 + index) }));
const months = [{ label: 'Semua Bulan', value: '' }, ...['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'].map((label, index) => ({ label, value: String(index + 1) }))];
const factoryOptions = [{ label: 'Semua Pabrik', value: '' }, ...props.factories.map((factory) => ({ label: factory.name, value: String(factory.id) }))];
const typeOptions = [{ label: 'Semua Jenis', value: '' }, ...props.types.map((type) => ({ label: type.name, value: String(type.id) }))];
const dateFrom = ref(props.dateFrom ?? '');
const dateTo = ref(props.dateTo ?? '');
watch(() => props.dateFrom, (value) => { dateFrom.value = value ?? ''; });
watch(() => props.dateTo, (value) => { dateTo.value = value ?? ''; });

const activeParams = () => ({
    year: String(props.year),
    month: props.month?.toString(),
    factory_id: props.factoryId?.toString(),
    type_id: props.typeId?.toString(),
    date_from: props.dateFrom ?? undefined,
    date_to: props.dateTo ?? undefined,
});
const apply = (overrides: Record<string, string | undefined>) => {
    router.get(route('laporan.internal-kalibrasi', { ...activeParams(), ...overrides }), {}, { preserveState: true, preserveScroll: true });
};
const applyDates = () => apply({ date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined });
const resetDates = () => { dateFrom.value = ''; dateTo.value = ''; applyDates(); };

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
            <select class="control" :value="String(year)" @change="apply({ year: ($event.target as HTMLSelectElement).value, month: month?.toString(), factory_id: factoryId?.toString(), type_id: typeId?.toString() })"><option v-for="option in years" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(month ?? '')" @change="apply({ year: String(year), month: ($event.target as HTMLSelectElement).value || undefined, factory_id: factoryId?.toString(), type_id: typeId?.toString() })"><option v-for="option in months" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(factoryId ?? '')" @change="apply({ year: String(year), month: month?.toString(), factory_id: ($event.target as HTMLSelectElement).value || undefined, type_id: typeId?.toString() })"><option v-for="option in factoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(typeId ?? '')" @change="apply({ year: String(year), month: month?.toString(), factory_id: factoryId?.toString(), type_id: ($event.target as HTMLSelectElement).value || undefined })"><option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <label class="date-filter">Dari <input v-model="dateFrom" type="date" :max="dateTo || undefined" /></label>
            <label class="date-filter">Sampai <input v-model="dateTo" type="date" :min="dateFrom || undefined" /></label>
            <button type="button" class="filter-btn" @click="applyDates">Terapkan</button>
            <button v-if="dateFrom || dateTo" type="button" class="filter-btn secondary" @click="resetDates">Reset Tanggal</button>
            <a class="export-btn" :href="route('laporan.internal-kalibrasi.export', { ...activeParams() })">
                <span class="export-button">Export Excel</span>
            </a>
        </div>

        <div class="white-card table-wrap">
            <div class="table-scroll">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Kode Alat</th><th>Lokasi</th><th>Jenis Alat</th><th>Merek</th><th>Kapasitas</th><th>Avg Correction</th><th>Reference Document</th><th>Acceptable Limit</th><th>Tgl Kalibrasi</th><th>Frek Kalibrasi</th><th>Hasil Kalibrasi</th><th>Next Kalibrasi</th><th>Inspector/Penguji</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="test in tests" :key="test.id">
                            <td class="code">{{ test.code ?? '—' }}</td><td>{{ test.location ?? '—' }}</td><td>{{ test.type ?? '—' }}</td><td>{{ test.brand ?? '—' }}</td><td>{{ test.capacity ?? '—' }}</td><td>{{ test.avg_correction ?? '—' }}</td><td>MDDWI-QA22</td><td>{{ test.acceptable_limit ?? '—' }}</td><td>{{ test.test_date ?? '—' }}</td><td>PER 1 BULAN</td><td><span class="status-badge" :class="test.status === 'OK' ? 'ok' : test.status === 'NG' ? 'ng' : 'warning'">{{ statusLabel(test.status) }}</span></td><td>{{ test.next_test_date ?? '—' }}</td><td>{{ test.tester ?? '—' }}</td>
                        </tr>
                        <tr v-if="tests.length === 0"><td colspan="13" class="empty">Tidak ada data kalibrasi.</td></tr>
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
.date-filter { display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 12px; }
.date-filter input { padding: 9px 10px; border: 1px solid #e2e8f0; border-radius: 10px; font: inherit; color: #334155; }
.filter-btn { border: 0; border-radius: 10px; padding: 9px 12px; background: #f97316; color: white; font-size: 12px; font-weight: 700; }
.filter-btn.secondary { background: #e2e8f0; color: #475569; }
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
