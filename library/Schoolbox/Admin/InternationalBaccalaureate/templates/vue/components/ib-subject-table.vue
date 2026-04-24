<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import SearchSelectModal from "@schoolboxVue/components/searchSelect/modal/search-select-modal.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import type { IBLanguage, IBLanguageListResponse, IBSubject, IBSubjectLanguage } from "../../js/types/ibConfigurationTypes";

/**
 * A single row in the table
 */
type SubjectRow = {
    id: string;
    groupSubjectId: number;
    subject: string;
    level: string;
    languages: number[];
    languageNamesById: Record<number, string>;
    classCode: string;
    studentCount: number;
    isLanguageRow?: boolean;
    parentRowId?: string;
    tableType?: string;
};

/**
 * The payload sent to the server to save a subject's language configuration
 */
type SubjectSavePayload = {
    groupSubjectId: number;
    languageId: number;
    classCode: string;
};


const props = defineProps<{
    categoryName: string;
    categoryTitle: string;
    categoryId: number;
    defaultLanguage: IBLanguage | null;
    saveEndpoint: string;
    ibSubjects: IBSubject[];
    searchLanguagesEndpoint: string | null;
}>();

const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);
const subjectRows = window.Vue.ref<SubjectRow[]>([]);
const currentEditingRow = window.Vue.ref<string | null>(null);
const isLanguageModalOpen = window.Vue.ref<boolean>(false);
const modalSelectedLanguages = window.Vue.ref<SearchSelectItem[]>([]);
const modalSelectionSnapshot = window.Vue.ref<SearchSelectItem[]>([]); // to determine whether a row needs to change on modal close
const modalDefaultResults = window.Vue.ref<SearchSelectItem[]>([]);
const modalSearchResults = window.Vue.ref<SearchSelectItem[]>([]);
const cachedLanguageNames = window.Vue.ref<Record<number, string>>({});

const isCoreCategory = window.Vue.computed(() => {
    return props.categoryName.toLowerCase() === "core";
});

const canEditLanguages = window.Vue.computed<boolean>(() => Boolean(props.searchLanguagesEndpoint));

const defaultLanguageId = window.Vue.computed<number | null>(() => props.defaultLanguage?.id ?? null);

/**
 * Build the default language-name map used before the modal has loaded.
 * @returns A language-name lookup keyed by language id.
 */
function getDefaultLanguageNameMap(): Record<number, string> {
    if (!props.defaultLanguage) {
        return {};
    }
    return {
        [props.defaultLanguage.id]: props.defaultLanguage.name,
    };
}

/**
 * Build a language-name lookup from configured subject languages.
 * @param configurations The configured subject-language entries.
 * @returns A language-name lookup keyed by language id.
 */
function createLanguageNameMapFromSubjectLanguages(configurations: IBSubjectLanguage[]): Record<number, string> {
    return configurations.reduce<Record<number, string>>((languageNames, configuration) => {
        const languageName = configuration.languageName;

        if (typeof languageName === "string") {
            languageNames[configuration.languageId] = languageName;
        }

        return languageNames;
    }, {});
}

/**
 * Build a language-name lookup from search-select items.
 * @param items The selected or fetched language items.
 * @returns A language-name lookup keyed by language id.
 */
function createLanguageNameMapFromItems(items: SearchSelectItem[]): Record<number, string> {
    return items.reduce<Record<number, string>>((languageNames, item) => {
        languageNames[item.id] = item.name;
        return languageNames;
    }, {});
}

/**
 * Merge server-provided language names into the local cache.
 */
function refreshCachedLanguageNames(): void {
    const nextCache = { ...cachedLanguageNames.value, ...getDefaultLanguageNameMap() };
    props.ibSubjects.forEach((subject) => {
        Object.assign(nextCache, createLanguageNameMapFromSubjectLanguages(subject.languages));
    });
    cachedLanguageNames.value = nextCache;
}

/**
 * Merge search-select items into the local language-name cache.
 * @param items The selected or fetched language items.
 */
function cacheSelectedLanguages(items: SearchSelectItem[]): void {
    cachedLanguageNames.value = {
        ...cachedLanguageNames.value,
        ...createLanguageNameMapFromItems(items),
    };
}

/**
 * Get the default language selection for a subject row.
 * @returns The default language ids for a subject row.
 */
function getDefaultLanguages(): number[] {
    return defaultLanguageId.value ? [defaultLanguageId.value] : [];
}

/**
 * Build the table rows for a subject and its configured languages.
 * @param subjectData The subject configuration being transformed into table rows.
 * @param index The position of the subject in the current collection.
 * @param tableType The core table grouping label when applicable.
 * @returns The main subject row and any child language rows.
 */
