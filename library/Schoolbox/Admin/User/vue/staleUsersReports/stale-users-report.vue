<script>

/**
 * To be able to bulk delete users from the system
 * A sortable/searchable table is displayed to select the users to delete
 *
 */

import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
import axios from 'axios';
import dayjs from 'dayjs';
import { ResizeObserver } from 'vue-resize';
import 'vue-resize/dist/vue-resize.css';

export default {

  name: 'stale-users-report',

  components: {
    Toast,
    RevealModal,
    ResizeObserver
  },

  props: {
    dateFormat: {
      type: String,
      required: true,
      default: 'DD/MM/YYYY'
    },
    src: {
      type: String,
      required: true
    },
    action: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      hasRowsLoaded: false,
      hasLoadError: false,
      rows: [], //will be populated once loaded
      selectedRows: [], //keep track of which rows could be deleted
      columns: ['username', 'external_id', 'firstname', 'lastname', 'role', 'year', 'updated_at'],
      hiddenColumns: ['id'],
      options: {
        headings: {
          username: 'Username',
          external_id: 'External ID',
          firstname: 'First Name',
          lastname: 'Surname',
          role: 'Role',
          year: 'Year Level',
          updated_at: 'Last Updated',
        },
        orderBy: {
          ascending: false,
          column: 'updated_at' //initial sorting display
        },
        templates: {
          updated_at: (h, row, index) => {
            return dayjs(row.updated_at).format(this.dateFormat); //to format date properly
          }
        },
        customSorting: {
          year: (ascending) => { //sort year level naturally
            return (a, b) => {
              if (ascending) {
                return this.collator.compare(a.year, b.year);
              } else {
                return this.collator.compare(b.year, a.year);
              }
            }
          }
        },
        pagination: {
          dropdown: true //use drop down rather than pagination buttons
        },
        perPage: 50, 
        perPageValues: [50, 100, 200],
        selectable: {
          mode: 'multiple' //to enable multi-select
        },
        texts: {
          filterPlaceholder: 'Search for users...',
          noResults: 'No matching users',
          count: 'Showing {from} to {to} of {count} users|{count} users|One user'
        },
        skin: 'table-striped table-bordered table-hover',
        sortIcon: {
          is: 'glyphicon-sort',
          base: 'glyphicon',
          up: 'glyphicon-chevron-up',
          down: 'glyphicon-chevron-down'
        },
        debounce: 100, //faster debouce as data is stored on the client side
      },
      singleRowName: 'user',
      pluralRowName: 'users',
      selectContainer: null,
      collator: new Intl.Collator('en', { numeric: true, sensitivity: 'base' }), //to natually sort year level column
      isShiftKeySelected: false, //to multi-select fields,
      hasSelectedShiftKey: false //to hide a UI note
    };
  },

  computed: {

    /**
     * To track how many are selected 
     *
     * @returns {Number}
     */
		selectedRowsCount () {
			return this.selectedRows.length;
		},

    /**
     * For proper count display, eg: '1 user' / '4 users'
     *
     * @returns {String}
     */
		selectedRowsCountWithRowName () {
			if (this.selectedRowsCount === 0) {
				return 'No ' + this.singleRowName;	
			} else if (this.selectedRowsCount === 1) {
				return this.selectedRowsCount + ' ' + this.singleRowName;	
			} else {
				return this.selectedRowsCount + ' ' + this.pluralRowName;	
			}
		},

	},

  mounted () {
    this.initShiftKeyDetection();
    this.initSelectContainer();
    this.loadRows();
  },

  methods: {

    /**
     * to allow multi select while holding the shift key
     *
     */
		initShiftKeyDetection () {
      document.addEventListener('keydown', (event) => {
				if (event.key === 'Shift') {
					this.isShiftKeySelected = true;
				}
			});
			document.addEventListener('keyup', (event) => {
				if (event.key === 'Shift') {
					this.isShiftKeySelected = false;
				}
			});
		},

    /**
     * To keep the select container the same size as the table
     *
     */
    initSelectContainer () {
      this.selectContainer = document.querySelector('[data-select-container]');
    },

    /**
     * To keep the width of the selected-action-container the same as the parent container
     * which will change depending on whether the side menu is opened/closed
     *
     * @returns { width, height }
     */
    onContainerResize (dimensions) {
      this.selectContainer.style.width = (dimensions.width) + 'px';
    },

    /**
     * load the stale users for display
     *
     */
    loadRows () {
      axios.get(this.src)
        .then((response) => {
          this.rows = response.data;
          this.hasRowsLoaded = true;
        }).catch((error) => {
          this.hasLoadError = true; //display error note in the DOM
      });
    },

    /**
     * to keep track of selected rows
     *
     */
    onRowSelected (event) {
      this.selectedRows = event;
      if (this.selectedRows.length > 1 && this.isShiftKeySelected) { //to hide a UI note, notifying of availability of shift-select
        this.hasSelectedShiftKey = true;
      }
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

      const usersToDeleteIDList = this.selectedRows.map(row => row.id);

      let formdata = new FormData();
      usersToDeleteIDList.forEach((item) => {
        formdata.append('users[]', item);
      });

      axios.post(this.action, formdata)
        .then((response) => {
          this.$refs.toast.timeout('Deleted', true, 2000);
          this.$refs.deleteModal.close();
          this.rows = this.getUpdatedRowsAfterUserDeletion();
          this.clearRows();

        }).catch((error) => {
          this.$refs.toast.timeout('Unable to delete', false, 2000);

        });

    },

    /**
     * To update the row display after deletion
     *
     * @returns {Array}
     */
    getUpdatedRowsAfterUserDeletion () {
      return this.rows.filter((row) => {
        if (this.selectedRows.some(rowToDelete => rowToDelete.id === row.id)) { //pull out any deleted rows, those rows that are in selectedRows, which have just been deleted
          return false;
        }
        return true;
      });
    },

    /**
		 * clear the selection after deletion
     *
     */
		clearRows () {
			this.selectedRows = [];
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
      this.clearRows();
      this.$refs.table.resetSelectedRows() 
    },

  }

}

