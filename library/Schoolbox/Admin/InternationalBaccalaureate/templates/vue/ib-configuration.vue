<script setup lang="ts">
/**
 * IB DP Subject Configuration page. This page displays configuration options
 * organized by IB subject groups as tabs. Each category can have specific
 * configuration options that are loaded dynamically.
 */

import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { IBCategory, IBConfiguration } from "../js/types/ibConfigurationTypes";

interface Props {
    categories: IBCategory[]; // The IB subject group categories to display
    configurationEndpoint: string; // The endpoint to fetch configuration data from
    saveEndpoint: string; // The endpoint to save configuration data to
}
const props = defineProps<Props>();

// Current category state
const currentCategory = window.Vue.ref<IBCategory>(props.categories[0]);
const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
const currentConfigurations = window.Vue.ref<IBConfiguration[]>([]);
const categoryParamName = "category";
const isSaving = window.Vue.ref<boolean>(false);
const saveError = window.Vue.ref<string | null>(null);

// Form change tracking for save button
const hasFormChanges = window.Vue.ref<boolean>(false);
const originalValues = window.Vue.ref<{ [key: string]: string }>({});
const currentValues = window.Vue.ref<{ [key: string]: string }>({});
const saveContainer = window.Vue.ref(null);
const isAtBottomOfPage = window.Vue.ref<boolean>(false);

// Computed property to show save button when there are changes or at bottom of page
const showSaveButton = window.Vue.computed(() => hasFormChanges.value || isAtBottomOfPage.value);

// Computed property to check if current tab is general config
const isGeneralConfigTab = window.Vue.computed(
    () =>
        currentCategory.value.name.toLowerCase().includes("general") ||
        currentCategory.value.name.toLowerCase().includes("config"),
);

