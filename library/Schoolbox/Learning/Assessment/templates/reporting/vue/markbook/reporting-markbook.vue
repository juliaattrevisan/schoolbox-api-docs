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
    <div v-else-if="mbStore.data === null" class="markbook-loader small-12 island">
        <section class="content">
            <div class="loader"></div>
        </section>
    </div>
    <markbook-inner v-else type="class" />

    <markbook-popout />
</template>

<style scoped>
/* getting around some chaotic other styles for the loader to stay in the right place */
.markbook-loader {
    padding-left: 0 !important;
    padding-right: 0 !important;
}
</style>
