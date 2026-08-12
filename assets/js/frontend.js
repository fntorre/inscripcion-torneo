( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		// Crear pago con Mercado Pago (Checkout Pro).
		var payButtons = document.querySelectorAll( '.if-btn-mp' );
		payButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var equipo = btn.getAttribute( 'data-equipo' );
				var nonce = btn.getAttribute( 'data-nonce' );
				var msg = btn.closest( '.if-pago-mp' ).querySelector( '.if-pago-msg' );
				btn.disabled = true;
				if ( msg ) { msg.textContent = ifData.loading; }

				var form = new FormData();
				form.append( 'action', 'if_crear_pago' );
				form.append( 'nonce', nonce );
				form.append( 'equipo_id', equipo );

				fetch( ifData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res.success && res.data.url ) {
							window.location.href = res.data.url;
						} else {
							if ( msg ) { msg.textContent = ( res.data && res.data.msg ) ? res.data.msg : ifData.error; }
							btn.disabled = false;
						}
					} )
					.catch( function () {
						if ( msg ) { msg.textContent = ifData.error; }
						btn.disabled = false;
					} );
			} );
		} );

		// Verificar estado del pago.
		var checkButtons = document.querySelectorAll( '.if-btn-check' );
		checkButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var equipo = btn.getAttribute( 'data-equipo' );
				var nonce = btn.getAttribute( 'data-nonce' );
				var msg = btn.closest( '.if-pago-mp' ).querySelector( '.if-pago-msg' );
				btn.disabled = true;
				if ( msg ) { msg.textContent = ifData.loading; }

				var form = new FormData();
				form.append( 'action', 'if_verificar_pago' );
				form.append( 'nonce', nonce );
				form.append( 'equipo_id', equipo );

				fetch( ifData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res.success ) {
							if ( msg ) { msg.innerHTML = res.data.label + ' <a href="javascript:location.reload()">&#8635;</a>'; }
							if ( res.data.estado === 'aprobado' ) { location.reload(); }
						} else if ( msg ) {
							msg.textContent = ( res.data && res.data.msg ) ? res.data.msg : ifData.error;
						}
						btn.disabled = false;
					} )
					.catch( function () {
						if ( msg ) { msg.textContent = ifData.error; }
						btn.disabled = false;
					} );
			} );
		} );
	} );
} )();
