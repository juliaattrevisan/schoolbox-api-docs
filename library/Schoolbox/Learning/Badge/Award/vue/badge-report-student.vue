<script>

/**
 * An individual student within the badge-report
 */

import { gsap } from 'gsap/all';
import BadgeImage from '../../vue/badge-image';
import { getIndex } from '@js/util/domUtils';
import HorizontalScroller from '@js/scroll/horizontalScroller';

const BADGE_ANIMATE_DURATION = 0.3;

export default {
    
    components: {
        BadgeImage,
    },

    props: {
        student: { //contains all the data needed to display
            type: Object,
            required: true
        },
        selectedStudents: { //to clear a students selection when cleared by badge-report
            type: Array,
            required: true
        }
    },

    computed: {

        /**
         * To check if there's badges to display
         * 
         * @returns { Boolean }
         */
        hasBadges () {
            if (this.student.badges) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * To ensure it's unchecked when 'Clear Selection' is selected in #badge-report
         * 
         * @returns { Boolean }
         */
        isInSelectedStudents () {
            return this.selectedStudents.includes(this.student.id);
        }

    },

    watch: {

        student: {
            deep: true,
            handler (newStudentData, oldStudentData) {
                if (newStudentData?.badges?.length !== oldStudentData?.badges?.length) {
                    setTimeout(() => { 
                        this.horizontalScroller.refresh();
                    }, BADGE_ANIMATE_DURATION * 2000);
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
            if (this.hasBadges) { //only if there's badges to show
                this.horizontalScroller = new HorizontalScroller(this.$el.querySelector('[data-scroller]')); 
            }
            
        },

        /**
         * Open the selected award detail modal
         * 
         * @params { Object } 
         */
        badgeSelected (award) {
            this.$emit('on-badge-selected', award);
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
            gsap.to(element, { opacity: 1, y: 0, ease: 'power1.out', duration: BADGE_ANIMATE_DURATION, delay: BADGE_ANIMATE_DURATION, onComplete: done } );
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

        /**
         * To be able to award badges to multiple students
         * 
         * @params { Boolean } 
         */
        onStudentSelected (isChecked) {
            this.$emit('on-student-selected', this.student.id, isChecked);
        },

    }

}
</script>

<template>
    <li class="awards student">
        <div class="student__checkbox">
            <div class="checklist">
                <input 
                    :id="`checkbox-${student.id}`"
                    type="checkbox"
                    :value="student.id"
                    :checked="isInSelectedStudents"
                    @change="onStudentSelected($event.target.checked)"
                />
                <label :for="`checkbox-${student.id}`"></label>
            </div>
        </div>
        <div class="student__portrait-and-name">
            <div class="portrait-and-name-container">
                <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="`profile photo of ${student.fullName}`" class="portrait">
                <template v-if="student.id">
                    <a :href="`/search/user/${student.id}`" title="View profile" class="name">
                        {{ student.lastName + ', ' + student.firstName }}
                    </a>
                </template>
                <template v-else>
                    <span class="name">{{ student.lastName + ', ' + student.firstName }}</span>
                </template> 
            </div>
        </div>
        <div class="student__badges">
            <div v-if="hasBadges" class="scroller-container" data-scroller>
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
                            <li v-for="award in student.badges" :key="award.id" class="award" data-scroller-item :data-title="award.badge.name">
                                <a href="#open-award-modal" @click.prevent="badgeSelected(award)" @keyup.enter="badgeSelected(award)" :title="award.badge.name">
                                    <div class="card-feature-image">
                                        <badge-image :badge="award.badge" :css="award.isGoal ? 'badge goal' : 'badge'"></badge-image>
                                    </div>
                                    <div class="card-content">
                                        <p>{{ award.badge.name }}</p>
                                    </div>
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
            <div v-else class="empty-state">
                <i class="icon-badge"></i>
                <p>No badges to display.</p>
            </div>
        </div>
    </li>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

$rowHeight: 150px;
$collapseStudent: 1000px;

.empty-state {
    height: $rowHeight;
}

.student {
    display: flex;
    width: 100%;
    height: $rowHeight;
    overflow: hidden;
    margin: 0 0 0.625rem 0;
    padding: 0;
    @media (max-width: $collapseStudent) {
        flex-wrap: wrap;
        height: auto;
        overflow: visible;
    }
}

.student__checkbox {
    width: 40px;
    padding-bottom: 0;
}

.student__portrait-and-name {
    width: 260px;
    padding-bottom: 0;
    @media (max-width: $collapseStudent) {
        margin-bottom: 1rem;
    }
}

.portrait-and-name-container {
    display: flex;
}

.portrait {
    margin-right: 1rem;
    @media (max-width: $collapseStudent) {
        width: 50px;
        height: auto;
    }
}

.student__badges {
    width: calc(100% - 300px); //minus the checkbox and name
    @media (max-width: $collapseStudent) {
        width: 100%;
    }
}

.scroller-container {
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

.card-feature-image {
    position: relative;
    padding: 0.25rem 0.5rem;
    background-color: #F6F6F6;
    text-align: center;
}

.card-content {
    padding: 0.25rem 0.5rem;
}

.award::v-deep {
    display: inline-block;
    width: 160px;
    height: $rowHeight - 10px;
    border: 1px solid #EDEDED;
    box-shadow: 0 2px 3px 0 rgb(0 0 0 / 10%);
    margin: 0px 0.5rem;
    padding: 0;
    vertical-align: top;
    img {
        width: 40px;
        height: auto;
        margin-bottom: 5px;
    }
    p {
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