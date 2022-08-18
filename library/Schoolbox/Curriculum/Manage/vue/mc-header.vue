<script setup>

/**
 * The heading for the Manage Curriclum page
 */

import { useStore } from './store/manageCurriculumStore';

const store = useStore();

/**
 * Reload the page to try loading again
 */
function onCreateNewSelected () {
    console.warn('onCreateNewSelected')
}

</script>

<template>
   <div :class="`row ${ store.isReadOnly ? '' : 'actions-small-1 actions-medium-2'}`">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Manage Curriculum</h1>
            </div>
        </div>
        <template v-if="store.hasLoadedOk && !store.isReadOnly">
            <nav>
                <span class="hide-for-small">
                    <a
                        href="#create-curriculum"
                        data-interaction="create-curriculum"
                        class="icon icon-add"
                        title="Create New Curriculum"
                        @click.prevent="onCreateNewSelected"
                        @keyup.enter="onCreateNewSelected"
                    >
                    </a>
                    <a href="/curriculum/import" class="icon icon-upload" title="Import Curriculum"></a>
                </span>
                <a href="#" class="icon-more show-for-small" data-dropdown="tab-control" title="Actions"></a>
            </nav>
            <ul id="tab-control" class="f-dropdown" data-dropdown-content="">
                <li>
                    <a
                        href="#create-curriculum"
                        data-interaction="create-curriculum"
                        class="icon icon-add"
                        @click.prevent="onCreateNewSelected"
                        @keyup.enter="onCreateNewSelected"
                    >
                    Create New Curriculum
                </a>
                </li>
                <li>
                    <a href="/curriculum/import" class="icon icon-upload">Import Curriculum</a>
                </li>
            </ul>
        </template>
    </div>
</template>

<style scoped>

</style>