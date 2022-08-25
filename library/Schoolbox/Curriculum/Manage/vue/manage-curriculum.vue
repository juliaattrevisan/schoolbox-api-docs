<script setup>

/**
 * To select Curriculum Codes to assign to something like a Due Work
 */

import { onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useActiveCodesStore } from './store/mcActiveCodesStore';
import { useSelectedCodesStore } from './store/mcSelectedCodeStore';
import McHeader from './mc-header.vue';
import McView from './mc-view.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: true,
        default: '',
    },
    /**
     * can only view the currently selected codes, not edit them
     */
    isReadOnly: {
        type: Boolean,
        required: true,
        default: true,
    },
});

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    checkForStorybook();
    const store = useStore();
    store.isReadOnly = props.isReadOnly;
    if (props.dataEndpoint !== '') { //only load when we need to
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
    <mc-view />
</template>

<style lang="scss" scoped>


</style>