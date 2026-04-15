<script>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
/**
 * Add a new row to the list
 *
 */

import { mapState } from "pinia";
import { useAdminListStore } from "./store/adminListStore";
import addRow from "./mixins/mixinAdminListAddRow";

export default {
    computed: {
        ...mapState(useAdminListStore, ["isProcessing"]),
    },

    mixins: [addRow],
};
</script>

<template>
    <div class="component-action">
        <section>
            <a
                @click.prevent="addRow_onNewRowSelected"
                href="#add-new-row"
                data-button="add-row"
                class="button"
                data-test="admin-list-add-row-button"
                :disabled="isProcessing"
                >Add New Row</a
            >
        </section>
    </div>
</template>
