<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import type { StudentRow, FailedRow, SubjectGroup, SubjectEntry } from "../../js/types/ibReportTypes";

interface Props {
    student: StudentRow | FailedRow | null;
    searchLanguagesEndpoint: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    saved: [studentId: number, updatedSubjects: SubjectGroup];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false });

type SubjectRowEntry = {
    groupSubjectId: number;
    level: "HL" | "SL" | "Core";
    subject: string;
    selected: SearchSelectItem[];
    defaultResults: SearchSelectItem[];
    hasError: boolean;
};

const subjectRows = window.Vue.ref<SubjectRowEntry[]>([]);
const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);

type LevelKey = keyof SubjectGroup;
type LevelLabel = SubjectRowEntry["level"];
const LEVEL_KEYS: Array<{ key: LevelKey; label: LevelLabel }> = [
    { key: "hl", label: "HL" },
    { key: "sl", label: "SL" },
    { key: "core", label: "Core" },
];

/**
 * Flattens a student's subjects into a flat list of rows with pre-filled language selections.
 * @param student The student whose subjects to flatten.
 * @returns A flat array of subject rows ready for rendering.
 */
function buildSubjectRows(student: StudentRow | FailedRow): SubjectRowEntry[] {
    const rows: SubjectRowEntry[] = [];
    for (const { key, label } of LEVEL_KEYS) {
        for (const entry of student.subjects[key]) {
            const preselected: SearchSelectItem[] =
                entry.languageId !== null && entry.language !== null
                    ? [{ id: entry.languageId, name: entry.language }]
                    : [];
            rows.push({
                groupSubjectId: entry.groupSubjectId,
                level: label,
                subject: entry.subject,
                selected: preselected,
                defaultResults: preselected,
                hasError: false,
            });
        }
    }
    return rows;
}

window.Vue.watch(isOpen, (newVal) => {
    if (newVal && props.student) {
        subjectRows.value = buildSubjectRows(props.student);
        saveError.value = null;
        isSaving.value = false;
    }
});

/** Validates selections, mocks the save, shows a toast, and emits updated subjects. */
async function onSave(): Promise<void> {
    if (isSaving.value || !props.student) {
        return;
    }

    let isValid = true;
    for (const row of subjectRows.value) {
        row.hasError = row.selected.length === 0;
        if (row.hasError) {
            isValid = false;
        }
    }
    if (!isValid) {
        return;
    }

    isSaving.value = true;
    saveError.value = null;

    const subjects = subjectRows.value.map((row) => ({
        groupSubjectId: row.groupSubjectId,
        languageId: Number(row.selected[0].id),
    }));

    const toast = window.sbProps.toastActivate("Saving...");

    const response = await apiRequest<{ success: boolean; message?: string }, { subjects: typeof subjects }>({
        url: props.student.updateLanguageEndpoint!,
        method: "PUT",
        body: { subjects },
    });

    isSaving.value = false;

    if (!response.success) {
        toast.timeout({ text: response.message ?? "Failed to save language selections.", isSuccess: false });
        saveError.value = response.message ?? "Failed to save language selections.";
        return;
    }

    const { hl: hlEntries, sl: slEntries, core: coreEntries } = props.student.subjects;

    /**
     * Maps subject entries to updated entries using the current modal selections.
     * @param entries The subject entries to update.
     * @param startIndex The index into subjectRows where these entries start.
     * @returns Updated subject entries with the selected language applied.
     */
    function buildUpdatedEntries(entries: SubjectEntry[], startIndex: number): SubjectEntry[] {
        return entries.map((entry, i) => {
            const sel = subjectRows.value[startIndex + i].selected[0];
            return { ...entry, language: sel.name, languageId: Number(sel.id) };
        });
    }

    const updatedHl = buildUpdatedEntries(hlEntries, 0);
    const updatedSl = buildUpdatedEntries(slEntries, hlEntries.length);
    const updatedCore = buildUpdatedEntries(coreEntries, hlEntries.length + slEntries.length);

    toast.timeout({ text: "Languages successfully updated." });

    emit("saved", props.student.id, { hl: updatedHl, sl: updatedSl, core: updatedCore });
    isOpen.value = false;
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <form v-if="student" class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onSave">
            <h2>Update Language Selection</h2>
            <p class="f-no-margins">{{ student.name }}</p>

            <div class="l-flex-col l-gap-s">
                <div v-for="(row, index) in subjectRows" :key="index">
                    <search-select
                        v-model:selected="row.selected"
                        :label="`${row.level}: ${row.subject}`"
                        :label-id="`ib-update-lang-${index}`"
                        :endpoint="searchLanguagesEndpoint"
                        :default-results="row.defaultResults"
                        :is-single-select="true"
                        :has-error="row.hasError"
                        error-message="Please select a language"
                        placeholder="Search for a language..."
                    />
                </div>
            </div>

            <request-error v-if="saveError" :further-info="saveError" />

            <div class="l-flex l-gap-xs f-no-margins">
                <button type="button" class="button l-flex__item--1" @click.prevent="isOpen = false">Cancel</button>
                <button class="button submit l-flex__item--1" :disabled="isSaving">
                    {{ isSaving ? "Saving..." : "Save" }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>
