<script setup lang="ts">
/**
 * To view and filter the curriculum nodes
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import { useStore } from "./store/manageCurriculumStore";
import { useNodesStore } from "./store/mcNodesStore";
import { useAddCurriculumStore } from "./store/mcAddCurriculumStore";
import McViewHeader from "./viewHeader/mc-view-header.vue";
import McViewNodes from "./viewNodes/mc-view-nodes.vue";

const store = useStore();
const nodesStore = useNodesStore();
const addCurriculumStore = useAddCurriculumStore();

/**
 * Reload the page to try loading again
 */
function onRefreshSelected() {
    window.location.reload();
}

/**
 * Display the prompt to create a new curriculum
 */
function onCreateNewSelected() {
    addCurriculumStore.prompt();
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <loading-table v-if="store.isLoading" />
            <section v-else-if="store.hasLoadError" class="content">
                <div class="alert-box alert">
                    Sorry, we are currently unable to load the Curriculum data. Please
                    <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">
                        refresh the page
                    </a>
                    to try again and contact your support if the problem persists.
                </div>
            </section>
            <div v-else-if="nodesStore.hasNoCurriculum" class="empty-state-flex-centered">
                <i class="icon-curriculum"></i>
                <p>There's currently no Curriculum loaded to display.</p>
                <template v-if="store.hasLoadedOk && !store.isReadOnly">
                    <a href="#create-curriculum" title="Create a new Curriculum" @click.prevent="onCreateNewSelected">
                        Create a new Curriculum
                    </a>
                </template>
            </div>
            <section v-else>
                <fieldset class="content">
                    <mc-view-header />
                    <mc-view-nodes />
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.row {
    margin-bottom: 180px; /* provide a minimum space to allow the bottom-drawer.vue */
}
</style>
