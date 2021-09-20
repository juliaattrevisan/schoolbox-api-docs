<script>

/**
 * Show a badge report for a class / group of users which can be filtered,
 * and from where new goals / acheievements can be assigned
 */

import { stringify } from 'qs';
import BadgeReportStudent from './badge-report-student';
import AwardDetailModal from './award-detail-modal';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';
import footerUI from '@vue/components/vueTables2/mixinFooterUI';
import BadgeReportStudentName from './badge-report-student-name';
import BadgeReportStudentBadges from './badge-report-student-badges';


export default {

    components: {
        BadgeReportStudent,
        AwardDetailModal,
        BottomDrawer
    },

    mixins: [
        footerUI //to place and keep the tables UI in the footer
    ],
    
    props: {
        subjectTitle: {
            type: String,
            required: true
        },
        getStudentListsEndpoint: { //where to get the student data from
            type: String,
            required: true
        },
        returnUrlName: { //Along with `encodedReturnUrl` this is used when generating a link to learning moments and badges, so that the user may be redirected somewhere after those forms are submitted.
            type: String,
            required: true,
        },
        encodedReturnUrl: { // Along with `returnUrlName` this is used when generating a link to learning moments and badges, so that the user may be redirected somewhere after those forms are submitted.
            type: String,
            required: true,
        },
    },
    
    data () {
        return {
            studentData: [], //hold all student data
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            selectedFilters: [], //to show only those badges curently filtered
            selectedStudents: [], //list of student ids that can have badges awarded to them
            columns: ['fullName', 'badges'],
            options: {
                headings: {
                    fullName: 'Name',
                    badges: 'Badges',
                },
                templates: {
                    fullName: BadgeReportStudentName,
                    badges: BadgeReportStudentBadges,
                },
                columnsClasses: {
                    fullName: 'name-column',
                    badges: 'badges-column',
                },
                orderBy: {
                    ascending: true,
                    column: 'fullName' //initial sorting display
                },
                perPage: 50, 
                perPageValues: [50, 100, 200],
                selectable: {
                    mode: 'multiple' //to enable multi-select
                },
                debounce: 100, //faster debouce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false,
                filterable: false, 
                texts: {
                    filterPlaceholder: 'Search for users...',
                    noResults: 'No matching users...',
                    count: '{from}-{to} of {count}',
                    limit: 'Rows per page:'
                },
                sortable: ['fullName'],
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                customSorting: {
                    fullName: (ascending) => { //sort year level naturally
                        return (a, b) => {
                            if (ascending) {
                                return a.lastName >= b.lastName ? 1 : -1;
                            } else {
                                return a.lastName <= b.lastName ? 1 : -1;
                            }
                        }
                    }
                }
            }
            
        }
    },

    computed: {

        /**
         * To determine whether to show the bottom-drawer to award badges to students
         * 
         * @returns { Boolean }
         */
        hasSelectedStudents () {
            return this.selectedStudents.length > 0;
        },

        /**
         * To determine whether to show the bottom-drawer to award badges to students
         * 
         * @returns { Boolean }
         */
        selectedStudentsCount () {
            return this.selectedStudents.length;
        }

    },

    mounted () {
        document.addEventListener('badge-report__onFilterChange', this.getStudentData); //the student data is ready
        document.addEventListener('badge-report-student-badges__onBadgeSelected', this.onBadgeSelected); //a badge has been selected 
    },

    methods: {

        /**
         * Load the current student data
         */
        async getStudentData (event) {
            try {
                const queryString = stringify( { filters: event.detail.selectedFilters }, { arrayFormat: 'brackets' } ); //use stringify to help split open and convert array values to a query string
                const response = await fetch(this.getStudentListsEndpoint + '?' + queryString);
                const responseData = await response.json();
                this.studentData = responseData.data;
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded () {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * to keep track of selected rows
         */
        onRowSelected (event) {
            this.selectedStudents = event;
        },

        /**
         * to keep track of selected rows
         */
        onFiltered (event) {
            document.dispatchEvent(new CustomEvent('badge-report__onFiltered', { detail: { event } } ) );
        },

        /**
         * to keep track of selected rows
         */
        onSorted (event) {
            document.dispatchEvent(new CustomEvent('badge-report__onSorted', { detail: { event } } ) );
        },

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        onBadgeSelected (event) {
            this.$refs.awardDetailModal.open(event.detail.badge);
        },

        /**
         * Clear the currently selected students
         */
        onClearStudentSelection () {
            this.selectedStudents = [];
            this.$refs.table.resetSelectedRows();
        },

        /**
         * Navigate to the set goal / award achievement page with the users selected
         */
        getUrl (selection) {
            const url = new URL(window.location.href);
            url.pathname = `learning/badge/award/user/${selection}`;
            url.searchParams.append(this.returnUrlName, this.encodedReturnUrl);
            const selectedStudentIDs = this.selectedStudents.map((selectedStudent) => selectedStudent.id); //just get the student ids to add
            url.searchParams.append('userIds', selectedStudentIDs.join(','));
            return url.toString();
        },

    }

}
</script>

<template>

    <div v-if="isDataLoaded" class="small-12 island">

        <v-client-table 
            ref="table"
            :data="studentData" 
            :columns="columns" 
            :options="options" 
            @loaded="onLoaded"
            @select="onRowSelected"
            @filter="onFiltered"
            @sorted="onSorted"
        >
            <div slot="afterTable">
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </div>
        </v-client-table>

        <award-detail-modal ref="awardDetailModal"></award-detail-modal>

        <bottom-drawer :showDrawer="hasSelectedStudents">
            <h3 class="selected-count"><em>{{ selectedStudentsCount }} selected</em></h3>
            <button @click.prevent="onClearStudentSelection" @keyup.enter="onClearStudentSelection" class="button-clear">Clear selection</button>
            <div class="modal-actions">
                <a :href="getUrl('goal')" title="Set goal" class="button submit icon-goal">Set goal</a>
                <a :href="getUrl('achievement')" title="Award achievement" class="button submit icon-achievement">Award achievement</a>
            </div>
        </bottom-drawer>

    </div>

    <div v-else-if="hasDataLoadError" class="alert-box alert">
        Sorry, we are currently unable to load the student data.
    </div>

    <div v-else class="loader"></div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';

.VueTables--client {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.VueTables__select-row {
    width: 40px;
    max-width: 40px;
}

.name-column {
    width: 250px;
    max-width: 250px;
}

td.badges-column {
    max-width: 0px; //@TODO look into this, this allows the badges to just fill the remaining horizontal td space, but feels pretty dodgy... 
    padding: 1rem 0 0 0; //the side buttons and bottom design remove the need for padding, so give the extra space
}

.selected-count {
    display: inline-block;
    margin-right: 1rem;
}

.modal-actions {
	display: flex;
    margin-bottom: 1rem;
	a {
		margin: 0 2px;
	}
}

.button-clear {
    display: inline-block;
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
}

</style>