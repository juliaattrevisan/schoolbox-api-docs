<script>

/**
 * Provide access to CRUD Admin > Lists
 *
 */

import { mapState, mapActions } from 'pinia';
import { useAdminListStore } from './store/adminListStore'
import { gsap } from 'gsap/all'
import AdminListHeader from './admin-list-header';
import AdminListPopOut from './admin-list-pop-out';
import AdminListNoRows from './admin-list-no-rows';
import AdminListRow from './row/admin-list-row';
import AdminListAddRow from './admin-list-add-row';
import AdminListDeleteRow from './admin-list-delete-row';
import saveRows from './mixins/mixinAdminListSaveRows';
import Toast from '@schoolboxVue/components/toast';


export default {

    components: {
        AdminListHeader,
        AdminListPopOut,
        AdminListNoRows,
        AdminListRow,
        AdminListAddRow,
        AdminListDeleteRow,
        Toast,
    },

    mixins: [
        saveRows
    ],

    props: {
        tableName: { //the name of the current list, used to submit updates to the DB
            type: String,
            required: true
        }
    },

    data () {
        return {
            isDataLoaded: false,
            hasDataLoadError: false,
            popOutHeight: 0, //so the popout can allow enough vertical space to fit the pop out
            hasDBid: false, // if true display the db ID on the front end.
        }
    },

    computed: {

        ...mapState(useAdminListStore, ['loadedData', 'rows', 'selectedRow', 'isPopOutOpen', 'headerFields', 'hasDBid']),

        /**
         * Whether the admin-list-pop-out.vue is requred
         *
         * @returns { Boolean }
         */
        hasPopOut () {
            return this.loadedData.fields.some((field) => field.hasOwnProperty('isPopOutList'));
        },

        /**
         * Retrieve any styles of the list that change with state
         *
         * @returns { Object }
         */
        listStyles () {
            return {
                minHeight: this.popOutHeight + 'px', //so the popout can allow enough vertical space to fit the pop out
                pointerEvents: this.isPopOutOpen ? 'none' : 'auto' //prevent editing/sorting while popoutList is open
            }
        }

    },

    mounted () {
        this.getData();
    },

    methods: {

        ...mapActions(useAdminListStore, ['updateLoadedData']),

        /**
         * Load the list data relevant to the current list
         */
        async getData () {
            try {
                const response = await fetch(`/adminv2/lists/data/${this.tableName}`);
                const responseData = await response.json();
                this.updateLoadedData(responseData);
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
                this.initSortable();
            }
        },

        /**
         * Init the sortable feature
         */
        initSortable () {
            if (this.loadedData.isSortable && this.rows.length > 0) {
                this.$nextTick(() => {
                    $('[data-sortable]').sortable({ handle: '.grip', axis: 'y' });
                });
            }
        },

        /**
         * As the list wasn't visible, check for sortable feature again
         */
        onListAfterEnter () {
            this.initSortable();
        },

        /**
         * Ensure there's enough height in the container so the pop out doesn't get cut off
         */
        onPopOutMounted (popOutHeight) {
            this.popOutHeight = popOutHeight;
        },

        /**
         * To make the new row 'grow' into the list
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param {DOMElement} element - the element that's animating
         */
        onRowBeforeEnter (row) {
            gsap.set(row, { height: 0 } );
        },

        /**
         * How the element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onRowEnter (row, done) {
            gsap.to(row, 0.3, { height: 'auto', onComplete: done } );
        },

        /**
         * How the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onRowLeave (row, done) {
            gsap.to(row, 0.3, { height: 0, onComplete: done } );
        },

    }

}
</script>

<template>

    <div>

        <div v-if="isDataLoaded">

            <div class="small-12 island">

                <transition name="fade" mode="out-in" @after-enter="onListAfterEnter">

                    <admin-list-no-rows v-if="rows.length === 0" key="empty-list" />

                    <form v-else method="post" action="/adminv2/lists/save" key="list">

                        <section>

                            <div class="list-container">

                                <admin-list-header />

                                <transition-group
                                    name="staggered-fade"
                                    tag="ul"
                                    :class="`information-list ${ loadedData.isSortable ? 'sortable ui-sortable' : 'unsortable'}`"
                                    :style="listStyles"
                                    v-bind:css="false"
                                    data-sortable
                                    data-list
                                    @before-enter="onRowBeforeEnter"
                                    @enter="onRowEnter"
                                    @leave="onRowLeave"
                                >
                                    <admin-list-row
                                        v-for="(row, index) in rows"
                                        :key="`key-${index}`"
                                        :row="row"
                                    />
                                </transition-group>

                                <admin-list-pop-out
                                    v-if="hasPopOut"
                                    :table-name="tableName"
                                    @on-pop-out-mounted="onPopOutMounted"
                                />

                                <admin-list-add-row v-if="!loadedData.noNewRecords" />

                            </div><!-- .list-container -->

                        </section>


                        <input type="submit"
                            @click.prevent="saveRows_onSaveSelected"
                            @keyup.enter="saveRows_onSaveSelected"
                            :value="saveRows_saveStatus"
                            :disabled="saveRows_isProcessing"
                        />

                    </form>

                </transition>

            </div>

            <toast ref="toast"></toast>

            <admin-list-delete-row
                v-if="loadedData.canDelete"
                :table-name="tableName"
            />

        </div>

        <div v-else-if="hasDataLoadError">
            <div class="small-12 island">
                <section class="content">
                    <div data-alert class="alert-box alert">
                        Sorry, we are currently unable to load this List data. Please contact Schoolbox Support it the problem persists.
                    </div>
                </section>
            </div>
        </div>

        <div v-else>
            <table class="skeleton">
                <thead>
                    <tr>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

</template>

<style lang="scss" scoped>

.list-container { //so rows aren't shown when animating on/off on add/remove
    overflow: hidden;
    position: relative;
}

.information-list {
    position: relative;
}

.component-action {
    position: relative;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}

</style>
