<script setup>

/**
 * To select Curriculum Codes to assign to something like a Due Work
 */

import { ref, onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
// import { useSelectedCodesStore } from './store/mcSelectedCodesStore';
// import { useSearchResultsStore } from './store/mcSearchResultsStore';
// import { useModalStore } from './store/mcStore';
// import { useModalActiveCodesStore } from './store/mcActiveCodesStore';
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
        // useSelectedCodesStore().$reset();
        // useSearchResultsStore().$reset();
        // useModalStore().$reset();
        // useModalActiveCodesStore().$reset();
        // useStore().$reset();
    }
}

</script>

<template>
    <mc-header />
    <mc-codes />
</template>

<style lang="scss" scoped>


</style>