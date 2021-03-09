<script>

/**
 * To be able to bulk delete users from the system
 * A sortable/searchable table is displayed to select the users to delete
 * 
 */

import Vue from 'vue';
import { VueGoodTable } from 'vue-good-table';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import { vgtSelectRows } from '@schoolbox/vueGoodTable/vgtSelectRows';
import { fetchAsJson } from '@shared/fetch/fetch';
import dayjs from 'dayjs';

export default {

	name: 'stale-users-report',

	components: {
		VueGoodTable,
        Toast,
        RevealModal
	},

	mixins: [
		vgtSelectRows //contains row selection functionality
	],

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
			singleRowName: 'User',
			pluralRowName: 'Users'
    	};
  	},

	mounted () {
		this.loadRows();
	},

	methods: {

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
			return dayjs(value).format(this.dateFormat);
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
			this.selectedRows = [];

        },

		/**
         * To update the row display
         *
		 * @returns {Array}
         */
        getUpdatedRowsAfterUserDeletion () {
			return this.rows.filter((row) => {
				if (this.selectedRows.some(rowToDelete => rowToDelete.id === row.id)) {
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
            this.selectedRows = [];
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
                <h4>You are about to delete <strong>{{ getSelectedRowsCount }}</strong>. Deleting users will prevent them from logging into the system and from accessing resources that belong to them.  Attached resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links.  Resources shared with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain available.</h4>
            </div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteUsersSelected" class="button submit">Confirm Deletion</a></li>
                <li><a @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
		<transition name="slide">
			<div v-if="selectedRows.length > 0" class="delete-container">
				<section class="content">
					<h3><em>{{ getSelectedRowsCount }} Selected</em></h3>
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