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
import IBGeneralConfig from "./components/IBGeneralConfig.vue";
import IBSubjectTable from "./components/IBSubjectTable.vue";
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

// Computed property to check if current tab is general config
const isGeneralConfigTab = window.Vue.computed(
    () =>
        currentCategory.value.name.toLowerCase().includes("general") ||
        currentCategory.value.name.toLowerCase().includes("config"),
);

// Define IB subjects mapped by category name (tab name)
const ibSubjectsByCategory: Record<string, Array<{ subject: string; level: string }>> = {
    "studies-in-language-and-literature": [
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
    core: [
        { subject: "Theory of Knowledge (TOK)", level: "" },
        { subject: "Extended Essay (EE)", level: "" },
        { subject: "Creativity, Activity, Service (CAS)", level: "" },
    ],
};

// Computed property to get subjects for current category
const currentSubjects = window.Vue.computed(() => {
    const categoryName = currentCategory.value.name.toLowerCase();
    return ibSubjectsByCategory[categoryName] || [];
});

/**
 * Handles configuration changes for general tab
 * @param config - The configuration object that changed
 * @param newValue - The new value for the configuration
 */
function onConfigChange(config: IBConfiguration, newValue: string): void {
    // Handle configuration changes for general tab
    // This can be extended if needed for form tracking
    // eslint-disable-next-line no-console
    console.info("Config changed:", config.key, newValue);
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

                <!-- General Configuration Tab -->
                <IBGeneralConfig
                    v-if="isGeneralConfigTab"
                    :configurations="currentConfigurations"
                    :is-loading="isLoading"
                    :save-error="null"
                    :save-endpoint="props.saveEndpoint"
                    :category-title="currentCategory.title"
                    @config-change="onConfigChange"
                />

                <!-- Subject Configuration Tabs -->
                <IBSubjectTable
                    v-else
                    :category-name="currentCategory.name"
                    :category-title="currentCategory.title"
                    :category-id="currentCategory.id"
                    :save-endpoint="props.saveEndpoint"
                    :ib-subjects="currentSubjects"
                />
            </div>
        </section>
    </div>
</template>
