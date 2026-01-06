import ViewSuggestedCustomisation from "./view-suggested-customisation.vue";

const container = document.getElementById("view-suggested-customisation-container");

/**
 * Only mount the Vue app if the container element is present, as this Vue component can
 * be called within the mage after choosing an activity type, the container may not be
 * immediately present when first requested. Oddly the mage appears to execute the
 * relevant twig twice, and on the first pass the container is not present, but on the second pass it is...
 */
if (container) {
    window.Vue.createApp({
        components: {
            ViewSuggestedCustomisation,
        },
    }).mount("#view-suggested-customisation-container");
}
