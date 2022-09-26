<script setup>

/**
 * To view and filter the curriculum codes
 */

import { computed } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useNodesStore } from './store/mcNodesStore';
import { useAddCurriculumStore } from './store/mcAddCurriculumStore';
import { useViewNodesStore } from './viewNodes/store/mcViewNodesStore';
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';
import McViewHeader from './viewHeader/mc-view-header.vue';
import McViewNodes from './viewNodes/mc-view-nodes.vue';


const store = useStore();
const nodesStore = useNodesStore();
const addCurriculumStore = useAddCurriculumStore();
const viewNodesStore = useViewNodesStore();

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}

/**
 * Display the prompt to create a new curriculum
 */
function onCreateNewSelected () {
    addCurriculumStore.prompt();
}

/**
 * To further clarify where the current search term hasn't found any code matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => viewNodesStore.current.name);

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    viewNodesStore.clearSearch();
}

</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <loading-table v-if="store.isLoading" />
            <section v-else-if="store.hasLoadError" class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load the Curriculum data. Please <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">refresh the page</a> to try again and contact your support if the problem persists.
                </div>
            </section>
            <div v-else-if="nodesStore.hasNoCurriculum" class="empty-state">
                <i class="icon-curriculum"></i>
                <p>There's currently no Curriculum loaded to display.</p>
                <template v-if="store.hasLoadedOk && !store.isReadOnly">
                    <button @click.prevent="onCreateNewSelected" @keyup.enter="onCreateNewSelected" title="Create a new Curriculum">Create a new Curriculum</button>
                    <a href="/curriculum/import" title="Import Curriculum">Import Curriculum</a>
                </template>
            </div>
            <div v-else-if="viewNodesStore.hasNoSearchResults" class="empty-state">
                <i class="icon-curriculum"></i>
                <p>No Curriculum in: {{ currentSelectedNodeTitle }} match your current search.</p>
                <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
            </div>
            <section v-else>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <mc-view-header />
                        <mc-view-nodes />
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.empty-state {
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 200px;
    p {
        max-width: 50%;
    }
    button {
        background: none;
        font-size: 0.875rem;
    }
}

.view-nodes {
    margin-bottom: 180px; /* provide a minimum space to allow the bottom-drawer.vue */
}

</style>