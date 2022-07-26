<script setup>

/**
 * To select Curriculum codes
 */

import { ref, onMounted } from 'vue';
import { useStore } from './store/curriculumSelectorStore';
import CsSearchInput from './cs-search-input.vue';
import CsSelectedCodes from './cs-selected-codes.vue';
import CsModal from './modal/cs-modal.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: true,
    },
});

const store = useStore();
const toastEl = ref(null);

/**
 * Start loading the group data
 */
onMounted(() => {
    store.toastEl = toastEl;
    store.$reset(); //ensure the store state is as it's initially set, this is just for Storybook when switching between stories
    store.load(props.dataEndpoint);
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}

</script>

<template>
   <div class="row">
        <div class="small-12 columns">
            <label>Curriculum Codes
                <span data-tooltip="" class="hint has-tip tip-top" data-width="200" data-selector="tooltip-l5qfe53bv" aria-describedby="tooltip-l5qfe53bv" title="">?</span>
            </label>
            <cs-selected-codes />
            <cs-search-input />
        </div>
    </div>
    <cs-modal />
</template>

<style scoped>

</style>