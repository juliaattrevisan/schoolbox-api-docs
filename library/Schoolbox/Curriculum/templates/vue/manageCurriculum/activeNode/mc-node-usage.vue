<script setup lang="ts">
/**
 * to view a nodes full breadcrumb trail, its description,
 * and all the places the node is used
 */

import { computed } from "vue";
import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeUsageStore } from "./store/mcNodeUsageStore";

const props = defineProps({
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const store = useStore();
const activeNodeStore = useActiveNodeStore();
const nodeUsageStore = useNodeUsageStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * close the update view
 */
function onCancelSelected() {
    // nodeUsageStore.cancel();
}

</script>

<template>
    <div class="node-container">
        Usage
    </div>
</template>

<style lang="scss" scoped>
.node-container {
    border-bottom: 1px solid var(--content-ui-background);
    border-top: 1px solid var(--content-ui-background);
    margin: -1px 0 0 v-bind(marginLeft); //-1px so it butts up against the node border above
    position: relative;
    z-index: 1;
}
</style>
