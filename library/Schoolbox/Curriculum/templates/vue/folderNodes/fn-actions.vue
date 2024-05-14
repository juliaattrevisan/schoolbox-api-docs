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

const folderNodesStore = useFolderNodesStore(props.folderId, props.componentId); // the folderNodesStore for the curriculum component, which can be null if no curriculum component is linked yet

const emit = defineEmits<{
    modifySelected: [];
}>();

/**
 * To open the curriculum code mapping modal
 */
function onModifySelected() {
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
