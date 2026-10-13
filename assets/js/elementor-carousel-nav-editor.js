(function () {
	'use strict';

	/**
	 * PHP already lists the Loop Carousels saved on the document. This script
	 * only adds carousels that exist in the open editor but are not saved yet.
	 * It never re-renders the control: render() rebuilds the select from a
	 * copy of the options and was wiping the list back to the placeholder.
	 */

	function addOption(options, id, title) {
		if (!id) {
			return;
		}

		options[id] = (title || 'Loop carousel') + ' (' + id + ')';
	}

	function walkModels(collection, options, seen) {
		if (!collection || seen.indexOf(collection) !== -1) {
			return;
		}

		seen.push(collection);

		var models = collection.models || (Array.isArray(collection) ? collection : null);

		if (!models && typeof collection.each === 'function') {
			collection.each(function (model) {
				visitModel(model, options, seen);
			});
			return;
		}

		if (models) {
			models.forEach(function (model) {
				visitModel(model, options, seen);
			});
		}
	}

	function visitModel(model, options, seen) {
		if (!model || typeof model.get !== 'function') {
			return;
		}

		if (model.get('widgetType') === 'loop-carousel' || model.get('widgetType') === 'image-carousel') {
			var settings = model.get('settings');
			var title = settings && typeof settings.get === 'function' ? settings.get('_title') : '';

			addOption(options, model.get('id'), title);
		}

		walkModels(model.get('elements'), options, seen);
	}

	function fromPreview(options) {
		var doc = null;
		var frame = document.getElementById('elementor-preview-iframe');

		if (frame && frame.contentDocument) {
			doc = frame.contentDocument;
		}

		if (!doc && window.elementor && elementor.$previewContents && elementor.$previewContents[0]) {
			var node = elementor.$previewContents[0];
			doc = node.nodeType === 9 ? node : node.ownerDocument;
		}

		if (!doc || !doc.querySelectorAll) {
			return;
		}

		doc.querySelectorAll('.elementor-widget-loop-carousel, .elementor-widget-image-carousel').forEach(function (element) {
			var kind = element.classList.contains('elementor-widget-image-carousel') ? 'Image carousel' : 'Loop carousel';

			addOption(options, element.getAttribute('data-id'), kind);
		});
	}

	function fromContainers(options) {
		if (!window.elementor || typeof elementor.getPreviewContainer !== 'function') {
			return;
		}

		var root;

		try {
			root = elementor.getPreviewContainer();
		} catch (error) {
			return;
		}

		var seen = [];

		function walk(container) {
			if (!container || seen.indexOf(container) !== -1) {
				return;
			}

			seen.push(container);

			var model = container.model;

			if (model && typeof model.get === 'function' && (model.get('widgetType') === 'loop-carousel' || model.get('widgetType') === 'image-carousel')) {
				var settings = model.get('settings');
				var title = settings && typeof settings.get === 'function' ? settings.get('_title') : '';

				addOption(options, container.id || model.get('id'), title);
			}

			var children = container.children;

			if (!children) {
				return;
			}

			if (typeof children.forEach === 'function') {
				children.forEach(walk);
			} else if (children.models) {
				children.models.forEach(walk);
			}
		}

		walk(root);
	}

	function collect(current) {
		var options = {
			'': 'Select a loop carousel',
		};
		var seen = [];

		fromContainers(options);

		if (window.elementor && elementor.elements) {
			walkModels(elementor.elements, options, seen);
		}

		fromPreview(options);

		if (current && !options[current]) {
			options[current] = current;
		}

		return options;
	}

	function hasCarousel(options) {
		return Object.keys(options).some(function (key) {
			return key !== '';
		});
	}

	function controlViews(pageView) {
		var views = [];

		if (!pageView || !pageView.children || typeof pageView.children.each !== 'function') {
			return views;
		}

		pageView.children.each(function (sectionView) {
			if (!sectionView.children || typeof sectionView.children.each !== 'function') {
				return;
			}

			sectionView.children.each(function (controlView) {
				if (controlView.model && controlView.model.get('name') === 'carousel_id') {
					views.push(controlView);
				}
			});
		});

		return views;
	}

	function fillSelect(select, options, value) {
		var keys = Object.keys(options);

		select.textContent = '';

		keys.forEach(function (key) {
			var option = document.createElement('option');
			var label = options[key];

			option.value = key;
			option.textContent = typeof label === 'string' ? label : label.title || key;
			select.appendChild(option);
		});

		if (value && Object.prototype.hasOwnProperty.call(options, value)) {
			select.value = value;
		}
	}

	function apply(model) {
		if (!model || typeof model.get !== 'function') {
			return;
		}

		var settings = model.get('settings');

		if (!settings || typeof settings.get !== 'function') {
			return;
		}

		var current = settings.get('carousel_id') || '';
		var options = collect(current);

		// A failed lookup must leave the PHP options in place. Replacing them
		// with only the placeholder is what blanked the closed select.
		if (!hasCarousel(options)) {
			return;
		}

		if (settings.controls && settings.controls.carousel_id) {
			settings.controls.carousel_id.options = options;
		}

		var cache =
			window.elementor &&
			elementor.widgetsCache &&
			elementor.widgetsCache['endeavour-carousel-nav'];

		if (cache && cache.controls && cache.controls.carousel_id) {
			cache.controls.carousel_id.options = options;
		}

		var panel = elementor.getPanelView && elementor.getPanelView();
		var pageView = panel && panel.getCurrentPageView && panel.getCurrentPageView();

		controlViews(pageView).forEach(function (controlView) {
			if (controlView.model && typeof controlView.model.set === 'function') {
				controlView.model.set('options', options, { silent: true });
			}

			var select = controlView.$el && controlView.$el.find('select[data-setting="carousel_id"]')[0];

			if (select) {
				fillSelect(select, options, current || select.value);
			}
		});
	}

	function schedule(model) {
		[0, 300, 1000].forEach(function (delay) {
			window.setTimeout(function () {
				apply(model);
			}, delay);
		});
	}

	function bind() {
		if (bind.done) {
			return;
		}

		bind.done = true;

		elementor.hooks.addAction(
			'panel/open_editor/widget/endeavour-carousel-nav',
			function (panel, model) {
				schedule(model);
			}
		);
	}

	window.addEventListener('elementor:init', bind);

	if (window.elementor && elementor.hooks) {
		bind();
	}
})();