// Define IB subjects mapped by category name (tab name)
const ibSubjectsByCategory: Record<string, Array<{ subject: string; level: string }>> = {
    // Multiple possible variations of Studies in Language and Literature
    "studies-in-language-and-literature": [
        { subject: "Language A: literature", level: "SL" },
        { subject: "Language A: literature", level: "HL" },
        { subject: "Language A: language and literature", level: "SL" },
        { subject: "Language A: language and literature", level: "HL" },
        { subject: "Literature and performance", level: "SL" },
    ],
    "studies in language and literature": [
        { subject: "Language A: literature", level: "SL" },
        { subject: "Language A: literature", level: "HL" },
        { subject: "Language A: language and literature", level: "SL" },
        { subject: "Language A: language and literature", level: "HL" },
        { subject: "Literature and performance", level: "SL" },
    ],
    "language and literature": [
        { subject: "Language A: literature", level: "SL" },
        { subject: "Language A: literature", level: "HL" },
        { subject: "Language A: language and literature", level: "SL" },
        { subject: "Language A: language and literature", level: "HL" },
        { subject: "Literature and performance", level: "SL" },
    ],
    "language-acquisition": [
        { subject: "Classical languages", level: "SL" },
        { subject: "Classical languages", level: "HL" },
        { subject: "Language Ab initio", level: "SL" },
        { subject: "Language B", level: "SL" },
        { subject: "Language B", level: "HL" },
    ],
    "language acquisition": [
        { subject: "Classical languages", level: "SL" },
        { subject: "Classical languages", level: "HL" },
        { subject: "Language Ab initio", level: "SL" },
        { subject: "Language B", level: "SL" },
        { subject: "Language B", level: "HL" },
    ],
    "individuals-and-societies": [
        { subject: "Business management", level: "SL" },
        { subject: "Business management", level: "HL" },
        { subject: "Digital society", level: "SL" },
        { subject: "Digital society", level: "HL" },
        { subject: "Economics", level: "SL" },
        { subject: "Economics", level: "HL" },
        { subject: "Geography", level: "SL" },
        { subject: "Geography", level: "HL" },
        { subject: "Global politics", level: "SL" },
        { subject: "Global politics", level: "HL" },
        { subject: "History", level: "SL" },
        { subject: "History of Africa and the Middle East", level: "HL" },
        { subject: "History of the Americas", level: "HL" },
        { subject: "History of Asia and Oceania", level: "HL" },
        { subject: "History of Europe", level: "HL" },
        { subject: "Philosophy", level: "SL" },
        { subject: "Philosophy", level: "HL" },
        { subject: "Psychology", level: "SL" },
        { subject: "Psychology", level: "HL" },
        { subject: "Social and cultural anthropology", level: "SL" },
        { subject: "Social and cultural anthropology", level: "HL" },
        { subject: "World religions", level: "SL" },
    ],
    "individuals and societies": [
        { subject: "Business management", level: "SL" },
        { subject: "Business management", level: "HL" },
        { subject: "Digital society", level: "SL" },
        { subject: "Digital society", level: "HL" },
        { subject: "Economics", level: "SL" },
        { subject: "Economics", level: "HL" },
        { subject: "Geography", level: "SL" },
        { subject: "Geography", level: "HL" },
        { subject: "Global politics", level: "SL" },
        { subject: "Global politics", level: "HL" },
        { subject: "History", level: "SL" },
        { subject: "History of Africa and the Middle East", level: "HL" },
        { subject: "History of the Americas", level: "HL" },
        { subject: "History of Asia and Oceania", level: "HL" },
        { subject: "History of Europe", level: "HL" },
        { subject: "Philosophy", level: "SL" },
        { subject: "Philosophy", level: "HL" },
        { subject: "Psychology", level: "SL" },
        { subject: "Psychology", level: "HL" },
        { subject: "Social and cultural anthropology", level: "SL" },
        { subject: "Social and cultural anthropology", level: "HL" },
        { subject: "World religions", level: "SL" },
    ],
    sciences: [
        { subject: "Biology", level: "SL" },
        { subject: "Biology", level: "HL" },
        { subject: "Chemistry", level: "SL" },
        { subject: "Chemistry", level: "HL" },
        { subject: "Computer science", level: "SL" },
        { subject: "Computer science", level: "HL" },
        { subject: "Design technology", level: "SL" },
        { subject: "Design technology", level: "HL" },
        { subject: "Environmental systems and societies", level: "SL" },
        { subject: "Environmental systems and societies", level: "HL" },
        { subject: "Physics", level: "SL" },
        { subject: "Physics", level: "HL" },
        { subject: "Sports, exercise and health science", level: "SL" },
        { subject: "Sports, exercise and health science", level: "HL" },
    ],
    mathematics: [
        { subject: "Analysis and approaches", level: "SL" },
        { subject: "Analysis and approaches", level: "HL" },
        { subject: "Applications and interpretation", level: "SL" },
        { subject: "Applications and interpretation", level: "HL" },
    ],
    "the-arts": [
        { subject: "Dance", level: "SL" },
        { subject: "Dance", level: "HL" },
        { subject: "Music", level: "SL" },
        { subject: "Music", level: "HL" },
        { subject: "Film", level: "SL" },
        { subject: "Film", level: "HL" },
        { subject: "Theatre", level: "SL" },
        { subject: "Theatre", level: "HL" },
        { subject: "Visual arts", level: "SL" },
        { subject: "Visual arts", level: "HL" },
    ],
    "the arts": [
        { subject: "Dance", level: "SL" },
        { subject: "Dance", level: "HL" },
        { subject: "Music", level: "SL" },
        { subject: "Music", level: "HL" },
        { subject: "Film", level: "SL" },
        { subject: "Film", level: "HL" },
        { subject: "Theatre", level: "SL" },
        { subject: "Theatre", level: "HL" },
        { subject: "Visual arts", level: "SL" },
        { subject: "Visual arts", level: "HL" },
    ],
    arts: [
        { subject: "Dance", level: "SL" },
        { subject: "Dance", level: "HL" },
        { subject: "Music", level: "SL" },
        { subject: "Music", level: "HL" },
        { subject: "Film", level: "SL" },
        { subject: "Film", level: "HL" },
        { subject: "Theatre", level: "SL" },
        { subject: "Theatre", level: "HL" },
        { subject: "Visual arts", level: "SL" },
        { subject: "Visual arts", level: "HL" },
    ],
};

