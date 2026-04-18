param(
    [string]$EnvFile = ".env.sentry.production",
    [ValidateSet('validate', 'create')]
    [string]$Mode = 'validate',
    [string]$ApiBaseUrl = "https://sentry.io",
    [switch]$SkipPreflight,
    [string]$EvidenceFile
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

function Get-SettingValue {
    param(
        [hashtable]$Settings,
        [string]$Key,
        [switch]$Required
    )

    if ($Settings.ContainsKey($Key) -and -not [string]::IsNullOrWhiteSpace([string]$Settings[$Key])) {
        return [string]$Settings[$Key]
    }

    $processValue = [System.Environment]::GetEnvironmentVariable($Key, 'Process')
    if (-not [string]::IsNullOrWhiteSpace($processValue)) {
        return $processValue
    }

    $userValue = [System.Environment]::GetEnvironmentVariable($Key, 'User')
    if (-not [string]::IsNullOrWhiteSpace($userValue)) {
        return $userValue
    }

    if ($Required) {
        throw "Falta variable requerida: $Key (en $EnvFile o variable de entorno del sistema)."
    }

    return ""
}

function Invoke-SentryApi {
    param(
        [ValidateSet('GET', 'POST', 'PUT')]
        [string]$Method,
        [string]$Path,
        [string]$Token,
        [string]$BaseUrl,
        [object]$Body
    )

    $baseUri = [System.Uri]::new($BaseUrl)
    $requestUri = [System.Uri]::new($baseUri, $Path).AbsoluteUri

    $headers = @{
        Authorization = "Bearer $Token"
    }

    $invokeParams = @{
        Method      = $Method
        Uri         = $requestUri
        Headers     = $headers
        ErrorAction = 'Stop'
    }

    if ($null -ne $Body -and ($Method -eq 'POST' -or $Method -eq 'PUT')) {
        $invokeParams['Body'] = ($Body | ConvertTo-Json -Depth 30)
        $invokeParams['ContentType'] = 'application/json'
    }

    try {
        return Invoke-RestMethod @invokeParams
    } catch {
        $response = $_.Exception.Response
        if ($null -ne $response) {
            $statusCode = [int]$response.StatusCode
            $bodyText = ""
            try {
                $reader = [System.IO.StreamReader]::new($response.GetResponseStream())
                $bodyText = $reader.ReadToEnd()
                $reader.Dispose()
            } catch {
                $bodyText = "(sin cuerpo de respuesta)"
            }

            throw "Sentry API fallo [$Method $Path] HTTP ${statusCode}: $bodyText"
        }

        throw "Sentry API fallo [$Method $Path]: $($_.Exception.Message)"
    }
}

function Get-SentryIssueRules {
    param(
        [string]$Token,
        [string]$BaseUrl,
        [string]$OrgSlug,
        [string]$ProjectSlug
    )

    $path = "/api/0/projects/$OrgSlug/$ProjectSlug/rules/"
    $response = Invoke-SentryApi -Method 'GET' -Path $path -Token $Token -BaseUrl $BaseUrl -Body $null
    if ($null -eq $response) {
        return @()
    }

    return @($response)
}

function Get-SentryMetricRules {
    param(
        [string]$Token,
        [string]$BaseUrl,
        [string]$OrgSlug,
        [string]$ProjectSlug
    )

    $path = "/api/0/organizations/$OrgSlug/alert-rules/"
    $response = Invoke-SentryApi -Method 'GET' -Path $path -Token $Token -BaseUrl $BaseUrl -Body $null
    if ($null -eq $response) {
        return @()
    }

    $allRules = @($response)
    $filtered = @()
    foreach ($rule in $allRules) {
        $projects = @($rule.projects)
        if ($projects.Count -eq 0) {
            continue
        }

        $projectMatch = $false
        foreach ($project in $projects) {
            if ([string]$project -eq $ProjectSlug) {
                $projectMatch = $true
                break
            }
        }

        if ($projectMatch) {
            $filtered += $rule
        }
    }

    return $filtered
}

function Get-ObjectPropertyValue {
    param(
        [object]$Object,
        [string]$PropertyName,
        [object]$Default = $null
    )

    if ($null -eq $Object) {
        return $Default
    }

    $property = $Object.PSObject.Properties[$PropertyName]
    if ($null -eq $property) {
        return $Default
    }

    return $property.Value
}

function Get-RuleByName {
    param(
        [object[]]$Rules,
        [string]$Name
    )

    foreach ($rule in $Rules) {
        $ruleName = [string](Get-ObjectPropertyValue -Object $rule -PropertyName 'name' -Default '')
        if ($ruleName -eq $Name) {
            return $rule
        }
    }

    return $null
}

function Get-JsonText {
    param([object]$Value)

    if ($null -eq $Value) {
        return ""
    }

    return ($Value | ConvertTo-Json -Depth 30 -Compress)
}

function Test-RuleEnabled {
    param([object]$Rule)

    if ($null -eq $Rule) {
        return $false
    }

    $isEnabled = Get-ObjectPropertyValue -Object $Rule -PropertyName 'isEnabled' -Default $null
    if ($null -ne $isEnabled) {
        return [bool]$isEnabled
    }

    $enabled = Get-ObjectPropertyValue -Object $Rule -PropertyName 'enabled' -Default $null
    if ($null -ne $enabled) {
        return [bool]$enabled
    }

    $statusValue = Get-ObjectPropertyValue -Object $Rule -PropertyName 'status' -Default $null
    if ($null -ne $statusValue) {
        $statusRaw = [string]$statusValue
        $status = $statusRaw.Trim().ToLowerInvariant()
        if ($status -eq 'active' -or $status -eq 'enabled' -or $status -eq '0') {
            return $true
        }

        if ($status -eq 'disabled' -or $status -eq 'inactive') {
            return $false
        }
    }

    return $true
}

function Test-RuleContainsEmail {
    param(
        [object]$Rule,
        [string]$Email
    )

    $actions = @(Get-ObjectPropertyValue -Object $Rule -PropertyName 'actions' -Default @())
    foreach ($action in $actions) {
        $targetIdentifier = [string](Get-ObjectPropertyValue -Object $action -PropertyName 'targetIdentifier' -Default '')
        if ($targetIdentifier -eq $Email) {
            return $true
        }
    }

    $triggers = @(Get-ObjectPropertyValue -Object $Rule -PropertyName 'triggers' -Default @())
    foreach ($trigger in $triggers) {
        $triggerActions = @(Get-ObjectPropertyValue -Object $trigger -PropertyName 'actions' -Default @())
        foreach ($action in $triggerActions) {
            $targetIdentifier = [string](Get-ObjectPropertyValue -Object $action -PropertyName 'targetIdentifier' -Default '')
            if ($targetIdentifier -eq $Email) {
                return $true
            }
        }
    }

    $json = Get-JsonText -Value $Rule
    return $json.ToLowerInvariant().Contains($Email.ToLowerInvariant())
}

function Test-RuleEnvironment {
    param(
        [object]$Rule,
        [string]$Environment
    )

    $env = [string](Get-ObjectPropertyValue -Object $Rule -PropertyName 'environment' -Default '')
    if (-not [string]::IsNullOrWhiteSpace($env)) {
        return $env.Trim().ToLowerInvariant() -eq $Environment
    }

    $json = Get-JsonText -Value $Rule
    return $json.ToLowerInvariant().Contains(('"{0}"' -f $Environment))
}

function Test-IssueRuleFilters {
    param(
        [object]$Rule,
        [string]$Environment,
        [string[]]$ExcludedTransactions,
        [switch]$RequireErrorLevel
    )

    $json = Get-JsonText -Value $Rule
    $jsonLower = $json.ToLowerInvariant()

    $hasFirstSeen = $jsonLower.Contains('first_seen_event')
    if (-not $hasFirstSeen) {
        return $false
    }

    $hasEnvironment = Test-RuleEnvironment -Rule $Rule -Environment $Environment
    if (-not $hasEnvironment) {
        return $false
    }

    if ($RequireErrorLevel) {
        $hasErrorLevel = $jsonLower.Contains('level') -and ($jsonLower.Contains('"40"') -or $jsonLower.Contains('"error"') -or $jsonLower.Contains('gte'))
        if (-not $hasErrorLevel) {
            return $false
        }
    }

    foreach ($transaction in $ExcludedTransactions) {
        $transactionLower = $transaction.ToLowerInvariant()
        $transactionNoSlash = $transactionLower.TrimStart('/')
        if (-not ($jsonLower.Contains($transactionLower) -or $jsonLower.Contains($transactionNoSlash))) {
            return $false
        }
    }

    return $true
}

function Test-MetricSpikeRule {
    param(
        [object]$Rule,
        [string]$ProjectSlug,
        [string]$Email
    )

    $dataset = [string](Get-ObjectPropertyValue -Object $Rule -PropertyName 'dataset' -Default '')
    if ($dataset -ne 'events') {
        return $false
    }

    $aggregate = [string](Get-ObjectPropertyValue -Object $Rule -PropertyName 'aggregate' -Default '')
    if ($aggregate -ne 'count()') {
        return $false
    }

    $timeWindow = [int](Get-ObjectPropertyValue -Object $Rule -PropertyName 'timeWindow' -Default -1)
    if ($timeWindow -ne 5) {
        return $false
    }

    $thresholdType = [int](Get-ObjectPropertyValue -Object $Rule -PropertyName 'thresholdType' -Default -1)
    if ($thresholdType -ne 0) {
        return $false
    }

    $resolveThreshold = [int](Get-ObjectPropertyValue -Object $Rule -PropertyName 'resolveThreshold' -Default -1)
    if ($resolveThreshold -ne 2) {
        return $false
    }

    $projects = @(Get-ObjectPropertyValue -Object $Rule -PropertyName 'projects' -Default @())
    if (-not ($projects -contains $ProjectSlug)) {
        return $false
    }

    $query = ([string](Get-ObjectPropertyValue -Object $Rule -PropertyName 'query' -Default '')).ToLowerInvariant()
    $hasEventTypeError = $query.Contains('event.type:error')
    $hasUpExclusion = $query.Contains('!transaction:/up') -or $query.Contains('!transaction:up')
    $hasHealthExclusion = $query.Contains('!transaction:/health') -or $query.Contains('!transaction:health')
    if (-not ($hasEventTypeError -and $hasUpExclusion -and $hasHealthExclusion)) {
        return $false
    }

    $triggers = @(Get-ObjectPropertyValue -Object $Rule -PropertyName 'triggers' -Default @())
    if ($triggers.Count -lt 2) {
        return $false
    }

    $hasCritical5 = $false
    $hasWarning3 = $false
    $hasEmailAction = $false

    foreach ($trigger in $triggers) {
        $label = ([string](Get-ObjectPropertyValue -Object $trigger -PropertyName 'label' -Default '')).ToLowerInvariant()
        $threshold = [double](Get-ObjectPropertyValue -Object $trigger -PropertyName 'alertThreshold' -Default -1)
        if ($label -eq 'critical' -and $threshold -eq 5) {
            $hasCritical5 = $true
        }

        if ($label -eq 'warning' -and $threshold -eq 3) {
            $hasWarning3 = $true
        }

        $triggerActions = @(Get-ObjectPropertyValue -Object $trigger -PropertyName 'actions' -Default @())
        foreach ($action in $triggerActions) {
            $actionType = ([string](Get-ObjectPropertyValue -Object $action -PropertyName 'type' -Default '')).ToLowerInvariant()
            if ($actionType -eq 'email') {
                $hasEmailAction = $true
            }
        }
    }

    return $hasCritical5 -and $hasWarning3 -and $hasEmailAction
}

function New-IssueRulePayload {
    param(
        [string]$RuleName,
        [string]$Environment,
        [string]$Email,
        [int]$FrequencyMinutes,
        [switch]$RequireErrorLevel,
        [string[]]$ExcludedTransactions
    )

    $filters = @()
    if ($RequireErrorLevel) {
        $filters += @{
            id = 'sentry.rules.filters.level.LevelFilter'
            match = 'gte'
            level = '40'
        }
    }

    foreach ($transaction in $ExcludedTransactions) {
        $filters += @{
            id = 'sentry.rules.filters.event_attribute.EventAttributeFilter'
            attribute = 'transaction'
            match = 'nc'
            value = $transaction
        }
    }

    return @{
        name = $RuleName
        frequency = $FrequencyMinutes
        actionMatch = 'all'
        filterMatch = 'all'
        conditions = @(
            @{
                id = 'sentry.rules.conditions.first_seen_event.FirstSeenEventCondition'
            }
        )
        filters = $filters
        actions = @(
            @{
                id = 'sentry.mail.actions.NotifyEmailAction'
                targetType = 'Specific'
                targetIdentifier = $Email
                fallthroughType = 'ActiveMembers'
            }
        )
        environment = $Environment
    }
}

function New-MetricSpikeRulePayload {
    param(
        [string]$RuleName,
        [string]$ProjectSlug,
        [string]$Email
    )

    return @{
        name = $RuleName
        aggregate = 'count()'
        dataset = 'events'
        queryType = 0
        eventTypes = @('error', 'default')
        projects = @($ProjectSlug)
        query = 'event.type:error !transaction:/up !transaction:/health'
        thresholdType = 0
        resolveThreshold = 2
        timeWindow = 5
        environment = 'production'
        triggers = @(
            @{
                label = 'critical'
                alertThreshold = 5
                actions = @(
                    @{
                        type = 'email'
                        targetType = 'specific'
                        targetIdentifier = $Email
                    }
                )
            },
            @{
                label = 'warning'
                alertThreshold = 3
                actions = @()
            }
        )
    }
}

$settings = Get-EnvMap -Path $EnvFile

if (-not $SkipPreflight) {
    $preflightPath = Join-Path -Path $PSScriptRoot -ChildPath 'sentry_preflight.ps1'
    & pwsh -File $preflightPath -EnvFile $EnvFile -SkipComposeCheck -RequireRealHttpsUrl
    if ($LASTEXITCODE -ne 0) {
        throw "Preflight fallo antes de consultar reglas Sentry."
    }
}

$appEnv = (Get-SettingValue -Settings $settings -Key 'APP_ENV' -Required).Trim().ToLowerInvariant()
$token = (Get-SettingValue -Settings $settings -Key 'SENTRY_AUTH_TOKEN' -Required).Trim()
$orgSlug = (Get-SettingValue -Settings $settings -Key 'SENTRY_ORG_SLUG' -Required).Trim()
$projectSlug = (Get-SettingValue -Settings $settings -Key 'SENTRY_PROJECT_SLUG' -Required).Trim()
$alertEmail = (Get-SettingValue -Settings $settings -Key 'ALERT_EMAIL_OPERATIONS' -Required).Trim().ToLowerInvariant()

if (-not $alertEmail.Contains('@')) {
    throw "ALERT_EMAIL_OPERATIONS no parece un email valido: $alertEmail"
}

$issueRules = Get-SentryIssueRules -Token $token -BaseUrl $ApiBaseUrl -OrgSlug $orgSlug -ProjectSlug $projectSlug
$metricRules = Get-SentryMetricRules -Token $token -BaseUrl $ApiBaseUrl -OrgSlug $orgSlug -ProjectSlug $projectSlug

$applyOps = New-Object System.Collections.Generic.List[object]

if ($Mode -eq 'create') {
    $issueRuleDefs = @(
        [PSCustomObject]@{
            Name = 'New Issue Email - Production'
            Environment = 'production'
            FrequencyMinutes = 240
            RequireErrorLevel = $true
            ExcludedTransactions = @('/up', '/health')
        },
        [PSCustomObject]@{
            Name = 'New Issue Email - Staging'
            Environment = 'staging'
            FrequencyMinutes = 1440
            RequireErrorLevel = $false
            ExcludedTransactions = @()
        }
    )

    foreach ($def in $issueRuleDefs) {
        $payload = New-IssueRulePayload -RuleName $def.Name -Environment $def.Environment -Email $alertEmail -FrequencyMinutes $def.FrequencyMinutes -RequireErrorLevel:([bool]$def.RequireErrorLevel) -ExcludedTransactions $def.ExcludedTransactions
        $existing = Get-RuleByName -Rules $issueRules -Name $def.Name

        if ($null -eq $existing) {
            try {
                [void](Invoke-SentryApi -Method 'POST' -Path "/api/0/projects/$orgSlug/$projectSlug/rules/" -Token $token -BaseUrl $ApiBaseUrl -Body $payload)
                $applyOps.Add([PSCustomObject]@{ Rule = $def.Name; Kind = 'issue'; Action = 'created'; Status = 'ok'; Detail = '' })
            } catch {
                $applyOps.Add([PSCustomObject]@{ Rule = $def.Name; Kind = 'issue'; Action = 'create'; Status = 'error'; Detail = $_.Exception.Message })
            }
        } else {
            try {
                [void](Invoke-SentryApi -Method 'PUT' -Path "/api/0/projects/$orgSlug/$projectSlug/rules/$($existing.id)/" -Token $token -BaseUrl $ApiBaseUrl -Body $payload)
                $applyOps.Add([PSCustomObject]@{ Rule = $def.Name; Kind = 'issue'; Action = 'updated'; Status = 'ok'; Detail = '' })
            } catch {
                $applyOps.Add([PSCustomObject]@{ Rule = $def.Name; Kind = 'issue'; Action = 'update'; Status = 'error'; Detail = $_.Exception.Message })
            }
        }
    }

    $metricRuleName = 'Error Rate Spike - Production'
    $metricPayload = New-MetricSpikeRulePayload -RuleName $metricRuleName -ProjectSlug $projectSlug -Email $alertEmail
    $existingMetric = Get-RuleByName -Rules $metricRules -Name $metricRuleName

    if ($null -eq $existingMetric) {
        try {
            [void](Invoke-SentryApi -Method 'POST' -Path "/api/0/organizations/$orgSlug/alert-rules/" -Token $token -BaseUrl $ApiBaseUrl -Body $metricPayload)
            $applyOps.Add([PSCustomObject]@{ Rule = $metricRuleName; Kind = 'metric'; Action = 'created'; Status = 'ok'; Detail = '' })
        } catch {
            $applyOps.Add([PSCustomObject]@{ Rule = $metricRuleName; Kind = 'metric'; Action = 'create'; Status = 'error'; Detail = $_.Exception.Message })
        }
    } else {
        try {
            [void](Invoke-SentryApi -Method 'PUT' -Path "/api/0/organizations/$orgSlug/alert-rules/$($existingMetric.id)/" -Token $token -BaseUrl $ApiBaseUrl -Body $metricPayload)
            $applyOps.Add([PSCustomObject]@{ Rule = $metricRuleName; Kind = 'metric'; Action = 'updated'; Status = 'ok'; Detail = '' })
        } catch {
            $applyOps.Add([PSCustomObject]@{ Rule = $metricRuleName; Kind = 'metric'; Action = 'update'; Status = 'error'; Detail = $_.Exception.Message })
        }
    }

    $issueRules = Get-SentryIssueRules -Token $token -BaseUrl $ApiBaseUrl -OrgSlug $orgSlug -ProjectSlug $projectSlug
    $metricRules = Get-SentryMetricRules -Token $token -BaseUrl $ApiBaseUrl -OrgSlug $orgSlug -ProjectSlug $projectSlug
}

$checks = @(
    [PSCustomObject]@{
        Name = 'New Issue Email - Production'
        Kind = 'issue'
        Environment = 'production'
        Validate = {
            param([object]$rule)
            $enabled = Test-RuleEnabled -Rule $rule
            $envOk = Test-RuleEnvironment -Rule $rule -Environment 'production'
            $emailOk = Test-RuleContainsEmail -Rule $rule -Email $alertEmail
            $paramsOk = Test-IssueRuleFilters -Rule $rule -Environment 'production' -ExcludedTransactions @('/up', '/health') -RequireErrorLevel
            return [PSCustomObject]@{ Enabled = $enabled; EnvironmentOk = $envOk; EmailOk = $emailOk; ParamsOk = $paramsOk }
        }
    },
    [PSCustomObject]@{
        Name = 'Error Rate Spike - Production'
        Kind = 'metric'
        Environment = 'production'
        Validate = {
            param([object]$rule)
            $enabled = Test-RuleEnabled -Rule $rule
            $envOk = Test-RuleEnvironment -Rule $rule -Environment 'production'
            $emailOk = Test-RuleContainsEmail -Rule $rule -Email $alertEmail
            $paramsOk = Test-MetricSpikeRule -Rule $rule -ProjectSlug $projectSlug -Email $alertEmail
            return [PSCustomObject]@{ Enabled = $enabled; EnvironmentOk = $envOk; EmailOk = $emailOk; ParamsOk = $paramsOk }
        }
    },
    [PSCustomObject]@{
        Name = 'New Issue Email - Staging'
        Kind = 'issue'
        Environment = 'staging'
        Validate = {
            param([object]$rule)
            $enabled = Test-RuleEnabled -Rule $rule
            $envOk = Test-RuleEnvironment -Rule $rule -Environment 'staging'
            $emailOk = Test-RuleContainsEmail -Rule $rule -Email $alertEmail
            $json = Get-JsonText -Value $rule
            $frequencyOk = $json.Contains('"frequency":1440')
            $firstSeenOk = $json.ToLowerInvariant().Contains('first_seen_event')
            $paramsOk = $frequencyOk -and $firstSeenOk
            return [PSCustomObject]@{ Enabled = $enabled; EnvironmentOk = $envOk; EmailOk = $emailOk; ParamsOk = $paramsOk }
        }
    }
)

$results = New-Object System.Collections.Generic.List[object]

foreach ($check in $checks) {
    $rule = if ($check.Kind -eq 'issue') {
        Get-RuleByName -Rules $issueRules -Name $check.Name
    } else {
        Get-RuleByName -Rules $metricRules -Name $check.Name
    }

    if ($null -eq $rule) {
        $results.Add([PSCustomObject]@{
            Rule = $check.Name
            Kind = $check.Kind
            Exists = $false
            Enabled = $false
            EnvironmentOk = $false
            EmailOk = $false
            ParamsOk = $false
            Result = 'FAIL'
            Detail = 'Regla no encontrada'
        })
        continue
    }

    $validation = & $check.Validate $rule
    $pass = $validation.Enabled -and $validation.EnvironmentOk -and $validation.EmailOk -and $validation.ParamsOk

    $detailParts = New-Object System.Collections.Generic.List[string]
    if (-not $validation.Enabled) { $detailParts.Add('enabled=no') }
    if (-not $validation.EnvironmentOk) { $detailParts.Add('environment=no') }
    if (-not $validation.EmailOk) { $detailParts.Add('email=no') }
    if (-not $validation.ParamsOk) { $detailParts.Add('parametros=no') }

    $results.Add([PSCustomObject]@{
        Rule = $check.Name
        Kind = $check.Kind
        Exists = $true
        Enabled = $validation.Enabled
        EnvironmentOk = $validation.EnvironmentOk
        EmailOk = $validation.EmailOk
        ParamsOk = $validation.ParamsOk
        Result = $(if ($pass) { 'PASS' } else { 'FAIL' })
        Detail = $(if ($detailParts.Count -gt 0) { $detailParts -join '; ' } else { 'ok' })
    })
}

Write-Host "Validacion de alert rules Sentry (T1.3)"
Write-Host ("  APP_ENV: {0}" -f $appEnv)
Write-Host ("  ORG: {0}" -f $orgSlug)
Write-Host ("  PROJECT: {0}" -f $projectSlug)
Write-Host ("  MODE: {0}" -f $Mode)
Write-Host ("  EMAIL: {0}" -f $alertEmail)

$results |
    Select-Object Rule, Kind, Exists, Enabled, EnvironmentOk, EmailOk, ParamsOk, Result, Detail |
    Format-Table -AutoSize

if ($applyOps.Count -gt 0) {
    Write-Host ""
    Write-Host "Operaciones de create/update"
    $applyOps |
        Select-Object Rule, Kind, Action, Status, Detail |
        Format-Table -AutoSize
}

if ([string]::IsNullOrWhiteSpace($EvidenceFile)) {
    $safeEnv = ($appEnv -replace '[^a-zA-Z0-9_-]', '_')
    $safeMode = ($Mode -replace '[^a-zA-Z0-9_-]', '_')
    $safeTs = [DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss')
    $EvidenceFile = Join-Path -Path 'Docs' -ChildPath (Join-Path -Path 'evidence' -ChildPath ("T1_3_SENTRY_RULES_{0}_{1}_{2}.md" -f $safeEnv, $safeMode, $safeTs))
}

$evidenceDirectory = Split-Path -Path $EvidenceFile -Parent
if (-not [string]::IsNullOrWhiteSpace($evidenceDirectory) -and -not (Test-Path -Path $evidenceDirectory)) {
    New-Item -ItemType Directory -Path $evidenceDirectory -Force | Out-Null
}

$timestampUtc = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ')
$markdown = New-Object System.Collections.Generic.List[string]
$markdown.Add('# Evidencia T1.3 - Validacion de alert rules Sentry')
$markdown.Add('')
$markdown.Add("- Fecha UTC: $timestampUtc")
$markdown.Add("- APP_ENV: $appEnv")
$markdown.Add("- EnvFile: $EnvFile")
$markdown.Add("- ApiBaseUrl: $ApiBaseUrl")
$markdown.Add("- SENTRY_ORG_SLUG: $orgSlug")
$markdown.Add("- SENTRY_PROJECT_SLUG: $projectSlug")
$markdown.Add("- ALERT_EMAIL_OPERATIONS: $alertEmail")
$markdown.Add("- Modo: $Mode")
$markdown.Add('')
$markdown.Add('| Regla | Tipo | Existe | Enabled | Environment ok | Email ok | Parametros ok | Resultado | Detalle |')
$markdown.Add('| --- | --- | --- | --- | --- | --- | --- | --- | --- |')

foreach ($entry in $results) {
    $markdown.Add(("| {0} | {1} | {2} | {3} | {4} | {5} | {6} | {7} | {8} |" -f
        $entry.Rule,
        $entry.Kind,
        $(if ($entry.Exists) { 'si' } else { 'no' }),
        $(if ($entry.Enabled) { 'si' } else { 'no' }),
        $(if ($entry.EnvironmentOk) { 'si' } else { 'no' }),
        $(if ($entry.EmailOk) { 'si' } else { 'no' }),
        $(if ($entry.ParamsOk) { 'si' } else { 'no' }),
        $entry.Result,
        $entry.Detail
    ))
}

if ($applyOps.Count -gt 0) {
    $markdown.Add('')
    $markdown.Add('## Operaciones de create/update ejecutadas')
    $markdown.Add('')
    $markdown.Add('| Regla | Tipo | Accion | Estado | Detalle |')
    $markdown.Add('| --- | --- | --- | --- | --- |')

    foreach ($op in $applyOps) {
        $markdown.Add(("| {0} | {1} | {2} | {3} | {4} |" -f
            $op.Rule,
            $op.Kind,
            $op.Action,
            $op.Status,
            $op.Detail
        ))
    }
}

Set-Content -Path $EvidenceFile -Value $markdown -Encoding UTF8
Write-Host ("Evidencia guardada en: {0}" -f $EvidenceFile)

$failedValidations = @($results | Where-Object { $_.Result -ne 'PASS' })
$failedOps = @($applyOps | Where-Object { $_.Status -ne 'ok' })

if ($failedOps.Count -gt 0) {
    throw ("Se detectaron errores en create/update de reglas ({0}). Revisa detalle en consola/evidencia." -f $failedOps.Count)
}

if ($failedValidations.Count -gt 0) {
    throw ("La validacion T1.3 de alert rules fallo en {0} regla(s)." -f $failedValidations.Count)
}

Write-Host "Validacion T1.3 de alert rules completada: PASS"