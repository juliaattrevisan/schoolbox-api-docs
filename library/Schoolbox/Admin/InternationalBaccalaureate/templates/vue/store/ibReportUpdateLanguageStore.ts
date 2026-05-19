import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Ref } from "vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import type { StudentRow, FailedRow, SubjectGroup, SubjectEntry } from "../../js/types/ibReportTypes";

type UpdatableStudent = StudentRow | FailedRow;
type SubjectRowEntry = {
    groupSubjectId: number;
    level: "HL" | "SL" | "Core";
    subject: string;
    selected: SearchSelectItem[];
    defaultResults: SearchSelectItem[];
    hasError: boolean;
};

type LevelKey = keyof SubjectGroup;
type LevelLabel = SubjectRowEntry["level"];

const LEVEL_KEYS: { key: LevelKey; label: LevelLabel }[] = [
    { key: "hl", label: "HL" },
    { key: "sl", label: "SL" },
    { key: "core", label: "Core" },
];

export const useIbReportUpdateLanguageStore = defineStore("ibReportUpdateLanguage", () => {
    const isOpen = window.Vue.ref(false);
    const isSaving = window.Vue.ref(false);
    const saveError = window.Vue.ref<string | null>(null);
    const activeStudent = window.Vue.ref<UpdatableStudent | null>(null);
    const searchLanguagesEndpoint = window.Vue.ref("");
    const subjectRows = window.Vue.ref<SubjectRowEntry[]>([]);

    let standardSections: Ref<StudentRow[]>[] = [];
    let failedSection: Ref<FailedRow[]> | null = null;

    /**
     * Set the search endpoint and register the report sections updated by this modal.
     * @param endpoint The search endpoint exposed by the page.
     * @param preparationStudents The preparation section rows.
     * @param unconfirmedStudents The unconfirmed section rows.
     * @param confirmedStudents The confirmed section rows.
     * @param failedStudents The failed section rows.
     */
    function init(
        endpoint: string,
        preparationStudents: Ref<StudentRow[]>,
        unconfirmedStudents: Ref<StudentRow[]>,
        confirmedStudents: Ref<StudentRow[]>,
        failedStudents: Ref<FailedRow[]>,
    ): void {
        searchLanguagesEndpoint.value = endpoint;
        standardSections = [preparationStudents, unconfirmedStudents, confirmedStudents];
        failedSection = failedStudents;
    }

    /**
     * Updates a student's subjects in-place across all registered report sections.
     * @param studentId The registration session student ID of the updated student.
     * @param updatedSubjects The new subject group with updated language selections.
     */
    function handleStudentUpdated(studentId: number, updatedSubjects: SubjectGroup): void {
        const sections = failedSection === null ? [...standardSections] : [...standardSections, failedSection];

        for (const section of sections) {
            const index = section.value.findIndex((student) => student.id === studentId);
            if (index !== -1) {
                section.value[index] = { ...section.value[index], subjects: updatedSubjects };
                return;
            }
        }
    }

    /**
     * Build the editable subject rows for a student.
     * @param student The student being edited.
     * @returns Flat subject rows for modal rendering.
     */
    function buildSubjectRows(student: UpdatableStudent): SubjectRowEntry[] {
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

    /**
     * Open the modal for a given student.
     * @param student The student to update.
     */
    function prompt(student: UpdatableStudent): void {
        activeStudent.value = student;
        subjectRows.value = buildSubjectRows(student);
        saveError.value = null;
        isSaving.value = false;
        isOpen.value = true;
    }

    /**
     * Close the modal and reset transient state.
     */
    function close(): void {
        isOpen.value = false;
        isSaving.value = false;
        saveError.value = null;
        activeStudent.value = null;
        subjectRows.value = [];
    }

    /**
     * Maps a slice of modal rows back onto the original subject entries.
     * @param entries The existing subject entries for a level.
     * @param startIndex The row index for the first entry in that level.
     * @returns Updated subject entries with the selected language applied.
     */
    function buildUpdatedEntries(entries: SubjectEntry[], startIndex: number): SubjectEntry[] {
        return entries.map((entry, index) => {
            const selection = subjectRows.value[startIndex + index].selected[0];
            return {
                ...entry,
                language: selection.name,
                languageId: Number(selection.id),
            };
        });
    }

    /**
     * Validate the current modal selections.
     * @returns true when all rows have a selected language.
     */
    function isValid(): boolean {
        let valid = true;

        for (const row of subjectRows.value) {
            row.hasError = row.selected.length === 0;
            if (row.hasError) {
                valid = false;
            }
        }

        return valid;
    }

    /**
     * Persist the current subject language selections.
     */
    async function save(): Promise<void> {
        const student = activeStudent.value;

        if (isSaving.value || student === null || student.updateLanguageEndpoint === null) {
            return;
        }

        if (!isValid()) {
            return;
        }

        isSaving.value = true;
        saveError.value = null;

        const subjects = subjectRows.value.map((row) => ({
            groupSubjectId: row.groupSubjectId,
            languageId: Number(row.selected[0].id),
        }));

        const toast = window.sbProps.toastActivate("Saving...");

        try {
            const response = await apiRequest<unknown, { subjects: typeof subjects }>({
                url: student.updateLanguageEndpoint,
                method: "PUT",
                body: { subjects },
            });

            if (!response.success) {
                const errorMessage = response.message ?? "Failed to save language selections.";
                saveError.value = errorMessage;
                toast.timeout({ text: errorMessage, isSuccess: false });
                return;
            }

            const updatedHl = buildUpdatedEntries(student.subjects.hl, 0);
            const updatedSl = buildUpdatedEntries(student.subjects.sl, student.subjects.hl.length);
            const updatedCore = buildUpdatedEntries(
                student.subjects.core,
                student.subjects.hl.length + student.subjects.sl.length,
            );

            handleStudentUpdated(student.id, {
                hl: updatedHl,
                sl: updatedSl,
                core: updatedCore,
            });

            toast.timeout({ text: "Languages successfully updated." });
            close();
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : "Failed to save language selections.";
            saveError.value = errorMessage;
            toast.timeout({ text: errorMessage, isSuccess: false });
        } finally {
            isSaving.value = false;
        }
    }

    return {
        isOpen,
        isSaving,
        saveError,
        activeStudent,
        searchLanguagesEndpoint,
        subjectRows,
        init,
        prompt,
        close,
        save,
    };
});
