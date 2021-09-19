<script>

/**
 * Add a new row to the list
 *
 */

import { mapState } from 'vuex';
import mixinAddRow from './mixins/mixinAdminListAddRow';

export default {

    mixins: [
        mixinAddRow
    ],

    computed: {
        ...mapState({
            loadedData: state => state.loadedData,
        }),
    }

}

</script>

<template>
    <div id="listEmptyState" class="empty-state" >
        <i class="icon-list"></i>
        <p>The {{ loadedData.title }} list is empty.<br><a @click.prevent="$_mixinAddRow_onNewRowSelected" href="#add-an-item">Add an item to this list</a>.</p>
    </div>
</template>