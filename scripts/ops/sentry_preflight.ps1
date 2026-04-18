param(
    [string]$EnvFile = ".env.sentry.staging",
    [switch]$RunSentryTest,
    [switch]$SkipComposeCheck,
    [switch]$RequireRealHttpsUrl
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
    'APP_URL',
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
$appUrl = ([string]$settings['APP_URL']).Trim()
$mailMailer = if ($settings.ContainsKey('MAIL_MAILER')) {
    ([string]$settings['MAIL_MAILER']).Trim().ToLowerInvariant()
} else {
    ''
}

if ($appEnv -ne $sentryEnv) {
    Write-Warning "APP_ENV ($appEnv) y SENTRY_ENVIRONMENT ($sentryEnv) no coinciden."
}

if (($appEnv -eq 'staging' -or $appEnv -eq 'production') -and $appDebug -ne 'false') {
    Write-Warning "APP_DEBUG deberia ser false en $appEnv. Valor actual: $appDebug"
}

$parsedAppUrl = $null
if (-not [System.Uri]::TryCreate($appUrl, [System.UriKind]::Absolute, [ref]$parsedAppUrl)) {
    throw "APP_URL no tiene formato valido en ${EnvFile}: $appUrl"
}

if ($parsedAppUrl.Scheme -ne 'http' -and $parsedAppUrl.Scheme -ne 'https') {
    throw "APP_URL debe usar esquema http/https. Valor actual: $appUrl"
}

$isStagingOrProduction = $appEnv -eq 'staging' -or $appEnv -eq 'production'
if ($isStagingOrProduction -and $parsedAppUrl.Scheme -ne 'https') {
    $message = "APP_URL deberia usar HTTPS para $appEnv. Valor actual: $appUrl"
    if ($RequireRealHttpsUrl) {
        throw $message
    }

    Write-Warning $message
}

$appHost = $parsedAppUrl.Host.ToLowerInvariant()
$localHosts = @('localhost', '127.0.0.1', '::1')
if ($isStagingOrProduction -and $localHosts.Contains($appHost)) {
    $message = "APP_URL apunta a host local para $appEnv. Valor actual: $appUrl"
    if ($RequireRealHttpsUrl) {
        throw $message
    }

    Write-Warning $message
}

$isExampleHost = $appHost -eq 'example.com' -or $appHost.EndsWith('.example.com')
if ($isStagingOrProduction -and $isExampleHost) {
    $message = "APP_URL sigue usando placeholder example.com para $appEnv. Valor actual: $appUrl"
    if ($RequireRealHttpsUrl) {
        throw $message
    }

    Write-Warning $message
}

if ($isStagingOrProduction) {
    if ([string]::IsNullOrWhiteSpace($mailMailer)) {
        Write-Warning "MAIL_MAILER no esta definido en ${EnvFile}. La app puede estar heredando MAIL_MAILER=log desde .env y no enviar correos reales."
    } elseif ($mailMailer -eq 'log' -or $mailMailer -eq 'array') {
        Write-Warning "MAIL_MAILER=$mailMailer en $appEnv. El enlace de recuperacion de password no se enviara por SMTP real."
    }
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
Write-Host ("  APP_URL: {0}" -f $settings['APP_URL'])
Write-Host ("  MAIL_MAILER: {0}" -f $(if ([string]::IsNullOrWhiteSpace($mailMailer)) { '(no definido en env-file)' } else { $mailMailer }))
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
