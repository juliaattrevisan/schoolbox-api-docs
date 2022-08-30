<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useUpdateNodeStore } from './store/mcUpdateNodeStore';


const props = defineProps({
    /**
     * to gradually add more marginLeft to indicate node level depth
     */
    depth: {
        type: Number,
        required: true,
    },
});

const activeNodeStore = useActiveNodeStore();
const updateNodeStore = useUpdateNodeStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { Number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * To instantly notify the user that the node is being updated
 * by updating the button text
 *
 * @returns { String }
 */
const updateButtonText = computed(() => updateNodeStore.isProcessing ? 'Updating...' : 'Update');

/**
 * submit save
 */
function onUpdateSelected () {
    updateNodeStore.update();
}

/**
 * close the update view
 */
function onCancelSelected () {
    updateNodeStore.cancel();
}

</script>

<template>
    <div class="node-container">
        <section>
            <fieldset class="content">
                <legend>Editing: {{ activeNodeStore.title }}</legend>
                <div class="small-12 columns">
                    <label for="name">Name</label>
                    <input type="text" id="name" v-model="updateNodeStore.activeNodeCopy.name">
                </div>
                <div class="small-12 columns">
                    <label for="code">Code</label>
                    <input type="text" id="code" v-model="updateNodeStore.activeNodeCopy.code">
                </div>
                <div class="small-12 columns">
                    <label for="description">Description</label>
                    <input type="text" id="description" v-model="updateNodeStore.activeNodeCopy.description">
                </div>
                <div class="small-12 columns">
                    <ul class="flex-list buttons">
                        <li><button @click.prevent="onCancelSelected" @keyup.enter="onCancelSelected" class="button" :disabled="updateNodeStore.isProcessing">Cancel</button></li>
                        <li><button @click.prevent="onUpdateSelected" @keyup.enter="onUpdateSelected" class="button submit" :disabled="updateNodeStore.isProcessing">{{ updateButtonText }}</button></li>
                    </ul>
                </div>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>

.node-container {
    border-bottom: 1px solid var(--content-ui-background);
    border-top: 1px solid var(--content-ui-background);
    margin: -1px 0 0 v-bind(marginLeft); //-1px so it butts up against the node border above
}

legend {
    font-size: 1rem;
}

</style>