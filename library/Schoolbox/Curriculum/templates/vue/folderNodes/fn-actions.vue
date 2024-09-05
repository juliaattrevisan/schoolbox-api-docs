<script setup lang="ts">
/**
 * To further modify the linked curriculum
 */

import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";

interface Props {
    folderId: number;
    componentId: number;
}
const props = defineProps<Props>();

const folderNodesStore = useFolderNodesStore(props.folderId, props.componentId); // to only display the action once the folder nodes have loaded

const emit = defineEmits<{
    modifySelected: [];
}>();

/**
 * To open the curriculum code mapping modal
 */
function onModifySelected(): void {
    emit("modifySelected");
}
</script>

<template>
    <div v-if="folderNodesStore().hasLoaded" class="component-action">
        <section>
            <button @click.prevent="onModifySelected">Modify Curriculum</button>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all; /* to allow the button to sit above the overlay */
}
</style>
