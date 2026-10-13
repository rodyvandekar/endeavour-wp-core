(function ($) {
	'use strict';

	/**
	 * Slides on display is a number. Auto is not, and the handler falls back
	 * to 3, which sizes every slide to a fraction of the row. A fixed item
	 * width only works when Swiper measures the slide itself.
	 */
	function useSlideWidth(swiper) {
		if (!swiper || swiper.destroyed) {
			return;
		}

		// A matching breakpoint puts slidesPerView back to a number on update.
		// Auto has to win at every breakpoint once a fixed width is set.
		function force(points) {
			if (!points) {
				return;
			}

			Object.keys(points).forEach(function (key) {
				points[key].slidesPerView = 'auto';
				points[key].slidesPerGroup = 1;
			});
		}

		swiper.params.slidesPerView = 'auto';
		swiper.params.slidesPerGroup = 1;

		if (swiper.originalParams) {
			swiper.originalParams.slidesPerView = 'auto';
			swiper.originalParams.slidesPerGroup = 1;
		}

		force(swiper.params.breakpoints);
		force(swiper.originalParams && swiper.originalParams.breakpoints);

		swiper.update();
	}

	function whenReady($scope, tries) {
		var swiper = $scope.find('.swiper').data('swiper');

		if (swiper) {
			useSlideWidth(swiper);
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
		// jQuery .data() reads data-endeavour-slide-width, so the guard needs
		// its own key. Otherwise the width JSON looks like we already ran.
		if (!$scope.attr('data-endeavour-slide-width') || $scope.data('endeavourSlideWidthBound')) {
			return;
		}

		$scope.data('endeavourSlideWidthBound', true);
		whenReady($scope, 0);
	}

	function bind() {
		elementorFrontend.hooks.addAction('frontend/element_ready/loop-carousel.default', onCarousel);
		$('.elementor-widget-loop-carousel[data-endeavour-slide-width]').each(function () {
			onCarousel($(this));
		});
	}

	$(window).on('elementor/frontend/init', bind);

	if (window.elementorFrontend && elementorFrontend.hooks) {
		bind();
	}
})(jQuery);
