param(
    [string]$EnvFile = ".env.sentry.staging",
    [switch]$RunSentryTest,
    [switch]$SkipComposeCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-EnvMap {
    param([string]$Path)

    if (-not (Test-Path -Path $Path)) {
        throw "No se encontro el archivo de entorno: $Path"
    }

    $map = @{}
    foreach ($line in [System.IO.File]::ReadAllLines((Resolve-Path -Path $Path))) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#")) {
            continue
        }

        $separatorIndex = $trimmed.IndexOf('=')
        if ($separatorIndex -lt 1) {
            continue
        }

        $key = $trimmed.Substring(0, $separatorIndex).Trim()
        $value = $trimmed.Substring($separatorIndex + 1).Trim()

        if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Mask-Secret {
    param([string]$Value)

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return "(vacio)"
    }

    if ($Value.Length -le 12) {
        return "********"
    }

    return "{0}...{1}" -f $Value.Substring(0, 8), $Value.Substring($Value.Length - 6)
}

$settings = Get-EnvMap -Path $EnvFile

$requiredKeys = @(
    'APP_ENV',
    'APP_DEBUG',
    'SENTRY_LARAVEL_DSN',
    'SENTRY_ENVIRONMENT',
    'SENTRY_SAMPLE_RATE',
    'SENTRY_TRACES_SAMPLE_RATE'
)

$missingKeys = @()
foreach ($requiredKey in $requiredKeys) {
    if (-not $settings.ContainsKey($requiredKey) -or [string]::IsNullOrWhiteSpace($settings[$requiredKey])) {
        $missingKeys += $requiredKey
    }
}

if ($missingKeys.Count -gt 0) {
    throw "Faltan variables requeridas en ${EnvFile}: $($missingKeys -join ', ')"
}

$dsn = [string]$settings['SENTRY_LARAVEL_DSN']
$dsnPattern = '^https://[^@\s]+@[^\s/]+/\d+$'
if ($dsn -notmatch $dsnPattern) {
    throw "SENTRY_LARAVEL_DSN no tiene formato valido. Esperado: https://<key>@<host>/<project_id>"
}

$appEnv = ([string]$settings['APP_ENV']).Trim().ToLowerInvariant()
$sentryEnv = ([string]$settings['SENTRY_ENVIRONMENT']).Trim().ToLowerInvariant()
$appDebug = ([string]$settings['APP_DEBUG']).Trim().ToLowerInvariant()

if ($appEnv -ne $sentryEnv) {
    Write-Warning "APP_ENV ($appEnv) y SENTRY_ENVIRONMENT ($sentryEnv) no coinciden."
}

if (($appEnv -eq 'staging' -or $appEnv -eq 'production') -and $appDebug -ne 'false') {
    Write-Warning "APP_DEBUG deberia ser false en $appEnv. Valor actual: $appDebug"
}

if (-not $SkipComposeCheck) {
    $dockerCmd = Get-Command docker -ErrorAction SilentlyContinue
    if ($null -ne $dockerCmd) {
        & docker compose --env-file $EnvFile config | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "docker compose config fallo con --env-file $EnvFile"
        }
    } else {
        Write-Warning "No se encontro docker en PATH. Se omite validacion de compose."
    }
}

Write-Host "Preflight Sentry OK"
Write-Host ("  APP_ENV: {0}" -f $settings['APP_ENV'])
Write-Host ("  APP_DEBUG: {0}" -f $settings['APP_DEBUG'])
Write-Host ("  SENTRY_ENVIRONMENT: {0}" -f $settings['SENTRY_ENVIRONMENT'])
Write-Host ("  SENTRY_LARAVEL_DSN: {0}" -f (Mask-Secret -Value $settings['SENTRY_LARAVEL_DSN']))

if ($RunSentryTest) {
    Write-Host "Aplicando variables al proceso actual para test de Sentry..."

    foreach ($key in $settings.Keys) {
        [System.Environment]::SetEnvironmentVariable($key, [string]$settings[$key], 'Process')
    }

    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo php artisan config:clear"
    }

    & php artisan sentry:test
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo php artisan sentry:test"
    }

    Write-Host "Evento de prueba enviado a Sentry correctamente."
}
