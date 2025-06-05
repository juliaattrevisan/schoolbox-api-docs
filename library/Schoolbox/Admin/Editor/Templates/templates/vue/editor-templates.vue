<script setup lang="ts">
/**
 * Display a list of editor templates.
 */

import { kebabToTitleCase } from "@js/util/stringUtils";
import type { Category } from "../js/types/editorTemplatesTypes";

interface Props {
    categories: Category[];
}
const props = defineProps<Props>();

const currentCategory = window.Vue.ref<string>(props.categories.length > 0 ? props.categories[0].name : "");

/**
 * Load and display the templates for the selected category.
 * @param category - the category that has been selected
 */
function onCategorySelected(category: string): void {
    currentCategory.value = category;
}
</script>

<template>
    <div class="small-12 island">
        <dl ref="tabsEl" class="tabs" data-test="editor-templates-tabs">
            <dd
                v-for="category in props.categories"
                :key="category.id"
                :class="{ active: currentCategory === category.name }"
            >
                <a :data-test="`editor-templates-tab-${category.name}`" @click="onCategorySelected(category.name)">{{
                    kebabToTitleCase(category.name)
                }}</a>
            </dd>
        </dl>
    </div>
</template>

<style scoped></style>
