(function () {
	'use strict';

	var observer;
	var suffixes = ['', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile'];

	function customWidthEnabled(settings) {
		return 'yes' === settings.get('endeavour_custom_slide_width');
	}

	function forceAuto(settings) {
		if (!customWidthEnabled(settings)) {
			return;
		}

		['slides_to_show', 'slides_to_scroll'].forEach(function (control) {
			suffixes.forEach(function (suffix) {
				var key = control + suffix;

				if (settings.get(key) !== 'auto') {
					settings.set(key, 'auto');
				}
			});
		});
	}

	function lockAutoControls(settings) {
		var locked = customWidthEnabled(settings);

		document.querySelectorAll(
			'.elementor-control-slides_to_show select, .elementor-control-slides_to_scroll select'
		).forEach(function (select) {
			select.disabled = locked;
			select.setAttribute(
				'title',
				locked ? 'Controlled by Enable custom width loop item' : ''
			);
		});
	}

	function refresh(settings) {
		forceAuto(settings);

		window.setTimeout(function () {
			lockAutoControls(settings);
		}, 0);
	}

	function bind() {
		elementor.hooks.addAction('panel/open_editor/widget', function (panel, model) {
			if (observer) {
				observer.disconnect();
				observer = null;
			}

			if ('loop-carousel' !== model.get('widgetType')) {
				return;
			}

			var settings = model.get('settings');
			var panelRoot = document.querySelector('#elementor-panel-content-wrapper');

			refresh(settings);

			if (settings.endeavourLoopWidthHandler) {
				settings.off('change', settings.endeavourLoopWidthHandler);
			}

			settings.endeavourLoopWidthHandler = function () {
				refresh(settings);
			};
			settings.on('change', settings.endeavourLoopWidthHandler);

			if (panelRoot) {
				observer = new MutationObserver(function () {
					lockAutoControls(settings);
				});
				observer.observe(panelRoot, { childList: true, subtree: true });
			}
		});
	}

	window.addEventListener('elementor:init', bind);

	if (window.elementor && elementor.hooks) {
		bind();
	}
})();
