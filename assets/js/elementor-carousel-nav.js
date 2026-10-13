(function ($) {
	'use strict';

	function carouselElement(nav) {
		var id = nav.getAttribute('data-carousel');
		var match = id && document.querySelector('.elementor-element-' + CSS.escape(id));

		if (match) {
			return match;
		}

		var widget = nav.closest('.elementor-element');
		var carousels = Array.prototype.slice.call(document.querySelectorAll('.elementor-widget-loop-carousel, .elementor-widget-image-carousel'));
		var previous = carousels.filter(function (carousel) {
			return carousel.compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING;
		});

		return previous.length ? previous[previous.length - 1] : null;
	}

	function swiperOf(carousel) {
		var element = carousel && carousel.querySelector('.swiper');

		if (!element) {
			return null;
		}

		return element.swiper || $(element).data('swiper') || null;
	}

	function setDisabled(button, disabled) {
		button.disabled = disabled;
		button.classList.toggle('is-disabled', disabled);
		button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
	}

	function sync(nav, swiper) {
		var loop = swiper.params.loop;
		var prev = nav.querySelector('[data-direction="prev"]');
		var next = nav.querySelector('[data-direction="next"]');

		if (prev) {
			setDisabled(prev, !loop && swiper.isBeginning);
		}

		if (next) {
			setDisabled(next, !loop && swiper.isEnd);
		}
	}

	function bind(nav, tries) {
		if (nav.dataset.endeavourCarouselNav) {
			return;
		}

		var swiper = swiperOf(carouselElement(nav));

		if (!swiper) {
			if (tries < 40) {
				window.setTimeout(function () {
					bind(nav, tries + 1);
				}, 50);
			}
			return;
		}

		nav.dataset.endeavourCarouselNav = 'true';

		nav.addEventListener('click', function (event) {
			var button = event.target.closest('[data-direction]');

			if (!button || button.disabled) {
				return;
			}

			if (button.getAttribute('data-direction') === 'prev') {
				swiper.slidePrev();
			} else {
				swiper.slideNext();
			}
		});

		swiper.on('slideChange', function () {
			sync(nav, swiper);
		});
		swiper.on('reachBeginning reachEnd fromEdge', function () {
			sync(nav, swiper);
		});
		sync(nav, swiper);
	}

	function start() {
		document.querySelectorAll('.endeavour-carousel-nav').forEach(function (nav) {
			bind(nav, 0);
		});
	}

	$(window).on('elementor/frontend/init', function () {
		elementorFrontend.hooks.addAction('frontend/element_ready/endeavour-carousel-nav.default', function ($scope) {
			var nav = $scope.find('.endeavour-carousel-nav').get(0);

			if (nav) {
				bind(nav, 0);
			}
		});
		start();
	});

	if (document.readyState !== 'loading') {
		start();
	} else {
		document.addEventListener('DOMContentLoaded', start);
	}
})(jQuery);
