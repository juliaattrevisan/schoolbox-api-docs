<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LanguageSelectionModal from "./language-selection-modal.vue";
import type { IBLanguage, IBSubject } from "../../js/types/ibConfigurationTypes";

interface SubjectRow {
    id: string;
    groupSubjectId: number;
    subject: string;
    level: string;
    languages: number[];
    classCode: string;
    studentCount: number;
    isLanguageRow?: boolean;
    parentRowId?: string;
    tableType?: string;
}

interface SubjectSavePayload {
    groupSubjectId: number;
    languageId: number;
    classCode: string;
}

interface Props {
    categoryName: string;
    categoryTitle: string;
    categoryId: number;
    saveEndpoint: string;
    ibSubjects: IBSubject[];
    ibLanguages: IBLanguage[];
}

const props = defineProps<Props>();

const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);
const subjectRows = window.Vue.ref<SubjectRow[]>([]);
const currentEditingRow = window.Vue.ref<string | null>(null);
const isLanguageModalOpen = window.Vue.ref<boolean>(false);

const isCoreCategory = window.Vue.computed(() => {
    return props.categoryName.toLowerCase() === "core";
});

const defaultLanguageId = window.Vue.computed<number | null>(() => {
    const englishLanguage = props.ibLanguages.find((language) => {
        return language.name.toLowerCase() === "english";
    });

    return englishLanguage?.id ?? props.ibLanguages[0]?.id ?? null;
});

function getDefaultLanguages(): number[] {
    return defaultLanguageId.value ? [defaultLanguageId.value] : [];
}

function buildRowsForSubject(subjectData: IBSubject, index: number, tableType?: string): SubjectRow[] {
    const configurations = subjectData.languages;
    const rowId = tableType ? `${tableType}_${subjectData.id}_${index}` : `group_subject_${subjectData.id}`;
    const selectedLanguages = configurations.map((configuration) => configuration.languageId);
    const mainRow: SubjectRow = {
        id: rowId,
        groupSubjectId: subjectData.id,
        subject: subjectData.subject,
        level: subjectData.level,
        languages: selectedLanguages.length > 0 ? selectedLanguages : getDefaultLanguages(),
        classCode: configurations.length === 1 ? configurations[0].classCode : "",
        studentCount: 0,
        tableType,
    };

    if (configurations.length <= 1) {
        return [mainRow];
    }

    const languageRows = configurations.map((configuration, configIndex) => ({
        id: `${rowId}_lang_${configIndex}`,
        groupSubjectId: subjectData.id,
        subject: subjectData.subject,
        level: subjectData.level,
        languages: [configuration.languageId],
        classCode: configuration.classCode,
        studentCount: 0,
        isLanguageRow: true,
        parentRowId: rowId,
        tableType,
    }));

    return [mainRow, ...languageRows];
}

function populateDefaultLanguagesForEmptyRows(): void {
    const defaultLanguages = getDefaultLanguages();

    if (defaultLanguages.length === 0) {
        return;
    }

    subjectRows.value.forEach((row) => {
        if (row.languages.length === 0) {
            row.languages = [...defaultLanguages];
        }
    });
}

function initializeSubjectRows(): void {
    if (isCoreCategory.value) {
        const diplomaRows = props.ibSubjects.flatMap((subjectData, index) =>
            buildRowsForSubject(subjectData, index, "Diploma Program Year 2 [Diploma]"),
        );
        const courseRows = props.ibSubjects.flatMap((subjectData, index) =>
            buildRowsForSubject(subjectData, index, "Diploma Course [Course]"),
        );

        subjectRows.value = [...diplomaRows, ...courseRows];
    } else {
        subjectRows.value = props.ibSubjects.flatMap((subjectData, index) => buildRowsForSubject(subjectData, index));
    }
}

window.Vue.watch(
    () => props.ibSubjects,
    () => {
        initializeSubjectRows();
    },
    { deep: true, immediate: true },
);

window.Vue.watch(
    () => props.ibLanguages,
    (newLanguages, oldLanguages) => {
        if (oldLanguages.length === 0 && newLanguages.length > 0) {
            populateDefaultLanguagesForEmptyRows();
        }
    },
    { deep: true },
);

window.Vue.onMounted(() => {
    initializeSubjectRows();
});

function openLanguageModal(rowId: string): void {
    currentEditingRow.value = rowId;
    isLanguageModalOpen.value = true;
}

function closeLanguageModal(): void {
    currentEditingRow.value = null;
}

