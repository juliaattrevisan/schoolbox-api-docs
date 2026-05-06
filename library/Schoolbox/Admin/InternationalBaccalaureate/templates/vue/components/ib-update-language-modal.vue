<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import type { StudentRow, FailedRow, SubjectGroup } from "../../js/types/ibReportTypes";

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
    level: "HL" | "SL" | "Core";
    subject: string;
    selected: SearchSelectItem[];
    defaultResults: SearchSelectItem[];
    hasError: boolean;
};

const subjectRows = window.Vue.ref<SubjectRowEntry[]>([]);
const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);

/**
 * Flattens a student's subjects into a flat list of rows with pre-filled language selections.
 * @param student The student whose subjects to flatten.
 * @returns A flat array of subject rows ready for rendering.
 */
function buildSubjectRows(student: StudentRow | FailedRow): SubjectRowEntry[] {
    const rows: SubjectRowEntry[] = [];

    for (const entry of student.subjects.hl) {
        const preselected: SearchSelectItem[] =
            entry.languageId !== null && entry.language !== null
                ? [{ id: entry.languageId, name: entry.language }]
                : [];
        rows.push({
            level: "HL",
            subject: entry.subject,
            selected: preselected,
            defaultResults: preselected,
            hasError: false,
        });
    }
    for (const entry of student.subjects.sl) {
        const preselected: SearchSelectItem[] =
            entry.languageId !== null && entry.language !== null
                ? [{ id: entry.languageId, name: entry.language }]
                : [];
        rows.push({
            level: "SL",
            subject: entry.subject,
            selected: preselected,
            defaultResults: preselected,
            hasError: false,
        });
    }
    for (const entry of student.subjects.core) {
        const preselected: SearchSelectItem[] =
            entry.languageId !== null && entry.language !== null
                ? [{ id: entry.languageId, name: entry.language }]
                : [];
        rows.push({
            level: "Core",
            subject: entry.subject,
            selected: preselected,
            defaultResults: preselected,
            hasError: false,
        });
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

    // TODO SA-22262: replace with real API call when backend endpoint is available
    await new Promise<void>((resolve) => setTimeout(resolve, 300));

    isSaving.value = false;

    const hlEntries = props.student.subjects.hl;
    const slEntries = props.student.subjects.sl;
    const coreEntries = props.student.subjects.core;

    let rowIndex = 0;
    const updatedHl = hlEntries.map((entry) => {
        const sel = subjectRows.value[rowIndex++].selected[0];
        return { ...entry, language: sel.name, languageId: sel.id };
    });
    const updatedSl = slEntries.map((entry) => {
        const sel = subjectRows.value[rowIndex++].selected[0];
        return { ...entry, language: sel.name, languageId: sel.id };
    });
    const updatedCore = coreEntries.map((entry) => {
        const sel = subjectRows.value[rowIndex++].selected[0];
        return { ...entry, language: sel.name, languageId: sel.id };
    });

    const toast = window.sbProps.toastActivate("Saving...");
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
