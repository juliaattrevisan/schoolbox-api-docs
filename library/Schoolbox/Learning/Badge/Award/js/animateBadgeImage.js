
import { gsap } from 'gsap/all';
import { init as initPopUp } from './animations/popUp';
import { init as initFlipUp } from './animations/flipUp';
import { init as initDropDown } from './animations/dropDown';
import { init as initSwingDown } from './animations/swingDown';
import { init as initCoinSpin } from './animations/coinSpin';

const CANVAS_SELECTOR = '[data-badge-award-canvas]';


/**
 * Spin in animation
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const spinIn = (badgeImage) => {
    const spinInTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    spinInTL.set(badgeImage, { opacity: 0, scale: 3, rotation: 720, transformOrigin: '50% 50%', filter: 'grayscale(100%)' } );
    spinInTL.to(badgeImage, { duration: 1, opacity: 1, scale: 1, rotation: 0, scale: 1, delay: 0.5 } );
    spinInTL.to(badgeImage, { duration: 0.2, filter: 'grayscale(0%)' }, '-=0.2' );
    return spinInTL;
}

/**
 * Animate the badge, acheievements should be celebrated a little!
 * 
 * @param { DOMElement }
 */
export const animateBadgeImage = (badgeImage) => {
    // const animationList = [initPopUp];
    // const animationList = [initFlipUp];
    // const animationList = [initDropDown];
    // const animationList = [initSwingDown];
    const animationList = [initCoinSpin];
    const initRandomAnimation = animationList[Math.floor(Math.random()*animationList.length)];
    initRandomAnimation(badgeImage);
}

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 *  
 * @return { Number, Number }
 */
export function getCanvasDimensions () {
    const canvas = document.querySelector(CANVAS_SELECTOR);
    return { 
        width: canvas.width, 
        height: canvas.height 
    }
}

/**
 * Get the context
 * https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
 * 
 * @return { CanvasRenderingContext2D }
 */
export function getContext () {
    const canvas = document.querySelector(CANVAS_SELECTOR);
    return canvas.getContext('2d');
}