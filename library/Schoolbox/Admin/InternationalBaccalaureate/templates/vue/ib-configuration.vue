<script setup lang="ts">
/**
 * IB DP Subject Configuration page. This page displays configuration options
 * organized by IB subject groups as tabs. Each category can have specific
 * configuration options that are loaded dynamically.
 */

import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
// Components will be imported when needed in the template
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

    try {
        const categoryId = currentCategory.value.id;
        const url = props.subjectsEndpoint.replace("{groupId}", categoryId.toString());

        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });

        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }

        const responseJson = (await response.json()) as Array<{ subject: string; level: string }>;
        if (responseJson === null || !Array.isArray(responseJson)) {
            throw new Error("Unexpected subjects response format");
        }

        currentSubjectsFromAPI.value = responseJson;
    } catch (error) {
        if (isAbortError(error)) {
            return;
        }
        console.error("Failed to load subjects:", error);
        currentSubjectsFromAPI.value = [];
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
            </div>
        </section>
    </div>
</template>
