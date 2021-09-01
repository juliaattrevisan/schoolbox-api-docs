<script>

/**
 * Provide access to CRUD Admin > Lists
 *
 */

import { mapState, mapGetters } from 'vuex';
import axios from 'axios';
import { gsap } from 'gsap/all'
import AdminListHeader from './admin-list-header';
import AdminListPopOut from './admin-list-pop-out';
import AdminListNoRows from './admin-list-no-rows';
import AdminListRow from './row/admin-list-row';
import AdminListAddRow from './admin-list-add-row';
import AdminListDeleteRow from './admin-list-delete-row';
import mixinSaveRows from './mixins/mixinAdminListSaveRows';
import Toast from '@vue/components/toast';
import { UPDATE_LOADED_DATA } from './store/adminListMutationTypes';


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
        mixinSaveRows
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
        }
    },

    computed: {

        ...mapState({
            loadedData: state => state.loadedData,
            rows: state => state.rows,
            selectedRow: state => state.selectedRow,
            isPopOutOpen: state => state.isPopOutOpen
        }),

        ...mapGetters([
            'headerFields'
        ]), 

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

        /**
         * Load the list data relevant to the current list
         */
        async getData () {
            try {
                const response = await axios.get(`/adminv2/lists/data/${this.tableName}`);
                this.$store.commit(UPDATE_LOADED_DATA, response.data);
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
            if (this.loadedData.isSortable) {
                this.$nextTick(() => {
                    $('[data-sortable]').sortable({ handle: '.grip', axis: 'y' });
                });
            }
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
        onBeforeEnter (row) {
            gsap.set(row, { height: 0 } );
        },

        /**
         * How the element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onEnter (row, done) {
            gsap.to(row, 0.3, { height: 'auto', onComplete: done } );
        },

        /**
         * How the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onLeave (row, done) {
            gsap.to(row, 0.3, { height: 0, onComplete: done } );
        },

    }

}
</script>

<template>

    <div>

        <div v-if="isDataLoaded">
        
            <div class="small-12 island">
                
                <transition name="fade" mode="out-in">
                    
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
                                    @before-enter="onBeforeEnter"
                                    @enter="onEnter"
                                    @leave="onLeave"
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
                            :value="saveRows_saveStatus" 
                            :disabled="saveRows_isProcessing" 
                        />

                    </form>

                </transition>    

            </div><!-- small-12 island -->  

            <toast ref="toast"></toast>

            <admin-list-delete-row 
                v-if="loadedData.canDelete" 
                :table-name="tableName" 
            />

        </div><!-- v-if="isDataLoaded" -->

        <div v-else-if="hasDataLoadError">
            <h2>Unable to load the list data</h2>
        </div>

        <div v-else>
            <h2>Loading please wait</h2>
        </div>

    </div>

</template>

<style lang="scss" scoped>

.list-container { //so rows aren't shown when animating on/off on add/remove
    position: relative;
    overflow: hidden;
}

.information-list {
    position: relative;
}

.component-action {
    position: relative;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}

</style>
