
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
    const animationList = [initPopUp];
    // const animationList = [initFlipUp];
    // const animationList = [initDropDown];
    // const animationList = [initSwingDown];
    // const animationList = [initCoinSpin];
    // const animationList = [initSpinIn];
    clearCanvas();
    // const animationList = [initPopUp, initFlipUp, initDropDown, initSwingDown, initCoinSpin, initSpinIn];
    const initRandomAnimation = animationList[Math.floor(Math.random()*animationList.length)];
    initRandomAnimation(badgeImage);
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 */
function clearCanvas () {
    const canvasDimensions = getCanvasDimensions();
    const context = getContext();
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
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

/**
 * Will be decreased on smaller screens, as there's not as 
 * much space to show them
 *  
 * @return { Number }
 */
 export function getStarCount () {
    return 600;
}