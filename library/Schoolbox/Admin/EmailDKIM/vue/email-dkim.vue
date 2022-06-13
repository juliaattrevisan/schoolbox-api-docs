<script>

/**
 * Email DKIM
 */
import Toast from '@schoolboxVue/components/toast';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI.js';

export default {

    components: {
        Toast,
        RevealModal
    },

    mixins: [
        footerUI //to place and keep the tables UI in the footer
    ],

    props: {
        srcDomainList: {
            type: String,
            required: true
        },
        srcAddDomain: {
            type: String,
            required: true
        },
        domains: {
            type: Array,
        },
    },

    data () {
        return {
            hasDataLoaded: false, //show loading display until true
            hasLoadError: false, //display load error
            rows: [],
            selectedDkimId: 0,
            selectedDomain: '',
            dkimName: '',
            dkimValue: '',
            tableColumns: ['domain', 'dateModified.formatted', 'edit'],
            tableOptions: {
                debounce: 100, //faster debounce as data is stored on the client side,
                headings: {
                    domain: 'Domain',
                    ['dateModified.formatted']: 'Date modified',
                    edit: ''
                },
                customSorting: {
                    ['dateModified.formatted']: (ascending) => { //sort date modified accurately by the timestamp
                        return (a, b) => {
                            if (ascending) {
                                return a.dateModified.timestamp >= b.dateModified.timestamp ? 1 : -1;
                            }
                            return a.dateModified.timestamp <= b.dateModified.timestamp ? 1 : -1;
                        }
                    },
                },
                orderBy: {
                    ascending: true,
                    column: 'domain'
                },
                perPage: 25,
                perPageValues: [25, 50, 100, 150, 200],
                resizableColumns: false,
                selectable: {
                    mode: '',
                },
                filterable: false,
                filterByColumn: false,
                sortable: ['domain', 'dateModified.formatted'], //the edit column doesn't need to be sortable
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
            },
            selectContainer: null,
            isShiftKeySelected: false, //to multi-select fields,
            hasSelectedShiftKey: false, //to hide a UI note
        }
    },
    mounted () {
        this.loadRows();
    },

    methods: {

        /**
         * Load domains
         */
        async loadRows () {
            try {
                const response = await fetch(this.srcDomainList, {
                    method: 'GET',
                    responseType: 'json'
                });
                this.rows = await response.json();
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.hasDataLoaded = true; //update the display to show the list
            }
        },
        /**
         * To update the table to show the UI in the footer
         */
        onLoaded () {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * Selected modify is complete, so clear the rows and update the data
         */
        onUpdated () {
            this.loadRows(); //refresh the data
        },
        onClickAddDomain (toggledDkim) {
            this.$refs.domainModal.open();
        },
        onCancelAddDomain () {
            this.$refs.domainModal.close();
        },
        async onAddDomain () {
            this.$refs.toast.activate('Adding...');
            try {
                await fetch(this.srcAddDomain, {
                    method: 'POST',
                    body: this.getFormData(),
                });
                this.$refs.toast.timeout('Added', true, 2000);
                this.$refs.domainModal.close();
                this.rows = this.loadRows();
            } catch (error) {
                this.$refs.toast.timeout('Failed to add', false, 2000);
            }
        },
        /**
         * Set up a deleted users array to POST
         *
         * @returns {FormData}
         */
        getFormData () {
            const formData = new FormData();
            formData.append('domain', this.selectedDomain);
            formData.append('dkimId', this.selectedDkimId);
            return formData;
        },

        async onClickGetKey (row) {
            try {
                const response = await fetch(row.dkimUrl, {
                    method: 'GET',
                    responseType: 'json'
                });
                let dkimData = await response.json();
                this.dkimName = dkimData[0].domain;
                this.dkimValue = dkimData[0].publicKey;
                this.$refs.dkimModal.open();
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.hasDataLoaded = true; //update the display to show the list
            }
        }
    },
}
</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item">
            <div class="small-12 island">
                <h2 class="subheader">Domains</h2>
            </div>
        </div>
        <nav>
            <a @click.prevent="onClickAddDomain" class="icon-add" title="Add a domain"></a>
        </nav>
    </div>
    <div>
        <div v-if="hasLoadError">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load. Please contact your support it the problem persists.
                </div>
            </section>
        </div>
        <v-client-table
            v-else-if="hasDataLoaded"
            ref="table"
            :data="rows"
            :columns="tableColumns"
            :options="tableOptions"
            @loaded="onLoaded"
        >
            <template #beforeTable>
            </template>
            <template #edit="props">
                <nav class="edit">
                    <a class="icon-eye" @click.prevent="onClickGetKey(props.row)" title="Get key"></a>
                    <a class="icon-modify" @click.prevent="onClickAddDomain(props.row)" title="Modify domain"></a>
                </nav>
            </template>
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>
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
    <toast ref="toast"></toast>
    <reveal-modal ref="domainModal">
        <h2>Domain name</h2>
        <form>
            <input type="text" placeholder="Domain" v-model="selectedDomain">
            <ul class="flex-list buttons">
                <li>
                    <button @click.prevent="onAddDomain" class="button submit">Add</button>
                </li>
                <li>
                    <button @click.prevent="onCancelAddDomain" class="button">Cancel</button>
                </li>
            </ul>
        </form>
    </reveal-modal>
    <reveal-modal ref="dkimModal">
        <h2>Domain name</h2>
        <p>Name: <input type="text" v-model="dkimName"></p>
        <p>Value: <input type="text" v-model="dkimValue"></p>
    </reveal-modal>
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';

</style>
