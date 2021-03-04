<script>

import Vue from 'vue';
import { VueGoodTable } from 'vue-good-table';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import { fetchAsJson } from '@shared/fetch/fetch';
// import moment from 'moment';
import { format, parseISO } from 'date-fns';

export default {

	name: 'stale-users-report',

	components: {
		VueGoodTable,
        Toast,
        RevealModal
	},

	props: {
		dateFormat: {
			type: String,
			required: true,
			default: 'DD/MM/YYYY'
		}
	},
	
  	data () {
    	return {
			hasRowsLoaded: false, 
			hasLoadError: false,
      		columns: [
				{
					label: 'Username',
					field: 'username',
				},
				{
					label: 'External ID',
					field: 'external_id',
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
					formatFn: this.formatLastUpdated
				},
				{
					label: 'pageIndex',
					field: 'pageIndex',
					hidden: true
				}
      		],
            rowsToDelete: [], //keep track of which rows could be deleted
			isShiftKeySelected: false, //to multi-select fields,
			checkCount: 0, //to increment on every consecutive row selection, to monitor for shift-select rows
			uncheckCount: 0, //to increment on every consecutive row de-selection, to monitor for shift-select rows
			currentPage: 1, //which page within pagination is active
			currentPerPage: 1 //how many rows per pagination
    	};
  	},

	mounted () {
		this.initShiftKeyDetection();
		this.loadRows();
	},

	methods: {

		/**
         * to allow multi select while holding the shift key
         *
         */
		initShiftKeyDetection () {
            document.addEventListener('keydown', (event) => {
				if (event.key === "Shift") {
					this.isShiftKeySelected = true;
				}
			});
			document.addEventListener('keyup', (event) => {
				if (event.key === "Shift") {
					this.isShiftKeySelected = false;
				}
			});
		},

		/**
         * load the stale users for display
         *
         */
		loadRows () {

			fetchAsJson('/adminv2/user/stale/list')
			.then((response) => {
				
				this.rows = response;
				this.hasRowsLoaded = true;

				Vue.nextTick()
				.then(() => {
					this.$refs.vueGoodTable.selectAllByPage = false; //defaults to true, not currently provided as a prop, but works as expected
				});	

			}).catch((error) => {
				this.hasLoadError = true;
			});

		},

		/**
         * display the date in the Numerical Date Format within settings
         *
         */
		formatLastUpdated (value) {
			// return moment(value).format(this.dateFormat); //need to update 'momentJsFormat' to new date strings
			return format(parseISO(value), 'dd/LL/yyyy');
		},

		/**
         * to reset shift-selections
         *
         */
		onSortChange () {
			this.checkCount = 0;
			this.uncheckCount = 0;
		},

		/**
         * to know which page is currently selected
         *
         * @param {Object: selectedRows} params
         */
		onPageChange (params) {
			this.checkCount = 0;
			this.uncheckCount = 0;
            this.currentPage = params.currentPage;
            this.currentPerPage = params.currentPerPage;
		},

		/**
         * to keep track of the rows to delete
         *
         * @param {Object} params
         */
		onRowClick (params) {
			document.getSelection().removeAllRanges(); //to avoid selecting text on shift-select
			this.checkRowSelection(params);
			this.checkForShiftSelectRows();
		},

		/**
         * allow easier selection of users with shift-select
         *
		 * @param {Object} params
         */
		checkRowSelection (params) {
			if (params.selected) { //if the rows not in yet, add it in
				this.addRow(params);
			} else { //if the row is in, pull it out
				this.removeRow(params)
			}
		},

		/**
         * add the row if not in already
         *
		 * @param {Object} params 
         */
		addRow (params) {
			if (!this.rowsToDelete.some(rowToDelete => rowToDelete.id === params.row.id)) {
				this.checkCount++;
				this.uncheckCount = 0;
				params.row.pageIndex = params.pageIndex + ((this.currentPage-1) * this.currentPerPage);
				this.rowsToDelete.push(params.row);
			}
		},

		/**
         * remove the row if in already
         *
		 * @param {Object} params
         */
		removeRow (params) {
			if (this.rowsToDelete.some(rowToDelete => rowToDelete.id === params.row.id)) {
				this.checkCount = 0;
				this.uncheckCount++;
				params.row.pageIndex = params.pageIndex + ((this.currentPage-1) * this.currentPerPage);
				this.rowsToDelete = this.rowsToDelete.filter(rowToDelete => rowToDelete.id !== params.row.id);
			}
		},

		/**
         * allow easier selection of rows with shift-select
         *
         */
		checkForShiftSelectRows () {
            
			if (this.isShiftKeySelected && this.checkCount >= 2)  {
				
				const rowsInCurrentOrder = this.$refs.vueGoodTable.filteredRows[0].children; //the current row list, as reflected by current sorting

				const previousCheckedRowIndex = this.rowsToDelete[this.rowsToDelete.length - 2].pageIndex;
				const latestCheckedRowIndex = this.rowsToDelete[this.rowsToDelete.length - 1].pageIndex;
				
				let rowsBetweenSelection;
				if (latestCheckedRowIndex > previousCheckedRowIndex) {
					rowsBetweenSelection = rowsInCurrentOrder.slice((previousCheckedRowIndex+1), latestCheckedRowIndex);
				} else {
					rowsBetweenSelection = rowsInCurrentOrder.slice((latestCheckedRowIndex+1), previousCheckedRowIndex);
				}
				
				rowsBetweenSelection.forEach( row => {
					if (!this.rowsToDelete.some(rowToDelete => rowToDelete.id === row.id)) { //if it's not already in, add it in
						this.$set(row, 'vgtSelected', true);
						this.rowsToDelete.unshift(row); //to not impact previousCheckedRow on possible further shift-selections
					}
				} );

			}

		},

		/**
         * to keep track of the rows to delete
         *
         * @param {Object: selectedRows} params
         */
		onSelectedRowsChange (params) {
            // this.rowsToDelete = params.selectedRows;
		},

        /**
         * to keep track of the rows to delete
         *
         * @param {Object: selectedRows} params
         */
		onSelectedRowsChange (params) {
            // this.rowsToDelete = params.selectedRows;
		},
		
        /**
         * to ensure ALL items, not just those within 
         * the current pagination group are selected
         *
         * @param {Object: selected, selectedRows} params
         */
        onSelectAllRows (params) {
			this.rowsToDelete = params.selectedRows;
		},
		
		/**
         * To confirm/cancel selected users deletion
         *
         */
        onDeleteUsersSelected () {
            this.$refs.deleteModal.open();
        },

        /**
         * Delete the selected users
         *
         */
        onConfirmDeleteUsersSelected () {

            this.$refs.toast.activate('Deleting...');
			this.$refs.toast.timeout('Deleted', true, 2000);
			this.$refs.deleteModal.close();
			
			this.rows = this.getUpdatedRowsAfterUserDeletion();
			this.rowsToDelete = [];

        },

		/**
         * To update the row display
         *
		 * @returns {Array}
         */
        getUpdatedRowsAfterUserDeletion () {
			return this.rows.filter((row) => {
				if (this.rowsToDelete.some(rowToDelete => rowToDelete.id === row.id)) {
					return false;
				}
				return true;
			} );
        },

		/**
         * Cancel the the selected users deletion
         *
         */
        onCancelDeleteUsersSelected () {
			this.$refs.deleteModal.close();
        },

		/**
         * To confirm/cancel selected users deletion
         *
         */
        onClearUsersToSelected () {
            this.rowsToDelete = [];
			this.$refs.vueGoodTable.unselectAllInternal(true);
        },

	}
	
}

