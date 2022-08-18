<script setup>

/**
 * To select Curriculum Codes to assign to something like a Due Work
 */

import { onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useActiveCodesStore } from './store/mcActiveCodesStore';
import { useSelectedCodesStore } from './store/mcSelectedCodeStore';
import McHeader from './mc-header.vue';
import McCodes from './mc-codes.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: false,
        default: '',
    },
});

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    checkForStorybook();
    const store = useStore();
    if (!store.isReadOnly && props.dataEndpoint !== '') { //only load when we need to
        store.load(props.dataEndpoint);
    }
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function checkForStorybook () {
    if (typeof STORYBOOK_ENV !== 'undefined') {
        useStore().$reset();
        useActiveCodesStore().$reset();
        useSelectedCodesStore().$reset();
    }
}

</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Manage Curriculum</h1>
            </div>
        </div>
        <nav>
            <span class="hide-for-small">
                <a href="/curriculum/add" data-interaction="create-curriculum" class="icon icon-add" title="Create New Curriculum"></a>
                <a href="/curriculum/import" class="icon icon-upload" title="Import Curriculum"></a>
            </span>
            <a href="#" class="icon-more show-for-small" data-dropdown="tab-control" title="Actions"></a>
        </nav>
        <ul id="tab-control" class="f-dropdown" data-dropdown-content="">
            <li>
                <a href="/curriculum/add" data-interaction="create-curriculum" class="icon icon-add">Create New Curriculum</a>
            </li>
            <li>
                <a href="/curriculum/import" class="icon icon-upload">Import Curriculum</a>
            </li>
        </ul>
    </div>
    <div class="row">
        <div class="small-12 columns island">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <mc-header />
                        <mc-codes />
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>


</style>