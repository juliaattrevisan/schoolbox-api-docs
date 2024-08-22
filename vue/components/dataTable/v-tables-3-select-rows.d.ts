/**
 * The v-tables-3-select-rows plugin does not have a typescript definition file
 * as such, we need to create one to avoid typescript errors in the editor
 *
 * This module provides only very basic types for the plugin for us to integrate with,
 * it should not be taken as a definitive type definition for the plugin
 */

declare module "v-tables-3-select-rows" {
    import type { Plugin } from "vue";

    export const ClientTable: Plugin;
}
