( function () {
	'use strict';
	
	const data           = window.compilekitAdmin || {};
	const messages       = data.messages || {};
	const defaultMessage = data.working || 'Working…';
	
	function buildOverlay() {
		const overlay = document.createElement('div');
		
		overlay.id = 'compilekit-overlay';
		overlay.setAttribute( 'role', 'alert' );
		overlay.setAttribute( 'aria-live', 'assertive' );
		overlay.setAttribute( 'aria-busy', 'true' );
		overlay.innerHTML =
			'<div class="compilekit-overlay__box">' +
			'<span class="compilekit-overlay__spinner" aria-hidden="true"></span>' +
			'<p class="compilekit-overlay__text"></p>' +
			'<p class="compilekit-overlay__hint"></p>' +
			'</div>';
		
		return overlay;
	}

	function show( message ) {
		let overlay = document.getElementById('compilekit-overlay');
		if ( ! overlay ) {
			overlay = buildOverlay();
			document.body.appendChild( overlay );
		}

		overlay.querySelector( '.compilekit-overlay__text' ).textContent = message;
		overlay.querySelector( '.compilekit-overlay__hint' ).textContent = data.hint || '';
		overlay.classList.add( 'is-visible' );
	}

	function init() {
		document.addEventListener( 'submit', function ( event ) {
			// A cancelled confirm() (e.g. delete prompts) prevents the default
			// submit, so we must not show the overlay in that case.
			let submitter;
			if ( event.defaultPrevented ) {
				return;
			}

			submitter = event.submitter || ( document.activeElement && document.activeElement.form === event.target ? document.activeElement : null );

			if ( ! submitter || ! submitter.name ) {
				return;
			}

			if ( ! Object.prototype.hasOwnProperty.call( messages, submitter.name ) ) {
				return;
			}

			show( messages[ submitter.name ] || defaultMessage );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	
} )();
