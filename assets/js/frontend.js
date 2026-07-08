/**
 * RC RaceMap Club Calendar – Frontend-Interaktion.
 *
 * Schaltet zwischen den Tabs "Kommende Rennen" und "Archiv" um, ohne die Seite
 * neu zu laden. Keine externen Abhängigkeiten. Mehrere Kalender-Instanzen auf
 * einer Seite werden unabhängig voneinander behandelt.
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function () {
	'use strict';

	/**
	 * Einen einzelnen Kalender initialisieren.
	 *
	 * @param {HTMLElement} root Wurzelelement ([data-rc-rcc]).
	 */
	function initCalendar( root ) {
		var tabs = Array.prototype.slice.call(
			root.querySelectorAll( '[data-rc-rcc-tab]' )
		);
		var panels = Array.prototype.slice.call(
			root.querySelectorAll( '[data-rc-rcc-panel]' )
		);

		if ( ! tabs.length || ! panels.length ) {
			return;
		}

		/**
		 * Einen Tab aktivieren und das zugehörige Panel anzeigen.
		 *
		 * @param {string}  name        Name des Ziel-Tabs.
		 * @param {boolean} focusTab    Fokus auf den aktiven Tab setzen.
		 */
		function activate( name, focusTab ) {
			tabs.forEach( function ( tab ) {
				var isActive = tab.getAttribute( 'data-rc-rcc-tab' ) === name;
				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', isActive ? '0' : '-1' );
				if ( isActive && focusTab ) {
					tab.focus();
				}
			} );

			panels.forEach( function ( panel ) {
				var isActive = panel.getAttribute( 'data-rc-rcc-panel' ) === name;
				panel.classList.toggle( 'is-active', isActive );
				if ( isActive ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', 'hidden' );
				}
			} );
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activate( tab.getAttribute( 'data-rc-rcc-tab' ), false );
			} );

			// Tastaturnavigation (Pfeiltasten, Home/End) für ARIA-Tabs.
			tab.addEventListener( 'keydown', function ( event ) {
				var newIndex = null;

				switch ( event.key ) {
					case 'ArrowRight':
					case 'ArrowDown':
						newIndex = ( index + 1 ) % tabs.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						newIndex = ( index - 1 + tabs.length ) % tabs.length;
						break;
					case 'Home':
						newIndex = 0;
						break;
					case 'End':
						newIndex = tabs.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();
				activate( tabs[ newIndex ].getAttribute( 'data-rc-rcc-tab' ), true );
			} );
		} );
	}

	/**
	 * Alle Kalender auf der Seite initialisieren.
	 */
	function init() {
		var roots = document.querySelectorAll( '[data-rc-rcc]' );
		Array.prototype.forEach.call( roots, initCalendar );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
