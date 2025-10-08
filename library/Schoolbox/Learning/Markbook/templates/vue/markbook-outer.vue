<script setup lang="ts">
import type { Template } from "@js/editor/types/editorTypes";
import { useMarkbookStore } from "./store/markbookStore";
import MarkbookInner from "./markbook-inner.vue";
import MarkbookPopout from "./markbook-popout.vue";

interface Props {
    type: "course" | "class";
    filterEventKey: string;
    editorTemplates: Template[];
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

window.Vue.onMounted(() => {
    mbStore.init(props.filterEventKey, props.editorTemplates);

    /**
     * Ensure tooltips are reflowed when the markbook is changed
     */
    window.Vue.watch(
        () => [mbStore.showDisplayMarks, mbStore.showDrafts],
        () => {
            if (document.readyState !== "complete") {
                // If the document isn't ready, we can't reflow tooltips yet
                return;
            }

            // @ts-expect-error -- When a tooltip is added after the page is loaded, we need to tell foundation to fix the tooltips
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- foundation isn't typed, see above
            $(document).foundation("tooltip", "reflow");
        },
        { immediate: true },
    );
});
</script>

<template>
    <div v-if="mbStore.data === null" class="markbook-loader small-12 island">
        <section class="content">
            <div class="loader"></div>
        </section>
    </div>
    <div v-else-if="mbStore.data.classImpls.length === 0" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>
            This course has not yet been imported to any classes or groups.
            <a
                v-if="mbStore.data._routes['courseImport.getImport']"
                :href="mbStore.data._routes['courseImport.getImport']"
            >
                Import this course </a
            >.
        </p>
    </div>
    <div v-else-if="mbStore.activityGroups.length === 0" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>There are no activities that match the selected filters. Try altering the filters to broaden your search.</p>
    </div>
    <markbook-inner v-else :type="props.type" />

    <markbook-popout />
</template>

<style scoped>
/* getting around some chaotic other styles for the loader to stay in the right place */
.markbook-loader {
    padding-left: 0 !important;
    padding-right: 0 !important;
}
</style>
