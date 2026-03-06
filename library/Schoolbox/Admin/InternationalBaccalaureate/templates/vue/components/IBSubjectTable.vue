<script setup lang="ts">
import type { IBConfiguration } from "../../js/types/ibConfigurationTypes";
import LanguageSelectionModal from "./LanguageSelectionModal.vue";

interface SubjectRow {
    id: string;
    subject: string;
    level: string;
    languages: string[];
    classCode: string;
    studentCount: number;
    isLanguageRow?: boolean;
    parentRowId?: string;
}

interface Props {
    categoryName: string;
    categoryTitle: string;
    categoryId: number;
    saveEndpoint: string;
    ibSubjects: Array<{ subject: string; level: string }>;
}

const props = defineProps<Props>();

const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);
const subjectRows = window.Vue.ref<SubjectRow[]>([]);
const currentEditingRow = window.Vue.ref<string | null>(null);
const languageModal = window.Vue.ref<InstanceType<typeof LanguageSelectionModal> | null>(null);

// Initialize subject rows from IB subjects
window.Vue.onMounted(() => {
    subjectRows.value = props.ibSubjects.map((subjectData, index) => ({
        id: `${props.categoryName}_subject_${index}`,
        subject: subjectData.subject,
        level: subjectData.level,
        languages: [],
        classCode: "",
        studentCount: 0,
    }));
});

function openLanguageModal(rowId: string): void {
    currentEditingRow.value = rowId;
    if (languageModal.value) {
        languageModal.value.open();
    }
}

function closeLanguageModal(): void {
    currentEditingRow.value = null;
}

function onLanguagesSelected(selectedLanguages: string[]): void {
    if (!currentEditingRow.value) return;

    const rowIndex = subjectRows.value.findIndex(row => row.id === currentEditingRow.value);
    if (rowIndex === -1) return;

    const mainRow = subjectRows.value[rowIndex];
    
    // Remove existing language rows for this subject
    subjectRows.value = subjectRows.value.filter(row => row.parentRowId !== mainRow.id);
    
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

function updateRowField(rowId: string, field: keyof SubjectRow, value: any): void {
    const row = subjectRows.value.find(r => r.id === rowId);
    if (row) {
        (row as any)[field] = value;
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

function getLanguageName(code: string): string {
    const languageNames: { [key: string]: string } = {
        'en': 'English', 'es': 'Spanish', 'fr': 'French', 'de': 'German', 'it': 'Italian',
        'pt': 'Portuguese', 'ru': 'Russian', 'zh': 'Chinese', 'ja': 'Japanese', 'ko': 'Korean',
        'ar': 'Arabic', 'hi': 'Hindi', 'nl': 'Dutch', 'sv': 'Swedish', 'no': 'Norwegian',
        'da': 'Danish', 'fi': 'Finnish', 'pl': 'Polish', 'cs': 'Czech', 'hu': 'Hungarian',
    };
    return languageNames[code] || code;
}

function getCurrentRowLanguages(): string[] {
    if (!currentEditingRow.value) return [];
    const row = subjectRows.value.find(r => r.id === currentEditingRow.value);
    return row?.languages || [];
}

async function saveSubjectConfiguration(): Promise<void> {
    if (!props.saveEndpoint) return;

    try {
        isSaving.value = true;
        saveError.value = null;

        // Prepare data for save
        const saveData = {
            categoryId: props.categoryId,
            categoryName: props.categoryName,
            subjects: subjectRows.value.filter(row => !row.isLanguageRow).map(mainRow => {
                const languageRows = subjectRows.value.filter(row => row.parentRowId === mainRow.id);
                
                return {
                    id: mainRow.id,
                    subject: mainRow.subject,
                    level: mainRow.level,
                    languages: languageRows.length > 0 ? languageRows.map(lr => ({
                        language: lr.languages[0],
                        classCode: lr.classCode,
                        studentCount: lr.studentCount,
                    })) : [{
                        language: mainRow.languages[0] || '',
                        classCode: mainRow.classCode,
                        studentCount: mainRow.studentCount,
                    }]
                };
            })
        };

        const response = await fetch(props.saveEndpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(saveData),
        });

        if (!response.ok) {
            throw new Error("Save failed");
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
            <table class="subject-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Level</th>
                        <th>Languages</th>
                        <th>Class Code</th>
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
                                        :disabled="isSaving"
                                    />
                                    <button 
                                        type="button"
                                        class="sbx-icon-only-button f-no-margins"
                                        aria-label="Edit languages" 
                                        title="Edit languages"
                                        :data-test="`ib-language-edit-${row.id}`"
                                        @click="openLanguageModal(row.id)"
                                        :disabled="isSaving"
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
                                    placeholder="Class code"
                                    :disabled="isSaving"
                                    @input="(event) => updateRowField(row.id, 'classCode', (event.target as HTMLInputElement).value)"
                                />
                                <span v-else class="field-moved">Moved to language rows below</span>
                            </td>
                            <td>
                                <input
                                    v-if="row.isLanguageRow || row.languages.length <= 1"
                                    :value="row.studentCount"
                                    class="table-input"
                                    type="number"
                                    min="0"
                                    :disabled="isSaving"
                                    @input="(event) => updateRowField(row.id, 'studentCount', parseInt((event.target as HTMLInputElement).value) || 0)"
                                />
                                <span v-else class="field-moved">Moved to language rows below</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div v-if="saveError" class="alert alert-box alert">
                <i class="fa fa-exclamation-triangle"></i>
                {{ saveError }}
            </div>

            <div class="form-actions">
                <button 
                    type="button" 
                    class="button submit"
                    :disabled="isSaving"
                    @click="saveSubjectConfiguration"
                >
                    <i v-if="isSaving" class="fa fa-spinner fa-spin"></i>
                    {{ isSaving ? 'Saving...' : `Save ${categoryTitle} Configuration` }}
                </button>
            </div>
        </div>

        <LanguageSelectionModal
            ref="languageModal"
            :selected-languages="getCurrentRowLanguages()"
            @close="closeLanguageModal"
            @languages-selected="onLanguagesSelected"
        />
    </div>
</template>