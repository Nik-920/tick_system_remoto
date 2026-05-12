import './bootstrap';

// Layout module: sidebar toggle, theme, dropdowns (all authenticated pages)
if (document.querySelector('.admin-layout')) {
	import('./components/layout')
		.then((mod) => mod.init?.())
		.catch((err) => console.error('Error loading layout module', err));
}

// Carga condicional de scripts por página (modularización segura)
// Detecta la página por selectores únicos en las vistas Blade y carga el módulo correspondiente.
const pageLoaders = [
	{ selector: '.users-page', loader: () => import('./pages/users') },
	{ selector: '.tickets-page', loader: () => import('./pages/tickets') },
	{ selector: '.locations-page', loader: () => import('./pages/locations') },
	{ selector: '.welcome-hero', loader: () => import('./pages/welcome') },
];

function runPageLoaders() {
	for (const { selector, loader } of pageLoaders) {
		if (document.querySelector(selector)) {
			loader()
				.then((mod) => {
					if (mod && typeof mod.init === 'function') {
						try {
							mod.init();
						} catch (e) {
							console.error('Error initializing page module', e);
						}
					}
				})
				.catch((err) => console.error('Error loading page module', err));
			break; // solo cargar la primera coincidencia
		}
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', runPageLoaders, { once: true });
} else {
	runPageLoaders();
}
