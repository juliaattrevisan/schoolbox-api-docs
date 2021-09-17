<script>

/**
 * An individual student within the badge-report
 */

import { gsap } from 'gsap/all';
import BadgeImage from '../../vue/badge-image';
import { getIndex } from '@js/util/domUtils';
import HorizontalScroller from '@js/scroll/horizontalScroller';

const BADGE_ANIMATE_DURATION = 0.5;

export default {
    
    components: {
        BadgeImage,
    },

    props: {
        student: { //contains all the data needed to display
            type: Object,
            required: true
        },
    },

    watch: {

        student: {
            deep: true,
            handler (newStudentData, oldStudentData) {
                if (newStudentData?.awards?.length !== oldStudentData?.awards?.length) {
                    setTimeout(() => { 
                        this.horizontalScroller.refresh();
                    }, BADGE_ANIMATE_DURATION * 1000);
                }
            }
        }

    },

    mounted () {
        this.initHorizontalScroller();
    },

    methods: {

        /**
         * To horizontally scroll the badge list
         */
        initHorizontalScroller () {
            this.horizontalScroller = new HorizontalScroller(this.$el.querySelector('[data-scroller]')); 
        },

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        awardSelected (award) {
            this.$emit('on-award-selected', award);
        },

        /**
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param {DOMElement} element - the element that's animating
         */
        onBeforeEnter (element) {
            gsap.set(element, { opacity: 0, y: 10 } );
        },

        /**
         * how the element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onEnter (element, done) {
            gsap.to(element, { opacity: 1, y: 0, ease: 'power1.out', duration: BADGE_ANIMATE_DURATION, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         */
        onBeforeLeave (element) {
            const leftTarget = (element.getBoundingClientRect().width + 16) * getIndex(element);
            element.style.position = 'absolute';
            element.style.left = `${leftTarget}px`;
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onLeave (element, done) {
            gsap.to(element, { opacity: 0, y: 10, ease: 'power1.out', duration: BADGE_ANIMATE_DURATION, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         */
        onAfterLeave (element) {
            element.style.position = 'static';
        },

    }

}
</script>

<template>
    <li class="awards student">
        <div class="small-12 medium-1 column">
            <input id="checkbox1" type="checkbox"><label for="checkbox1">Checkbox 1</label>
        </div>
        <div class="small-12 medium-1 column">
            <a :href="`/search/user/${student.id}`">
                <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="student.fullName">
                <h4>{{ student.fullName }}</h4>
            </a>
        </div>
        <div class="small-12 medium-10 column">
            <div class="scroller-container" data-scroller>
                <div class="scroller-button-container">
                    <button class="scroller-button" data-scroller-previous-button>&lt;</button>
                </div>
                <div class="scroller-flex-container">
                    <div class="scroller-outer-container" data-scroller-outer-container>
                        <transition-group
                            tag="ul"
                            class="scroller-inner-container"
                            data-scroller-inner-container
                            :css="false"
                            @before-enter="onBeforeEnter"
                            @enter="onEnter"
                            @before-leave="onBeforeLeave" 
                            @leave="onLeave" 
                            @after-leave="onAfterLeave" 
                        >      
                            <li v-for="award in student.awards" :key="award.id" class="award" data-scroller-item :data-title="award.badge.name">
                                <a href="#open-award-modal" @click.prevent="awardSelected(award)" :title="award.badge.name">
                                    <badge-image :badge="award.badge" :css="award.isGoal ? 'badge goal' : 'badge'"></badge-image>
                                    <span>{{ award.badge.name }}</span>
                                </a>
                            </li>
                        </transition-group>
                        <div class="scroller-bar" data-scroller-scroll-bar></div>
                    </div>
                </div>
                <div class="scroller-button-container">
                    <button class="scroller-button" data-scroller-next-button>&gt;</button>
                </div>
            </div>
        </div>
    </li>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

$rowHeight: 140px;

.student {
    height: $rowHeight;
    overflow: hidden;
    margin-bottom: 1rem;
}

.column {
    padding-bottom: 0;
}

// .student {
//     height: $rowHeight;
//     margin-bottom: 1rem;
// }

// .column-checkbox {
//     flex: 0 0 50px;
// }

// .column-name {
//     flex: 0 0 50px;
// }

// .column-badges {
//     flex: 1 1 auto;
// }

.scroller-container {
    display: flex;
    align-items: flex-start;
    justify-content: space-around;
    height: $rowHeight;
}

.scroller-button-container {
    flex: 0 0 50px;
    height: 100%;
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

.scroller-flex-container {
    flex: 1 1 auto;
    height: 100%;
    overflow: hidden;
    white-space: nowrap;
}

.scroller-outer-container {
    position: relative;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

.scroller-inner-container {
    height: 100%;
    margin: 0;
    padding: 0;
}

.award::v-deep {
    display: inline-block;
    width: $rowHeight;
    margin: 0px 0.5rem;
    vertical-align: top;
    text-align: center;
    transition: all 0.3s;
    img {
        width: 60px;
        height: auto;
        margin-bottom: 5px;
    }
    span {
        display: inline-block;
        font-size: 0.75rem;
        line-height: 1rem;
        white-space: normal;
    }
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