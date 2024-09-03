<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { updateFooterUI } from "@schoolboxVue/components/vueTables2/useFooterUI";
import type { TableOptions } from "@schoolboxVue/components/vueTables2/types/vueTables2Types";
import { getOptions } from "./getDataTableOptions";
import { getTemplates } from "./templates/getTemplates";
import DataTableImage from "./templates/data-table-image.vue";
import DataTableLink from "./templates/data-table-link.vue";
import type { ColumnConfig, ImageComponentData, LinkComponentData } from "./types/dataTableTypes";

interface Props {
    /**
     * The id of the table
     */
    id: string;
    /**
     * The title of the table
     */
    title: string;
    /**
     * The endpoint to retrieve all the staff data from
     */
    endpoint: string;
    /**
     * The columns to display in the table
     */
    columnsConfig: ColumnConfig[];
    /**
     * The configuration for the vue-tables-2 component. Refer to https://matanya.gitbook.io/vue-tables-2/options-api for a full list of options.
     */
    tableOptions: TableOptions;
}
const props = defineProps<Props>();

const data = window.Vue.ref([] as unknown[]); // the table data to display
const isLoading = window.Vue.ref(true); // whether the table data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the table data
const columns = props.columnsConfig.map((column: ColumnConfig) => column.key); // the columns to display in the table, retrieved by getting the data key from the columns config
const options = getOptions(props.columnsConfig, props.tableOptions); // the options for the vue-tables-2 component
const templates = getTemplates(props.columnsConfig); // any columns that use templates

/**
 * load the staff data for display
 */
async function loadData() {
    try {
        const response = await fetch(props.endpoint);
        data.value = (await response.json()) as unknown[];
    } catch (error) {
        hasLoadError.value = true; // display load error note
    } finally {
        isLoading.value = false; // hide the loading table
    }
}

/**
 * Start loading the group data
 */
window.Vue.onMounted(() => {
    void loadData();
});

let isFirstLoad = true;

/**
 * We need to do a bit of wrangling to properly display table headings
 * On smaller screens, we want to avoid the table headings and sort icons wrapping,
 * so we wrap both elements in a div and set the div to display flex
 */
function updateTableHeadings() {
    const thElements = document.querySelectorAll(`#${props.id} table th`);
    thElements.forEach((thElement) => {
        const divElement = document.createElement("div");
        divElement.classList.add("heading-container");
        const headingChildNodes = thElement.childNodes;
        headingChildNodes.forEach((childNode) => {
            divElement.appendChild(childNode);
        });
        // VueTables puts the sort icon after the title, but we want them before
        const firstSpan = divElement.children[0];
        divElement.appendChild(firstSpan);
        thElement.appendChild(divElement);
    });
}

/**
 * To update the table to show the UI in the footer
 */
async function onLoaded() {
    if (isFirstLoad) {
        // loaded gets called on every state change, and we only want to do the below once
        isFirstLoad = false;
        await updateFooterUI();
        updateTableHeadings();
    }
}
</script>

<template>
    <div class="row">
        <div :id="props.id" class="small-12 columns">
            <loading-table v-if="isLoading" />
            <data-load-error v-else-if="hasLoadError" :data-title="props.title" />
            <v-client-table v-else :data="data" :columns="columns" :options="options" @loaded="onLoaded">
                <template v-for="slot in templates" :key="slot.key" #[slot.key]="tableData">
                    <template v-if="slot.component === 'image'">
                        <data-table-image
                            :row-data="tableData.row"
                            :component-data="slot.componentData as ImageComponentData"
                        />
                    </template>
                    <template v-else-if="slot.component === 'link'">
                        <data-table-link
                            :row-data="tableData.row"
                            :component-data="slot.componentData as LinkComponentData"
                        />
                    </template>
                </template>
                <template #afterTable>
                    <div ref="tableFooterEl" class="VueTables__table-footer" data-table-footer></div>
                </template>
            </v-client-table>
        </div>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
