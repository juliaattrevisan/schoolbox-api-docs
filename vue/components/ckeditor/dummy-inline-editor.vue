<script setup lang="ts">
/**
 * A placeholder component to load a hidden inline CKEditor instance when required
 * This significantly decreases the time it takes to load the editor when a user clicks
 * an element linked to displaying a inline ckeditor (~100ms instead of ~700ms on my machine)
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorType } from "@js/editor/types/editorTypes";

const editorId = `dummy-inline-editor`;

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
