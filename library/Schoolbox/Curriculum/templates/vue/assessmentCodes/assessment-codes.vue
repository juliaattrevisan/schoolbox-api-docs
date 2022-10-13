<script setup>

/**
 * A container to display curriculum codes linked to an assessment
 * It's specific to an assesment as the other areas that display curriculum codes are
 * currently handled in other Vue files
 */

import { ref } from 'vue';
import CodesList from '@schoolboxCurriculum/vue/codes-list';


const props = defineProps({
    /**
     * the node to load and display
     */
    nodes: {
        type: Object,
        required: true,
    },
});

const selectedNode = ref({ id: 0 });

/**
 * to open up the details modal
 *
 * @pararm { object }
 */
function onCodeSelected (node) {
    selectedNode.value = node;
    // this.$refs.nodeDetailsModal.open();
}

</script>

<template>
   <ul class="meta">
        <li class="icon-curriculum">
            <codes-list
                :nodes="props.nodes"
                @selected="onCodeSelected"
            />
        </li>
    </ul>
</template>

<style scoped>

</style>