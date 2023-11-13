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

<style lang="scss" scoped>
.content-container:not(.expanded) {
    max-height: v-bind(maxContentHeightStyle);
    position: relative;
    overflow: hidden;
}
.show-more-container {
    bottom: 0;
    position: absolute;
    z-index: 1;
    width: 100%;
    background: white;
    &:before {
        content: "";
        position: absolute;
        top: -20px;
        left: 0;
        height: 20px;
        background: linear-gradient(rgba(255, 255, 255, 0), white);
        width: 100%;
    }
}
.show-more-container button {
    margin: 5px;
    float: right;
}
</style>
