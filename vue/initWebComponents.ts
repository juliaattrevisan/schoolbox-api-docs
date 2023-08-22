/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import WcActionsDropdown from "./components/dropDown/wc-actions-dropdown.ce.vue";

/**
 * Register all web components here
 */
export function register() {
    const WcActionsDropdownCE = window.Vue.defineCustomElement(WcActionsDropdown);
    window.customElements.define("wc-actions-dropdown", WcActionsDropdownCE);
}

register();
