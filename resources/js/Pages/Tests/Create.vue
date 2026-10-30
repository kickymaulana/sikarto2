<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Decimal = number | string;
type Instrument = {
    id: number;
    code: string;
    factory: { name: string } | null;
    department: { name: string } | null;
    type: { name: string } | null;
    brand: { name: string } | null;
    specification: {
        name: string;
        length_min: Decimal | null;
        length_max: Decimal | null;
        width_min: Decimal | null;
        width_max: Decimal | null;
        diameter_min: Decimal | null;
        diameter_max: Decimal | null;
        dimension_unit: string | null;
    } | null;
    capacity: { name: string; groups: Array<{ id: number; name: string; reference_media: string; standards: Array<{ id: number; standard_value: Decimal }> }> } | null;
    acceptable_limit: { name: string; min_correction: Decimal; max_correction: Decimal; unit: string } | null;
};
type Point = { standard_template_id: number; standard_value: Decimal; reading_value: string; index: number };
type Group = { id: number; name: string; reference_media: string; open: boolean; points: Point[] };
type DimensionCheck = { dimension: string; label: string; min: Decimal; max: Decimal; measured_value: string };
type InstrumentOption = { id: number; code: string };
const props = defineProps<{ instruments: Instrument[] }>();
const selected = ref<Instrument | null>(null);
const search = ref('');
const searchResults = ref<InstrumentOption[]>(props.instruments.map(({ id, code }) => ({ id, code })));
const searching = ref(false);
const searchError = ref('');
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchRequest = 0;
const groups = ref<Group[]>([]);
const dimensionChecks = ref<DimensionCheck[]>([]);
const today = new Date();
const localToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
const form = reactive({ instrument_id: '' as number | string, test_date: localToday, status: '', notes: '' });
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const instrumentOptions = computed(() => searchResults.value);
const items = computed(() => groups.value.flatMap((group) => group.points));
const isManual = computed(() => ['SPARE', 'NA', 'SERVICE'].includes(form.status));
const unit = computed(() => selected.value?.acceptable_limit?.unit ?? '');
const scaled = (value: Decimal): number | null => {
    const text = String(value).trim();
    if (!/^[+-]?(?:\d+(?:\.\d{1,4})?|\.\d{1,4})$/.test(text) || Math.abs(Number(text)) > 99999999.9999) return null;
    const [whole, fraction = ''] = text.replace(/^[+-]/, '').split('.');
    return (text.startsWith('-') ? -1 : 1) * (Number(whole) * 10000 + Number(fraction.padEnd(4, '0')));
};
const correction = (point: Point): number | null => {
    const reading = scaled(point.reading_value);
    const standard = scaled(point.standard_value);
    if (reading === null || standard === null) return null;
    const difference = reading - standard;
    return Math.abs(difference) <= 999999999999 ? difference : null;
};
const mean = (points: Point[]): number | null => {
    const values = points.map(correction).filter((value): value is number => value !== null);
    if (!values.length) return null;
    const sum = values.reduce((total, value) => total + BigInt(value), 0n);
    const count = BigInt(values.length);
    const absolute = sum < 0n ? -sum : sum;
    const rounded = absolute / count + ((absolute % count) * 2n >= count ? 1n : 0n);
    return Number(sum < 0n ? -rounded : rounded) / 10000;
};
const progress = (group: Group) => group.points.filter((point) => correction(point) !== null).length;
const allFilled = computed(() => groups.value.length > 0 && groups.value.every((group) => group.points.length > 0 && progress(group) === group.points.length));
const avgCorrection = computed(() => allFilled.value ? mean(items.value) : null);
const limitValid = computed(() => {
    const limit = selected.value?.acceptable_limit;
    if (!limit?.unit) return false;
    const min = scaled(limit.min_correction);
    const max = scaled(limit.max_correction);
    return min !== null && max !== null && min <= max;
});
const computedStatus = computed(() => {
    const limit = selected.value?.acceptable_limit;
    if (isManual.value || avgCorrection.value === null || !limit || !limitValid.value) return '';
    return avgCorrection.value >= Number(limit.min_correction) && avgCorrection.value <= Number(limit.max_correction) ? 'OK' : 'NG';
});
const finalStatus = computed(() => form.status || computedStatus.value);
const dimensionsFilled = computed(() => dimensionChecks.value.every((check) => scaled(check.measured_value) !== null));
const dimensionStatus = (check: DimensionCheck) => {
    const measured = scaled(check.measured_value);
    const min = scaled(check.min);
    const max = scaled(check.max);
    if (measured === null || min === null || max === null) return '';
    return measured >= min && measured <= max ? 'OK' : 'NG';
};
const hasCalibrationPoints = computed(() => items.value.length > 0);
const measurementReady = computed(() => (hasCalibrationPoints.value ? allFilled.value && limitValid.value : dimensionChecks.value.length > 0 && ['OK', 'NG'].includes(form.status)) && dimensionsFilled.value);
const canSubmit = computed(() => !!selected.value && !!form.test_date && form.test_date <= localToday && (isManual.value || measurementReady.value));
const pointErrors = (point: Point) => Object.entries(errors.value).filter(([key]) => key === `items.${point.index}` || key.startsWith(`items.${point.index}.`));
const applyInstrument = (instrument: Instrument) => {
    selected.value = instrument;
    form.instrument_id = instrument.id;
    form.status = '';
    errors.value = {};
    let index = 0;
    groups.value = (instrument.capacity?.groups ?? []).map((group, groupIndex) => ({
        id: group.id,
        name: group.name,
        reference_media: group.reference_media,
        open: groupIndex === 0,
        points: group.standards.map((point) => ({ standard_template_id: point.id, standard_value: point.standard_value, reading_value: '', index: index++ })),
    }));
    const specification = instrument.specification;
    dimensionChecks.value = specification ? [
        { dimension: 'length', label: 'Panjang', min: specification.length_min, max: specification.length_max },
        { dimension: 'width', label: 'Lebar', min: specification.width_min, max: specification.width_max },
        { dimension: 'diameter', label: 'Diameter', min: specification.diameter_min, max: specification.diameter_max },
    ].filter((check) => check.min !== null && check.max !== null).map((check) => ({ ...check, min: check.min!, max: check.max!, measured_value: '' })) : [];
};
const selectInstrument = async (id: unknown) => {
    const option = searchResults.value.find((item) => item.id === Number(id));
    if (!option) return;
    searching.value = true;
    searchError.value = '';
    try {
        const response = await fetch(route('tests.instruments.show', option.id), { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Gagal memuat alat ukur.');
        const payload = await response.json() as { data: Instrument };
        applyInstrument(payload.data);
        search.value = payload.data.code;
        searchResults.value = [option];
    } catch (error) {
        searchError.value = error instanceof Error ? error.message : 'Gagal memuat alat ukur.';
    } finally {
        searching.value = false;
    }
};
const searchInstruments = async (value: string) => {
    const query = value.trim();
    if (query.length < 2) {
        searchResults.value = [];
        searchError.value = '';
        return;
    }
    const requestId = ++searchRequest;
    searching.value = true;
    searchError.value = '';
    try {
        const response = await fetch(`${route('tests.instruments.search')}?search=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Gagal mencari alat ukur.');
        const payload = await response.json() as { data: InstrumentOption[] };
        if (requestId === searchRequest) searchResults.value = payload.data;
    } catch (error) {
        if (requestId === searchRequest) searchError.value = error instanceof Error ? error.message : 'Gagal mencari alat ukur.';
    } finally {
        if (requestId === searchRequest) searching.value = false;
    }
};
watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { void searchInstruments(value); }, 300);
});
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer); });
const submit = () => {
    if (!canSubmit.value || saving.value) return;
    saving.value = true;
    errors.value = {};
    const payload = {
        instrument_id: form.instrument_id,
        test_date: form.test_date,
        notes: form.notes,
        ...(form.status ? { status: form.status } : {}),
        ...(!isManual.value ? {
            items: items.value.map((point) => ({ standard_template_id: point.standard_template_id, reading_value: point.reading_value.trim() })),
            dimension_checks: dimensionChecks.value.map((check) => ({ dimension: check.dimension, measured_value: check.measured_value.trim() })),
        } : {}),
    };
    router.post(route('tests.store'), payload, {
        onError: (messages) => {
            errors.value = messages;
            groups.value.forEach((group) => {
                if (messages.items || group.points.some((point) => pointErrors(point).length)) group.open = true;
            });
            errors.value.general = 'Gagal menyimpan pengujian. Periksa input.';
        },
        onFinish: () => { saving.value = false; },
    });
};
if (import.meta.env.DEV) {
    const point = (reading_value: string): Point => ({ standard_template_id: 1, standard_value: '0', reading_value, index: 0 });
    console.assert(scaled('0') === 0 && scaled('') === null && scaled('1.00001') === null && scaled('100000000') === null, 'Decimal validation');
    console.assert(mean([point('-0.0001'), point('0')]) === -0.0001 && mean([point('0.0001'), point('0')]) === 0.0001, 'Half-away-from-zero rounding');
    console.assert(mean([point('0'), point('0'), point('3')]) === 1, 'Point-weighted mean');
}
</script>

<template>
    <div class="white-card">
        <h3 class="card-title">1. Pilih Alat Ukur</h3>
        <input v-model="search" class="control" type="search" placeholder="Ketik minimal 2 karakter kode, jenis, atau merk" :disabled="saving || searching" autocomplete="off" />
        <p v-if="searching" class="search-state">Mencari...</p>
        <p v-else-if="search.trim().length < 2" class="search-state">Ketik minimal 2 karakter untuk mencari alat ukur.</p>
        <p v-else-if="!instrumentOptions.length && !searchError" class="search-state">Alat ukur tidak ditemukan.</p>
        <p v-if="searchError" class="error-list">{{ searchError }}</p>
        <select v-model="form.instrument_id" class="control" :disabled="saving || searching || !instrumentOptions.length" @change="selectInstrument(form.instrument_id)">
            <option value="">Pilih hasil pencarian</option>
            <option v-for="option in instrumentOptions" :key="option.id" :value="option.id">{{ option.code }}</option>
        </select>
    </div>
    <div v-if="Object.keys(errors).length" role="alert" class="white-card error-list">
        <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
    </div>
    <div v-if="selected" class="info-card">
        <h3 class="selected-code">{{ selected.code }}</h3>
        <div class="info-row"><span>Factory / Departemen</span><span>{{ selected.factory?.name ?? '—' }} / {{ selected.department?.name ?? '—' }}</span></div>
        <div class="info-row"><span>Jenis / Merk</span><span>{{ selected.type?.name ?? '—' }} / {{ selected.brand?.name ?? '—' }}</span></div>
        <div class="info-row"><span>Kapasitas</span><span>{{ selected.capacity?.name ?? '—' }}</span></div>
        <div class="info-row"><span>Spesifikasi</span><span>{{ selected.specification?.name ?? '—' }}</span></div>
        <div class="info-row"><span>Toleransi</span><span v-if="selected.acceptable_limit">{{ selected.acceptable_limit.min_correction }} s/d {{ selected.acceptable_limit.max_correction }} {{ unit }}</span><span v-else>—</span></div>
    </div>
    <div v-if="selected" class="white-card">
        <h3 class="card-title">2. Input Penunjukan</h3>
        <label for="test-date">Tanggal Uji</label>
        <input id="test-date" v-model="form.test_date" type="date" :max="localToday" :disabled="saving" />
        <div class="field-block">
            <label class="field-label">Status pilihan (menggantikan otomatis)</label>
            <select v-model="form.status" class="control" :disabled="saving"><option value="">Otomatis</option><option v-for="status in ['OK', 'NG', 'SPARE', 'NA', 'SERVICE']" :key="status" :value="status">{{ status }}</option></select>
        </div>
        <div v-if="isManual" class="notice info">Status {{ form.status }} mengabaikan pengukuran. Input tetap tersimpan sementara di halaman ini, tetapi tidak dikirim atau disimpan sebagai hasil uji.</div>
        <div v-show="!isManual" class="items-list">
            <p v-if="!groups.length && !dimensionChecks.length">Alat belum memiliki titik uji kapasitas atau pemeriksaan ukuran. Lengkapi master atau pilih SPARE/NA/SERVICE.</p>
            <details v-for="group in groups" :key="group.id" :open="group.open" class="group-card" @toggle="group.open = ($event.target as HTMLDetailsElement).open">
                <summary>{{ group.name }} · {{ progress(group) }}/{{ group.points.length }} titik valid</summary>
                <div class="group-content">
                    <p>Media referensi: {{ group.reference_media }}</p>
                    <p>Rata-rata Koreksi Sementara: {{ mean(group.points) ?? '—' }} {{ unit }}</p>
                    <p v-if="!group.points.length" class="error-list">Grup belum memiliki titik uji.</p>
                    <div v-for="point in group.points" :key="point.standard_template_id" class="item-row">
                        <label :for="`reading-${point.standard_template_id}`">Penunjukan untuk standar {{ point.standard_value }} {{ unit }}</label>
                        <input :id="`reading-${point.standard_template_id}`" v-model="point.reading_value" type="text" inputmode="decimal" :disabled="saving" :aria-invalid="!!pointErrors(point).length || (point.reading_value !== '' && correction(point) === null)" :aria-describedby="`reading-error-${point.standard_template_id}`" />
                        <span>Koreksi: {{ correction(point) === null ? '—' : correction(point)! / 10000 }} {{ unit }}</span>
                        <div :id="`reading-error-${point.standard_template_id}`" class="error-list" aria-live="polite">
                            <p v-if="point.reading_value !== '' && scaled(point.reading_value) === null">Isi angka maksimal 4 desimal, rentang ±99999999.9999.</p>
                            <p v-else-if="point.reading_value !== '' && correction(point) === null">Standar tidak valid atau koreksi di luar rentang ±99999999.9999.</p>
                            <p v-for="([key, message]) in pointErrors(point)" :key="key">{{ message }}</p>
                        </div>
                    </div>
                </div>
            </details>
            <div v-if="hasCalibrationPoints && !allFilled" class="notice warning">Semua titik uji wajib diisi angka valid, termasuk nol. Status pilihan OK/NG tetap memerlukan semua titik.</div>
            <div v-if="hasCalibrationPoints && !limitValid" class="notice warning">Batas toleransi atau satuan tidak valid. Perbaiki master alat.</div>
            <div v-if="!hasCalibrationPoints && dimensionChecks.length && !['OK', 'NG'].includes(form.status)" class="notice warning">Pilih status OK atau NG secara manual untuk pengujian ukuran.</div>
            <div v-if="dimensionChecks.length" class="group-card">
                <div class="group-content">
                    <h3 class="card-title">Pemeriksaan Ukuran</h3>
                    <div v-for="check in dimensionChecks" :key="check.dimension" class="item-row">
                        <label :for="`dimension-${check.dimension}`">{{ check.label }} ({{ check.min }} s/d {{ check.max }} {{ selected.specification?.dimension_unit }})</label>
                        <input :id="`dimension-${check.dimension}`" v-model="check.measured_value" type="text" inputmode="decimal" :disabled="saving" />
                        <span v-if="dimensionStatus(check)" class="status-chip" :class="dimensionStatus(check) === 'OK' ? 'ok' : 'ng'">{{ dimensionStatus(check) }}</span>
                        <p v-if="check.measured_value !== '' && scaled(check.measured_value) === null" class="error-list">Isi angka maksimal 4 desimal, rentang ±99999999.9999.</p>
                    </div>
                </div>
            </div>
        </div>
        <textarea v-model="form.notes" class="control min-h-24" placeholder="Catatan (opsional)" :disabled="saving" />
        <div class="result" aria-live="polite">
            <p>Rata-rata Koreksi Global: {{ isManual ? '—' : avgCorrection ?? '—' }} {{ isManual ? '' : unit }}</p>
            <p>Otomatis: {{ computedStatus || (isManual ? 'Tidak dihitung' : 'Menunggu semua titik valid') }}</p>
            <p>Pilihan: {{ form.status || 'Tidak dipilih' }}</p>
            <strong>Status efektif: {{ finalStatus || '—' }}</strong>
        </div>
        <button type="button" class="submit-btn" :disabled="!canSubmit || saving" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan Pengujian' }}</button>
    </div>
</template>

<style scoped>
.card-title { margin: 0 0 12px; font-size: 14px; color: #0f172a; }
.info-card { background: #fdf0ea; border-radius: 16px; padding: 14px 16px; border: 1px solid #ffe0b2; }
.info-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 13px; }
.selected-code { margin: 0 0 8px; font-size: 16px; color: #f57c00; }
.group-card { border: 1px solid #f1f5f9; border-radius: 16px; background: white; margin: 12px 0; overflow: hidden; }
summary { padding: 14px; background: #fdf0ea; color: #9a4d00; cursor: pointer; font-weight: 600; overflow-wrap: anywhere; }
summary:focus-visible, input:focus-visible { outline: 2px solid #fb8c00; outline-offset: -2px; }
.group-content { padding: 12px; }
.item-row { display: grid; gap: 8px; background: #f8fafc; border-radius: 12px; padding: 12px; margin-top: 10px; }
input { box-sizing: border-box; width: 100%; min-width: 0; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font: inherit; background: #fdf0ea; color: #0f172a; }
.error-list { color: #b91c1c; font-size: 13px; }
.error-list p { margin: 4px 0; }
.result { margin: 12px 0; }
.submit-btn { margin-top: 12px; background: linear-gradient(135deg, #fb8c00, #f57c00); border-radius: 100px; font-weight: 700; }
</style>
