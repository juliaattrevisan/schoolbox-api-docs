<script setup lang="ts">
/**
 * Display a list of pastoral records within a modal
 */

import { useTemplateRef, toRefs, toRef, type Ref } from "vue";
import { useScroll } from "@vueuse/core";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { useLoadRecords } from "./composable/useLoadRecords";
import PastoralRecord from "./pastoral-record.vue";

const props = defineProps<{
    url: string;
    title?: string;
    meta?: string;
}>();

// const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");
// const title = window.Vue.inject<string>("title");
const isOpen = window.Vue.ref(false);
// const selectedHeader = window.Vue.ref<TableHeader | null>(null);
// const scopeUrl = window.Vue.ref("");
// const scopeUrlParams = new URLSearchParams();
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(toRef(() => props.url));

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
const modalResultsEl = useTemplateRef<HTMLDivElement>("modalResultsRef");
const { arrivedState } = useScroll(modalResultsEl, {
    offset: { bottom: 30 },
});

const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    // if (isAtBottom && payload.value && payload.value.metadata.cursor.next !== null) {
    //     scopeUrlParams.set(ScopeUrlParam.CURSOR as string, payload.value.metadata.cursor.next);
    //     scopeUrl.value = `${selectedTab?.value._links.scope}?${scopeUrlParams.toString()}`;
    // }
    // notify at bottom to prompt loading more results
});
</script>

<template>
    <basic-modal v-model:is-open="isOpen" has-background-click>
        <div v-if="isNewLoad && isNewLoading" class="loader"></div>
        <data-load-error v-else-if="isNewLoad && loadError" data-title="record" :further-info="loadError" />
        <div class="l-flex-col l-gap-m">
            <div class="l-flex-row l-flex--justify-space l-flex--no-wrap l-gap-m">
                <div class="l-flex-col l-gap-s f-no-margins">
                    <h2 v-if="props.title">{{ props.title }}</h2>
                    <p v-if="props.meta" class="meta">
                        {{ props.meta }}
                    </p>
                </div>
            </div>
            <div ref="modalResultsRef" class="prm__records">
                <div class="l-flex-col l-gap-s">
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
