{{-- Upload Error Modal — included once in layouts/app.blade.php.
     Populated and shown by resources/js/upload-guard.js. --}}
<div data-upload-error-modal hidden role="dialog" aria-modal="true" aria-labelledby="upload-error-title">
    <div class="upload-error-modal__overlay" data-upload-error-close aria-hidden="true"></div>
    <section class="upload-error-modal__dialog">
        <header class="upload-error-modal__header">
            <span class="upload-error-modal__icon" aria-hidden="true">!</span>
            <h2 id="upload-error-title">No se pudo adjuntar el archivo</h2>
            <button type="button" data-upload-error-close aria-label="Cerrar">&#x00D7;</button>
        </header>
        <div class="upload-error-modal__body" data-upload-error-body></div>
        <footer class="upload-error-modal__actions">
            <button type="button" data-upload-error-close>Entendido</button>
        </footer>
    </section>
</div>
