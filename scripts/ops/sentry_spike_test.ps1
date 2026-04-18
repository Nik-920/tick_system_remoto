param(
    [string]$EnvFile = ".env.sentry.staging",
    [int]$Iterations = 8
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($Iterations -lt 2) {
    throw "Iterations debe ser >= 2 para simular spike."
}

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

$preflightPath = Join-Path -Path $PSScriptRoot -ChildPath "sentry_preflight.ps1"
& pwsh -File $preflightPath -EnvFile $EnvFile -SkipComposeCheck
if ($LASTEXITCODE -ne 0) {
    throw "Preflight fallo antes de ejecutar spike test."
}

$settings = Get-EnvMap -Path $EnvFile
foreach ($key in $settings.Keys) {
    [System.Environment]::SetEnvironmentVariable($key, [string]$settings[$key], 'Process')
}

& php artisan config:clear
if ($LASTEXITCODE -ne 0) {
    throw "Fallo php artisan config:clear"
}

for ($i = 1; $i -le $Iterations; $i++) {
    Write-Host ("Enviando evento de spike {0}/{1}" -f $i, $Iterations)

    & php artisan sentry:test
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo php artisan sentry:test en iteracion $i"
    }
}

Write-Host "Spike test completado. Verifica metric alert en Sentry y correo de alerta."
