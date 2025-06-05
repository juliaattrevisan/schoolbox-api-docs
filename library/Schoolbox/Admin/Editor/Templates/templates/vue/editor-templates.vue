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
</script>

<template>
    <div class="small-12 island">
        <dl ref="tabsEl" class="tabs" data-test="editor-templates-tabs">
            <dd v-for="category in props.categories" :key="category.id" class="">
                <a :data-test="`editor-templates-tab-${category.type}`">{{ kebabToTitleCase(category.type) }}</a>
            </dd>
        </dl>
    </div>
</template>

<style scoped></style>