function onLanguagesSelected(selectedLanguages: number[]): void {
    if (!currentEditingRow.value) return;

    const rowIndex = subjectRows.value.findIndex((row) => row.id === currentEditingRow.value);
    if (rowIndex === -1) return;

    const mainRow = subjectRows.value[rowIndex];

    subjectRows.value = subjectRows.value.filter((row) => row.parentRowId !== mainRow.id);

    if (selectedLanguages.length === 0) {
        mainRow.languages = [];
        mainRow.classCode = "";
    } else if (selectedLanguages.length === 1) {
        mainRow.languages = selectedLanguages;
    } else {
        mainRow.languages = selectedLanguages;
        mainRow.classCode = "";

        const existingConfigurations =
            props.ibSubjects.find((subject) => subject.id === mainRow.groupSubjectId)?.languages ?? [];
        const classCodeByLanguage = new Map(
            existingConfigurations.map((configuration) => [configuration.languageId, configuration.classCode]),
        );

        const newLanguageRows: SubjectRow[] = selectedLanguages.map((language, index) => ({
            id: `${mainRow.id}_lang_${index}`,
            groupSubjectId: mainRow.groupSubjectId,
            subject: mainRow.subject,
            level: mainRow.level,
            languages: [language],
            classCode: classCodeByLanguage.get(language) ?? "",
            studentCount: 0,
            isLanguageRow: true,
            parentRowId: mainRow.id,
            tableType: mainRow.tableType,
        }));

        subjectRows.value.splice(rowIndex + 1, 0, ...newLanguageRows);
    }
}

function updateRowField(rowId: string, field: keyof SubjectRow, value: string | number): void {
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (row && field in row) {
        (row as Record<string, unknown>)[field] = value;

        if (field === "classCode") {
            updateStudentCount(rowId);
        }
    }
}

function getLanguageDisplayText(row: SubjectRow): string {
    if (row.languages.length === 0) {
        return "Select languages";
    } else if (row.languages.length === 1) {
        return getLanguageName(row.languages[0]);
    } else {
        return `${row.languages.length} selected`;
    }
}

function getLanguageName(languageId: number): string {
    const matchedLanguage = props.ibLanguages.find((language) => language.id === languageId);

    return matchedLanguage?.name || String(languageId);
}

function getCurrentRowLanguages(): number[] {
    if (!currentEditingRow.value) return [];
    const row = subjectRows.value.find((r) => r.id === currentEditingRow.value);
    return row?.languages.length ? row.languages : getDefaultLanguages();
}

function updateStudentCount(rowId: string): void {
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (!row) return;

    try {
        if (isCoreCategory.value) {
            const mockStudentCount = Math.floor(Math.random() * 150) + 50;
            row.studentCount = mockStudentCount;
        } else {
            if (!row.classCode.trim()) {
                row.studentCount = 0;
                return;
            }

            const classCodes = row.classCode
                .split(",")
                .map((code) => code.trim())
                .filter((code) => code);
            const mockStudentCount = classCodes.length * Math.floor(Math.random() * 25) + 5;

            row.studentCount = mockStudentCount;
        }
    } catch (error) {
        console.error("Failed to fetch student count:", error);
        row.studentCount = 0;
    }
}

function buildSubjectSavePayload(): SubjectSavePayload[] {
    const payload = subjectRows.value
        .filter((row) => row.isLanguageRow || row.languages.length <= 1)
        .flatMap((row) => {
            const languageId = row.languages[0];

            if (!languageId) {
                return [];
            }

            return [
                {
                    groupSubjectId: row.groupSubjectId,
                    languageId,
                    classCode: row.classCode.trim(),
                },
            ];
        });

    return payload.filter((item, index, items) => {
        return (
            items.findIndex((candidate) => {
                return (
                    candidate.groupSubjectId === item.groupSubjectId &&
                    candidate.languageId === item.languageId &&
                    candidate.classCode === item.classCode
                );
            }) === index
        );
    });
}

