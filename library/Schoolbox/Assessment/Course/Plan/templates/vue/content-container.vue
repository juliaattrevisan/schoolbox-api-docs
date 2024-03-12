<script setup lang="ts">
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

const showViewMore = window.Vue.computed(() => {
    if (!store.isDragging && store.expandedUnits.includes(props.unitId)) return false;
    return contentHeight.value > store.unexpandedUnitHeight;
});
const showViewMoreButton = window.Vue.computed(() => {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    if (store.isDragging) return false;
    if (store.expandedUnits.includes(props.unitId)) return false;
    return showViewMore.value;
});

window.Vue.onMounted(() => {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
});
</script>

<template>
    <div class="content-container" :data-content-height="contentHeight">
        <div ref="contentEl"><slot></slot></div>

        <div class="show-more-container" :style="{ opacity: showViewMore ? 1 : 0 }">
            <button :style="{ opacity: showViewMoreButton ? 1 : 0 }" @click.prevent="store.toggleExpanded(unitId)">
                Show More
            </button>
        </div>
    </div>
</template>

<style scoped>
.show-more-container {
    bottom: 0;
    position: absolute;
    width: 100%;
    background: linear-gradient(0deg, #fff 0%, #fff0 100%);
    pointer-events: none;
    transition: opacity 0.3s;
}
.show-more-container button {
    margin: 5px;
    float: right;
    pointer-events: all;
}
</style>
