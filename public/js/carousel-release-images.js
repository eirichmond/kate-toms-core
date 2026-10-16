/**
 * Keep the Jetpack carousel's memory bounded while swiping (#487).
 *
 * Jetpack loads a full-size image into each slide as it is visited and never lets go of
 * it, so every photo seen stays decoded until the carousel closes. With the uncropped
 * 1536px files a long house gallery adds up to hundreds of MB, and iOS Safari kills the
 * tab ("A problem repeatedly occurred"). Here, slides more than a couple of places from
 * the current one drop their image and are marked unloaded, so Jetpack loads them again
 * (from the HTTP cache) if the visitor comes back.
 */
( function () {
	// Jetpack preloads one slide either side; keep one more so a quick swipe never shows a reload.
	var KEEP_DISTANCE = 2;

	function releaseDistantSlides( swiper ) {
		var slides = swiper.el.querySelectorAll( '.swiper-slide[data-swiper-slide-index]' );
		var total = slides.length;
		var active = swiper.realIndex;

		slides.forEach( function ( slide ) {
			var image = slide.querySelector( 'img[data-loaded]' );
			if ( ! image ) {
				return;
			}

			var distance = Math.abs( Number( slide.getAttribute( 'data-swiper-slide-index' ) ) - active );
			// The carousel loops, so the first and last slides are neighbours.
			if ( Math.min( distance, total - distance ) <= KEEP_DISTANCE ) {
				return;
			}

			image.removeAttribute( 'data-loaded' );
			image.removeAttribute( 'data-zoom-loaded' );
			image.removeAttribute( 'src' );
			image.style.filter = '';
		} );
	}

	document.addEventListener( 'jp_carousel.afterOpen', function ( event ) {
		var container = event.target.querySelector( '.jp-carousel-swiper-container' );
		var swiper = container && container.swiper;
		if ( ! swiper ) {
			return;
		}

		// Jetpack destroys the swiper on close, which removes this listener with it.
		swiper.on( 'slideChange', function () {
			releaseDistantSlides( swiper );
		} );
	} );
} )();
