<script setup lang="ts">
/**
 * A placeholder component to load a hidden inline CKEditor instance for the unit planner
 * This significantly decreases the time it takes to load the editor when a user clicks
 * one of the textbox cells in the unit planner (~100ms instead of ~700ms on my machine)
 *
 * This is only used when the user has access to edit textbox content in at least one loaded unit
 */
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorType } from "@js/editor/types/editorTypes";

const editorId = `up-unit-cell-textbox-content-placeholder`;

window.Vue.onMounted(() => {
    useEditor(
        "",
        `#${editorId}`,
        {
            targetElements: [`#${editorId}`],
            type: EditorType.INLINE,
        },
        true,
    );
});
</script>

<template>
    <div :id="editorId" ref="editorContentEl" class="hide"></div>
</template>
