/* ============================================================
   Guía de inscripción — Scripts
   Progreso con checkboxes (guardado en el navegador), enlaces
   y acordeón de FAQ.
   ============================================================ */

(function () {
	'use strict';

	/* ------------------------------------------------------------
	   1) CONFIG — editá acá los enlaces reales de tu sitio
	------------------------------------------------------------ */
	var CONFIG = {
		// URL de la página que tiene el shortcode [if_registro_delegado]
		urlRegistro: 'https://tusitio.com/registro/',
		// URL del panel del delegado [if_panel_delegado]
		urlPanel: 'https://tusitio.com/cargar-equipo/'
	};

	/* ------------------------------------------------------------
	   2) Enlaces: completa los botones con la URL configurada
	------------------------------------------------------------ */
	function configurarEnlaces() {
		document.querySelectorAll('[data-enlace]').forEach(function (btn) {
			var destino = btn.getAttribute('data-enlace');
			if (destino === 'registro' && CONFIG.urlRegistro) {
				btn.href = CONFIG.urlRegistro;
			} else if (destino === 'panel' && CONFIG.urlPanel) {
				btn.href = CONFIG.urlPanel;
			}
		});
	}

	/* ------------------------------------------------------------
	   3) Progreso: cuenta checkboxes y guarda el estado
	------------------------------------------------------------ */
	var CLAVE_GUARDADO = 'guia-inscripcion-progreso';

	function guardarEstado() {
		var marcados = {};
		document.querySelectorAll('.guia-checklist input[type="checkbox"]').forEach(function (cb) {
			if (cb.checked) {
				marcados[cb.getAttribute('data-check')] = true;
			}
		});
		try {
			window.localStorage.setItem(CLAVE_GUARDADO, JSON.stringify(marcados));
		} catch (e) { /* localStorage no disponible */ }
	}

	function restaurarEstado() {
		var marcados = null;
		try {
			marcados = JSON.parse(window.localStorage.getItem(CLAVE_GUARDADO) || 'null');
		} catch (e) { /* ignorar */ }
		if (!marcados) { return; }

		document.querySelectorAll('.guia-checklist input[type="checkbox"]').forEach(function (cb) {
			var clave = cb.getAttribute('data-check');
			if (marcados[clave]) {
				cb.checked = true;
			}
		});
	}

	function actualizarProgreso() {
		var total = 0;
		var hechos = 0;

		document.querySelectorAll('.guia-checklist input[type="checkbox"]').forEach(function (cb) {
			total += 1;
			if (cb.checked) { hechos += 1; }
			var li = cb.closest('li');
			if (li) {
				li.classList.toggle('hecho', cb.checked);
			}
		});

		var porciento = total ? Math.round((hechos / total) * 100) : 0;

		var relleno = document.getElementById('guiaProgressFill');
		var texto = document.getElementById('guiaProgressText');
		if (relleno) { relleno.style.width = porciento + '%'; }
		if (texto) { texto.textContent = porciento; }
	}

	/* ------------------------------------------------------------
	   4) FAQ: al abrir una pregunta se cierran las demás
	------------------------------------------------------------ */
	function configurarFaq() {
		var items = document.querySelectorAll('.guia-faq-item');

		items.forEach(function (item) {
			var resumen = item.querySelector('summary');
			if (!resumen) { return; }

			resumen.addEventListener('click', function () {
				// Se cierra recién cuando el navegador abre el <details>;
				// por eso esperamos al microtask/next frame.
				setTimeout(function () {
					if (!item.open) { return; }
					items.forEach(function (otro) {
						if (otro !== item) { otro.open = false; }
					});
				}, 0);
			});
		});
	}

	/* ------------------------------------------------------------
	   5) Scroll-spy: marca el paso visible en la navegación
	------------------------------------------------------------ */
	function configurarScrollSpy() {
		var secciones = document.querySelectorAll('.guia-paso');
		var enlaces = document.querySelectorAll('.guia-nav-item');
		if (!secciones.length || !enlaces.length) { return; }

		function resaltar() {
			var posicion = window.scrollY + 140;
			var actual = null;

			secciones.forEach(function (sec) {
				if (sec.offsetTop <= posicion) {
					actual = sec.id;
				}
			});

			enlaces.forEach(function (enlace) {
				var destino = enlace.getAttribute('href');
				enlace.classList.toggle('activo', destino === '#' + actual);
			});
		}

		window.addEventListener('scroll', resaltar, { passive: true });
		resaltar();
	}

	/* ------------------------------------------------------------
	   Arranque
	------------------------------------------------------------ */
	document.addEventListener('DOMContentLoaded', function () {
		configurarEnlaces();
		restaurarEstado();
		actualizarProgreso();
		configurarFaq();
		configurarScrollSpy();

		document.querySelectorAll('.guia-checklist input[type="checkbox"]').forEach(function (cb) {
			cb.addEventListener('change', function () {
				guardarEstado();
				actualizarProgreso();
			});
		});
	});
})();