</script>

<template>
  <div>

    <div v-if="!hasRowsLoaded && !hasLoadError" class="loader"></div>
    <div v-else-if="!hasRowsLoaded && hasLoadError" data-alert class="alert-box alert">Sorry, we are currently unable to
      load the Stale Users Report.
    </div>

    <resize-observer @notify="onContainerResize"/>

    <v-client-table 
      v-if="hasRowsLoaded"
      ref="table"
      :data="rows" 
      :columns="columns" 
      :options="options" 
      @select="onRowSelected"
    />

   <toast ref="toast"></toast>

    <reveal-modal ref="deleteModal">
      <h2>Please Confirm Multiple User Deletion</h2>
      <div data-alert class="alert-box alert">
       You are about to permanently delete <strong>{{ selectedRowsCountWithRowName }}</strong>. Deleting users
          will prevent them from logging into the system and from accessing resources that belong to them. Attached
          resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links. Resources shared
          with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain
          available.
      </div>
      <ul class="flex-list buttons">
        <li><a @click.prevent="onConfirmDeleteUsersSelected" class="button submit">Confirm Deletion</a></li>
        <li><a @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</a></li>
      </ul>
    </reveal-modal>

    <transition name="slide">
      <div v-show="selectedRowsCount > 0" class="selected-action-container" data-select-container>
        <section class="content">
          <h3><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
          <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
          <button @click.prevent="onClearUsersToSelected" class="button-clear">Clear User Selection</button>
          <button @click.prevent="onDeleteUsersSelected" class="button submit">Delete Users</button>
        </section>
      </div>
    </transition>

  </div>
</template>

<style lang="scss">

@import '@schoolbox/vueTables2/vueTables2';
@import 'staleUsersReport';

</style>
