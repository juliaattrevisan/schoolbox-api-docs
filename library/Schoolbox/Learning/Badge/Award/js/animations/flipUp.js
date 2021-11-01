
/**
 * 'Pop up' the badge and throw some stars
 */

import { gsap, Physics2DPlugin } from 'gsap/all';
import tinycolor from 'tinycolor2';
import Star from './Star';
import { getCanvasDimensions, getContext } from '../animateBadgeImage';

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
    const flipUpTL = gsap.timeline( { repeat: 0 } ); 
    flipUpTL.set(badgeImage, { transformOrigin: '50% 100%', transformStyle: 'preserve-3d', rotationX: 90, scaleX: 0.4, filter: 'grayscale(100%)' } );
    flipUpTL.to(badgeImage, { duration: 1.5, rotationX: 0, scaleX: 1, ease: 'back.out(3.5)', transformOrigin: '50% 100%', delay: 0.5 } );
    flipUpTL.to(badgeImage, { duration: 0.8, filter: 'grayscale(0%)' }, '-=0.7' );
}

/**
 * Get all the stars, add an anmation for each, and ensure the Canvas is redrawn on each update
 */
function animateStars () {
    const stars = getStars(); 
    const starsTimeline = gsap.timeline( { //https://greensock.com/docs/v3/GSAP/gsap.timeline()
        delay: 0.48, //wait prior to animating the stars to link when the pop up has 'hit' the screen
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
    const starCount = 600;
    const canvasDimensions = getCanvasDimensions();
    for (let i = 0; i < starCount; i++) { 
        const randomColour = tinycolor({ r: gsap.utils.random(0, 255), g: gsap.utils.random(0, 255), b: gsap.utils.random(0, 255) });
        const randomPastelColour = tinycolor.mix(randomColour.saturate(10), tinycolor('white'), 25); //desaturate a bit and mix in white to make it pastel
        const star = new Star(canvasDimensions.width / 2, canvasDimensions.height - 20, randomPastelColour.toHexString(), gsap.utils.random(20, 50), 0);
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
            accelerationAngle: 'random(0, 0.6)',
            friction: 'random(0, 0.2)',
            gravity: 'random(300, 400)',
            velocity: 'random(600, 850)',
        },
    }, 0 //0 so there's no delay between the animations, otherwise each star would only animate after the previous is complete
    );
}