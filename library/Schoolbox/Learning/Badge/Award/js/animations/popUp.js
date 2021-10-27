
import { gsap, Physics2DPlugin } from 'gsap/all';
import tinycolor from 'tinycolor2';
import Star from './Star';

gsap.registerPlugin(Physics2DPlugin);



export function init (badgeImage) {
    
    const canvas = document.querySelector('[data-canvas-background]');
    const ctx = canvas.getContext('2d');
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;
    
    animateBadge(badgeImage);
    const starCount = 600;
    const starList = [];
    for (let i = 0; i < starCount; i++) { 
        const randomBrightColour = tinycolor({ h: gsap.utils.random(0, 255), s: 100, l: 50 });
        const star = new Star(486, 150, randomBrightColour.toHexString(), gsap.utils.random(5, 50), 0);
        starList.push(star);
    }
    ctx.clearRect(0, 0, canvasWidth, canvasHeight);
    setTimeout(() => {
        animateStars(starList);
        gsap.ticker.add(() => {
            ctx.clearRect(0, 0, canvasWidth, canvasHeight);
            starList.forEach((star) => star.draw(ctx));
        });
    }, 525);

}

/**
 * animateStars
 * 
 * @param { Array }
 * 
 */
 function animateStars (starList) {
    starList.forEach((star) => {
        gsap.to(star, {
            delay: 'random(0, 0.25)',
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
 * animateBadge
 * 
 * @param { DOMElement }
 * 
 */
 function animateBadge (badgeImage) {
    const popUpTL = gsap.timeline( { repeat: 0 } ); 
    popUpTL.set(badgeImage, { scale: 0, transformOrigin: '50% 50%', filter: 'grayscale(100%)' } );
    popUpTL.to(badgeImage, { duration: 2, scale: 1, ease: 'elastic.out', delay: 0.5 } );
    popUpTL.to(badgeImage, { duration: 0.15, filter: 'grayscale(0%)' }, '-=1.75');
}