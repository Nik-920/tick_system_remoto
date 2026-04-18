param(
    [string]$SeedFile = ".github/board/issues_seed_p0_p1_p2.json",
    [string]$Repo = "",
    [switch]$CreateLabels,
    [switch]$CreateIssues,
    [switch]$DryRun = $true
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Invoke-Gh {
    param(
        [string[]]$CommandArgs,
        [switch]$DryRunMode
    )

    if ($DryRunMode) {
        Write-Host ("DRY-RUN gh " + ($CommandArgs -join " "))
        return
    }

    & gh @CommandArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo ejecutando comando gh: gh $($CommandArgs -join ' ')"
    }
}

function Build-IssueBody {
    param($Issue)

    $lines = @()
    $lines += "## Resumen"
    $lines += $Issue.summary
    $lines += ""
    $lines += "## Criterios de aceptacion"
    foreach ($item in $Issue.acceptance) {
        $lines += "- [ ] $item"
    }

    $lines += ""
    $lines += "## Verificacion"
    foreach ($item in $Issue.verification) {
        $lines += "- [ ] $item"
    }

    if ($Issue.PSObject.Properties.Name -contains "dependencies" -and $Issue.dependencies.Count -gt 0) {
        $lines += ""
        $lines += "## Dependencias"
        foreach ($dep in $Issue.dependencies) {
            $lines += "- $dep"
        }
    }

    if ($Issue.PSObject.Properties.Name -contains "id") {
        $lines += ""
        $lines += "## Tracking"
        $lines += "- Backlog ID: $($Issue.id)"
    }

    return ($lines -join "`n")
}

if (-not (Test-Path $SeedFile)) {
    throw "No se encontro el archivo seed: $SeedFile"
}

if (-not $CreateLabels -and -not $CreateIssues) {
    $CreateLabels = $true
    $CreateIssues = $true
}

$ghCommand = Get-Command gh -ErrorAction SilentlyContinue
if (-not $DryRun -and $null -eq $ghCommand) {
    throw "No se encontro gh CLI. Instala GitHub CLI para publicar labels/issues."
}

if ($DryRun -and $null -eq $ghCommand) {
    Write-Host "Aviso: gh CLI no esta instalado. El modo DryRun sigue disponible para previsualizar comandos."
}

if (-not $DryRun) {
    & gh auth status | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "No hay sesion activa en gh CLI. Ejecuta: gh auth login"
    }
}

$seedRaw = Get-Content -Path $SeedFile -Raw -Encoding UTF8
$seed = $seedRaw | ConvertFrom-Json

if ($CreateLabels) {
    Write-Host "Creando/actualizando labels..."
    foreach ($label in $seed.labels) {
        $ghArgs = @(
            "label", "create", $label.name,
            "--color", $label.color,
            "--description", $label.description,
            "--force"
        )

        if ($Repo -ne "") {
            $ghArgs += @("--repo", $Repo)
        }

        Invoke-Gh -CommandArgs $ghArgs -DryRunMode:$DryRun
    }
}

if ($CreateIssues) {
    Write-Host "Creando issues desde seed..."

    foreach ($issue in $seed.issues) {
        $body = Build-IssueBody -Issue $issue
        $tmpFile = New-TemporaryFile
        Set-Content -Path $tmpFile -Value $body -Encoding UTF8

        try {
            $ghArgs = @(
                "issue", "create",
                "--title", $issue.title,
                "--body-file", $tmpFile
            )

            foreach ($label in $issue.labels) {
                $ghArgs += @("--label", $label)
            }

            if ($Repo -ne "") {
                $ghArgs += @("--repo", $Repo)
            }

            Invoke-Gh -CommandArgs $ghArgs -DryRunMode:$DryRun
        }
        finally {
            Remove-Item -Path $tmpFile -ErrorAction SilentlyContinue
        }
    }
}

Write-Host "Proceso finalizado."
if ($DryRun) {
    Write-Host "Se ejecuto en DRY-RUN. Para aplicar cambios usa -DryRun:`$false"
}
