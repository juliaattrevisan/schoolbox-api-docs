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
    <mc-header />
    <mc-codes />
</template>

<style lang="scss" scoped>


</style>