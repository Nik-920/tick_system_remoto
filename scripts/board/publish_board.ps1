param(
    [string]$SeedFile = ".github/board/issues_seed_p0_p1_p2.json",
    [string]$Repo = "",
    [switch]$CreateLabels,
    [switch]$CreateIssues,
    [switch]$AssignP0OwnersExisting,
    [string[]]$P0Owners = @(),
    [string]$P0OwnerMapFile = "",
    [switch]$DryRun = $true
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Invoke-Gh {
    param(
        [string[]]$CommandArgs,
        [switch]$DryRunMode,
        [switch]$CaptureOutput
    )

    if ($DryRunMode) {
        Write-Host ("DRY-RUN gh " + ($CommandArgs -join " "))
        if ($CaptureOutput) {
            return ""
        }
        return
    }

    if ($CaptureOutput) {
        $output = & gh @CommandArgs
        if ($LASTEXITCODE -ne 0) {
            throw "Fallo ejecutando comando gh: gh $($CommandArgs -join ' ')"
        }
        return $output
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

function Load-OwnerMap {
    param([string]$OwnerMapFile)

    $ownerMap = @{}

    if ([string]::IsNullOrWhiteSpace($OwnerMapFile)) {
        return $ownerMap
    }

    if (-not (Test-Path $OwnerMapFile)) {
        throw "No se encontro el archivo de owners P0: $OwnerMapFile"
    }

    $raw = Get-Content -Path $OwnerMapFile -Raw -Encoding UTF8 | ConvertFrom-Json

    foreach ($property in $raw.PSObject.Properties) {
        $key = [string]$property.Name
        $value = $property.Value

        if ($null -eq $value) {
            continue
        }

        if ($value -is [System.Array]) {
            $owners = @()
            foreach ($item in $value) {
                $normalized = ([string]$item).Trim()
                if (-not [string]::IsNullOrWhiteSpace($normalized)) {
                    $owners += $normalized
                }
            }

            if ($owners.Count -gt 0) {
                $ownerMap[$key] = $owners
            }
        }
        else {
            $normalized = ([string]$value).Trim()
            if (-not [string]::IsNullOrWhiteSpace($normalized)) {
                $ownerMap[$key] = @($normalized)
            }
        }
    }

    return $ownerMap
}

function Is-P0Issue {
    param($Issue)

    if ($Issue.PSObject.Properties.Name -contains "id") {
        $id = ([string]$Issue.id).Trim()
        if ($id.StartsWith("P0-")) {
            return $true
        }
    }

    if ($Issue.PSObject.Properties.Name -contains "labels") {
        foreach ($label in $Issue.labels) {
            if ([string]$label -eq "priority:P0") {
                return $true
            }
        }
    }

    return $false
}

function Select-P0Owner {
    param(
        $Issue,
        [hashtable]$OwnerMap,
        [string[]]$RoundRobinOwners,
        [ref]$RoundRobinIndex
    )

    if ($Issue.PSObject.Properties.Name -contains "id") {
        $issueId = ([string]$Issue.id).Trim()
        if ($OwnerMap.ContainsKey($issueId) -and $OwnerMap[$issueId].Count -gt 0) {
            return [string]$OwnerMap[$issueId][0]
        }
    }

    if ($RoundRobinOwners.Count -gt 0) {
        $owner = $RoundRobinOwners[$RoundRobinIndex.Value % $RoundRobinOwners.Count]
        $RoundRobinIndex.Value++
        return ([string]$owner).Trim()
    }

    return ""
}

function Parse-BacklogIdFromBody {
    param([string]$Body)

    if ([string]::IsNullOrWhiteSpace($Body)) {
        return ""
    }

    $match = [regex]::Match($Body, "Backlog ID:\\s*([A-Z0-9-]+)", [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($match.Success) {
        return $match.Groups[1].Value.Trim().ToUpperInvariant()
    }

    return ""
}

function Normalize-P0Owners {
    param([string[]]$RawOwners)

    $normalized = @()

    foreach ($entry in $RawOwners) {
        $parts = ([string]$entry).Split(',')
        foreach ($part in $parts) {
            $value = $part.Trim()
            if (-not [string]::IsNullOrWhiteSpace($value)) {
                $normalized += $value
            }
        }
    }

    return $normalized
}

if (-not (Test-Path $SeedFile)) {
    throw "No se encontro el archivo seed: $SeedFile"
}

if (-not $CreateLabels -and -not $CreateIssues -and -not $AssignP0OwnersExisting) {
    $CreateLabels = $true
    $CreateIssues = $true
}

$p0OwnerMap = Load-OwnerMap -OwnerMapFile $P0OwnerMapFile
$resolvedP0Owners = Normalize-P0Owners -RawOwners $P0Owners

if (($AssignP0OwnersExisting -or $CreateIssues) -and $p0OwnerMap.Count -eq 0 -and $resolvedP0Owners.Count -eq 0) {
    Write-Host "Aviso: no se definieron owners P0. Para asignar owners usa -P0Owners o -P0OwnerMapFile."
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

    $p0CreateRoundRobinIndex = 0

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

            if (Is-P0Issue -Issue $issue) {
                $owner = Select-P0Owner -Issue $issue -OwnerMap $p0OwnerMap -RoundRobinOwners $resolvedP0Owners -RoundRobinIndex ([ref]$p0CreateRoundRobinIndex)
                if (-not [string]::IsNullOrWhiteSpace($owner)) {
                    $ghArgs += @("--assignee", $owner)
                }
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

if ($AssignP0OwnersExisting) {
    Write-Host "Asignando owners a issues P0 existentes..."

    if ($DryRun -and $null -eq $ghCommand) {
        Write-Host "DRY-RUN: no se puede consultar issues remotos sin gh instalado."
    }
    else {
        $listArgs = @(
            "issue", "list",
            "--state", "open",
            "--label", "priority:P0",
            "--limit", "100",
            "--json", "number,title,body,assignees"
        )

        if ($Repo -ne "") {
            $listArgs += @("--repo", $Repo)
        }

        if ($DryRun) {
            Invoke-Gh -CommandArgs $listArgs -DryRunMode:$true
            Write-Host "DRY-RUN: usa -DryRun:`$false para leer issues reales y aplicar owners."
        }
        else {
            $issuesJson = Invoke-Gh -CommandArgs $listArgs -DryRunMode:$false -CaptureOutput
            $existingIssues = @($issuesJson | ConvertFrom-Json)

            if ($existingIssues.Count -eq 0) {
                Write-Host "No se encontraron issues abiertos con label priority:P0."
            }
            else {
                $p0ExistingRoundRobinIndex = 0
                $sortedIssues = $existingIssues | Sort-Object -Property number

                foreach ($existingIssue in $sortedIssues) {
                    $backlogId = Parse-BacklogIdFromBody -Body ([string]$existingIssue.body)
                    $ownerToAssign = ""

                    if (-not [string]::IsNullOrWhiteSpace($backlogId) -and $p0OwnerMap.ContainsKey($backlogId) -and $p0OwnerMap[$backlogId].Count -gt 0) {
                        $ownerToAssign = [string]$p0OwnerMap[$backlogId][0]
                    }
                    elseif ($resolvedP0Owners.Count -gt 0) {
                        $ownerToAssign = [string]$resolvedP0Owners[$p0ExistingRoundRobinIndex % $resolvedP0Owners.Count]
                        $p0ExistingRoundRobinIndex++
                    }

                    if ([string]::IsNullOrWhiteSpace($ownerToAssign)) {
                        Write-Warning "No se pudo resolver owner para issue #$($existingIssue.number) ($($existingIssue.title))."
                        continue
                    }

                    $alreadyAssigned = $false
                    foreach ($assignee in $existingIssue.assignees) {
                        if ([string]$assignee.login -eq $ownerToAssign) {
                            $alreadyAssigned = $true
                            break
                        }
                    }

                    if ($alreadyAssigned) {
                        Write-Host "Issue #$($existingIssue.number) ya tiene owner $ownerToAssign."
                        continue
                    }

                    $editArgs = @("issue", "edit", [string]$existingIssue.number, "--add-assignee", $ownerToAssign)
                    if ($Repo -ne "") {
                        $editArgs += @("--repo", $Repo)
                    }

                    Invoke-Gh -CommandArgs $editArgs -DryRunMode:$false
                }
            }
        }
    }
}

Write-Host "Proceso finalizado."
if ($DryRun) {
    Write-Host "Se ejecuto en DRY-RUN. Para aplicar cambios usa -DryRun:`$false"
}
