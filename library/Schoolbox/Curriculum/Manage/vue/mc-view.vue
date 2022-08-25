<script setup>

/**
 * To view and filter the curriculum codes
 */

import { useStore } from './store/manageCurriculumStore';
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';
import McViewHeader from './mc-view-header.vue';
import McViewCodes from './mc-view-codes.vue';

const store = useStore();

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}

</script>

<template>
    <div v-if="store.isLoading" class="row">
        <div class="small-12 island">
            <loading-table  />
        </div>
    </div>
    <div v-else-if="store.hasLoadError" class="row">
        <div class="small-12 island">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load the curriculum data. Please <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">refresh the page</a> to try again and contact your support if the problem persists.
                </div>
            </section>
        </div>
    </div>
    <div v-else class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <mc-view-header />
                        <mc-view-codes />
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style scoped>

</style>