# Jenkins Job de Lint y Code Style

Esta guía configura el job `system_app-lint` para validar estilo y calidad de código en Laravel/PHP.

## 1. Objetivo del job

El job ejecuta:

1. `laravel/pint` en modo test.
2. `phpstan` con Larastan.

Si una validación falla, el build queda en rojo y evita promover código con problemas.

## 2. Archivo de pipeline

- Script path recomendado: `CI/jenkins/Jenkinsfile.lint`
- Nombre de job recomendado: `system_app-lint`

## 3. Crear el job en Jenkins

1. En Jenkins, clic en **Nueva Tarea**.
2. Nombre: `system_app-lint`.
3. Tipo: **Pipeline**.
4. En **Definition**, seleccionar **Pipeline script from SCM**.
5. SCM: **Git** y configurar repositorio + credenciales.
6. Branch Specifier: `*/develop`.
7. Script Path: `CI/jenkins/Jenkinsfile.lint`.
8. En **Build Triggers**, activar **GitHub hook trigger for GITScm polling**.
9. Guardar.

## 4. Parámetros opcionales del job

- `SKIP_PHPSTAN`: omite análisis estático.

Por defecto queda en `false` para validar calidad completa.

## 5. Comandos locales equivalentes

```bash
composer run lint:pint
composer run lint:phpstan
```

## 6. Integración con webhook

Si ya tienes ngrok para Jenkins principal, puedes reutilizar el mismo endpoint:

```text
https://TU_DOMINIO_NGROK/github-webhook/
```

El webhook dispara el job según triggers y branch configurados.

## 7. Troubleshooting

- `vendor/bin/phpstan` no encontrado:
  - Ejecutar `composer install` y verificar `composer.lock` actualizado.

- Falla en sintaxis PHP:
  - Revisar la salida de la etapa **PHP Syntax** para ubicar archivo y línea.

- Falla en pint o phpstan:
  - Corregir formato localmente y volver a hacer push.
