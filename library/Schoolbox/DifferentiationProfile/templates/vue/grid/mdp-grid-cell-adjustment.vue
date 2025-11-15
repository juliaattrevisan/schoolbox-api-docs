<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    adjustment: AdjustmentType[] | null;
    options: AdjustmentType[];
}>();

/**
 * When the add adjustment button is clicked.
 */
function onAdd(): void {
    // open modal
}

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    // open modal
}
</script>

<template>
    <div>
        <empty-state v-if="adjustment === null" icon="icon-list">
            <button @click.prevent="onAdd">Add adjustment</button>
        </empty-state>
        <!-- <div class="editor-wrapper" :style="{ display: currentText !== null ? 'block' : 'none' }">
            <media-article
                :id="editorId"
                ref="editorContentEl"
                tag="div"
                class="editor-content"
                :content="initialTextboxContent"
            ></media-article>
            <div class="clearfix"></div>
        </div> -->
    </div>
    <!-- <mdp-grid-cell-text-action
        :profile="props.profile"
        :header-key="props.headerKey"
        :has-changed="hasChanged"
        :is-saving="isSaving"
        :is-focused="isFocused"
        @modify="onModify"
        @save="onSaveSelected"
    /> -->
</template>

<style scoped></style>
