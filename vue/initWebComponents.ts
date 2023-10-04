/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import SbxActionsDropdown from "@schoolboxVue/components/dropdown/sbx-actions-dropdown.ce.vue";

/**
 * Register all web components here
 */
export function register() {
    const SbxActionsDropdownCE = window.Vue.defineCustomElement(SbxActionsDropdown);
    window.customElements.define("sbx-actions-dropdown", SbxActionsDropdownCE);
}

register();
