/**
 * Keep the icon picker usable after Font Awesome tabs are removed.
 *
 * Nav Menu and several other widgets ship a Recommended list keyed by
 * fa-solid / fa-regular. The picker always opens that tab, then reads
 * library.icons. Those libraries are gone, so the read throws
 * "Cannot read properties of undefined (reading 'icons')" and the modal
 * never renders.
 *
 * Drop recommendations that name a missing library. With nothing left, the
 * Recommended tab is not added and the first remaining library — Endeavour
 * Icons, moved to the front — is what opens.
 */
(function () {
	'use strict';

	function libraryNames() {
		var names = {};

		(elementor.config.icons.libraries || []).forEach(function (library) {
			names[library.name] = true;
		});

		return names;
	}

	function withoutMissingLibraries(recommended, names) {
		if (!recommended || 'object' !== typeof recommended) {
			return recommended;
		}

		var next = {};

		Object.keys(recommended).forEach(function (name) {
			if (names[name]) {
				next[name] = recommended[name];
			}
		});

		return Object.keys(next).length ? next : false;
	}

	function preferCustomLibraries() {
		var libraries = elementor.config.icons.libraries || [];
		var custom = [];
		var rest = [];

		libraries.forEach(function (library) {
			if ('all' !== library.name && !library.native) {
				custom.push(library);
			} else {
				rest.push(library);
			}
		});

		if (custom.length) {
			elementor.config.icons.libraries = custom.concat(rest);
		}
	}

	function guardPicker() {
		var manager = elementor.iconManager;

		if (!manager || 'function' !== typeof manager.onPickerShow || manager.endeavourPickerGuarded) {
			return;
		}

		var original = manager.onPickerShow;

		manager.onPickerShow = function () {
			var controlView = this.getSettings('controlView');
			var model = controlView && controlView.model;

			if (model && 'function' === typeof model.get && 'function' === typeof model.set) {
				var recommended = withoutMissingLibraries(model.get('recommended'), libraryNames());

				if (recommended !== model.get('recommended')) {
					model.set('recommended', recommended, { silent: true });
				}
			}

			return original.apply(this, arguments);
		};

		manager.endeavourPickerGuarded = true;
	}

	function boot() {
		if (!window.elementor || !elementor.config || !elementor.config.icons) {
			return;
		}

		preferCustomLibraries();
		guardPicker();
	}

	window.addEventListener('elementor:init', boot);

	if (window.elementor && elementor.hooks) {
		boot();
	}
})();
