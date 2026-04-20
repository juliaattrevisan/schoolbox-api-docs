<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LanguageSelectionModal from "./language-selection-modal.vue";
import type { IBLanguage } from "../../js/types/ibConfigurationTypes";

interface SubjectRow {
    id: string;
    subject: string;
    level: string;
    languages: number[];
    classCode: string;
    studentCount: number;
    isLanguageRow?: boolean;
    parentRowId?: string;
    tableType?: string;
}

interface Props {
    categoryName: string;
    categoryTitle: string;
    categoryId: number;
    saveEndpoint: string;
    ibSubjects: Array<{ subject: string; level: string }>;
    ibLanguages: IBLanguage[];
}

const props = defineProps<Props>();

const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);
const subjectRows = window.Vue.ref<SubjectRow[]>([]);
const currentEditingRow = window.Vue.ref<string | null>(null);
const isLanguageModalOpen = window.Vue.ref<boolean>(false);

// Check if this is a Core category
const isCoreCategory = window.Vue.computed(() => {
    return props.categoryName.toLowerCase() === "core";
});

const defaultLanguageId = window.Vue.computed<number | null>(() => {
    const englishLanguage = props.ibLanguages.find((language) => {
        return language.name.toLowerCase() === "english";
    });

    return englishLanguage?.id ?? props.ibLanguages[0]?.id ?? null;
});

/**
 * Gets the default language selection for new rows.
 * @returns The English language id when available, otherwise the first configured language
 */
function getDefaultLanguages(): number[] {
    return defaultLanguageId.value ? [defaultLanguageId.value] : [];
}

/**
 * Initializes subject rows from the ibSubjects prop.
 */
function initializeSubjectRows(): void {
    if (isCoreCategory.value) {
        // For Core category, create two separate tables using subjects from database via API
        const coreSubjects = props.ibSubjects; // Now comes from database

        const diplomaRows = coreSubjects.map((subjectData, index) => ({
            id: `diploma_${index}`,
            subject: subjectData.subject,
            level: subjectData.level,
            languages: getDefaultLanguages(),
            classCode: "",
            studentCount: 0,
            tableType: "Diploma Program Year 2 [Diploma]",
        }));

        const courseRows = coreSubjects.map((subjectData, index) => ({
            id: `course_${index}`,
            subject: subjectData.subject,
            level: subjectData.level,
            languages: getDefaultLanguages(),
            classCode: "",
            studentCount: 0,
            tableType: "Diploma Course [Course]",
        }));

        subjectRows.value = [...diplomaRows, ...courseRows];

        // Keep student counts at 0 for Core subjects (as requested)
        // subjectRows.value.forEach(row => {
        //     void updateStudentCount(row.id);
        // });
    } else {
        // Regular subjects for other categories
        subjectRows.value = props.ibSubjects.map((subjectData, index) => ({
            id: `${props.categoryName}_subject_${index}`,
            subject: subjectData.subject,
            level: subjectData.level,
            languages: getDefaultLanguages(),
            classCode: "",
            studentCount: 0,
        }));
    }
}

// Watch for changes to ibSubjects prop
window.Vue.watch(
    () => props.ibSubjects,
    () => {
        initializeSubjectRows();
    },
    { deep: true, immediate: true },
);

// Initialize on mount
window.Vue.onMounted(() => {
    initializeSubjectRows();
});

/**
 * Opens the language selection modal for a specific row
 * @param rowId - The ID of the row to edit languages for
 */
function openLanguageModal(rowId: string): void {
    currentEditingRow.value = rowId;
    isLanguageModalOpen.value = true;
}

/**
 * Closes the language selection modal
 */
function closeLanguageModal(): void {
    currentEditingRow.value = null;
}

/**
 * Handles language selection from the modal
 * @param selectedLanguages - Array of selected language ids
 */
function onLanguagesSelected(selectedLanguages: number[]): void {
    if (!currentEditingRow.value) return;

    const rowIndex = subjectRows.value.findIndex((row) => row.id === currentEditingRow.value);
    if (rowIndex === -1) return;

    const mainRow = subjectRows.value[rowIndex];

    // Remove existing language rows for this subject
    subjectRows.value = subjectRows.value.filter((row) => row.parentRowId !== mainRow.id);

    if (selectedLanguages.length === 0) {
        // No languages selected, reset main row
        mainRow.languages = [];
    } else if (selectedLanguages.length === 1) {
        // Single language, update main row
        mainRow.languages = selectedLanguages;
    } else {
        // Multiple languages, create language rows
        mainRow.languages = selectedLanguages;

        const newLanguageRows: SubjectRow[] = selectedLanguages.map((language, index) => ({
            id: `${mainRow.id}_lang_${index}`,
            subject: mainRow.subject,
            level: mainRow.level,
            languages: [language],
            classCode: "",
            studentCount: 0,
            isLanguageRow: true,
            parentRowId: mainRow.id,
        }));

        // Insert language rows after the main row
        subjectRows.value.splice(rowIndex + 1, 0, ...newLanguageRows);
    }
}

