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

</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <section>
            <fieldset class="content">
                <legend class="title">Node details</legend>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";
</style>
