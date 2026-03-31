<script setup lang="ts">
/**
 * IB DP Subject Configuration page. This page displays configuration options
 * organized by IB subject groups as tabs. Each category can have specific
 * configuration options that are loaded dynamically.
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import IBGeneralConfig from "./components/ib-general-config.vue";
import IBSubjectTable from "./components/ib-subject-table.vue";
import type { IBCategory, IBConfiguration } from "../js/types/ibConfigurationTypes";

interface Props {
    categories: IBCategory[]; // The IB subject group categories to display
    configurationEndpoint: string; // The endpoint to fetch configuration data from
    saveEndpoint: string; // The endpoint to save configuration data to
    subjectsEndpoint: string; // The endpoint to fetch subjects for a category
}
const props = defineProps<Props>();

// Current category state
const currentCategory = window.Vue.ref<IBCategory>(props.categories[0]);
const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
const currentConfigurations = window.Vue.ref<IBConfiguration[]>([]);
const currentSubjectsFromAPI = window.Vue.ref<Array<{ subject: string; level: string }>>([]);
const categoryParamName = "category";

// Computed property to check if current tab is general config
const isGeneralConfigTab = window.Vue.computed(
    () =>
        currentCategory.value.name.toLowerCase().includes("general") ||
        currentCategory.value.name.toLowerCase().includes("config"),
);

// Removed unused computed property and function

/**
 * Load subjects for the current category from the API.
 */
async function loadCategorySubjects(): Promise<void> {
    if (!props.subjectsEndpoint || isGeneralConfigTab.value) {
        currentSubjectsFromAPI.value = [];
        return;
    }

    const url = props.subjectsEndpoint.replace("{groupId}", currentCategory.value.id.toString());
    const response = await apiRequest<Array<{ subject: string; level: string }>>({ url, method: "GET" });

    if (!response.success || !Array.isArray(response.data)) {
        console.error("Failed to load subjects:", response.success ? "Unexpected format" : response.message);
        currentSubjectsFromAPI.value = [];
        return;
    }

    currentSubjectsFromAPI.value = response.data;
}

/**
 * Load configuration data for the current category from the API.
 */
async function loadCategoryConfigurations(): Promise<void> {
    if (!props.configurationEndpoint) {
        return;
    }

    isLoading.value = true;
    loadError.value = null;

    const url = props.configurationEndpoint.replace("{categoryId}", currentCategory.value.id.toString());
    const response = await apiRequest<IBConfiguration[]>({ url, method: "GET" });

    isLoading.value = false;

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    if (!Array.isArray(response.data)) {
        loadError.value = "Unexpected response format";
        return;
    }

    currentConfigurations.value = response.data;
}

/**
 * Handle category selection, update URL.
 * @param category - The selected IB category/tab
 */
async function onCategorySelected(category: IBCategory): Promise<void> {
    currentCategory.value = category;

    // Wait for Vue to update reactive values
    await window.Vue.nextTick();

    // Update URL without adding to browser history
    const url = new URL(window.location.href);
    url.searchParams.set(categoryParamName, category.name);
    window.history.replaceState(null, "", url.toString());

    // Load configurations and subjects sequentially to avoid race conditions
    await loadCategoryConfigurations();
    await loadCategorySubjects();
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
    void loadCategorySubjects();
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

        <section class="content tabs-content">
            <div class="content active">
                <h2>{{ currentCategory.title }}</h2>

                <div v-if="isLoading" class="loader"></div>
                <div v-else-if="loadError" class="alert alert-box alert">
                    <i class="fa fa-exclamation-triangle"></i>
                    {{ loadError }}
                </div>
                <IBGeneralConfig
                    v-else-if="isGeneralConfigTab"
                    :configurations="currentConfigurations"
                    :is-loading="isLoading"
                    :save-error="loadError"
                    :save-endpoint="saveEndpoint"
                    :category-title="currentCategory.title"
                />
                <IBSubjectTable
                    v-else
                    :category-name="currentCategory.name"
                    :category-title="currentCategory.title"
                    :category-id="currentCategory.id"
                    :save-endpoint="saveEndpoint"
                    :ib-subjects="currentSubjectsFromAPI"
                />
            </div>
        </section>
    </div>
</template>
