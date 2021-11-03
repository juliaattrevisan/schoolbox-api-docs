
/**
 * 'Pop up' the badge and throw some stars
 */

import { gsap, Physics2DPlugin } from 'gsap/all';
import Star from './Star';
import { getCanvasDimensions, getContext, getStarCount } from '../animateBadgeImage';

gsap.registerPlugin(Physics2DPlugin);


/**
 * Set up both the badge and the stars animations
 * 
 * @param { DOMElement }
 */
export async function init (badgeImage) {
    animateBadge(badgeImage);
    animateStars();
}

/**
 * Pop up the badge from the center, with a bit of elastic 'bounce'
 * 
 * @param { DOMElement }
 */
function animateBadge (badgeImage) {
    const dropDownTL = gsap.timeline( { repeat: 0 } ); 
    dropDownTL.set(badgeImage, { y: -500, transformOrigin: '50% 70%', transformStyle: 'preserve-3d', scaleX: 0, filter: 'grayscale(100%)' } );
    dropDownTL.to(badgeImage, { duration: 1.2, y: 0, scaleX: 1, transformStyle: 'preserve-3d', ease: 'elastic.out(1, 0.3)', delay: 0.5 } );
    dropDownTL.to(badgeImage, { duration: 0.1, filter: 'grayscale(0%)' }, '-=0.7' );
}

/**
 * Get all the stars, add an anmation for each, and ensure the Canvas is redrawn on each update
 */
function animateStars () {
    const stars = getStars(); 
    const starsTimeline = gsap.timeline( { //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.6, //wait prior to animating the stars to link when the pop up has 'hit' the screen
        repeat: 0, 
        onUpdate: () => redrawStars(stars) //to draw the new position of the stars each tick
    } ); 
    stars.forEach((star) => addStarAnimation(starsTimeline, star));
}

/**
 * Set up an array of Stars with random bright colours
 * Put them in the middle bottom of the canvas, ready to animate out from the middle bottom
 * 
 * @return { Array }
 */
function getStars () {
    const stars = [];
    const starCount = getStarCount();
    const canvasDimensions = getCanvasDimensions();
    for (let i = 0; i < starCount; i++) { 
        const star = new Star({
            x: gsap.utils.random(0, canvasDimensions.width), 
            y: canvasDimensions.height + 50, 
            radius: gsap.utils.random(20, 50),
        });
        stars.push(star);
    }
    return stars;
}

/**
 * Each star needs to be removed and redrawn each 'tick'
 * 
 * @param { Array }
 */
function redrawStars (stars) {
    const canvasDimensions = getCanvasDimensions();
    const context = getContext();
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

/**
 * Each star has an animation, and the properties to animate are specified within
 * Through the onUpdate of the timeline, the properties are updated each tick
 * 
 * @param { gsap.timeline }
 * @param { Star }
 */
function addStarAnimation (starsTimeline, star) {
    starsTimeline.to(star, {
        delay: 'random(0, 0.25)', 
        duration: 3,
        radius: 'random(0, 0.3)',
        rotation: 'random(0, 360)',
        physics2D: { //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
            acceleration: 0,
            angle: 'random(180, 360)',
            accelerationAngle: 'random(0, 40)',
            friction: 'random(0, 0.3)',
            gravity: 'random(100, 400)',
            velocity: 'random(600, 850)',
        },
    }, 0 //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}