import ViewSuggestedCustomisation from "./view-suggested-customisation.vue";

const container = document.getElementById("view-suggested-customisation-container");

//  Only mount the Vue app if the container element is present, as this will be called within the mage
if (container) {
    window.Vue.createApp({
        components: {
            ViewSuggestedCustomisation,
        },
    }).mount("#view-suggested-customisation-container");
}
