<script>

/**
 * To scroll a list of items horizontally
 */

import HorizontalScroller from '@js/scroll/HorizontalScroller';

const BADGE_ANIMATE_DURATION = 0.3;

export default {
    
    // components: {
        
    // },

    props: {
        // student: { //contains all the data needed to display
        //     type: Object,
        //     required: true
        // },
        // selectedStudents: { //to clear a students selection when cleared by badge-report
        //     type: Array,
        //     required: true
        // }
    },


    mounted () {
        this.initHorizontalScroller();
    },

    methods: {

        /**
         * To horizontally scroll the badge list
         */
        initHorizontalScroller () {
            this.horizontalScroller = new HorizontalScroller(this.$el); 
        },

    }

}
</script>

<template>
    <div class="scroller" data-scroller>
        <div class="scroller-button-container">
            <button class="scroller-button" data-scroller-previous-button>&lt;</button>
        </div>
        <div class="scroller-outer-container" data-scroller-outer-container>
            <div class="scroller-inner-container" data-scroller-inner-container>
                <slot></slot>
            </div>
            <div class="scroller-bar" data-scroller-scroll-bar></div>
        </div>
        <div class="scroller-button-container">
            <button class="scroller-button" data-scroller-next-button>&gt;</button>
        </div>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

$rowHeight: 150px;
$collapseStudent: 1000px;

.scroller {
    display: flex;
    align-items: flex-start;
    justify-content: space-around;
    height: $rowHeight;
}

.scroller-button-container {
    flex: 0 0 50px;
    height: 100%;
    @media (max-width: $collapseStudent) {
        display: none;
    }
}

.scroller-button {
    width: 100%;
    height: 100%;
    margin: 0px auto;
    background: none;
    font-weight: bold;
    font-size: 1.5rem;
    color: #4f4f4f;
}

// .scroller-flex-container {
//     flex: 1 1 auto;
//     height: 100%;
//     overflow: hidden;
//     white-space: nowrap;
// }

.scroller-outer-container {
    flex: 1 1 auto;
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
    white-space: nowrap;
}

.scroller-inner-container {
    height: 100%;
    margin: 0;
    padding: 0;
}

.scroller-bar {
    position: absolute;
    left: 5px;
    bottom: 0px;
    width: 120px; //will be updated relative to scroll amount by horizontalScoller.js
    height: 4px;
    background: $content-ui-foreground;
}

</style>