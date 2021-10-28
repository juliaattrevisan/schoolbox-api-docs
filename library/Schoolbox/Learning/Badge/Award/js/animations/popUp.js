
import { gsap, Physics2DPlugin } from 'gsap/all';
import tinycolor from 'tinycolor2';
import Star from './Star';
import { timeout } from '@js/util/jsUtils';
import { getCanvasDimensions, getContext } from '../animateBadgeImage';

gsap.registerPlugin(Physics2DPlugin);


/**
 * 
 * 
 * @param { DOMElement }
 */
export async function init (badgeImage) {
    animateBadge(badgeImage);
    await timeout(525); //wait prior to animating the stars to link when the pop up has 'hit' the screen
    const stars = getStars(); 
    setupStarsAnimations(stars);
    gsap.ticker.add(() => redrawStars(stars));
}

/**
 * 
 * 
 * @param { DOMElement }
 */
function animateBadge (badgeImage) {
    const popUpTL = gsap.timeline( { repeat: 0 } ); 
    popUpTL.set(badgeImage, { scale: 0, transformOrigin: '50% 50%', filter: 'grayscale(100%)' } );
    popUpTL.to(badgeImage, { duration: 2, scale: 1, ease: 'elastic.out', delay: 0.5 } );
    popUpTL.to(badgeImage, { duration: 0.15, filter: 'grayscale(0%)' }, '-=1.75');
}

/**
 * Set up an array of Stars with random bright colours
 * Put them in the middle of the canvas, ready to animate out from the centre
 * 
 * @return { Array }
 */
function getStars () {
    const stars = [];
    const starCount = 600;
    const canvasDimensions = getCanvasDimensions();
    for (let i = 0; i < starCount; i++) { 
        const randomBrightColour = tinycolor({ h: gsap.utils.random(0, 255), s: 100, l: 50 });
        const star = new Star(canvasDimensions.width / 2, canvasDimensions.height / 2, randomBrightColour.toHexString(), gsap.utils.random(5, 50), 0);
        stars.push(star);
    }
    return stars;
}

/**
 * 
 * 
 * @param { Array }
 */
function setupStarsAnimations (stars) {
    stars.forEach((star) => {
        gsap.to(star, {
            delay: 'random(0, 0.25)', //525
            radius: 'random(0, 0.3)',
            rotation: 'random(0, 360)',
            duration: 3,
            physics2D: { //https://greensock.com/docs/v3/Plugins/Physics2DPlugin
                acceleration: 0,
                angle: 'random(0, 360)',
                accelerationAngle: 'random(0, 0.1)',
                friction: 'random(0, 0.1)',
                gravity: 'random(300, 500)',
                velocity: 'random(500, 1550)',
            },
        });
    });
}

/**
 * 
 * 
 * @param { Array }
 */
function redrawStars (stars) {
    const canvasDimensions = getCanvasDimensions();
    const context = getContext();
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
    stars.forEach((star) => star.draw(context));
}

