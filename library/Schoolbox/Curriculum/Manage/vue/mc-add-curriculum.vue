<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { computed, onMounted } from 'vue';
import { isScriptAdded, injectScript } from '@js/util/domUtils';
import { useAddCurriculumStore } from './store/mcAddCurriculumStore';



const addCurriculumStore = useAddCurriculumStore();


/**
 * Store a reference to the ckeditor used for the description
 *
 * @returns { String }
 */
const ckeditorInstance = computed(() => `new-curriculum` );

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
                addCurriculumStore.curriculum.description = CKEDITOR.instances[ckeditorInstance.value].getData();
            },
            instanceReady: (event) => { //can display the loaded CKEditor now
                if (addCurriculumStore.curriculum.description !== null) {
                    event.editor.setData(addCurriculumStore.curriculum.description); //set the initial data
                }
            }
        },
        targetElements: $(`#${ckeditorInstance.value}`),
    }
}

/**
 * To instantly notify the user that the subsection is being added
 * by updating the button text
 *
 * @returns { String }
 */
const addButtonText = computed(() => addCurriculumStore.isProcessing ? 'Adding...' : 'Add');

/**
 * submit save
 */
function onAddSubsectionSelected () {
    addCurriculumStore.add();
}

/**
 * close the addSubsection view
 */
function onCancelSelected () {
    addCurriculumStore.cancel();
}

</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <legend>Add new Curriculum</legend>
                    <div class="small-12 columns">
                        <label for="name">Name</label>
                        <input type="text" id="name" v-model="addCurriculumStore.curriculum.name">
                    </div>
                    <div class="small-12 columns">
                        <label for="code">Code</label>
                        <input type="text" id="code" v-model="addCurriculumStore.curriculum.code">
                    </div>
                    <div class="small-12 columns">
                        <label for="description">Description</label>
                        <textarea :id="ckeditorInstance" rows="6"></textarea>
                    </div>
                    <div class="small-12 columns">
                        <ul class="flex-list buttons">
                            <li><button @click.prevent="onCancelSelected" @keyup.enter="onCancelSelected" class="button" :disabled="addCurriculumStore.isProcessing">Cancel</button></li>
                            <li><button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button submit" :disabled="addCurriculumStore.isProcessing">{{ addButtonText }}</button></li>
                        </ul>
                    </div>
                </fieldset>
            </section>
        </div>
    </div>
</template>

<style lang="scss" scoped>

legend {
    font-size: 1rem;
    margin-bottom: 1rem;
}

</style>