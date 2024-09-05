<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Add a new row to the list
 *
 */

import { mapState } from "pinia";
import { useAdminListStore } from "./store/adminListStore";
import mixinAddRow from "./mixins/mixinAdminListAddRow";

export default {
    mixins: [mixinAddRow],

    computed: {
        ...mapState(useAdminListStore, ["loadedData"]),
    },
};
</script>

<template>
    <div id="listEmptyState" class="empty-state">
        <i class="icon-list"></i>
        <p>
            The {{ loadedData.title }} list is empty.<br /><a
                @click.prevent="addRow_onNewRowSelected"
                href="#add-an-item"
                >Add an item to this list</a
            >.
        </p>
    </div>
</template>
