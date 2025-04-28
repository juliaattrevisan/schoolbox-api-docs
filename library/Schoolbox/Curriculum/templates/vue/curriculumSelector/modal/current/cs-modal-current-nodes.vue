<script setup lang="ts">
/**
 * To only show the nodes that are currently assigned to the unit
 */

import type { Node } from "@schoolboxCurriculum/types/Node";

const isProcessing = window.Vue.ref(false);
const nodes = window.Vue.ref<Node[]>([]);

const folderId = 180;

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(async () => {
    try {
        isProcessing.value = true;
        const response = await fetch(`/api/curriculum/usage/folder/${folderId}?returnFormat=tree&descendants=0`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            // body: JSON.stringify([...selectedNodes.value]),
        });
        if (response.ok) {
            const newNodes = (await response.json()) as Node[];
            nodes.value = newNodes;
            // toast.timeout({ text: "Curriculum updated successfully" });
        } else {
            const responseText = (await response.json()) as { title: string; type: string; status: number };
            // toast.timeout({ text: responseText.title, isSuccess: false });
        }
    } catch (error) {
        // toast.timeout({ text: "There was an unexpected error", isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
});
</script>

<template>
    <div>
        <p>Whoop</p>
    </div>
</template>

<style scoped></style>
