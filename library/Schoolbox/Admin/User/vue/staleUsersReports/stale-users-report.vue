<script>

/**
 * To be able to bulk delete users from the system
 * A sortable/searchable table is displayed to select the users to delete
 *
 */

// import {VueGoodTable} from 'vue-good-table';
import {ClientTable} from 'vue-tables-2';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';
// import {mixinVgtSelectRows} from '@schoolbox/vueGoodTable/mixinVgtSelectRows';
import axios from 'axios';
import dayjs from 'dayjs';
import {ResizeObserver} from 'vue-resize';
import 'vue-resize/dist/vue-resize.css';

export default {

  name: 'stale-users-report',

  components: {
    // VueGoodTable,
    Toast,
    RevealModal,
    ResizeObserver
  },

  // mixins: [
  //   mixinVgtSelectRows //contains row selection functionality
  // ],

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

  data() {
    return {
      hasRowsLoaded: false,
      hasLoadError: false,
      rows: [],
      columns: ['id', 'username', 'external_id', 'firstname', 'lastname', 'role', 'year', 'updated_at'],
      hiddenColumns: ['id'],
      options: {
        stickyHeader: true,
        headings: {
          username: 'Username',
          external_id: 'External ID',
          firstname: 'First Name',
          lastname: 'Surname',
          role: 'Role',
          year: 'Year Level',
          updated_at: 'Last Updated',
        },
        pagination: {
          dropdown: true
        },
        perPage: 50, 
        perPageValues: [50, 100, 200],
        selectable: {
          mode: 'multiple'
        },
        
      },
      //   {
      //     id: 'id',
      //     field: 'id',
      //     hidden: true
      //   },
      //   {
      //     label: 'Username',
      //     field: 'username',
      //   },
      //   {
      //     label: 'External ID',
      //     field: 'external_id',
      //   },
      //   {
      //     label: 'First Name',
      //     field: 'firstname',
      //   },
      //   {
      //     label: 'Surname',
      //     field: 'lastname',
      //   },
      //   {
      //     label: 'Role',
      //     field: 'role',
      //   },
      //   {
      //     label: 'Year Level',
      //     field: 'year',
      //   },
      //   {
      //     label: 'Last Updated',
      //     field: 'updated_at',
      //     formatFn: this.formatLastUpdated
      //   },
      //   {
      //     label: 'pageIndex', //to assist in shift-selection
      //     field: 'pageIndex',
      //     hidden: true
      //   }
      // ],
      singleRowName: 'user',
      pluralRowName: 'users',
      selectContainer: null,
    };
  },

  mounted() {
    // this.selectContainer = document.querySelector('[data-select-container]');
    this.loadRows();
  },

  methods: {

    /**
     * To keep the width of the selected-action-container the same as the parent container
     * which will change depending on whether the side menu is opened/closed
     *
     * @returns { width, height }
     */
    onContainerResize(dimensions) {
      // this.selectContainer.style.width = (dimensions.width) + 'px';
    },

    /**
     * load the stale users for display
     *
     */
    loadRows() {
      axios.get(this.src)
        .then((response) => {
          this.rows = response.data;
          this.hasRowsLoaded = true;
          // this.customVueGoodTableSetup();
        }).catch((error) => {
          this.hasLoadError = true; //display error note in the DOM
       });
    },

    /**
     * custom fixes to get VGT to do exactly what we want it to
     *
     */
    // customVueGoodTableSetup() {
    //   this.$nextTick()
    //       .then(() => { //to ensure it exists in the DOM
    //         this.$refs.vueGoodTable.selectAllByPage = false; //defaults to true, not currently provided as a prop, but works as expected
    //       });
    // },

    /**
     * display the date in the Numerical Date Format within settings
     *
     */
    formatLastUpdated(value) {
      return dayjs(value).format(this.dateFormat);
    },

    /**
     * To confirm/cancel selected users deletion
     *
     */
    // onDeleteUsersSelected() {
    //   this.$refs.deleteModal.open();
    // },

    /**
     * Delete the selected users
     *
     */
    // onConfirmDeleteUsersSelected() {

    //   this.$refs.toast.activate('Deleting...');

    //   const usersToDeleteIDList = this.$_vgtSelectRows_getSelectedRows().map(row => row.id);

    //   let formdata = new FormData();
    //   usersToDeleteIDList.forEach((item) => {
    //     formdata.append('users[]', item);
    //   })

    //   axios.post(this.action, formdata)
    //     .then((response) => {
    //       this.$refs.toast.timeout('Deleted', true, 2000);
    //       this.$refs.deleteModal.close();
    //       this.rows = this.getUpdatedRowsAfterUserDeletion();
    //       this.$_vgtSelectRows_clearRows();

    //     }).catch((error) => {
    //       this.$refs.toast.timeout('Unable to delete', false, 2000);

    //     });

    // },

    /**
     * To update the row display
     *
     * @returns {Array}
     */
    // getUpdatedRowsAfterUserDeletion() {
    //   return this.rows.filter((row) => {
    //     if (this.$_vgtSelectRows_getSelectedRows().some(rowToDelete => rowToDelete.id === row.id)) {
    //       return false;
    //     }
    //     return true;
    //   });
    // },

    /**
     * Cancel the the selected users deletion
     *
     */
    // onCancelDeleteUsersSelected() {
    //   this.$refs.deleteModal.close();
    // },

    /**
     * To confirm/cancel selected users deletion
     *
     */
    // onClearUsersToSelected() {
    //   this.$_vgtSelectRows_clearRows();
    //   this.$refs.vueGoodTable.unselectAllInternal(true);
    // },

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
      :data="rows" 
      :columns="columns" 
      :options="options" 
    />

    <!-- <toast ref="toast"></toast>

    <reveal-modal ref="deleteModal">
      <h2>Please Confirm Multiple User Deletion</h2>
      <div data-alert class="alert-box alert">
       You are about to permanently delete <strong>{{ $_vgtSelectRows_selectedRowsCountWithRowName }}</strong>. Deleting users
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
      <div v-show="$_vgtSelectRows_selectedRowsCount  > 0" class="selected-action-container" data-select-container>
        <section class="content">
          <h3><em>{{ $_vgtSelectRows_selectedRowsCountWithRowName }} selected</em></h3>
          <span v-if="!$_vgtSelectRows_hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
          <button @click.prevent="onClearUsersToSelected" class="button-clear">Clear User Selection</button>
          <button @click.prevent="onDeleteUsersSelected" class="button submit">Delete Users</button>
        </section>
      </div>
    </transition> -->

  </div>
</template>

<style lang="scss">

// @import '@schoolbox/vueGoodTable/vueGoodTable';
// @import 'staleUsersReport';

</style>
