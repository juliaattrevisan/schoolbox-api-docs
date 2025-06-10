<script setup lang="ts">
/**
 * Display a list of editor templates.
 */

import { kebabToTitleCase } from "@js/util/stringUtils";
import type { Category } from "../js/types/editorTemplatesTypes";

interface Props {
    categories: Category[]; // The categories of templates to display
    templatesEndpoint: string; // The endpoint to fetch templates from
}
const props = defineProps<Props>();

const currentCategory = window.Vue.ref<Category>(props.categories[0]);

/**
 * Load and display the templates for the selected category.
 * @param category - the category that has been selected
 */
function onCategorySelected(category: Category): void {
    currentCategory.value = category;
}
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
    </div>
</template>

<style scoped></style>
