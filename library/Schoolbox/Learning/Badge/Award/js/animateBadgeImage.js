import { init as initPopUp } from "./animations/popUp";
import { init as initFlipUp } from "./animations/flipUp";
import { init as initDropDown } from "./animations/dropDown";
import { init as initSwingDown } from "./animations/swingDown";
import { init as initCoinSpin } from "./animations/coinSpin";
import { init as initSpinIn } from "./animations/spinIn";

/**
 * Animate the badge, acheievements should be celebrated a little!
 * Put the list of animations in an array so one can be randomly chosen and played
 *
 * @param { DOMElement }
 */
export function animateBadgeImage(badgeImage, awardCanvas) {
    clearCanvas(awardCanvas);
    // const animationList = [initSpinIn]; //to test just one animation
    const animationList = [initPopUp, initFlipUp, initDropDown, initSwingDown, initCoinSpin, initSpinIn];
    const initRandomAnimation = animationList[Math.floor(Math.random() * animationList.length)];
    initRandomAnimation(badgeImage, awardCanvas);
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 * @param DOMElement canvas
 */
export function clearCanvas(canvas) {
    const canvasDimensions = getCanvasDimensions(canvas);
    const context = getContext(canvas);
    context.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
}

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 * @return { Number, Number }
 * @param DOMElement
 */
export function getCanvasDimensions(canvas) {
    return {
        width: canvas.width,
        height: canvas.height,
    };
}

/**
 * Get the context
 * https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
 *
 * @return { CanvasRenderingContext2D }
 * @param DOMElement
 */
export function getContext(canvas) {
    return canvas.getContext("2d");
}

/**
 * Will be decreased on smaller screens, as there's not as much space to show them.
 * This gives us about 600 at max width (972px) and decreases as we get down to around 168 at (272px),
 * which is an iPhone 5, a pretty small screen.
 *
 * @return { Number }
 * @param DOMElement
 */
export function getStarCount(canvas) {
    const currentCanvasDimensions = getCanvasDimensions(canvas);
    return Math.round(currentCanvasDimensions.width * 0.62);
}
