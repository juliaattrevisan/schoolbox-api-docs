import { createApp } from 'vue';
import MarkbookPopOut from './pop-out-menu-markbook.vue';
import { createPinia } from 'pinia';

const markbookPopOutObjs = document.querySelectorAll('[data-markbook-pop-out]');

markbookPopOutObjs.forEach((markbookPopOutObj) => {
    /* Only spawn one vue component per object on the Manage Rubric page
     *
     * To curb this insanity, we only initialise components that we haven't
     * already encountered by checking the data-loaded attribute and using
     * that as a flag
     */
    if (!markbookPopOutObj.hasAttribute('data-loaded')) {
        createApp({
            components: {
                MarkbookPopOut
            }
        }).use(createPinia()).mount(markbookPopOutObj);
    }
    markbookPopOutObj.setAttribute('data-loaded', true);
});
