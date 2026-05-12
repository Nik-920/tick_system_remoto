Organización JS del proyecto

- `app.js`: punto de entrada. Importa `bootstrap` y carga módulos de página condicionalmente según selectores DOM.
- `bootstrap.js`: shim de compatibilidad; delega inicializaciones globales a `services/http.js`.

Estructura recomendada

- `components/`: componentes reutilizables (toasts, modals, widgets). Exportar funciones/objetos reutilizables.
- `pages/`: módulos por vista. Cada archivo exporta `init()` que se ejecuta cuando la vista correspondiente está presente.
- `services/`: inicializadores y wrappers (por ejemplo `http.js`, `api` wrappers).
- `stores/`: stores simples o integración con Pinia/Vuex/Redux según stack.

Cómo añadir un script por página

1. Crear `resources/js/pages/nombre.js` exportando `init()`.
2. Asegurarse que la vista Blade contiene un selector único (ej: `.users-page`).
3. `app.js` detectará el selector y cargará el módulo automáticamente.

Ejecutar build

```bash
npm install
npm run dev   # desarrollo con HMR
npm run build # producción
php artisn test # Comando para correr los test de PHP, Se encuentra en la carpeta ./tests
```