// Computed property to get table rows for subject tabs
const tableRows = window.Vue.computed(() => {
    if (isGeneralConfigTab.value) {
        return [];
    }

    // If we have configurations, use those as the base
    if (currentConfigurations.value.length > 0) {
        return currentConfigurations.value;
    }

    // Get subjects for the current category by name
    const categoryName = currentCategory.value.name.toLowerCase();

    // Debug: Check what category names we're actually getting
    console.info("Current category name:", categoryName);
    console.info("Category title:", currentCategory.value.title);
    console.info("Available mapping keys:", Object.keys(ibSubjectsByCategory));

    const subjects = ibSubjectsByCategory[categoryName] || [];

    // Create table rows from IB subjects
    return subjects.map((subjectData, index) => ({
        id: index + 1, // Use numeric ID for compatibility
        key: `${categoryName}_subject_${index}`,
        categoryId: currentCategory.value.id,
        value: "",
        label: `${subjectData.subject} (${subjectData.level})`,
        type: "text",
        defaultSubject: subjectData.subject,
        defaultLevel: subjectData.level,
    }));
});

/**
 * Initialize intersection observer to track if we're at the bottom of the page
 */
function initIntersectionObserver(): void {
    const intersectionObserver = new IntersectionObserver((elements) => {
        isAtBottomOfPage.value = elements[0].intersectionRatio !== 0;
    });
    if (saveContainer.value) {
        intersectionObserver.observe(saveContainer.value);
    }
}

/**
 * Initialize form tracking after configurations are loaded
 */
function initFormTracking(): void {
    // Reset form change tracking
    hasFormChanges.value = false;
    originalValues.value = {};
    currentValues.value = {};

    // Store initial values
    currentConfigurations.value.forEach((config) => {
        const key = config.key;
        originalValues.value[key] = config.value || "";
        currentValues.value[key] = config.value || "";

        // For subject tabs, initialize table field values
        if (!isGeneralConfigTab.value) {
            originalValues.value[key + "_subject"] = "";
            originalValues.value[key + "_level"] = "SL";
            originalValues.value[key + "_languages"] = "";
            originalValues.value[key + "_class_code"] = "";
            originalValues.value[key + "_student_count"] = "0";

            currentValues.value[key + "_subject"] = "";
            currentValues.value[key + "_level"] = "SL";
            currentValues.value[key + "_languages"] = "";
            currentValues.value[key + "_class_code"] = "";
            currentValues.value[key + "_student_count"] = "0";
        }
    });
}

/**
 * Handle input changes and track them for save button display
 * @param config - The configuration object being changed
 * @param newValue - The new value for the configuration
 */
function onInputChange(config: IBConfiguration, newValue: string): void {
    currentValues.value[config.key] = newValue;

    // Check if any values have changed from original
    hasFormChanges.value = Object.keys(originalValues.value).some(
        (key) => originalValues.value[key] !== currentValues.value[key],
    );
}

/**
 * Save changed configurations for the current tab only
 */
async function saveCurrentTabChanges(): Promise<void> {
    if (!hasFormChanges.value || !props.saveEndpoint) {
        return;
    }

    try {
        isSaving.value = true;
        saveError.value = null;

        // Find changed configurations for the current category only
        const changedConfigs = currentConfigurations.value.filter(
            (config) =>
                config.categoryId === currentCategory.value.id &&
                originalValues.value[config.key] !== currentValues.value[config.key],
        );

        if (changedConfigs.length === 0) {
            hasFormChanges.value = false;
            return;
        }

        // Save each changed configuration for current tab
        for (const config of changedConfigs) {
            const newValue = currentValues.value[config.key];

            const response = await fetch(props.saveEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    id: config.id,
                    key: config.key,
                    value: newValue,
                    categoryId: config.categoryId,
                }),
            });

            if (!response.ok) {
                const errorMessage = await extractErrorMessage(response);
                throw new Error(errorMessage);
            }

            // Update the local state
            config.value = newValue;
            originalValues.value[config.key] = newValue;
        }

        // Check if there are still changes in other tabs
        const hasOtherTabChanges = Object.keys(originalValues.value).some((key) => {
            const config = currentConfigurations.value.find((c) => c.key === key);
            return (
                config &&
                config.categoryId !== currentCategory.value.id &&
                originalValues.value[key] !== currentValues.value[key]
            );
        });

        // Reset form change tracking if no other tabs have changes
        if (!hasOtherTabChanges) {
            hasFormChanges.value = false;
        }

        // Show success message
        const toast = window.sbProps.toastActivate(`${currentCategory.value.title} configuration saved successfully`);
        toast.timeout({ text: "Saved" });
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            saveError.value = error.message;
        }
        const toast = window.sbProps.toastActivate("Save failed");
        toast.timeout({ text: "Save failed", isSuccess: false });
        console.error(error);
    } finally {
        isSaving.value = false;
    }
}

