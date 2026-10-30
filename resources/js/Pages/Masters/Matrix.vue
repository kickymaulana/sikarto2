<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Cell = { day: string; status: string };

const props = defineProps<{
    year: number;
    typeId: number | null;
    types: Array<{ id: number; name: string }>;
    rows: Array<{
        code: string;
        type?: string;
        brand?: string;
        capacity?: string;
        location?: string;
        test_cell: Record<number, Cell>;
        next_cell: Record<number, Cell>;
    }>;
}>();

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const years = Array.from({ length: 11 }, (_, i) => 2020 + i).map((y) => ({ label: String(y), value: String(y) }));

const typeOptions = [
    { label: 'Semua Jenis', value: '' },
    ...props.types.map((t) => ({ label: t.name, value: String(t.id) })),
];

const matrixColors: Record<string, string> = {
    none: '#e0e0e0',
    OK: '#4caf50',
    NG: '#f44336',
    SPARE: '#2196f3',
    NA: '#9e9e9e',
    SERVICE: '#ff9800',
};
const matrixBg: Record<string, string> = {
    none: '#ffffff',
    OK: '#e8f5e9',
    NG: '#ffebee',
    SPARE: '#e3f2fd',
    NA: '#eceff1',
    SERVICE: '#fff3e0',
};
const statusLabel = (s: string) => (s === 'none' ? '—' : s);

const onYearChange = (value: string | number) => {
    router.get(route('masters.matrix', { year: String(value), type_id: props.typeId ?? undefined }), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const onTypeChange = (value: string | number) => {
    const typeId = value === '' || value === null || value === undefined ? undefined : Number(value);
    router.get(route('masters.matrix', { year: props.year, type_id: typeId }), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};


</script>

<template>
    <div>
        <div class="content-head">
            <h2 class="page-title">Matriks Uji Bulanan {{ year }}</h2>
        </div>

        <div class="toolbar">
            <select class="control" :value="String(year)" @change="onYearChange(($event.target as HTMLSelectElement).value)"><option v-for="option in years" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <select class="control" :value="String(typeId ?? '')" @change="onTypeChange(($event.target as HTMLSelectElement).value)"><option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
            <div class="legend">
                <span v-for="(color, key) in matrixColors" :key="key" class="legend-item">
                    <span class="dot" :style="{ background: color }"></span>
                    {{ key === 'none' ? 'Belum Uji' : key }}
                </span>
            </div>
            <a class="export-btn" :href="route('masters.matrix.export', { year, type_id: typeId ?? undefined })">
                <span class="export-button">Export Excel</span>
            </a>
        </div>

        <div class="white-card table-wrap">
            <div class="table-scroll">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="sticky-col col-code">Kode Alat</th>
                            <th rowspan="2" class="sticky-col col-info">Merk</th>
                            <th rowspan="2" class="sticky-col col-info">Kapasitas</th>
                            <th rowspan="2" class="sticky-col col-info">Lokasi</th>
                            <th v-for="m in monthNames" :key="`m-${m}`" colspan="2" class="center">{{ m }}</th>
                        </tr>
                        <tr>
                            <template v-for="m in monthNames" :key="`s-${m}`">
                                <th class="sub center">Uji</th>
                                <th class="sub center">Next</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.code">
                            <td class="cell-code sticky-col col-code">{{ row.code }}</td>
                            <td class="cell-info sticky-col col-info">{{ row.brand ?? '—' }}</td>
                            <td class="cell-info sticky-col col-info">{{ row.capacity ?? '—' }}</td>
                            <td class="cell-info sticky-col col-info">{{ row.location ?? '—' }}</td>
                            <template v-for="(m, idx) in monthNames" :key="`${row.code}-${idx}`">
                                <td
                                    class="cell-day center"
                                    :style="{ background: matrixBg[row.test_cell[idx + 1]?.status] ?? matrixBg.none }"
                                    :title="statusLabel(row.test_cell[idx + 1]?.status) + (row.test_cell[idx + 1]?.day ? ' · Tanggal ' + row.test_cell[idx + 1]?.day : '')"
                                >
                                    {{ row.test_cell[idx + 1]?.day || '—' }}
                                </td>
                                <td
                                    class="cell-day center"
                                    :style="{ background: matrixBg[row.next_cell[idx + 1]?.status] ?? matrixBg.none }"
                                    :title="statusLabel(row.next_cell[idx + 1]?.status) + (row.next_cell[idx + 1]?.day ? ' · Tanggal ' + row.next_cell[idx + 1]?.day : '')"
                                >
                                    {{ row.next_cell[idx + 1]?.day || '—' }}
                                </td>
                            </template>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="28" class="center">Tidak ada alat</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.content-head {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 12px;
}

.page-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.toolbar {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.year-field {
    width: 140px;
}

.type-field {
    width: 240px;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    padding: 8px 12px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    margin-left: auto;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #475569;
}

.dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.export-btn {
    width: 130px;
    height: 40px;
    text-decoration: none;
    display: block;
}

.table-wrap {
    padding: 12px;
}

.table-scroll {
    overflow-x: auto;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.matrix-table th,
.matrix-table td {
    padding: 6px 4px;
    border-bottom: 1px solid #f1f5f9;
    border-right: 1px solid #f1f5f9;
    text-align: center;
    vertical-align: middle;
}

.matrix-table th {
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
}

.matrix-table th.sub {
    font-size: 10px;
    color: #94a3b8;
    background: #fafafa;
}

.matrix-table .center {
    text-align: center;
}

.cell-code {
    font-family: monospace;
    font-weight: 700;
    white-space: nowrap;
    padding-right: 8px !important;
    text-align: left !important;
    min-width: 80px;
    background: #ffffff;
}

.cell-info {
    white-space: nowrap;
    font-size: 11px;
    color: #475569;
    text-align: left;
    padding-left: 8px !important;
    padding-right: 8px !important;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
}

.cell-day {
    width: 36px;
    min-width: 36px;
    font-weight: 600;
    color: #0f172a;
}

.sticky-col {
    position: sticky;
    z-index: 1;
    background: #ffffff;
}

.col-code {
    left: 0;
    min-width: 80px;
}

.col-info:nth-of-type(2),
.matrix-table th.sticky-col:nth-of-type(2) {
    left: 80px;
    min-width: 90px;
}

.col-info:nth-of-type(3),
.matrix-table th.sticky-col:nth-of-type(3) {
    left: 170px;
    min-width: 90px;
}

.col-info:nth-of-type(4),
.matrix-table th.sticky-col:nth-of-type(4) {
    left: 260px;
    min-width: 140px;
}

tbody tr:nth-child(n+2) td.sticky-col {
    background: #ffffff;
}
</style>