/**
 * Updates a field in a subject row
 * @param rowId - The ID of the row to update
 * @param field - The field to update
 * @param value - The new value for the field
 */
function updateRowField(rowId: string, field: keyof SubjectRow, value: string | number): void {
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (row && field in row) {
        // Type assertion is safe here as we check the field exists in the row
        (row as Record<string, unknown>)[field] = value;

        // Update student count when class code changes
        if (field === "classCode") {
            void updateStudentCount(rowId);
        }
    }
}

/**
 * Gets the display text for languages in a row
 * @param row - The subject row
 * @returns Display text for the languages
 */
function getLanguageDisplayText(row: SubjectRow): string {
    if (row.languages.length === 0) {
        return "Select languages";
    } else if (row.languages.length === 1) {
        return getLanguageName(row.languages[0]);
    } else {
        return `${row.languages.length} selected`;
    }
}

/**
 * Gets the human-readable name for a language id
 * @param languageId - The language id
 * @returns The language name
 */
function getLanguageName(languageId: number): string {
    const matchedLanguage = props.ibLanguages.find((language) => language.id === languageId);

    return matchedLanguage?.name || String(languageId);
}

/**
 * Gets the languages for the currently editing row
 * @returns Array of language ids for the current row
 */
function getCurrentRowLanguages(): number[] {
    if (!currentEditingRow.value) return [];
    const row = subjectRows.value.find((r) => r.id === currentEditingRow.value);
    return row?.languages.length ? row.languages : getDefaultLanguages();
}

/**
 * Updates student count based on class codes or qualification
 * This would typically call an API to get actual student counts
 * @param rowId - The ID of the row to update student count for
 */
function updateStudentCount(rowId: string): void {
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (!row) return;

    try {
        if (isCoreCategory.value) {
            // For Core subjects, count students based on qualification
            const mockStudentCount = Math.floor(Math.random() * 150) + 50; // 50-200 students
            row.studentCount = mockStudentCount;

            // TODO: Replace with actual API call to get students by qualification
            // const response = await fetch(`/api/student-count-by-qualification?subject=${row.subject}`);
            // const data = await response.json();
            // row.studentCount = data.totalStudents;
        } else {
            // For regular subjects, count by class codes
            if (!row.classCode.trim()) {
                row.studentCount = 0;
                return;
            }

            // Split class codes by comma and simulate student count
            const classCodes = row.classCode
                .split(",")
                .map((code) => code.trim())
                .filter((code) => code);
            const mockStudentCount = classCodes.length * Math.floor(Math.random() * 25) + 5; // 5-30 students per class

            row.studentCount = mockStudentCount;

            // TODO: Replace with actual API call
            // const response = await fetch(`/api/student-count?classCodes=${classCodes.join(',')}`);
            // const data = await response.json();
            // row.studentCount = data.totalStudents;
        }
    } catch (error) {
        console.error("Failed to fetch student count:", error);
        row.studentCount = 0;
    }
}

/**
 * Saves the subject configuration to the server
 */
async function saveSubjectConfiguration(): Promise<void> {
    if (isSaving.value) return; // Prevent multiple simultaneous saves
    if (!props.saveEndpoint) return;

    try {
        isSaving.value = true;
        saveError.value = null;

        // Prepare data for save
        const saveData = {
            categoryId: props.categoryId,
            categoryName: props.categoryName,
            subjects: subjectRows.value
                .filter((row) => !row.isLanguageRow)
                .map((mainRow) => {
                    const languageRows = subjectRows.value.filter((row) => row.parentRowId === mainRow.id);

                    return {
                        id: mainRow.id,
                        subject: mainRow.subject,
                        level: mainRow.level,
                        languages:
                            languageRows.length > 0
                                ? languageRows.map((lr) => ({
                                      language: lr.languages[0],
                                      classCode: lr.classCode,
                                      studentCount: lr.studentCount,
                                  }))
                                : [
                                      {
                                          language: mainRow.languages[0] || "",
                                          classCode: mainRow.classCode,
                                          studentCount: mainRow.studentCount,
                                      },
                                  ],
                    };
                }),
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
            <!-- Core Category: Two separate tables -->
            <template v-if="isCoreCategory">
                <!-- Diploma Program Year 2 Table -->
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

                <!-- Diploma Course Table -->
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

            <!-- Other Categories: Single table -->
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
