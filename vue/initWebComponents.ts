/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import WcButton from "@schoolboxVue/components/button/wc-button.ce.vue";
import WcActionsDropdown from "@schoolboxVue/components/dropdown/wc-actions-dropdown.ce.vue";

/**
 * Register all web components here
 */
export function register() {
    const WcButtonCE = window.Vue.defineCustomElement(WcButton);
    window.customElements.define("wc-button", WcButtonCE);
    const WcActionsDropdownCE = window.Vue.defineCustomElement(WcActionsDropdown);
    window.customElements.define("wc-actions-dropdown", WcActionsDropdownCE);
}

register();
