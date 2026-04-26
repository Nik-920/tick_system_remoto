# Jenkins CI para Tick System

Esta guía habilita integración continua con Jenkins usando el archivo `Jenkinsfile` ubicado en la raíz del repositorio.

## 1. Requisitos del agente Jenkins

El nodo/agente que ejecuta el pipeline debe tener instalado:

- Git
- PHP 8.2+
- Composer 2+
- Node.js 20+
- npm 10+

Comandos de validación en el agente:

```bash
php -v
composer --version
node -v
npm -v
```

## 2. Crear el Job Pipeline

1. En Jenkins, crear un nuevo item de tipo **Pipeline**.
2. En **Pipeline Definition**, seleccionar **Pipeline script from SCM**.
3. SCM: **Git**.
4. Configurar URL del repositorio y credenciales.
5. Branch recomendado:
   - `*/develop` para integración continua.
   - `*/main` para validación de rama principal.
6. Script Path: `Jenkinsfile`.
7. Guardar y ejecutar **Build Now**.

## 3. Qué ejecuta el pipeline

El pipeline corre estas etapas:

1. Checkout del repositorio.
2. Verificación de toolchain (PHP/Composer/Node/npm).
3. Instalación backend (`composer install`).
4. Preparación entorno de tests (`.env` y `php artisan key:generate`).
5. Pruebas backend (`vendor/bin/phpunit` con reporte JUnit).
6. Build frontend (`npm ci` + `npm run build`).

También publica:

- Reporte de pruebas JUnit en Jenkins.
- Artefactos compilados en `public/build`.

## 4. Parámetros del pipeline

- `SKIP_FRONTEND` (boolean): cuando es `true`, omite `npm ci` y `npm run build`.

Útil para validar solo backend cuando haya incidencias en toolchain frontend del agente.

## 5. Webhook recomendado

Configurar webhook del repositorio apuntando a:

```text
https://TU_JENKINS/github-webhook/
```

Con eso, cada push o PR dispara el job automáticamente (según tu configuración de triggers).

## 6. Troubleshooting rápido

- Error: `php: command not found`
  - Solución: instalar PHP en el agente y añadirlo al PATH del servicio Jenkins.

- Error: `npm: command not found`
  - Solución: instalar Node/npm en el agente o ejecutar el job en un nodo con toolchain frontend.

- Error en `key:generate`
  - Solución: verificar permisos de escritura del workspace en Jenkins.

- Tests intermitentes por cache
  - Solución: limpiar workspace y volver a construir.

## 7. Siguiente mejora sugerida

Agregar una etapa de calidad con:

```bash
vendor/bin/pint --test
```

para bloquear merges con formato PSR-12 incorrecto.
