<script setup lang="ts">
import type { Template } from "@js/editor/types/editorTypes";
import { listenForFilterLoad, listenForFilterLoading } from "@schoolboxLibrary/Filter/templates/ts/filterEvent";
import { useMarkbookStore } from "./store/markbookStore";
import type { Markbook } from "./types/payload";
import MarkbookInner from "./markbook-inner.vue";
import MarkbookPopout from "./markbook-popout.vue";

interface Props {
    type: "course" | "class";
    filterEventKey: string;
    editorTemplates?: Template[];
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

window.Vue.onMounted(() => {
    mbStore.init(props.editorTemplates ?? []);

    /**
     * Listen to the filter overlords which control what we show and when we show it
     */
    listenForFilterLoading(props.filterEventKey, mbStore.onLoading);

    mbStore.onLoading();
    listenForFilterLoad<Markbook>(props.filterEventKey, (detail) => mbStore.onMarkbookLoaded(detail.content));
});
</script>

<template>
    <div v-if="mbStore.data === null" class="markbook-loader small-12 island">
        <section class="content">
            <div class="loader"></div>
        </section>
    </div>
    <div v-else-if="mbStore.data.classImpls.length === 0" class="empty-state">
        <sbx-icon name="curriculum" size="2.5rem"></sbx-icon>
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
        <sbx-icon name="curriculum" size="2.5rem"></sbx-icon>
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
