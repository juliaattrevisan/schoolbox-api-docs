/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import SbxFreeStyle from "@schoolboxVue/components/generic/sbx-free-style.ce.vue";
import SbxPopOut from "@schoolboxVue/components/popOut/sbx-pop-out.ce.vue";
import SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";

/**
 * Register all web components here
 */
export function register(): void {
    const SbxFreeStyleCE = window.Vue.defineCustomElement(SbxFreeStyle);
    window.customElements.define("sbx-free-style", SbxFreeStyleCE);
    const SbxPopOutCE = window.Vue.defineCustomElement(SbxPopOut);
    window.customElements.define("sbx-pop-out", SbxPopOutCE);
    const SbxIconCE = window.Vue.defineCustomElement(SbxIcon);
    window.customElements.define("sbx-icon", SbxIconCE);
}

register();
