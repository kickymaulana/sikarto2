<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Item = { id: number; standard_value: number | string; reading_value: number | string };
type Group = { id: number; name: string; reference_media: string };
type Dimension = { id: number; dimension: string; label: string; min_value: number | string; max_value: number | string; measured_value: number | string; unit: string };
type StraightnessKey = 'dudukan' | 'tiang_kelurusan' | 'titik_tengah';
type StraightnessChecks = Record<StraightnessKey, string>;
const straightnessFields: Array<{ key: StraightnessKey; label: string }> = [
    { key: 'dudukan', label: 'Dudukan' },
    { key: 'tiang_kelurusan', label: 'Tiang / Kelurusan' },
    { key: 'titik_tengah', label: 'Titik Tengah' },
];
const props = defineProps<{ test: { id: number; test_date: string; status: string; selected_status: string | null; selected_group_id: number | null; reported_correction: string | null; reported_acceptable_limit: string | null; notes: string | null; items: Item[]; dimension_checks: Dimension[]; straightness_checks: StraightnessChecks | null; instrument?: { type?: { name: string } | null } | null }; groups: Group[]; straightnessSuggestions: Record<StraightnessKey, string[]> }>();
const isStraightness = computed(() => props.test.straightness_checks != null || props.test.instrument?.type?.name.trim().toUpperCase() === 'STRAIGHTNESS');
const straightnessChecks = reactive<StraightnessChecks>({
    dudukan: props.test.straightness_checks?.dudukan ?? '',
    tiang_kelurusan: props.test.straightness_checks?.tiang_kelurusan ?? '',
    titik_tengah: props.test.straightness_checks?.titik_tengah ?? '',
});
const normalizeStraightness = (key: StraightnessKey) => { straightnessChecks[key] = straightnessChecks[key].replace(/\s+/gu, ' ').trim().toUpperCase(); };
const form = reactive({
    test_date: props.test.test_date,
    status: props.test.selected_status ?? '',
    selected_group_id: props.test.selected_group_id ?? (props.groups.length === 1 ? props.groups[0].id : null),
    reported_correction: props.test.reported_correction ?? '',
    reported_acceptable_limit: props.test.reported_acceptable_limit ?? '',
    notes: props.test.notes ?? '',
    items: props.test.items.map((item) => ({ id: item.id, standard_value: item.standard_value, reading_value: String(item.reading_value) })),
    dimension_checks: props.test.dimension_checks.map((item) => ({ id: item.id, dimension: item.dimension, label: item.label, min_value: item.min_value, max_value: item.max_value, measured_value: String(item.measured_value), unit: item.unit })),
});
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const isManual = computed(() => ['SPARE', 'NA', 'SERVICE'].includes(form.status));
const straightnessReady = computed(() => ['OK', 'NG'].includes(form.status) && straightnessFields.every(({ key }) => !!straightnessChecks[key].trim()));
const submit = () => {
    if (saving.value || (isStraightness.value && !straightnessReady.value)) return;
    saving.value = true;
    errors.value = {};
    router.put(route('tests.update', props.test.id), {
        test_date: form.test_date,
        notes: form.notes,
        ...(isStraightness.value ? {
            selected_status: form.status,
            straightness_checks: { ...straightnessChecks },
        } : {
            status: form.status || null,
            selected_group_id: form.selected_group_id,
            reported_correction: form.reported_correction.trim() || null,
            reported_acceptable_limit: form.reported_acceptable_limit.trim() || null,
            items: isManual.value ? [] : form.items.map((item) => ({ id: item.id, reading_value: item.reading_value.trim() })),
            dimension_checks: isManual.value ? [] : form.dimension_checks.map((item) => ({ id: item.id, measured_value: item.measured_value.trim() })),
        }),
    }, {
        onError: (messages) => { errors.value = messages; },
        onFinish: () => { saving.value = false; },
    });
};
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="white-card">
            <h3 class="card-title">Edit Pengujian #{{ test.id }}</h3>
            <label for="test-date">Tanggal Uji</label>
            <input id="test-date" v-model="form.test_date" type="date" :disabled="saving" />
            <label for="status">Status pilihan</label>
            <select id="status" v-model="form.status" class="control" :disabled="saving">
                <option value="">{{ isStraightness ? 'Pilih OK atau NG' : 'Otomatis' }}</option>
                <option v-for="status in (isStraightness ? ['OK', 'NG'] : ['OK', 'NG', 'SPARE', 'NA', 'SERVICE'])" :key="status" :value="status">{{ status }}</option>
            </select>
            <p v-if="errors.selected_status" class="error-list">{{ errors.selected_status }}</p>
            <template v-if="!isStraightness">
            <label v-if="groups.length > 1" for="scoring-group">Grup penentu rata-rata dan status</label>
            <select v-if="groups.length > 1" id="scoring-group" v-model="form.selected_group_id" class="control" :disabled="saving">
                <option :value="null">Pilih grup penilaian</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }} · {{ group.reference_media }}</option>
            </select>
            <p v-else-if="groups.length === 1" class="hint">Grup penilaian: {{ groups[0].name }} · {{ groups[0].reference_media }}</p>
            <label for="reported-correction">Correction untuk laporan</label>
            <input id="reported-correction" v-model="form.reported_correction" type="text" placeholder="Contoh: OK, -0,005 GR, +0,5 GR" :disabled="saving" />
            <label for="reported-limit">Acceptable Limit untuk laporan</label>
            <input id="reported-limit" v-model="form.reported_acceptable_limit" type="text" placeholder="Contoh: ± 0,5 GR atau -" :disabled="saving" />
            </template>
        </div>
        <div v-if="isStraightness" class="white-card">
            <h3 class="card-title">Pemeriksaan Straightness</h3>
            <p class="hint">Isi hasil pemeriksaan atau pilih saran riwayat. Teks baru diperbolehkan. Status OK atau NG wajib dipilih; tidak ada perhitungan otomatis.</p>
            <div v-for="field in straightnessFields" :key="field.key" class="item-row">
                <label :for="`straightness-${field.key}`">{{ field.label }}</label>
                <input :id="`straightness-${field.key}`" v-model="straightnessChecks[field.key]" type="text" maxlength="255" :list="`straightness-options-${field.key}`" :disabled="saving" :aria-invalid="!!errors[`straightness_checks.${field.key}`]" :aria-describedby="`straightness-error-${field.key}`" @blur="normalizeStraightness(field.key)" />
                <datalist :id="`straightness-options-${field.key}`"><option v-for="suggestion in (straightnessSuggestions[field.key] ?? []).slice(0, 20)" :key="suggestion" :value="suggestion" /></datalist>
                <p v-if="errors[`straightness_checks.${field.key}`]" :id="`straightness-error-${field.key}`" class="error-list">{{ errors[`straightness_checks.${field.key}`] }}</p>
            </div>
        </div>
        <div v-if="Object.keys(errors).length" role="alert" class="white-card error-list">
            <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
        </div>
        <div v-if="!isStraightness && !isManual && form.items.length" class="white-card">
            <h3 class="card-title">Titik Uji</h3>
            <div v-for="item in form.items" :key="item.id" class="item-row">
                <label :for="`item-${item.id}`">Standar {{ item.standard_value }}</label>
                <input :id="`item-${item.id}`" v-model="item.reading_value" type="text" inputmode="decimal" :disabled="saving" />
            </div>
        </div>
        <div v-if="!isStraightness && !isManual && form.dimension_checks.length" class="white-card">
            <h3 class="card-title">Pemeriksaan Ukuran</h3>
            <div v-for="item in form.dimension_checks" :key="item.id" class="item-row">
                <label :for="`dimension-${item.id}`">{{ item.label }} ({{ item.min_value }} s/d {{ item.max_value }} {{ item.unit }})</label>
                <input :id="`dimension-${item.id}`" v-model="item.measured_value" type="text" inputmode="decimal" :disabled="saving" />
            </div>
        </div>
        <div class="white-card">
            <label for="notes">Catatan</label>
            <textarea id="notes" v-model="form.notes" class="control min-h-24" :disabled="saving" />
            <button type="button" class="submit-btn" :disabled="saving || (isStraightness && !straightnessReady)" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}</button>
        </div>
    </div>
</template>

<style scoped>
.card-title { margin: 0 0 12px; font-size: 14px; color: #0f172a; }
label { display: block; margin: 10px 0 6px; font-size: 13px; font-weight: 600; color: #475569; }
input, textarea, select { box-sizing: border-box; width: 100%; min-width: 0; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font: inherit; background: #fdf0ea; color: #0f172a; }
.item-row { display: grid; gap: 6px; background: #f8fafc; border-radius: 12px; padding: 12px; margin-top: 10px; }
.item-row label { margin: 0; }
.error-list { color: #b91c1c; font-size: 13px; }
.error-list p { margin: 4px 0; }
.submit-btn { margin-top: 16px; background: linear-gradient(135deg, #fb8c00, #f57c00); border-radius: 100px; font-weight: 700; color: white; padding: 12px 18px; border: 0; }
</style>
