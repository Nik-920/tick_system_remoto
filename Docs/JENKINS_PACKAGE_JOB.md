# Jenkins Job de Empaquetado y Artifacts

Esta guía configura el job `system_app-package` para generar una versión lista para despliegue sin recompilar frontend en cada deploy.

## 1. Objetivo del job

El job genera y publica:

1. ZIP de código fuente (`system_app-source-<build>.zip`).
2. ZIP de release (`system_app-release-<build>.zip`) con backend, `vendor` y assets compilados.
3. Artefactos frontend (`public/build/**`).
4. Reporte de pruebas JUnit (`storage/test-results/phpunit.xml`) cuando se ejecutan tests.
5. Archivos de release (`release-info.txt` y `checksums.sha256`).

## 2. Archivo de pipeline

- Script Path recomendado: `ci/jenkins/Jenkinsfile.package`
- Nombre de job recomendado: `system_app-package`

## 3. Crear el job en Jenkins

1. En Jenkins, clic en **Nueva Tarea**.
2. Nombre: `system_app-package`.
3. Tipo: **Pipeline**.
4. En **Definition**, seleccionar **Pipeline script from SCM**.
5. SCM: **Git** y configurar repositorio + credenciales.
6. Branch Specifier: `*/develop` (o la rama de release).
7. Script Path: `ci/jenkins/Jenkinsfile.package`.
8. En **Build Triggers**, activar trigger por webhook si aplica.
9. Guardar y ejecutar **Build Now**.

## 4. Parámetros del job

- `SKIP_FRONTEND_BUILD`:
  - `false` (default): compila frontend (`npm ci && npm run build`).
  - `true`: omite build frontend.
- `RUN_TESTS`:
  - `true` (default): ejecuta PHPUnit y publica JUnit.
  - `false`: omite tests para empaquetado rápido.

## 5. Artifacts esperados

Se publican bajo `storage/package/`:

- `system_app-source-<build>.zip`
- `system_app-release-<build>.zip`
- `release-info.txt`
- `checksums.sha256`

Además:

- `public/build/**` (assets frontend compilados)
- `storage/test-results/phpunit.xml` (si `RUN_TESTS=true`)

## 6. Beneficio para deploy

El pipeline entrega un paquete de release reutilizable. Eso permite que el deploy use artifacts ya compilados y validados, reduciendo tiempo y evitando recompilar frontend en cada despliegue.
