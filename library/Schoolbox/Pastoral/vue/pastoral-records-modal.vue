<script setup lang="ts">
/**
 * Display a list of pastoral records within a modal
 */

import { useTemplateRef, toRefs } from "vue";
import { useScroll } from "@vueuse/core";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { useLoadRecords } from "./composable/useLoadRecords";
import PastoralRecord from "./pastoral-record.vue";

const props = defineProps<{
    title?: string;
    meta?: string;
}>();

const isOpen = window.Vue.ref(false);
const url = defineModel<string>("url", { default: "" }); // to open/close the modal
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(url);

/**
 * whenever a new url is passed in open the modal,
 * useLoadRecords will handle loading the data
 */
window.Vue.watch(url, (newVal) => {
    if (newVal !== "" && !isOpen.value) {
        isOpen.value = true;
    }
});

/**
 * when the modal is closed, clear the url
 */
window.Vue.watch(isOpen, (newVal) => {
    if (newVal === false) {
        url.value = "";
    }
});

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
const modalResultsEl = useTemplateRef<HTMLDivElement>("modalResultsRef");
const { arrivedState } = useScroll(modalResultsEl, {
    offset: { bottom: 30 },
});

const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && payload.value && payload.value.metadata && payload.value.metadata.cursor.next !== null) {
        const nextUrl = new URL(url.value, window.location.origin);
        nextUrl.searchParams.set(SearchParam.CURSOR as string, payload.value.metadata.cursor.next);
        url.value = nextUrl.toString();
    }
});
</script>

<template>
    <basic-modal v-model:is-open="isOpen" has-background-click allow-outside-click>
        <div v-if="isNewLoad && isNewLoading" class="loader"></div>
        <data-load-error v-else-if="isNewLoad && loadError" data-title="record" :further-info="loadError" />
        <div v-else class="u-flex-col l-gap-m">
            <div class="u-flex-row u-flex--justify-space u-flex--no-wrap l-gap-m">
                <div class="u-flex-col l-gap-s f-no-margins">
                    <h2 v-if="props.title">{{ props.title }}</h2>
                    <p v-if="props.meta" class="meta">
                        {{ props.meta }}
                    </p>
                </div>
            </div>
            <div ref="modalResultsRef" class="prm__records">
                <div class="u-flex-col l-gap-s">
                    <div v-for="record in payload?.data.results" :key="record.id" class="c-island">
                        <ul class="information-list threaded">
                            <pastoral-record :record="record" />
                        </ul>
                    </div>
                </div>
                <div v-if="isMoreLoading" class="c-island l-pad-s">
                    <div class="loader"></div>
                </div>
                <data-load-error v-else-if="!isNewLoad && loadError" data-title="record" :further-info="loadError" />
            </div>
        </div>
    </basic-modal>
</template>

<style scoped>
.prm__records {
    max-height: 650px;
    overflow-y: auto;
}
</style>
