<script setup lang="ts">
/**
 * to view a nodes full breadcrumb trail, its description,
 * and all the places the node is used
 */

import { computed } from "vue";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeDetailsStore } from "./store/mcNodeDetailsStore";
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';

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
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 *
 * @returns { string }
 */
const breadcrumbs = computed(() => {
    if (activeNodeStore.node.breadcrumbs?.length > 0) {
        const justNames = activeNodeStore.node.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(' > ');
    }
    return '';
});

</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <loading-table v-if="nodeDetailsStore.isProcessing" rows="2" />
        <section v-else>
            <fieldset class="content">
                <Transition mode="out-in" @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                    <div>
                        <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
                        <div v-html="activeNodeStore.node.description"></div>
                    </div>
                </Transition>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";
</style>
