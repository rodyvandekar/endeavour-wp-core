(function ($) {
	'use strict';

	/**
	 * Slides on display is a count, so every logo would be stretched to the
	 * same fraction of the row. Auto measures the image, which keeps the 40px
	 * height and the file's own width. The gap stays the Image Spacing control.
	 *
	 * The design centres the row on the page and lets logos run off both
	 * edges. The Image Carousel has no centred-slides control, so the active
	 * logo is centred here and the row starts at the middle of the set.
	 */
	function useImageWidth(swiper) {
		if (!swiper || swiper.destroyed) {
			return;
		}

		function force(points) {
			if (!points) {
				return;
			}

			Object.keys(points).forEach(function (key) {
				points[key].slidesPerView = 'auto';
				points[key].slidesPerGroup = 1;
				points[key].centeredSlides = true;
			});
		}

		[swiper.params, swiper.originalParams].forEach(function (params) {
			if (!params) {
				return;
			}

			params.slidesPerView = 'auto';
			params.slidesPerGroup = 1;
			params.centeredSlides = true;
			params.centerInsufficientSlides = false;
		});

		force(swiper.params.breakpoints);
		force(swiper.originalParams && swiper.originalParams.breakpoints);

		// Loop was built for a fixed slide count. Auto needs the duplicates
		// measured again, or the row jumps when it wraps.
		if (swiper.params.loop && typeof swiper.loopDestroy === 'function') {
			swiper.loopDestroy();
			swiper.loopCreate();
		}

		swiper.update();

		var count = swiper.wrapperEl.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').length;
		var middle = Math.floor(count / 2);

		if (swiper.params.loop) {
			swiper.slideToLoop(middle, 0, false);
		} else {
			swiper.slideTo(middle, 0, false);
		}
	}

	function whenReady($scope, tries) {
		var swiper = $scope.find('.swiper').data('swiper');

		if (swiper) {
			useImageWidth(swiper);
			return;
		}

		if (tries > 40) {
			return;
		}

		window.setTimeout(function () {
			whenReady($scope, tries + 1);
		}, 50);
	}

	function onCarousel($scope) {
		if (!$scope.hasClass('endeavour-logo-carousel') || $scope.data('endeavourLogoCarouselBound')) {
			return;
		}

		$scope.data('endeavourLogoCarouselBound', true);
		whenReady($scope, 0);
	}

	function bind() {
		elementorFrontend.hooks.addAction('frontend/element_ready/image-carousel.default', onCarousel);
		$('.elementor-widget-image-carousel.endeavour-logo-carousel').each(function () {
			onCarousel($(this));
		});
	}

	$(window).on('elementor/frontend/init', bind);

	if (window.elementorFrontend && elementorFrontend.hooks) {
		bind();
	}
})(jQuery);