</script>

<template>
	<div>
        <div v-if="!hasRowsLoaded && !hasLoadError" class="loader"></div>
        <div v-else-if="!hasRowsLoaded && hasLoadError" data-alert class="alert-box alert">Sorry, we are currently unable to load the Stale Users Report.</div>
		<vue-good-table
			v-if="hasRowsLoaded"
			ref="vueGoodTable"
			:columns="columns"
			:rows="rows"
			styleClass="vgt-table striped bordered"
			compactMode
			:search-options="{
				enabled: true,
				placeholder: 'Search for users...',
			}"
			:sort-options="{
				enabled: true,
				initialSortBy: { 
					field: 'updated_at',
					type: 'asc'
				}
			}"
			:pagination-options="{
				enabled: true,
				perPage: 50
			}"
			:select-options="{ 
				enabled: true,
				disableSelectInfo: true,
			}"
			@on-sort-change="onSortChange"
			@on-page-change="onPageChange"
			@on-row-click="onRowClick"
			@on-selected-rows-change="onSelectedRowsChange"
			@on-select-all="onSelectAllRows"
		>
			<div slot="emptystate">
				<p>There are currently no users to delete.</p>
			</div>
		</vue-good-table>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please confirm</h2>
			<div data-alert class="alert-box alert">
                <p>You are about to delete {{ rowsToDelete.length }} user(s). Deleting users will prevent them from logging into the system and from accessing resources that belong to them.  Attached resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links.  Resources shared with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain available.</p>
            </div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteUsersSelected" class="button submit">Confirm Deletion</a></li>
                <li><a @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
		<transition name="slide">
			<div v-if="rowsToDelete.length > 0" class="delete-container">
				<h3>{{ rowsToDelete.length }} selected</h3>
				<button @click.prevent="onDeleteUsersSelected">Delete Users</button>
				<button @click.prevent="onClearUsersToSelected">Clear Users To Delete</button>
			</div>
		</transition>
	</div>
</template>

<style lang="scss">

@import 'vue-good-table/dist/vue-good-table';

.vgt-table input[type=checkbox] {
    position: static; 
    width: 1rem; 
    height: 1rem; 
    min-height: 1rem; 
    opacity: 1; 
}

.vgt-wrap__footer .footer__row-count__select {
	width: 100px;
	margin: 0;
	padding: 0px 10px;
}

.delete-container {
	position: fixed;
	width: calc(100% - 30px);
	height: 80px;
	bottom: 0px;
	border: 1px solid #dcdfe6;
	padding: 20px;
	background: linear-gradient(#f4f5f8, #f1f3f6);
	h3, button {
		display: inline-block;
	}
	h3 {
		margin-right: 20px;
	}
}

.slide-enter-active,
.slide-leave-active {
  	transition: transform 0.3s ease-out;
}
.slide-enter-active {
	transform: translateY(100%);
}
.slide-enter-to {
	transform: translateY(0%);
}
.slide-leave-to {
	transform: translateY(100%);
}

</style>