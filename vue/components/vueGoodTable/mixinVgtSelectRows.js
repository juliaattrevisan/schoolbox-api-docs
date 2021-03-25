
/**
 * Provides all functionality when :select-options is enabled on a vue-good-table
 * Also adds shift-select as is not currently provided by default
 * https://xaksis.github.io/vue-good-table/guide/advanced/checkbox-table.html#configuration
 */

export const mixinVgtSelectRows = {
  
	data () {
    	return {
			selectedRows: [], //keep track of which rows could be deleted
			isShiftKeySelected: false, //to multi-select fields,
			hasSelectedShiftKey: false, //to hide a UI note
			consecutiveRowSelectionCount: 0, //to increment on every consecutive row selection, to monitor for shift-select rows
			currentPage: 1, //which page within pagination is active
			currentPerPage: 1 //how many rows per pagination
    	};
  	},

	computed: {

		$_vgtSelectRows_selectedRowsCount () {
			return this.selectedRows.length;
		},

		$_vgtSelectRows_selectedRowsCountWithRowName () {
			if (this.selectedRows.length === 0) {
				return 'No ' + this.singleRowName;	
			} else if (this.selectedRows.length === 1) {
				return this.selectedRows.length + ' ' + this.singleRowName;	
			} else {
				return this.selectedRows.length + ' ' + this.pluralRowName;	
			}
		},

		$_vgtSelectRows_hasSelectedShiftKey () {
			return this.hasSelectedShiftKey;
		},

	},

	mounted () {
		this.initShiftKeyDetection();
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
		 * to reset shift-selections
         *
         */
		$_vgtSelectRows_onSortChange () {
			this.consecutiveRowSelectionCount = 0;
		},

		/**
         * to know which page is currently selected
         *
         * @param {Object: selectedRows} params
         */
		$_vgtSelectRows_onPageChange (params) {
			this.consecutiveRowSelectionCount = 0;
            this.currentPage = params.currentPage;
			this.currentPerPage = params.currentPerPage;
		},

		/**
         * to keep track of the rows to delete
         *
         * @param {Object} params
         */
		$_vgtSelectRows_onRowClick (params) {
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
			if (!this.selectedRows.some(rowToDelete => rowToDelete.id === params.row.id)) { //not already in
				this.selectedRows.push(params.row);
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
			if (this.selectedRows.some(rowToDelete => rowToDelete.id === params.row.id)) { //is in already
				this.selectedRows = this.selectedRows.filter(rowToDelete => rowToDelete.id !== params.row.id); //return all but matching id
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
				
				this.hasSelectedShiftKey = true;

				const rowsInCurrentOrder = this.$refs.vueGoodTable.filteredRows[0].children; //the current row list, as reflected by current sorting

				const previousCheckedRowIndex = this.selectedRows[this.selectedRows.length - 2].pageIndex;
				const latestCheckedRowIndex = this.selectedRows[this.selectedRows.length - 1].pageIndex;
				
				let rowsBetweenSelection;
				if (latestCheckedRowIndex > previousCheckedRowIndex) {
					rowsBetweenSelection = rowsInCurrentOrder.slice((previousCheckedRowIndex+1), latestCheckedRowIndex);
				} else {
					rowsBetweenSelection = rowsInCurrentOrder.slice((latestCheckedRowIndex+1), previousCheckedRowIndex);
				}
				
				rowsBetweenSelection.forEach( row => {
					if (!this.selectedRows.some(rowToDelete => rowToDelete.id === row.id)) { //if it's not already in, add it in
						this.$set(row, 'vgtSelected', true);
						this.selectedRows.unshift(row); //to not impact previousCheckedRowIndex on possible further shift-selections
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
        $_vgtSelectRows_onSelectAllRows (params) {
			this.selectedRows = params.selectedRows;
		},

		/**
		 * clear the selection
         *
		 * @returns {Number}
         */
		$_vgtSelectRows_getSelectedRows () {
			return this.selectedRows;
		},

		/**
		 * clear the selection
         *
         */
		$_vgtSelectRows_clearRows () {
			this.selectedRows = [];
		},

	}
	
}