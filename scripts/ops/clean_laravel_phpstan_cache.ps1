param(
    [switch]$DryRun,
    [switch]$SkipArtisan
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Push-Location $repoRoot

try {
    function Remove-CachePath {
        param(
            [Parameter(Mandatory = $true)]
            [string]$Path,
            [switch]$Directory
        )

        $fullPath = Join-Path $repoRoot $Path

        if (-not (Test-Path -Path $fullPath)) {
            Write-Host ("No existe, se omite: {0}" -f $Path)
            return
        }

        if ($DryRun) {
            Write-Host ("[DRY RUN] Eliminar: {0}" -f $Path)
            return
        }

        Remove-Item -Path $fullPath -Recurse -Force
        Write-Host ("Eliminado: {0}" -f $Path)

        if ($Directory) {
            New-Item -Path $fullPath -ItemType Directory -Force | Out-Null
            Write-Host ("Recreado directorio: {0}" -f $Path)
        }
    }

    Write-Host "Iniciando limpieza de caches Laravel/PHPStan..."

    Remove-CachePath -Path ".php-cs-fixer.cache"
    Remove-CachePath -Path ".phpunit.result.cache"
    Remove-CachePath -Path ".phpunit.cache" -Directory
    Remove-CachePath -Path "storage/phpstan" -Directory

    if (-not $SkipArtisan) {
        $phpCmd = Get-Command php -ErrorAction SilentlyContinue
        if ($null -eq $phpCmd) {
            Write-Warning "No se encontro PHP en PATH. Se omite php artisan optimize:clear"
        } elseif (-not (Test-Path -Path "artisan")) {
            Write-Warning "No se encontro el archivo artisan en la raiz. Se omite optimize:clear"
        } elseif ($DryRun) {
            Write-Host "[DRY RUN] Ejecutar: php artisan optimize:clear"
        } else {
            Write-Host "Ejecutando php artisan optimize:clear..."
            & php artisan optimize:clear
            if ($LASTEXITCODE -ne 0) {
                throw "Fallo php artisan optimize:clear"
            }
        }
    }

    Write-Host "Limpieza completada."
}
finally {
    Pop-Location
}
