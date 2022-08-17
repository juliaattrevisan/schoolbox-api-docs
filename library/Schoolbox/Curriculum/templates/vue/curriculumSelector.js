import { createApp } from 'vue';
import CurriculumSelector from './curriculum-selector';
import { createPinia } from 'pinia';

const curriculumSelectorObjs = document.querySelectorAll('[data-cs-container]');

curriculumSelectorObjs.forEach((curriculumSelectorObj) => {
    /* Only spawn one vue component per object on the Manage Rubric page
     *
     * To curb this insanity, we only initialise components that we haven't
     * already encountered by checking the data-loaded attribute and using
     * that as a flag
     */
    if (!curriculumSelectorObj.hasAttribute('data-loaded')) {
        createApp({
            components: {
                CurriculumSelector
            }
        }).use(createPinia()).mount(curriculumSelectorObj);
    }
    curriculumSelectorObj.setAttribute('data-loaded', true);
});
