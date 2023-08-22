/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import WcDropdown from "./components/dropDown/wc-dropdown.ce.vue";

/**
 * Register all web components here
 */
export function register() {
    const WcDropdownCE = window.Vue.defineCustomElement(WcDropdown);
    window.customElements.define("wc-dropdown", WcDropdownCE);
}

register();
