<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import SearchSelectModal from "@schoolboxVue/components/searchSelect/modal/search-select-modal.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import type {
    IBClassCode,
    IBLanguage,
    IBLanguageListResponse,
    IBSubject,
    IBSubjectLanguage,
} from "../../js/types/ibConfigurationTypes";

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
    searchClassCodesEndpoint: string | null;
    studentCountEndpoint: string | null;
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

const isClassCodeModalOpen = window.Vue.ref<boolean>(false);
const classCodeEditingRowId = window.Vue.ref<string | null>(null);
const classCodeModalSelected = window.Vue.ref<SearchSelectItem[]>([]);
const classCodeSearchResults = window.Vue.ref<SearchSelectItem[]>([]);
const classCodeDefaultResults = window.Vue.ref<SearchSelectItem[]>([]);

const isCoreCategory = window.Vue.computed(() => {
    return props.categoryName.toLowerCase() === "core";
});

const canEditLanguages = window.Vue.computed<boolean>(() => Boolean(props.searchLanguagesEndpoint));

const canEditClassCodes = window.Vue.computed<boolean>(() => Boolean(props.searchClassCodesEndpoint));

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
    const configurations = subjectData.languages;
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
        void refreshStudentCounts();
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
 * Get the display text for the class code field.
 * @param row The subject row being displayed.
 * @returns Comma-joined class codes or empty string.
 */
function getClassCodeDisplayText(row: SubjectRow): string {
    if (!row.classCode.trim()) return "";
    return row.classCode
        .split(",")
        .map((c) => c.trim())
        .filter(Boolean)
        .join(", ");
}

/**
 * Open the class code selection modal for a row.
 * @param rowId The row identifier to edit.
 */
function openClassCodeModal(rowId: string): void {
    if (!props.searchClassCodesEndpoint) return;
    const row = subjectRows.value.find((r) => r.id === rowId);
    if (!row) return;
    classCodeEditingRowId.value = rowId;
    // Class codes are strings; cast is safe because SearchSelectModal only does === comparisons on id.
    classCodeModalSelected.value = row.classCode
        .split(",")
        .map((c) => c.trim())
        .filter(Boolean)
        .map((code) => ({ id: code, name: code })) as unknown as SearchSelectItem[];
    isClassCodeModalOpen.value = true;
}

/**
 * Update class code modal results from search or default response.
 * @param json The API response.
 * @param results The results ref to update.
 * @param isMoreResults Whether to append.
 */
function updateClassCodeModalResults(
    json: unknown,
    results: typeof classCodeDefaultResults,
    isMoreResults: boolean,
): void {
    if (typeof json !== "object" || json === null || !("data" in json) || !Array.isArray(json.data)) {
        return;
    }

    // Class codes are strings; cast is safe because SearchSelectModal only does === comparisons on id.
    const formattedResults = (json.data as IBClassCode[]).map((item) => ({
        id: item.code,
        name: `${item.name} (${item.code})`,
    })) as unknown as SearchSelectItem[];

    if (isMoreResults) {
        results.value = [...results.value, ...formattedResults];
        return;
    }

    results.value = [...formattedResults];
}

/**
 * Handle the initial class code search/default-results response.
 * @param json The returned json.
 * @param isActive Whether the search is active.
 */
function onClassCodeModalSearchResponse(json: unknown, isActive: boolean): void {
    updateClassCodeModalResults(json, isActive ? classCodeSearchResults : classCodeDefaultResults, false);
}

/**
 * Handle a paginated class code modal response.
 * @param json The returned json.
 * @param isActive Whether the search is active.
 */
function onClassCodeModalNextSearchResponse(json: unknown, isActive: boolean): void {
    updateClassCodeModalResults(json, isActive ? classCodeSearchResults : classCodeDefaultResults, true);
}

window.Vue.watch(isClassCodeModalOpen, (isOpen, wasOpen) => {
    if (isOpen || !wasOpen || !classCodeEditingRowId.value) {
        return;
    }

    const row = subjectRows.value.find((r) => r.id === classCodeEditingRowId.value);
    if (row) {
        row.classCode = classCodeModalSelected.value.map((item) => String(item.id)).join(", ");
    }

    classCodeEditingRowId.value = null;
});

/**
 * Refresh student counts for all rows in the current category after a save.
 */
async function refreshStudentCounts(): Promise<void> {
    if (!props.studentCountEndpoint || isCoreCategory.value) return;

    const groupSubjectIds = [...new Set(subjectRows.value.map((r) => r.groupSubjectId))];

    if (groupSubjectIds.length === 0) return;

    const params = groupSubjectIds.map((id) => `groupSubjectId[]=${id}`).join("&");
    const response = await apiRequest<
        Array<{ groupSubjectId: number; total: number; byLanguage: Array<{ languageId: number; count: number }> }>
    >({
        url: `${props.studentCountEndpoint}?${params}`,
        method: "GET",
    });

    if (!response.success || !Array.isArray(response.data)) return;

    for (const entry of response.data) {
        for (const row of subjectRows.value) {
            if (row.groupSubjectId !== entry.groupSubjectId) continue;

            if (!row.isLanguageRow) {
                row.studentCount = entry.total;
            } else {
                const langEntry = entry.byLanguage.find((l) => l.languageId === row.languages[0]);
                row.studentCount = langEntry?.count ?? 0;
            }
        }
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
        await refreshStudentCounts();
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
                                    <div v-if="row.isLanguageRow || row.languages.length <= 1" class="l-flex l-gap-xs">
                                        <input
                                            type="text"
                                            :value="getClassCodeDisplayText(row)"
                                            class="table-input l-flex__item--1"
                                            placeholder="Add class codes"
                                            readonly
                                        />
                                        <button
                                            type="button"
                                            class="sbx-icon-only-button f-no-margins"
                                            aria-label="Edit class codes"
                                            title="Edit class codes"
                                            :disabled="!canEditClassCodes"
                                            :data-test="`ib-class-code-edit-${row.id}`"
                                            @click="openClassCodeModal(row.id)"
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
            label="Search"
            label-id="ib-language-modal-search"
            placeholder="Search for languages..."
            title="Select Languages"
            :endpoint="props.searchLanguagesEndpoint ?? ''"
            :manage-search-response="true"
            :allow-partial-matches="true"
            @search-response="onModalSearchResponse"
            @next-search-response="onModalNextSearchResponse"
        />

        <search-select-modal
            v-model:selected="classCodeModalSelected"
            v-model:is-open="isClassCodeModalOpen"
            v-model:results="classCodeSearchResults"
            :default-results="classCodeDefaultResults"
            show-selected
            label="Search"
            label-id="ib-class-code-modal-search"
            placeholder="Search for class codes..."
            title="Select Class Codes"
            :endpoint="props.searchClassCodesEndpoint ?? ''"
            :manage-search-response="true"
            :allow-partial-matches="true"
            @search-response="onClassCodeModalSearchResponse"
            @next-search-response="onClassCodeModalNextSearchResponse"
        />
    </div>
</template>