/**
 * Load configuration data for the current category from the API.
 */
async function loadCategoryConfigurations(): Promise<void> {
    if (!props.configurationEndpoint) {
        return;
    }

    try {
        isLoading.value = true;
        loadError.value = null;

        const url = props.configurationEndpoint.replace("{categoryId}", currentCategory.value.id.toString());
        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });

        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }

        const responseJson = (await response.json()) as IBConfiguration[];
        if (responseJson === null || !Array.isArray(responseJson)) {
            throw new Error("Unexpected response format");
        }

        currentConfigurations.value = responseJson;
        initFormTracking();
    } catch (error) {
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error(error);
    } finally {
        isLoading.value = false;
    }
}

/**
 * Handle category selection, update URL and load configurations.
 * @param category - The selected IB category/tab
 */
function onCategorySelected(category: IBCategory): void {
    currentCategory.value = category;
    // Update URL without adding to browser history
    const url = new URL(window.location.href);
    url.searchParams.set(categoryParamName, category.name);
    window.history.replaceState(null, "", url.toString());
    void loadCategoryConfigurations();
}

/**
 * Initialize the component, check URL for category parameter.
 */
window.Vue.onMounted((): void => {
    // Check if there's a category in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const categoryParam = urlParams.get(categoryParamName);
    // Update the current category if available
    if (categoryParam) {
        const foundCategory = props.categories.find((cat) => cat.name === categoryParam);
        if (foundCategory) {
            currentCategory.value = foundCategory;
        }
    }
    void loadCategoryConfigurations();

    // Initialize intersection observer for bottom drawer
    void window.Vue.nextTick(() => {
        initIntersectionObserver();
    });
});
</script>

