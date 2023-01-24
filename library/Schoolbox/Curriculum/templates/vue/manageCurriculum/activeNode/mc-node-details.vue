<script setup lang="ts">
/**
 * to view a nodes full breadcrumb trail, its description,
 * and all the places the node is used
 */

import { computed } from "vue";
import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeDetailsStore } from "./store/mcNodeDetailsStore";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const store = useStore();
const activeNodeStore = useActiveNodeStore();
const nodeDetailsStore = useNodeDetailsStore();

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
    // nodeDetailsStore.cancel();
}
</script>

<template>
    <div class="node-container">
        <section>
            <fieldset class="content">
                <legend>Node details</legend>
            </fieldset>
        </section>
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

legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}
</style>
