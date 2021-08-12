<script>

/**
 * Add a new row to the list
 *
 */

import { mixinAddRow } from './mixinAdminListAddRow';

export default {

    mixins: [
        mixinAddRow
    ],

}

</script>

<template>
    <div id="listEmptyState" class="empty-state" >
        <i class="icon-list"></i>
        <p>The {{ data.title }} list is empty.<br><a @click.prevent="$_mixinAddRow_onNewRowSelected" href="#add-an-item">Add an item to this list</a>.</p>
    </div>
</template>