<template>
    <div class="small-12 island">
        <dl ref="tabsEl" class="tabs" data-test="ib-configuration-tabs">
            <dd
                v-for="category in props.categories"
                :key="category.id"
                :class="{ active: currentCategory.name === category.name }"
            >
                <a :data-test="`ib-configuration-tab-${category.name}`" @click="onCategorySelected(category)">
                    {{ category.title }}
                </a>
            </dd>
        </dl>

        <loading-table v-if="isLoading" />

        <section v-else-if="loadError" class="content">
            <data-load-error :data-title="currentCategory.title" :further-info="loadError" />
        </section>

        <section v-else class="content tabs-content">
            <div class="content active">
                <h2>{{ currentCategory.title }}</h2>

                <!-- General Configuration Tab - Form Fields -->
                <div v-if="isGeneralConfigTab">
                    <div v-if="currentConfigurations.length === 0" class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        No configurations available for {{ currentCategory.title }}.
                    </div>

                    <form class="c-island" @submit.prevent="void 0">
                        <div v-for="config in currentConfigurations" :key="config.id" class="form-row">
                            <label :for="`config-${config.key}`" class="form-label">
                                {{ config.label }}
                                <span v-if="config.description" :title="config.description" class="tooltip-icon">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </label>
                            <div class="form-field">
                                <input
                                    :id="`config-${config.key}`"
                                    :type="config.type"
                                    :value="currentValues[config.key] || config.value"
                                    :data-test="`ib-configuration-input-${config.key}`"
                                    class="form-control"
                                    :disabled="isSaving"
                                    @input="(event) => onInputChange(config, (event.target as HTMLInputElement).value)"
                                />
                                <small v-if="config.description" class="form-help">
                                    {{ config.description }}
                                </small>
                            </div>
                        </div>

                        <div v-if="saveError" class="alert alert-box alert">
                            <i class="fa fa-exclamation-triangle"></i>
                            {{ saveError }}
                        </div>
                    </form>
                </div>

                <!-- Subject Tabs - Table Layout -->
                <div v-else class="c-island">
                    <div v-if="currentConfigurations.length === 0" class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        No subjects configured yet for {{ currentCategory.title }}. Use the table below to add subjects.
                    </div>

                    <div>
                        <table class="subject-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Level</th>
                                    <th>Available Languages</th>
                                    <th>Class Code Mapping</th>
                                    <th>Current Student Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="config in tableRows" :key="config.id">
                                    <td>
                                        <input
                                            :value="
                                                currentValues[config.key + '_subject'] ||
                                                (config as any).defaultSubject ||
                                                ''
                                            "
                                            class="table-input"
                                            placeholder="Subject name"
                                            :disabled="isSaving"
                                            @input="
                                                (event) =>
                                                    onInputChange(
                                                        { ...config, key: config.key + '_subject' },
                                                        (event.target as HTMLInputElement).value,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td>
                                        <input
                                            :value="
                                                currentValues[config.key + '_level'] ||
                                                (config as any).defaultLevel ||
                                                ''
                                            "
                                            class="table-input"
                                            placeholder="SL/HL"
                                            :disabled="isSaving"
                                            @input="
                                                (event) =>
                                                    onInputChange(
                                                        { ...config, key: config.key + '_level' },
                                                        (event.target as HTMLInputElement).value,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td>
                                        <input
                                            :value="currentValues[config.key + '_languages'] || ''"
                                            class="table-input"
                                            placeholder="e.g., English, Spanish"
                                            :disabled="isSaving"
                                            @input="
                                                (event) =>
                                                    onInputChange(
                                                        { ...config, key: config.key + '_languages' },
                                                        (event.target as HTMLInputElement).value,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td>
                                        <input
                                            :value="currentValues[config.key + '_class_code'] || ''"
                                            class="table-input"
                                            placeholder="Class code"
                                            :disabled="isSaving"
                                            @input="
                                                (event) =>
                                                    onInputChange(
                                                        { ...config, key: config.key + '_class_code' },
                                                        (event.target as HTMLInputElement).value,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td>
                                        <input
                                            :value="currentValues[config.key + '_student_count'] || '0'"
                                            class="table-input"
                                            type="number"
                                            min="0"
                                            :disabled="isSaving"
                                            @input="
                                                (event) =>
                                                    onInputChange(
                                                        { ...config, key: config.key + '_student_count' },
                                                        (event.target as HTMLInputElement).value,
                                                    )
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="saveError" class="alert alert-box alert">
                            <i class="fa fa-exclamation-triangle"></i>
                            {{ saveError }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div ref="saveContainer" class="save-container">
            <bottom-drawer :show-drawer="showSaveButton">
                <input
                    type="submit"
                    value="Save"
                    :disabled="isSaving || !hasFormChanges"
                    @click="saveCurrentTabChanges"
                />
            </bottom-drawer>
        </div>
    </div>
</template>

<style scoped>
.form-row {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.tooltip-icon {
    margin-left: 0.5rem;
    color: #666;
    cursor: help;
}

.tooltip-icon:hover {
    color: #333;
}

.form-field {
    position: relative;
}

.form-control {
    width: 100%;
    max-width: 400px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.form-control:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    color: #666;
    font-size: 0.875rem;
    line-height: 1.4;
}

.alert-box {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 4px;
}

.alert-box.alert {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.save-container {
    min-height: 30px;
}

/* Subject table styles */
.subject-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.9rem;
}

.subject-table th {
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    padding: 0.75rem;
    text-align: left;
    font-weight: bold;
    color: #333;
}

.subject-table td {
    border: 1px solid #ddd;
    padding: 0.5rem;
    vertical-align: top;
}

.subject-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.subject-table tbody tr:hover {
    background-color: #f1f1f1;
}

.table-input {
    width: 100%;
    padding: 0.25rem 0.5rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 0.875rem;
    box-sizing: border-box;
}

.table-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 1px rgba(0, 124, 186, 0.2);
}

.table-input:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.table-select {
    width: 100%;
    padding: 0.25rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 0.875rem;
    box-sizing: border-box;
}

.table-select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 1px rgba(0, 124, 186, 0.2);
}

.table-select:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}
</style>
