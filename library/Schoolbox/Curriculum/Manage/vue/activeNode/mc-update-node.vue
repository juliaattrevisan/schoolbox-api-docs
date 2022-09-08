<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { computed, onMounted } from 'vue';
import { isScriptAdded, injectScript } from '@js/util/domUtils';
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
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorInstance = computed(() => `description-${activeNodeStore.node.id}` );

/**
 * to load the ckeditor when ready
 */
onMounted(() => {
    loadCKEditor();
});

/**
 * load the ckeditor into the browser
 */
async function loadCKEditor () {
    try {
        const ckeditorScript = `${cdnPrefix}/js/ckeditor/ckeditor.js?v=${siteVersion}`;
        if (!isScriptAdded(ckeditorScript)) {
            await injectScript(ckeditorScript);
        }
        const jqueryEditorScript = `${cdnPrefix}/js/editor/jquery.editor.js?v=${siteVersion}`
        if (!isScriptAdded(jqueryEditorScript)) {
            await injectScript(jqueryEditorScript);
        }
        $.fn.alaressEditor($.alaressEditorConfigForComments(getVueSpecificConfig()));
    } catch (error) {
        console.warn(error);
    }
}

/**
 * The config that needs to be passed to CKEditor in order to for it to work in Vue land
 *
 * @returns { Object }
 */
function getVueSpecificConfig () {
    return {
        on: {
            change: () => { // Bind the content of CKEditor into the description model on change
                updateNodeStore.activeNodeCopy.description = CKEDITOR.instances[ckeditorInstance.value].getData();
            },
            instanceReady: (event) => { //can display the loaded CKEditor now
                if (updateNodeStore.activeNodeCopy.description !== null) {
                    event.editor.setData(updateNodeStore.activeNodeCopy.description); //set the initial data
                }
            }
        },
        targetElements: $(`#${ckeditorInstance.value}`),
    }
}

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
                <legend>Update {{ activeNodeStore.title }}</legend>
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
                    <textarea :id="ckeditorInstance" rows="6"></textarea>
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
    position: relative;
    z-index: 1;
}

legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}

</style>