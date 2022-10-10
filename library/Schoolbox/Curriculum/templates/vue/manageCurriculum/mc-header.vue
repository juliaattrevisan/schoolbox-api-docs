<script setup>

/**
 *
 */

import { slideDown, slideUp } from '@js/util/animateUtils';
import { useStore } from './store/manageCurriculumStore';
import { useAddCurriculumStore } from './store/mcAddCurriculumStore';
import McAddCurriculum from './mc-add-curriculum.vue';

const store = useStore();
const addCurriculumStore = useAddCurriculumStore();

/**
 * To show / hide the add curriculum display
 */
function onCreateNewSelected () {
    if (addCurriculumStore.isOpen) {
        addCurriculumStore.cancel();
    } else {
        addCurriculumStore.prompt();
    }
}

/**
 * Slide down to show the add curriculum display
 *
 * @param { DOMElement }
 */
async function onEnter (el, done) {
    await slideDown(el);
    done()
}

/**
 * Slide up on close or when the curriculum is added
 *
 * @param { DOMElement }
 */
async function onLeave (el, done) {
    await slideUp(el);
    done()
}

</script>

<template>
   <div :class="`row ${ store.isReadOnly ? '' : 'actions-small-1 actions-medium-2'}`">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Manage Curriculum</h1>
            </div>
        </div>
        <template v-if="store.hasLoadedOk && !store.isReadOnly">
            <nav>
                <a
                    href="#create-curriculum"
                    data-interaction="create-curriculum"
                    :class="`icon ${ addCurriculumStore.isOpen ? 'icon-close' : 'icon-add' }`"
                    title="Create New Curriculum"
                    @click.prevent="onCreateNewSelected"
                    @keyup.enter="onCreateNewSelected"
                >
                </a>
                <a href="/curriculum/import" class="icon icon-upload" title="Import Curriculum"></a>
            </nav>
        </template>
    </div>
    <Transition @enter="onEnter" @leave="onLeave" >
        <mc-add-curriculum v-if="addCurriculumStore.isOpen" />
    </Transition>
</template>

<style scoped>

</style>