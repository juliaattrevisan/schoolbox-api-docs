<script>

import { VueGoodTable } from 'vue-good-table';
import { testData } from './test-stale-users-data';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';

export default {
	components: {
		VueGoodTable,
        Toast,
        RevealModal
	},
  	data () {
    	return {
      		columns: [
				{
					label: 'Username',
					field: 'username',
				},
				{
					label: 'External ID',
					field: 'external_id',
					type: 'number',
				},
				{
					label: 'First Name',
					field: 'firstname',
				},
				{
					label: 'Second Name',
					field: 'lastname',
				},
				{
					label: 'Second Name',
					field: 'lastname',
				},
				{
					label: 'Role',
					field: 'role',
				},
				{
					label: 'Year Level',
					field: 'year',
				},
				{
					label: 'Last Updated',
					field: 'updated_at',
				},
				// {
				//   label: 'Created On',
				//   field: 'createdAt',
				//   type: 'date',
				//   dateInputFormat: 'yyyy-MM-dd',
				//   dateOutputFormat: 'MMM do yy',
				// },
				// {
				//   label: 'Percent',
				//   field: 'score',
				//   type: 'percentage',
				// },
      		],
      		rows: testData,
            rowsToDelete: [] //keep track of which rows could be deleted
    	};
  	},

	methods: {

        /**
         * to keep track of the rows to delete
         *
         * @param {Object: selectedRows} params
         */
		onSelectedRowsChange (params) {
            this.rowsToDelete = params.selectedRows;
			// params.selectedRows - all rows that are selected (this page)
		},
		
        /**
         * to ensure ALL items, not just those within 
         * the current pagination group are selected
         *
         * @param {Object: selected, selectedRows} params
         */
        onSelectAll (params) {
            this.rowsToDelete = params.selectedRows;
            // if (params.selected) {
            //     this.selectAll();
            // }
		},
		
        /**
         * select ALL items, not just those within 
         * the current pagination group
         *
         */
		selectAll () {
			this.rows.forEach( item => {
				if (item.vgtSelected) {
					this.$set(item, 'vgtSelected', false);
				} else {
					this.$set(item, 'vgtSelected', true);
				}
			}, this);
		},

        /**
         * To confirm/cancel the user deletion
         *
         */
        showDeleteModal () {
            this.$refs.deleteModal.open();
        },

        /**
         * Delete the selected users
         *
         */
        deleteUsers () {
            // 
        }

	}
};
</script>

<template>
	<div>
		<vue-good-table
			:columns="columns"
			:rows="rows"
			styleClass="vgt-table striped condensed bordered"
			compactMode
			:search-options="{
				enabled: true,
				placeholder: 'Search this table',
			}"
			:sort-options="{
				enabled: true,
				initialSortBy: {field: 'username', type: 'asc'}
			}"
			:pagination-options="{
				enabled: true
			}"
			:select-options="{ 
				enabled: true,
				selectOnCheckboxOnly: true
			}"
			@on-selected-rows-change="onSelectedRowsChange"
			@on-select-all="onSelectAll"
		>
			<div slot="table-actions-bottom">
				<em>Extra buttons</em>
			</div>
			<div slot="emptystate">
				This will show up when there are no rows
			</div>
			<div slot="selected-row-actions">
				<button @click.prevent="$refs.deleteModal.open">Delete selected users</button>
			</div>
		</vue-good-table>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
            <p>You are about to delete {{ rowsToDelete.length }} users. Deleting users will prevent them from logging into the system and from accessing resources that belong to them.  Attached resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links.  Resources shared with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain available.</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="deleteUsers" class="button submit">Confirm Deletion</a></li>
                <li><a @click.prevent="$refs.deleteModal.close" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
	</div>
</template>

<style lang="scss">

@import 'vue-good-table/dist/vue-good-table';

.vgt-table input[type=checkbox] {
    position: static; 
    width: auto; 
    height: auto; 
    min-height: auto; 
    opacity: 1; 
}

</style>