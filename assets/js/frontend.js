/**
 * RC RaceMap Club Calendar – Frontend-Interaktion.
 *
 * Zwei Ebenen ohne Seitenreload:
 *   1. Haupt-Tabs   „Aktuelle Termine" / „Archiv"
 *   2. Jahres-Pills innerhalb jedes Tabs
 *
 * Keine externen Abhängigkeiten. Mehrere Kalender-Instanzen pro Seite werden
 * unabhängig behandelt; die Jahres-Navigation ist auf ihr Tab-Panel begrenzt.
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function () {
	'use strict';

	function slice( nodeList ) {
		return Array.prototype.slice.call( nodeList );
	}

	/**
	 * Wire a set of tab-like buttons to their panels (generic, reused for the
	 * main tabs and the year navigation).
	 *
	 * @param {HTMLElement[]} buttons   Buttons carrying btnAttr.
	 * @param {HTMLElement[]} panels    Panels carrying panelAttr.
	 * @param {string}        btnAttr   Attribute holding a button's key.
	 * @param {string}        panelAttr Attribute holding a panel's key.
	 */
	function wireGroup( buttons, panels, btnAttr, panelAttr ) {
		if ( ! buttons.length || ! panels.length ) {
			return;
		}

		function activate( name, focusButton ) {
			buttons.forEach( function ( button ) {
				var isActive = button.getAttribute( btnAttr ) === name;
				button.classList.toggle( 'is-active', isActive );
				button.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				button.setAttribute( 'tabindex', isActive ? '0' : '-1' );
				if ( isActive && focusButton ) {
					button.focus();
				}
			} );

			panels.forEach( function ( panel ) {
				var isActive = panel.getAttribute( panelAttr ) === name;
				panel.classList.toggle( 'is-active', isActive );
				if ( isActive ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', 'hidden' );
				}
			} );
		}

		buttons.forEach( function ( button, index ) {
			button.addEventListener( 'click', function () {
				activate( button.getAttribute( btnAttr ), false );
			} );

			button.addEventListener( 'keydown', function ( event ) {
				var newIndex = null;

				switch ( event.key ) {
					case 'ArrowRight':
					case 'ArrowDown':
						newIndex = ( index + 1 ) % buttons.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						newIndex = ( index - 1 + buttons.length ) % buttons.length;
						break;
					case 'Home':
						newIndex = 0;
						break;
					case 'End':
						newIndex = buttons.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();
				activate( buttons[ newIndex ].getAttribute( btnAttr ), true );
			} );
		} );
	}

	/**
	 * Initialise a single calendar instance.
	 *
	 * @param {HTMLElement} root Wurzelelement ([data-rc-rcc]).
	 */
	function initCalendar( root ) {

		// Level 1: main tabs.
		wireGroup(
			slice( root.querySelectorAll( '[data-rc-rcc-tab]' ) ),
			slice( root.querySelectorAll( '[data-rc-rcc-panel]' ) ),
			'data-rc-rcc-tab',
			'data-rc-rcc-panel'
		);

		// Level 2: year navigation, scoped to each main panel.
		slice( root.querySelectorAll( '[data-rc-rcc-panel]' ) ).forEach( function ( panel ) {
			wireGroup(
				slice( panel.querySelectorAll( '[data-rc-rcc-year]' ) ),
				slice( panel.querySelectorAll( '[data-rc-rcc-year-panel]' ) ),
				'data-rc-rcc-year',
				'data-rc-rcc-year-panel'
			);
		} );
	}

	function init() {
		slice( document.querySelectorAll( '[data-rc-rcc]' ) ).forEach( initCalendar );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
