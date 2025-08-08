<script setup lang="ts">
/**
 * Display a list of editor templates. The page divides the templates into categories,
 * which are displayed as tabs. When a category is selected, the templates for that category are loaded.
 * From here we can also add new templates, edit existing ones, or delete them.
 */

import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import { kebabToTitleCase } from "@js/util/stringUtils";
import { getFromNow } from "@js/util/dateUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { Category, Template } from "../js/types/editorTemplatesTypes";

interface Props {
    categories: Category[]; // The categories of templates to display
    templatesEndpoint: string; // The endpoint to fetch templates from
    createTemplateEndpoint: string; // The endpoint to create a new template
    updateTemplateEndpoint: string; // The endpoint to update an existing template
    deleteTemplateEndpoint: string; // The endpoint to delete an existing template
}
const props = defineProps<Props>();

const currentTemplate = window.Vue.ref<Template | null>(null); // the currently selected template, used for displaying or deleting a template

// #region load / display a category

const currentCategory = window.Vue.ref<Category>(props.categories[0]);
const isLoading = window.Vue.ref<boolean>(false);
const loadError = window.Vue.ref<string | null>(null);
const currentTemplates = window.Vue.ref<Template[]>([]);
const categoryParamName = "category";

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
 * Load and display the templates for the selected category,
 * updating the URL to reflect the current category.
 * @param category - the category that has been selected
 */
function onCategorySelected(category: Category): void {
    currentCategory.value = category;
    // Update URL without adding to browser history
    const url = new URL(window.location.href);
    url.searchParams.set(categoryParamName, category.name);
    window.history.replaceState(null, "", url.toString());
    void loadCategoryTemplates();
}

/**
 * Load the first category's templates when
 * the component is mounted, also checking the URL for a category.
 */
window.Vue.onMounted((): void => {
    // Check if there's a category in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const categoryParam = urlParams.get(categoryParamName);
    // and if so update the current category if available
    if (categoryParam) {
        const foundCategory = props.categories.find((cat) => cat.name === categoryParam);
        if (foundCategory) {
            currentCategory.value = foundCategory;
        }
    }
    void loadCategoryTemplates();
});

// #endregion

// #region display the templates body

const isPreviewOpen = window.Vue.ref<boolean>(false);

/**
 * Display the body of the selected template in a modal.
 * @param template - the template that has been selected
 */
function onPreviewSelected(template: Template): void {
    currentTemplate.value = template;
    isPreviewOpen.value = true;
}

// #endregion

// #region  delete the template

const isDeleteOpen = window.Vue.ref<boolean>(false);
const isDeleting = window.Vue.ref<boolean>(false);
const deleteError = window.Vue.ref<string | null>(null);

/**
 * Display the body of the selected template in a modal.
 * @param template - the template that has been selected
 */
function onDeleteSelected(template: Template): void {
    currentTemplate.value = template;
    isDeleteOpen.value = true;
}

/**
 * Delete the currently selected template.
 */
async function onDeleteConfirmed(): Promise<void> {
    if (isDeleting.value || props.deleteTemplateEndpoint === "" || !currentTemplate.value) {
        return;
    }
    const url = props.deleteTemplateEndpoint.replace("{templateId}", currentTemplate.value.id.toString());
    const toast = window.sbProps.toastActivate("Deleting...");
    try {
        isDeleting.value = true;
        deleteError.value = null;
        const response = await fetch(url, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
            },
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        // filter out the deleted template from the current templates
        currentTemplates.value = currentTemplates.value.filter(
            (template) => template.id !== (currentTemplate.value as Template).id,
        );
        currentTemplate.value = null; // reset the current template
        isDeleteOpen.value = false; // close the delete modal
        toast.timeout({ text: "Deleted" });
    } catch (error) {
        // ignore abort errors, as the previous request was cancelled
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            deleteError.value = error.message;
        }
        toast.timeout({ text: "Delete failed", isSuccess: false });
        console.error(error);
    } finally {
        isDeleting.value = false;
    }
}

// #endregion
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
        <data-load-error v-else-if="loadError" :data-title="currentCategory.name" :further-info="loadError" />
        <div v-else-if="currentTemplates.length === 0" class="empty-state-flex-centered">
            <i class="icon-textbox"></i>
            <p>No {{ kebabToTitleCase(currentCategory.name) }} templates have been created.</p>
            <a
                v-if="props.createTemplateEndpoint !== ''"
                :href="props.createTemplateEndpoint.replace('{categoryId}', currentCategory.id.toString())"
                >Create a new template</a
            >
        </div>
        <div v-else class="c-island">
            <div v-for="(template, index) in currentTemplates" :key="template.id" class="c-card">
                <div
                    :class="`l-flex-row l-gap-m ${index !== currentTemplates.length - 1 ? `c-card--border-end-alt` : ''}`"
                >
                    <div class="l-flex-col l-flex--justify-center l-flex__item--1 l-gap-xs l-pad-m f-no-margins">
                        <h3>
                            <button class="sbx-link-button f-no-margins" @click.prevent="onPreviewSelected(template)">
                                {{ template.title }}
                            </button>
                        </h3>
                        <p v-if="template.description && template.description !== ''" class="f-no-margins">
                            {{ template.description }}
                        </p>
                        <p class="meta">
                            <span
                                >By
                                <a
                                    v-if="template.createdBy._links && template.createdBy._links.profile"
                                    :href="template.createdBy._links.profile"
                                    >{{ template.createdBy.fullName }}</a
                                >
                                <template v-else>{{ template.createdBy.fullName }}</template>
                            </span>
                            <span>
                                —
                                <time :title="template.createdAt.local">{{
                                    getFromNow(template.createdAt.unixTimestamp)
                                }}</time></span
                            >
                        </p>
                    </div>
                    <div class="l-flex l-flex--align-start l-gap-xs">
                        <a
                            class="sbx-icon-only-button f-no-margins"
                            aria-label="Edit template"
                            title="Edit template"
                            :href="props.updateTemplateEndpoint.replace('{templateId}', template.id.toString())"
                        >
                            <sbx-icon name="modify" />
                        </a>
                        <button
                            class="sbx-icon-only-button f-no-margins"
                            aria-label="Delete template"
                            title="Delete template"
                            @click.prevent="onDeleteSelected(template)"
                        >
                            <sbx-icon name="delete" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="props.createTemplateEndpoint !== ''" class="component-action">
                <section>
                    <a
                        :href="props.createTemplateEndpoint.replace('{categoryId}', currentCategory.id.toString())"
                        class="button"
                        >Add template</a
                    >
                </section>
            </div>
        </div>
    </div>
    <basic-modal v-model:is-open="isPreviewOpen" :has-background-click="true">
        <media-article :content="(currentTemplate as Template).body"></media-article>
    </basic-modal>
    <basic-modal v-model:is-open="isDeleteOpen" :has-background-click="true">
        <div class="l-flex-col l-gap-s f-no-margins">
            <h2>Please confirm</h2>
            <p>Are you sure you want to delete this template?</p>
            <div v-if="deleteError" class="alert-box alert">
                Sorry, there was an error deleting the template. Please
                <a href="" title="refresh the page"> refresh the page </a>
                to try again and contact your support if the problem persists.<br />
                <em>Further support info: {{ deleteError }}</em>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button submit l-flex__item--1" @click.prevent="onDeleteConfirmed">
                    {{ isDeleting ? "Deleting..." : "Delete" }}
                </button>
                <button class="button l-flex__item--1" @click.prevent="isDeleteOpen = false">Cancel</button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
