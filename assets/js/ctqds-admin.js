/* global ctqdsData */
(function () {
	if ( typeof ctqdsData === 'undefined' ) {
		return;
	}

	var idx            = ctqdsData.ruleCount;
	var tbody          = document.getElementById( 'pbd-rules-body' );
	var tpl            = document.getElementById( 'pbd-rule-tpl' );
	var conflictWarning = document.getElementById( 'pbd-conflict-warning' );

	function pbd_check_conflicts() {
		var rows   = tbody.querySelectorAll( '.pbd-rule-row' );
		var groups = {};

		rows.forEach( function ( row ) {
			var targetEl = row.querySelector( 'input[name$="[target_qty]"]' );
			var typeEl   = row.querySelector( 'select[name$="[discount_type]"]' );
			var valueEl  = row.querySelector( 'input[name$="[discount_value]"]' );
			var scopeEl  = row.querySelector( 'select[name$="[discount_scope]"]' );
			var skusEl   = row.querySelector( 'input[name$="[skus]"]' );
			if ( ! targetEl || ! typeEl || ! valueEl ) {
				return;
			}

			var targetVal = targetEl.value.trim();
			if ( ! targetVal ) {
				return;
			}

			var scopeVal = scopeEl ? scopeEl.value : 'cart';
			var skusVal  = skusEl ? skusEl.value.trim() : '';
			var key      = targetVal + '_' + scopeVal + '_' + skusVal;

			if ( ! groups[ key ] ) {
				groups[ key ] = [];
			}
			var normalized = typeEl.value + ':' + parseFloat( valueEl.value.replace( ',', '.' ) || '0' );
			groups[ key ].push( normalized );
		} );

		var hasConflict = false;
		Object.keys( groups ).forEach( function ( key ) {
			var unique = groups[ key ].filter( function ( v, i, a ) {
				return a.indexOf( v ) === i;
			} );
			if ( unique.length > 1 ) {
				hasConflict = true;
			}
		} );

		conflictWarning.style.display = hasConflict ? '' : 'none';
	}

	document.getElementById( 'pbd-add-rule' ).addEventListener( 'click', function () {
		var html = tpl.innerHTML.replace( /__IDX__/g, idx++ );
		var tmp  = document.createElement( 'tbody' );
		tmp.innerHTML = html;
		tbody.appendChild( tmp.firstElementChild );
		pbd_check_conflicts();
	} );

	tbody.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'pbd-remove-row' ) ) {
			if ( tbody.querySelectorAll( '.pbd-rule-row' ).length > 1 ) {
				e.target.closest( 'tr' ).remove();
				pbd_check_conflicts();
			} else {
				// eslint-disable-next-line no-alert
				window.alert( ctqdsData.minRuleMsg );
			}
		}
	} );

	tbody.addEventListener( 'input', pbd_check_conflicts );
	tbody.addEventListener( 'change', pbd_check_conflicts );

	// Initial check on page load.
	pbd_check_conflicts();

	// Show/hide cart and product sections based on display location.
	(function () {
		var radios     = document.querySelectorAll( 'input[name="ctqds_display_location"]' );
		var cartH2     = document.getElementById( 'pbd-h2-cart' );
		var cartFields = document.getElementById( 'pbd-cart-fields' );
		var prodH2     = document.getElementById( 'pbd-h2-product' );
		var prodFields = document.getElementById( 'pbd-product-fields' );

		function syncVisibility() {
			var val      = ( document.querySelector( 'input[name="ctqds_display_location"]:checked' ) || {} ).value || 'cart';
			var showCart = val === 'cart' || val === 'both';
			var showProd = val === 'product' || val === 'both';

			[ cartH2, cartFields ].forEach( function ( el ) {
				if ( el ) el.style.display = showCart ? '' : 'none';
			} );
			[ prodH2, prodFields ].forEach( function ( el ) {
				if ( el ) el.style.display = showProd ? '' : 'none';
			} );
		}

		radios.forEach( function ( r ) {
			r.addEventListener( 'change', syncVisibility );
		} );
		syncVisibility();
	}() );
}());
