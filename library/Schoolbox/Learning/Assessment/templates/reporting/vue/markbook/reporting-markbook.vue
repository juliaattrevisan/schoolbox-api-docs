<script setup lang="ts">
/**
 * The reporting markbook / reporting status page
 */

import type { Template } from "@js/editor/types/editorTypes";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { useMarkbookStore } from "@schoolboxLibrary/Learning/Markbook/templates/vue/store/markbookStore";
import type { Markbook } from "@schoolboxLibrary/Learning/Markbook/templates/vue/types/payload";
import MarkbookInner from "@schoolboxLibrary/Learning/Markbook/templates/vue/markbook-inner.vue";
import MarkbookPopout from "@schoolboxLibrary/Learning/Markbook/templates/vue/markbook-popout.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";

interface Props {
    editorTemplates?: Template[];
    routeGetStatusData?: string | null;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const loadError = window.Vue.ref<string | null>(null);

/**
 * Load the reporting markbook data
 */
async function loadData(): Promise<void> {
    mbStore.onLoading();

    if (!props.routeGetStatusData) {
        console.error("Missing route for loading markbook data");
        loadError.value = "";
        return;
    }

    loadError.value = null;
    const response = await apiRequest<Markbook>({
        method: "GET",
        url: props.routeGetStatusData,
    });

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    mbStore.onMarkbookLoaded(response.data);
}

window.Vue.onMounted(async () => {
    mbStore.init(props.editorTemplates ?? []);
    await loadData();
});
</script>

<template>
    <request-error v-if="loadError" :further-info="loadError" />
    <loading-table v-else-if="mbStore.data === null" />
    <markbook-inner v-else type="class" />

    <markbook-popout />
</template>
