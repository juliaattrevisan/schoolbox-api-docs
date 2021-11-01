
import { gsap } from 'gsap/all';
import { init as initPopUp } from './animations/popUp';
import { init as initFlipUp } from './animations/flipUp';
import { init as initDropDown } from './animations/dropDown';
import { init as initSwingDown } from './animations/swingDown';
import { init as initCoinSpin } from './animations/coinSpin';
import { init as initSpinIn } from './animations/spinIn';

const CANVAS_SELECTOR = '[data-badge-award-canvas]';


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
    // const animationList = [initCoinSpin];
    const animationList = [initSpinIn];
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