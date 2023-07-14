import { init as initPopUp } from "./animations/popUp";
import { init as initFlipUp } from "./animations/flipUp";
import { init as initDropDown } from "./animations/dropDown";
import { init as initSwingDown } from "./animations/swingDown";
import { init as initCoinSpin } from "./animations/coinSpin";
import { init as initSpinIn } from "./animations/spinIn";
import { clearCanvas } from "@js/util/canvasUtils";

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