async function saveSubjectConfiguration(): Promise<void> {
    if (isSaving.value) return;
    if (!props.saveEndpoint) return;

    try {
        isSaving.value = true;
        saveError.value = null;

        const saveData = {
            categoryId: props.categoryId,
            categoryName: props.categoryName,
            subjectLanguages: buildSubjectSavePayload(),
        };

        const response = await apiRequest<{ success: boolean }, Record<string, unknown>>({
            url: props.saveEndpoint,
            method: "POST",
            body: saveData as Record<string, unknown>,
        });

        if (!response.success) {
            throw new Error(response.message || "Save failed");
        }

        const toast = window.sbProps.toastActivate(`${props.categoryTitle} configuration saved successfully`);
        toast.timeout({ text: "Saved" });
    } catch (error) {
        saveError.value = "Failed to save configuration";
        const toast = window.sbProps.toastActivate("Save failed");
        toast.timeout({ text: "Save failed", isSuccess: false });
        console.error(error);
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div class="subject-table-container">
        <div v-if="subjectRows.length === 0" class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            No subjects configured yet for {{ categoryTitle }}. Use the table below to add subjects.
        </div>

        <div v-else>
            <template v-if="isCoreCategory">
                <h3>Diploma Program Year 2 [Diploma]</h3>
                <table class="subject-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Languages</th>
                            <th>Current Student Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="row in subjectRows.filter((r) => r.tableType === 'Diploma Program Year 2 [Diploma]')"
                            :key="row.id"
                        >
                            <tr>
                                <td>{{ row.subject }}</td>
                                <td>
                                    <div class="l-flex l-gap-xs">
                                        <input
                                            type="text"
                                            :value="getLanguageDisplayText(row)"
                                            class="table-input l-flex__item--1"
                                            placeholder="Select languages"
                                            readonly
                                        />
                                        <button
                                            type="button"
                                            class="sbx-icon-only-button f-no-margins"
                                            aria-label="Edit languages"
                                            title="Edit languages"
                                            :data-test="`ib-language-edit-${row.id}`"
                                            @click="openLanguageModal(row.id)"
                                        >
                                            <sbx-icon name="modify"></sbx-icon>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="student-count-display">{{ row.studentCount }}</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <h3 style="margin-top: 2rem">Diploma Course [Course]</h3>
                <table class="subject-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Languages</th>
                            <th>Current Student Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="row in subjectRows.filter((r) => r.tableType === 'Diploma Course [Course]')"
                            :key="row.id"
                        >
                            <tr>
                                <td>{{ row.subject }}</td>
                                <td>
                                    <div class="l-flex l-gap-xs">
                                        <input
                                            type="text"
                                            :value="getLanguageDisplayText(row)"
                                            class="table-input l-flex__item--1"
                                            placeholder="Select languages"
                                            readonly
                                        />
                                        <button
                                            type="button"
                                            class="sbx-icon-only-button f-no-margins"
                                            aria-label="Edit languages"
                                            title="Edit languages"
                                            :data-test="`ib-language-edit-${row.id}`"
                                            @click="openLanguageModal(row.id)"
                                        >
                                            <sbx-icon name="modify"></sbx-icon>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="student-count-display">{{ row.studentCount }}</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>

            <template v-else>
                <table class="subject-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Level</th>
                            <th>Languages</th>
                            <th>
                                Class Code Mapping
                                <span title="Enter the classcodes for each subject." class="tooltip-icon">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </th>
                            <th>Student Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in subjectRows" :key="row.id">
                            <tr :class="{ 'language-row': row.isLanguageRow }">
                                <td>
                                    <span v-if="row.isLanguageRow" class="language-indent">↳</span>
                                    {{ row.subject }}
                                </td>
                                <td>{{ row.level }}</td>
                                <td>
                                    <div v-if="!row.isLanguageRow" class="l-flex l-gap-xs">
                                        <input
                                            type="text"
                                            :value="getLanguageDisplayText(row)"
                                            class="table-input l-flex__item--1"
                                            placeholder="Select languages"
                                            readonly
                                        />
                                        <button
                                            type="button"
                                            class="sbx-icon-only-button f-no-margins"
                                            aria-label="Edit languages"
                                            title="Edit languages"
                                            :data-test="`ib-language-edit-${row.id}`"
                                            @click="openLanguageModal(row.id)"
                                        >
                                            <sbx-icon name="modify"></sbx-icon>
                                        </button>
                                    </div>
                                    <span v-else class="language-display">
                                        {{ getLanguageName(row.languages[0]) }}
                                    </span>
                                </td>
                                <td>
                                    <input
                                        v-if="row.isLanguageRow || row.languages.length <= 1"
                                        :value="row.classCode"
                                        class="table-input"
                                        placeholder="Enter class codes (comma separated)"
                                        @input="
                                            (event) =>
                                                updateRowField(
                                                    row.id,
                                                    'classCode',
                                                    (event.target as HTMLInputElement).value,
                                                )
                                        "
                                    />
                                    <span v-else class="field-moved">Moved to language rows below</span>
                                </td>
                                <td>
                                    <input
                                        v-if="row.isLanguageRow || row.languages.length <= 1"
                                        :value="row.studentCount"
                                        class="table-input"
                                        type="text"
                                        @input="
                                            (event) =>
                                                updateRowField(
                                                    row.id,
                                                    'studentCount',
                                                    parseInt((event.target as HTMLInputElement).value) || 0,
                                                )
                                        "
                                    />
                                    <span v-else class="field-moved">Moved to language rows below</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>

            <div v-if="saveError" class="l-mar-m--bottom">
                <request-error :further-info="saveError" />
            </div>

            <div class="form-actions">
                <button type="button" class="button submit" @click="saveSubjectConfiguration">
                    {{ isSaving ? "Saving..." : `Save ${categoryTitle} Configuration` }}
                </button>
            </div>
        </div>

        <LanguageSelectionModal
            v-model:is-open="isLanguageModalOpen"
            :available-languages="props.ibLanguages"
            :selected-languages="getCurrentRowLanguages()"
            @close="closeLanguageModal"
            @languages-selected="onLanguagesSelected"
        />
    </div>
</template>
