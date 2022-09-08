<script setup>

/**
 * Inline toast messages that appear when a node is added or updated
 */

import { ref, onMounted, computed } from 'vue';
import { timeout } from '@js/util/jsUtils';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { useAddCurriculumStore } from '../store/mcAddCurriculumStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';


const addCurriculumStore = useAddCurriculumStore();
const addSubsectionStore = useAddSubsectionStore();

const showToast = ref(true); //to show hide the toast
const action = ref(''); //to help describe what action took place, ie 'updated' or 'added'


/**
 * this node could have been just added, so we check
 * if we should display the toast for just added
 */
onMounted(() => {
    // if (props.node.id === addSubsectionStore.id) {
    //     display('added');
    //     addSubsectionStore.unsetId();
    // } else if (props.node.id === addCurriculumStore.id) {
    //     display('added');
    //     addCurriculumStore.unsetId();
    // }
});

/**
 * we listen for the actions we want to respond with
 * https://pinia.vuejs.org/core-concepts/actions.html#without-setup
 */
useUpdateNodeStore().$onAction(({ name, after, onError }) => onAction({ name, after, onError }));

const actionsList = ['update']; //the list of actions that can occur that we are listening for

/**
 * one of the stores actions has been run, so check if it's one
 * we are listening for and then respond
 *
 * @param { object }
 */
// function onAction ({ name, after, onError }) {
//     if (actionsList.includes(name)) {
//         after((nodeId) => {
//             if (props.node.id === nodeId) {
//                 display('updated');
//             }
//         });
//         onError((error) => {
//             console.warn(error);
//         });
//     }
// }

/**
 * display the toast for five seconds
 *
 * @param { string }
 */
async function display ($action) {
    action.value = $action;
    showToast.value = true;
    await timeout(5000);
    showToast.value = false;
}

/**
 * the current message to display
 *
 * @returns { string }
 */
// const message = computed(() => `${props.title} successfully ${action.value}`);
const message = computed(() => `Nodes deleted`);

/**
 * close the toast prior to it dissapearing on its own
 */
function onCloseSelected () {
    showToast.value = false;
}

/**
 * Slide down the node to be updated / added
 *
 * @param { DOMElement }
 */
async function onEnter (el, done) {
    await slideDown(el);
    done()
}

/**
 * Slide up on close or when the node is updated  / added
 *
 * @param { DOMElement }
 */
async function onLeave (el, done) {
    await slideUp(el);
    done()
}

</script>

<template>
    <!-- <div class="toast-outer-container"> -->
        <Transition @enter="onEnter" @leave="onLeave" :css="false">
            <div v-if="showToast" class="alert-box success toast-container">
                <em>{{ message }}</em>
                <button class="close" title="Close" @click.prevent="onCloseSelected" @keyup.enter="onCloseSelected">×</button>
            </div>
        </Transition>
    <!-- </div> -->
</template>

<style lang="scss" scoped>

// .toast-outer-container {
//     height: 52px;
//     overflow: hidden;
//     position: relative;
//     width: 100%;
// }
.toast-container {
    font-size: 1rem;
    height: 52px;
    // left: 0;
    margin: 0;
    // padding: 0;
    // position: absolute;
    // top: 0;
    width: 100%;
    .close {
        top: 2px;
        right: 0.2rem;
    }
}

</style>