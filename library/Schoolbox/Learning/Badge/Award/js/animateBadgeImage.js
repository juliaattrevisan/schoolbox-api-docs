
import { gsap } from 'gsap/all'

/**
 * Pop up 
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const popUp = (badgeImage) => {
    const popUpTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    popUpTL.set(badgeImage, { scale: 0, transformOrigin: '50% 50%', filter: 'grayscale(100%)' } );
    popUpTL.to(badgeImage, { duration: 1.2, scale: 1, ease: 'bounce.out', delay: 0.5 } );
    popUpTL.to(badgeImage, { duration: 0.3, filter: 'grayscale(0%)' }, '-=80%');
    return popUpTL;
}

/**
 * Coin spin animation
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const coinSpin = (badgeImage) => {
    const coinSpinTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    coinSpinTL.set(badgeImage, { filter: 'grayscale(100%)', rotationY: 90 } );
    coinSpinTL.to(badgeImage, { duration: 1.5, rotationY: 360, ease: 'power2.out', delay: 0.5 } );
    coinSpinTL.to(badgeImage, { duration: 0.4, filter: 'grayscale(0%)' }, '-=0.5' );
    return coinSpinTL;
}

/**
 * Flip up animation
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const flipUp = (badgeImage) => {
    const flipUpTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    flipUpTL.set(badgeImage, { transformOrigin: '50% 100%', transformStyle: 'preserve-3d', rotationX: 90, scaleX: 0.4, filter: 'grayscale(100%)' } );
    flipUpTL.to(badgeImage, { duration: 1.5, rotationX: 0, scaleX: 1, ease: 'back.out(3.5)', transformOrigin: '50% 100%', delay: 0.5 } );
    flipUpTL.to(badgeImage, { duration: 0.8, filter: 'grayscale(0%)' }, '-=0.7' );
    return flipUpTL;
}

/**
 * Drop down animation
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const dropDown = (badgeImage) => {
    const dropDownTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    dropDownTL.set(badgeImage, { y: -500, transformOrigin: '50% 70%', transformStyle: 'preserve-3d', scaleX: 0, filter: 'grayscale(100%)' } );
    dropDownTL.to(badgeImage, { duration: 1.2, y: 0, scaleX: 1, transformStyle: 'preserve-3d', ease: 'elastic.out(1, 0.3)', delay: 0.5 } );
    dropDownTL.to(badgeImage, { duration: 0.1, filter: 'grayscale(0%)' }, '-=0.7' );
    return dropDownTL;
}

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
 * Swing down animation
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
const swingDown = (badgeImage) => {
    const swingDownTL = gsap.timeline( { paused: true, repeat: 0 } ); 
    swingDownTL.set(badgeImage, { rotationZ: 180, y: -50, transformOrigin: '50% 0%', filter: 'grayscale(100%)' } );
    swingDownTL.to(badgeImage, { duration: 2.3, y: 0, rotationZ: 0, ease: 'elastic', delay: 0.5 } );
    swingDownTL.to(badgeImage, { duration: 0.4, filter: 'grayscale(0%)' }, '-=0.6' );
    return swingDownTL;
}

/**
 * Slowly spin a scaled up badge
 * 
 * @param { DOMElement }
 * 
 * @returns { Timeline }
 */
 const scaleUpAndRotateSlowly = (badgeImage) => {
    const scaleUpAndRotateSlowlyTL = gsap.timeline( { paused: true, repeat: -1 } ); 
    scaleUpAndRotateSlowlyTL.set(badgeImage, { opacity: 0.1, scale: 4 } );
    scaleUpAndRotateSlowlyTL.to(badgeImage, { rotationZ: 360, y: 0, duration: 15, ease: 'none' } );
    return scaleUpAndRotateSlowlyTL;
}

/**
 * Play a random animation
 * 
 * @param { DOMElement }
 */
 const animateForeground = (badgeImage) => {
    const animationList = [popUp, coinSpin, flipUp, dropDown, spinIn, swingDown];
    const randomAnimation = animationList[Math.floor(Math.random()*animationList.length)](badgeImage);
    randomAnimation.play();
}

/**
 * Animate the badge, acheievements should be celebrated a little!
 * 
 * @param { DOMElement }
 */
export const animateBadgeImage = (badgeImage) => {
    animateForeground(badgeImage)
}
