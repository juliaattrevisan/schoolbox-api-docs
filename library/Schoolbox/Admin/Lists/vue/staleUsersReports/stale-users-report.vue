<script>

import Vue from 'vue';
import { VueGoodTable } from 'vue-good-table';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import { fetchAsJson } from '@shared/fetch/fetch';
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
					label: 'pageIndex', //to assist in shift-selection
					field: 'pageIndex',
					hidden: true
				}
      		],
            rowsToDelete: [], //keep track of which rows could be deleted
			isShiftKeySelected: false, //to multi-select fields,
			hasShiftKeySelected: false, //to hide a UI note
			consecutiveRowSelectionCount: 0, //to increment on every consecutive row selection, to monitor for shift-select rows
			currentPage: 1, //which page within pagination is active
			currentPerPage: 1 //how many rows per pagination
    	};
  	},

	mounted () {
		this.initShiftKeyDetection();
		this.loadRows();
	},

	computed: {

		getUsersToDeleteCount () {
			if (this.rowsToDelete.length > 1) {
				return this.rowsToDelete.length + ' Users'
			} else {
				return this.rowsToDelete.length + ' User';			
			}
		}

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
				.then(() => { //to ensure it exists in the DOM
					this.$refs.vueGoodTable.selectAllByPage = false; //defaults to true, not currently provided as a prop, but works as expected
				});	

			}).catch((error) => {
				this.hasLoadError = true; //display error note in the DOM
			});

		},

		/**
         * display the date in the Numerical Date Format within settings
         *
         */
		formatLastUpdated (value) {
			return format(parseISO(value), 'dd/LL/yyyy');
		},

		/**
         * to reset shift-selections
         *
         */
		onSortChange () {
			this.consecutiveRowSelectionCount = 0;
		},

		/**
         * to know which page is currently selected
         *
         * @param {Object: selectedRows} params
         */
		onPageChange (params) {
			this.consecutiveRowSelectionCount = 0;
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
			if (params.selected) { //whether the row is selected or not
				this.addRow(params);
			} else { 
				this.removeRow(params)
			}
		},

		/**
         * add the row if not in already
         *
		 * @param {Object} params 
         */
		addRow (params) {
			if (!this.rowsToDelete.some(rowToDelete => rowToDelete.id === params.row.id)) { //not already in
				this.rowsToDelete.push(params.row);
				params.row.pageIndex = params.pageIndex + ((this.currentPage-1) * this.currentPerPage);  //to track which rows to select if shift-select
				this.consecutiveRowSelectionCount++;
			}
		},

		/**
         * remove the row if in already
         *
		 * @param {Object} params
         */
		removeRow (params) {
			if (this.rowsToDelete.some(rowToDelete => rowToDelete.id === params.row.id)) { //is in already
				this.rowsToDelete = this.rowsToDelete.filter(rowToDelete => rowToDelete.id !== params.row.id); //return all but matching id
				params.row.pageIndex = params.pageIndex + ((this.currentPage-1) * this.currentPerPage);
				this.consecutiveRowSelectionCount = 0;
			}
		},

		/**
         * allow easier selection of rows with shift-select
		 * retrieve all rows in between the last two selections 
		 * and set them as selected when shift is pressed
         *
         */
		checkForShiftSelectRows () {
            
			if (this.isShiftKeySelected && this.consecutiveRowSelectionCount >= 2)  {
				
				this.hasShiftKeySelected = true;

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
						this.rowsToDelete.unshift(row); //to not impact previousCheckedRowIndex on possible further shift-selections
					}
				} );

			}

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
			styleClass="vgt-table striped bordered"
			compactMode
			:columns="columns"
			:rows="rows"
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
				perPage: 50,
				perPageDropdown: [50, 100, 200],
				rowsPerPageLabel: 'Users per page',
				infoFn: (params) => `${params.firstRecordOnPage} - ${params.lastRecordOnPage} of ${params.totalRecords} Users`,

			}"
			:select-options="{ 
				enabled: true,
				disableSelectInfo: true,
			}"
			@on-sort-change="onSortChange"
			@on-page-change="onPageChange"
			@on-row-click="onRowClick"
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
                <h4>You are about to delete <strong>{{ getUsersToDeleteCount }}</strong>. Deleting users will prevent them from logging into the system and from accessing resources that belong to them.  Attached resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links.  Resources shared with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain available.</h4>
            </div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteUsersSelected" class="button submit">Confirm Deletion</a></li>
                <li><a @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
		<transition name="slide">
			<div v-if="rowsToDelete.length > 0" class="delete-container">
				<section class="content">
					<h3><em>{{ getUsersToDeleteCount }} Selected</em></h3>
					<span v-if="!hasShiftKeySelected" class="shift-select-note">Use Shift+Select to select multiple Users</span>
					<button @click.prevent="onClearUsersToSelected" class="button-clear">Clear User Selection</button>
					<button @click.prevent="onDeleteUsersSelected" class="button submit">Delete Users</button>
				</section>
			</div>
		</transition>
	</div>
</template>

<style lang="scss">

@import 'vue-good-table/dist/vue-good-table';

.vgt-wrap {
	padding-bottom: 100px; //to allow space for .delete-container at the bottom
}

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
	width: 80%;
	height: 120px;
	bottom: 0px;
	border: 1px solid #dcdfe6;
	padding: 10px 20px;
	background: linear-gradient(#f4f5f8, #f1f3f6);
	h3, button {
		display: inline-block;
	}
	h3 {
		margin-right: 5px;
	}
	.shift-select-note {
		padding-right: 20px;
		font-size: 0.8rem;
		font-style: italic;
	}
	.button-clear {
		padding: 0px;
		background: none;
		vertical-align: baseline;
		font-size: 0.8rem;
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