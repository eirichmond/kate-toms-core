/******/ (() => { // webpackBootstrap
/*!********************************************!*\
  !*** ./blocks/kateandtoms-reviews/view.js ***!
  \********************************************/
/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

/* eslint-disable no-console */
console.log('Hello World! (from create-block-kateandtoms-reviews block)');
/* eslint-enable no-console */

document.addEventListener('DOMContentLoaded', function () {
  const reviews = document.querySelectorAll('.review-item');
  let currentIndex = 0;
  let intervalId;

  // Function to show next review
  function showNextReview() {
    reviews.forEach((review, index) => {
      if (index === currentIndex) {
        review.classList.add('visible');
      } else {
        review.classList.remove('visible');
      }
    });
    currentIndex = (currentIndex + 1) % reviews.length;
  }

  // Start the animation
  function startAnimation() {
    showNextReview();
    intervalId = setInterval(showNextReview, 5000);
  }

  // Pause animation on hover/touch
  reviews.forEach(review => {
    review.addEventListener('mouseenter', () => {
      clearInterval(intervalId);
      review.classList.add('paused');
    });
    review.addEventListener('mouseleave', () => {
      review.classList.remove('paused');
      startAnimation();
    });
    review.addEventListener('touchstart', () => {
      clearInterval(intervalId);
      review.classList.add('paused');
    });
    review.addEventListener('touchend', () => {
      review.classList.remove('paused');
      startAnimation();
    });
  });

  // Start the initial animation
  startAnimation();
});
/******/ })()
;
//# sourceMappingURL=view.js.map