function buildRowsForSubject(subjectData: IBSubject, index: number, tableType?: string): SubjectRow[] {
    const configurations = subjectData.languages as IBSubjectLanguage[];
    const rowId = tableType ? `${tableType}_${subjectData.id}_${index}` : `group_subject_${subjectData.id}`;
    const selectedLanguages = configurations.map((configuration) => configuration.languageId);
    const configuredLanguageNames = createLanguageNameMapFromSubjectLanguages(configurations);
    const mainRow: SubjectRow = {
        id: rowId,
        groupSubjectId: subjectData.id,
        subject: subjectData.subject,
        level: subjectData.level,
        languages: selectedLanguages.length > 0 ? selectedLanguages : getDefaultLanguages(),
        languageNamesById: selectedLanguages.length > 0 ? configuredLanguageNames : getDefaultLanguageNameMap(),
        classCode: configurations.length === 1 ? configurations[0].classCode : "",
        studentCount: 0,
        tableType,
    };

    if (configurations.length <= 1) {
        return [mainRow];
    }

    const languageRows = configurations.map((configuration, configIndex) => {
        const languageName = configuration.languageName;

        return {
            id: `${rowId}_lang_${configIndex}`,
            groupSubjectId: subjectData.id,
            subject: subjectData.subject,
            level: subjectData.level,
            languages: [configuration.languageId],
            languageNamesById:
                typeof languageName === "string"
                    ? {
                          [configuration.languageId]: languageName,
                      }
                    : {},
            classCode: configuration.classCode,
            studentCount: 0,
            isLanguageRow: true,
            parentRowId: rowId,
            tableType,
        };
    });

    return [mainRow, ...languageRows];
}

/**
 * Initialise the subject rows for the current category.
 */
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
        refreshCachedLanguageNames();
        initializeSubjectRows();
    },
    { deep: true, immediate: true },
);

/**
 * Open the language selection modal for a row.
 * @param rowId The row identifier to edit.
 */
function openLanguageModal(rowId: string): void {
    if (!props.searchLanguagesEndpoint) {
        return;
    }

    const row = subjectRows.value.find((candidate) => candidate.id === rowId);
    const selectedLanguageIds = row?.languages.length ? row.languages : getDefaultLanguages();

    currentEditingRow.value = rowId;
    modalSelectedLanguages.value = selectedLanguageIds.map((languageId) => ({
        id: languageId,
        name: getLanguageName(languageId, row),
    }));
    modalSelectionSnapshot.value = [...modalSelectedLanguages.value];
    isLanguageModalOpen.value = true;
}

/**
 * Clear the current language selection modal context.
 */
function closeLanguageModal(): void {
    currentEditingRow.value = null;
    modalSelectedLanguages.value = [];
    modalSelectionSnapshot.value = [];
}

/**
 * Compare modal selections by language id to determine whether the subject row should be updated on modal close.
 * @param previousSelection The original modal selection.
 * @param nextSelection The current modal selection.
 * @returns Whether the selection has changed.
 */
function hasLanguageSelectionChanged(
    previousSelection: SearchSelectItem[],
    nextSelection: SearchSelectItem[],
): boolean {
    const previousIds = previousSelection.map((item) => item.id).sort((left, right) => left - right);
    const nextIds = nextSelection.map((item) => item.id).sort((left, right) => left - right);
    if (previousIds.length !== nextIds.length) {
        return true;
    }
    return previousIds.some((languageId, index) => languageId !== nextIds[index]);
}

window.Vue.watch(isLanguageModalOpen, (isOpen, wasOpen) => {
    if (isOpen || !wasOpen || !currentEditingRow.value) {
        return;
    }

    if (hasLanguageSelectionChanged(modalSelectionSnapshot.value, modalSelectedLanguages.value)) {
        applySelectedLanguages(modalSelectedLanguages.value);
    }

    closeLanguageModal();
});

/**
 * Update the selected languages for the row being edited.
 * @param selectedItems The languages selected in the modal.
 */
function applySelectedLanguages(selectedItems: SearchSelectItem[]): void {
    if (!currentEditingRow.value) return;

    cacheSelectedLanguages(selectedItems);

    const selectedLanguages = selectedItems.map((item) => item.id);
    const selectedLanguageNames = createLanguageNameMapFromItems(selectedItems);

    const rowIndex = subjectRows.value.findIndex((row) => row.id === currentEditingRow.value);
    if (rowIndex === -1) return;

    const mainRow = subjectRows.value[rowIndex];

    subjectRows.value = subjectRows.value.filter((row) => row.parentRowId !== mainRow.id);

    if (selectedLanguages.length === 0) {
        mainRow.languages = [];
        mainRow.languageNamesById = {};
        mainRow.classCode = "";
    } else if (selectedLanguages.length === 1) {
        mainRow.languages = selectedLanguages;
        mainRow.languageNamesById = selectedLanguageNames;
    } else {
        mainRow.languages = selectedLanguages;
        mainRow.languageNamesById = selectedLanguageNames;
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
            languageNamesById: selectedLanguageNames[language]
                ? {
                      [language]: selectedLanguageNames[language],
                  }
                : {},
            classCode: classCodeByLanguage.get(language) ?? "",
            studentCount: 0,
            isLanguageRow: true,
            parentRowId: mainRow.id,
            tableType: mainRow.tableType,
        }));

        subjectRows.value.splice(rowIndex + 1, 0, ...newLanguageRows);
    }
}

