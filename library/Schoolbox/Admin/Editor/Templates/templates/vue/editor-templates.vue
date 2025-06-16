<script setup lang="ts">
/**
 * Display a list of editor templates.
 */

import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import { kebabToTitleCase } from "@js/util/stringUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import type { Category, Template } from "../js/types/editorTemplatesTypes";

interface Props {
    categories: Category[]; // The categories of templates to display
    templatesEndpoint: string; // The endpoint to fetch templates from
}
const props = defineProps<Props>();

const currentCategory = window.Vue.ref<Category>(props.categories[0]);
const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
const currentTemplates = window.Vue.ref<Template[]>([]);

/**
 * Load the templates for the current category from the server.
 */
async function loadCategoryTemplates(): Promise<void> {
    if (props.templatesEndpoint === "") {
        return;
    }
    const url = props.templatesEndpoint.replace("{categoryId}", currentCategory.value.id.toString());
    try {
        isLoading.value = true;
        loadError.value = null;
        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        const responseJson = (await response.json()) as Template[];
        // check if the response in not null and is an array
        if (responseJson === null || !Array.isArray(responseJson)) {
            throw new Error("Unexpected response format");
        }
        currentTemplates.value = responseJson;
    } catch (error) {
        // ignore abort errors, as the previous request was cancelled
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
 * Load and display the templates for the selected category.
 * @param category - the category that has been selected
 */
function onCategorySelected(category: Category): void {
    currentCategory.value = category;
    void loadCategoryTemplates();
}

/**
 * Load the first category's templates when
 * the component is mounted.
 */
window.Vue.onMounted((): void => {
    void loadCategoryTemplates();
});
</script>

<template>
    <div class="small-12 island">
        <dl ref="tabsEl" class="tabs" data-test="editor-templates-tabs">
            <dd
                v-for="category in props.categories"
                :key="category.id"
                :class="{ active: currentCategory.name === category.name }"
            >
                <a :data-test="`editor-templates-tab-${category.name}`" @click="onCategorySelected(category)">{{
                    kebabToTitleCase(category.name)
                }}</a>
            </dd>
        </dl>
        <loading-table v-if="isLoading" />
        <data-load-error v-else-if="loadError" :data-title="currentCategory.name" />
        <div v-else-if="currentTemplates.length === 0" class="empty-state-flex-centered">
            <i class="icon-textbox"></i>
            <p>No {{ kebabToTitleCase(currentCategory.name) }} templates have been created.</p>
        </div>
        <div v-else class="templates">
            <ul class="template-list">
                <li v-for="template in currentTemplates" :key="template.id" class="template-item">
                    <a
                        :href="`/editor/${template.id}`"
                        :data-test="`editor-template-${template.title}`"
                        class="template-link"
                    >
                        <span class="sbx-icon sbx-icon-template"></span>
                        <span>{{ template.title }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped></style>
