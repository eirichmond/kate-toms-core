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
 

let currentSlide = 0;

const slides = document.querySelectorAll(".slide");
const totalSlides = slides.length;

function showSlide(index) {
	slides.forEach((slide, i) => {
		if (i === index) {
			slide.classList.add("active");
		} else {
			slide.classList.remove("active");
		}
	});
}

function nextSlide() {
	currentSlide = (currentSlide + 1) % totalSlides;
	showSlide(currentSlide);
}

function startSlider() {

	showSlide(currentSlide);
	setInterval(nextSlide, 5000);
}

document.addEventListener("DOMContentLoaded", startSlider);
