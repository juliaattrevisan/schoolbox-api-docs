<script setup lang="ts">
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const maxContentHeight = 300;
const maxContentHeightStyle = window.Vue.computed(() => `${maxContentHeight}px`);

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const isDefaultExpanded = window.Vue.ref(false);

const isExpanded = window.Vue.computed(() => {
    if (isDefaultExpanded.value) return true;
    if (store.expandedUnits.includes(props.unitId)) return true;
    return false;
});

window.Vue.onMounted(() => {
    if (contentEl.value) {
        const height = contentEl.value.offsetHeight;
        if (height > 300) {
            isDefaultExpanded.value = false;
        } else {
            isDefaultExpanded.value = true;
        }
    }
});
</script>

<template>
    <div class="content-container" :class="{ expanded: isExpanded }">
        <div ref="contentEl"><slot></slot></div>
    </div>

    <div v-if="!isExpanded" class="show-more-container">
        <button @click.prevent="store.toggleExpanded(unitId)">Show More</button>
    </div>
</template>

<style scoped>
.content-container:not(.expanded) {
    max-height: v-bind(maxContentHeightStyle);
    overflow: hidden;
}
.show-more-container {
    bottom: 0;
    position: absolute;
    width: 100%;
    background: white;
}
.show-more-container button {
    margin: 5px;
    float: right;
}
</style>