/**
 * Update a field on a subject row.
 * @param rowId The row identifier to update.
 * @param field The row field being changed.
 * @param value The updated value for the field.
 */
function updateRowField(rowId: string, field: keyof SubjectRow, value: string | number): void {
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (row && field in row) {
        (row as Record<string, unknown>)[field] = value;

        if (field === "classCode") {
            updateStudentCount(rowId);
        }
    }
}

/**
 * Get the language display text for a subject row.
 * @param row The subject row being displayed.
 * @returns The label shown in the languages input.
 */
function getLanguageDisplayText(row: SubjectRow): string {
    if (row.languages.length === 0) {
        return "Select languages";
    } else if (row.languages.length === 1) {
        return getLanguageName(row.languages[0], row);
    } else {
        return `${row.languages.length} selected`;
    }
}

/**
 * Get the display name for a language.
 * @param languageId The language identifier.
 * @param row The subject row being displayed.
 * @returns The display name for the language.
 */
function getLanguageName(languageId: number, row?: SubjectRow): string {
    return row?.languageNamesById[languageId] ?? cachedLanguageNames.value[languageId] ?? String(languageId);
}

/**
 * Update the modal results, either the search results or the default results.
 * @param json The returned json.
 * @param results The results collection to update.
 * @param isMoreResults Whether these results should be appended.
 */
function updateModalResults(json: unknown, results: typeof modalDefaultResults, isMoreResults: boolean): void {
    if (typeof json !== "object" || json === null || !("data" in json) || !Array.isArray(json.data)) {
        return;
    }

    const responseData = json as IBLanguageListResponse;
    const formattedResults = responseData.data.map((language) => ({
        id: language.id,
        name: language.name,
    }));

    cacheSelectedLanguages(formattedResults);

    if (isMoreResults) {
        results.value = [...results.value, ...formattedResults];
        return;
    }

    results.value = [...formattedResults];
}

/**
 * Handle the initial modal search/default-results response.
 * @param json The returned json.
 * @param isActive Whether the search is active.
 */
function onModalSearchResponse(json: unknown, isActive: boolean): void {
    updateModalResults(json, isActive ? modalSearchResults : modalDefaultResults, false);
}

/**
 * Handle a paginated modal response.
 * @param json The returned json.
 * @param isActive Whether the search is active.
 */
function onModalNextSearchResponse(json: unknown, isActive: boolean): void {
    updateModalResults(json, isActive ? modalSearchResults : modalDefaultResults, true);
}

/**
 * Update the displayed student count for a row.
 * @param rowId The row identifier to update.
 */
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

/**
 * Build the payload used to save the subject configuration.
 * @returns The subject configuration payload for the save request.
 */
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

/**
 * Save the subject configuration to the server.
 */
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
    <div>
        <div v-if="subjectRows.length === 0" class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            No subjects configured yet for {{ categoryTitle }}. Use the table below to add subjects.
        </div>

        <div v-else>
            <template v-if="isCoreCategory">
                <h3>Diploma Program Year 2 [Diploma]</h3>
                <table class="tiger-stripe">
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
                                            :disabled="!canEditLanguages"
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
                <table class="tiger-stripe">
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
                                            :disabled="!canEditLanguages"
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
                <table class="tiger-stripe">
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
                                            :disabled="!canEditLanguages"
                                            :data-test="`ib-language-edit-${row.id}`"
                                            @click="openLanguageModal(row.id)"
                                        >
                                            <sbx-icon name="modify"></sbx-icon>
                                        </button>
                                    </div>
                                    <span v-else class="language-display">
                                        {{ getLanguageName(row.languages[0], row) }}
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

        <search-select-modal
            v-model:selected="modalSelectedLanguages"
            v-model:is-open="isLanguageModalOpen"
            v-model:results="modalSearchResults"
            :default-results="modalDefaultResults"
            show-selected
            label="Search languages"
            label-id="ib-language-modal-search"
            placeholder="Search for languages..."
            title="Select Languages"
            :endpoint="props.searchLanguagesEndpoint ?? ''"
            :manage-search-response="true"
            :allow-partial-matches="true"
            @search-response="onModalSearchResponse"
            @next-search-response="onModalNextSearchResponse"
        />
    </div>
</template>
