param(
    [string]$EnvFile = ".env.sentry.staging",
    [string]$BaseUrl,
    [int]$TimeoutSec = 20,
    [int]$RetryAttempts = 3,
    [string[]]$Paths = @('/', '/health', '/login', '/dashboard', '/api/tickets'),
    [switch]$SkipPreflight,
    [switch]$LocalDebugMode,
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

function Normalize-Path {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return '/'
    }

    if ($Path.StartsWith('/')) {
        return $Path
    }

    return '/' + $Path
}

function Resolve-ExpectedStatus {
    param([string]$Path)

    $normalizedPath = Normalize-Path -Path $Path
    switch -Regex ($normalizedPath) {
        '^/dashboard$' { return 302 }
        '^/api/' { return 401 }
        default { return 200 }
    }
}

function Get-HeaderValueFromResponse {
    param(
        [System.Net.Http.HttpResponseMessage]$Response,
        [string]$Name
    )

    if ($null -eq $Response) {
        return ''
    }

    $values = $null
    if ($Response.Headers.TryGetValues($Name, [ref]$values)) {
        return [string]::Join(' | ', $values)
    }

    if ($null -ne $Response.Content) {
        $contentValues = $null
        if ($Response.Content.Headers.TryGetValues($Name, [ref]$contentValues)) {
            return [string]::Join(' | ', $contentValues)
        }
    }

    return ''
}

function Has-HeaderValue {
    param([string]$Value)

    return -not [string]::IsNullOrWhiteSpace($Value)
}

if ($RetryAttempts -lt 1) {
    throw "RetryAttempts debe ser >= 1"
}

$preflightPath = Join-Path -Path $PSScriptRoot -ChildPath "sentry_preflight.ps1"
if (-not $SkipPreflight) {
    $preflightArgs = @(
        '-File', $preflightPath,
        '-EnvFile', $EnvFile,
        '-SkipComposeCheck'
    )

    if (-not $LocalDebugMode) {
        $preflightArgs += '-RequireRealHttpsUrl'
    }

    & pwsh @preflightArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Preflight fallo antes de validar cabeceras."
    }
}

$settings = Get-EnvMap -Path $EnvFile
if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    if (-not $settings.ContainsKey('APP_URL')) {
        throw "No se encontro APP_URL en $EnvFile y tampoco se proporciono -BaseUrl."
    }

    $BaseUrl = [string]$settings['APP_URL']
}

$baseUri = $null
if (-not [System.Uri]::TryCreate($BaseUrl, [System.UriKind]::Absolute, [ref]$baseUri)) {
    throw "BaseUrl no es una URL valida: $BaseUrl"
}

if (-not $LocalDebugMode -and $baseUri.Scheme -ne 'https') {
    throw "La validacion estricta de T1.4 requiere HTTPS real. BaseUrl actual: $BaseUrl"
}

if ($LocalDebugMode) {
    Write-Warning "LocalDebugMode activo: la evidencia generada NO cierra T1.4 en ambientes reales."
}

$environmentName = if ($settings.ContainsKey('APP_ENV')) { [string]$settings['APP_ENV'] } else { 'unknown' }
$timestampUtc = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ')
$headerNames = @(
    'X-Frame-Options',
    'X-Content-Type-Options',
    'Referrer-Policy',
    'Permissions-Policy',
    'Cross-Origin-Opener-Policy',
    'Cross-Origin-Resource-Policy',
    'Content-Security-Policy-Report-Only',
    'Content-Security-Policy',
    'Strict-Transport-Security'
)

$results = @()

 $httpHandler = [System.Net.Http.HttpClientHandler]::new()
$httpHandler.AllowAutoRedirect = $false
$httpClient = [System.Net.Http.HttpClient]::new($httpHandler)
$httpClient.Timeout = [TimeSpan]::FromSeconds($TimeoutSec)

try {
    foreach ($rawPath in $Paths) {
        $path = Normalize-Path -Path $rawPath
        $requestUri = [System.Uri]::new($baseUri, $path).AbsoluteUri
        $expectedStatus = Resolve-ExpectedStatus -Path $path

        $requestHeaders = @{}
        if ($path.StartsWith('/api/')) {
            $requestHeaders['Accept'] = 'application/json'
        }

        if ($LocalDebugMode -and $baseUri.Scheme -eq 'http') {
            $requestHeaders['X-Forwarded-Proto'] = 'https'
        }

        $response = $null
        $status = -1
        $attemptsUsed = 0

        for ($attempt = 1; $attempt -le $RetryAttempts; $attempt++) {
            $attemptsUsed = $attempt

            $request = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::Get, $requestUri)
            foreach ($headerEntry in $requestHeaders.GetEnumerator()) {
                [void]$request.Headers.TryAddWithoutValidation([string]$headerEntry.Key, [string]$headerEntry.Value)
            }

            $attemptResponse = $httpClient.Send($request)
            $attemptStatus = [int]$attemptResponse.StatusCode

            if ($attemptStatus -eq $expectedStatus -or $attempt -eq $RetryAttempts) {
                $response = $attemptResponse
                $status = $attemptStatus
                $request.Dispose()
                break
            }

            $attemptResponse.Dispose()
            $request.Dispose()
        }

        if ($null -eq $response) {
            throw "No se pudo obtener respuesta HTTP final para: $requestUri"
        }

        $routeHeaders = @{}
        foreach ($headerName in $headerNames) {
            $routeHeaders[$headerName] = Get-HeaderValueFromResponse -Response $response -Name $headerName
        }

        $cspValue = if (Has-HeaderValue -Value $routeHeaders['Content-Security-Policy']) {
            $routeHeaders['Content-Security-Policy']
        } else {
            $routeHeaders['Content-Security-Policy-Report-Only']
        }

        $headersPass =
            (Has-HeaderValue -Value $routeHeaders['X-Frame-Options']) -and
            (Has-HeaderValue -Value $routeHeaders['X-Content-Type-Options']) -and
            (Has-HeaderValue -Value $routeHeaders['Referrer-Policy']) -and
            (Has-HeaderValue -Value $routeHeaders['Permissions-Policy']) -and
            (Has-HeaderValue -Value $routeHeaders['Cross-Origin-Opener-Policy']) -and
            (Has-HeaderValue -Value $routeHeaders['Cross-Origin-Resource-Policy']) -and
            (Has-HeaderValue -Value $cspValue) -and
            (Has-HeaderValue -Value $routeHeaders['Strict-Transport-Security'])

        $statusPass = $status -eq $expectedStatus
        $overallPass = $statusPass -and $headersPass

        $results += [PSCustomObject]@{
            Path = $path
            RequestUri = $requestUri
            Attempts = $attemptsUsed
            ExpectedStatus = $expectedStatus
            Status = $status
            StatusPass = $statusPass
            HeadersPass = $headersPass
            OverallPass = $overallPass
            XFrameOptions = $routeHeaders['X-Frame-Options']
            XContentTypeOptions = $routeHeaders['X-Content-Type-Options']
            ReferrerPolicy = $routeHeaders['Referrer-Policy']
            PermissionsPolicy = $routeHeaders['Permissions-Policy']
            CrossOriginOpenerPolicy = $routeHeaders['Cross-Origin-Opener-Policy']
            CrossOriginResourcePolicy = $routeHeaders['Cross-Origin-Resource-Policy']
            ContentSecurityPolicy = $cspValue
            StrictTransportSecurity = $routeHeaders['Strict-Transport-Security']
        }

        $response.Dispose()
        $request.Dispose()
    }
} finally {
    $httpClient.Dispose()
    $httpHandler.Dispose()
}

$failed = @($results | Where-Object { -not $_.OverallPass })

Write-Host "Validacion T1.4 de cabeceras"
Write-Host ("  Fecha UTC: {0}" -f $timestampUtc)
Write-Host ("  APP_ENV: {0}" -f $environmentName)
Write-Host ("  BaseUrl: {0}" -f $baseUri.AbsoluteUri)
Write-Host ("  Modo: {0}" -f ($(if ($LocalDebugMode) { 'LOCAL_DEBUG' } else { 'STRICT' })))

$results |
    Select-Object Path, Attempts, Status, ExpectedStatus, StatusPass, HeadersPass, OverallPass |
    Format-Table -AutoSize

if ([string]::IsNullOrWhiteSpace($EvidenceFile)) {
    $safeEnv = ($environmentName -replace '[^a-zA-Z0-9_-]', '_')
    $safeTs = [DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss')
    $EvidenceFile = Join-Path -Path 'Docs' -ChildPath (Join-Path -Path 'evidence' -ChildPath ("T1_4_HEADERS_{0}_{1}.md" -f $safeEnv, $safeTs))
}

$evidenceDirectory = Split-Path -Path $EvidenceFile -Parent
if (-not [string]::IsNullOrWhiteSpace($evidenceDirectory) -and -not (Test-Path -Path $evidenceDirectory)) {
    New-Item -ItemType Directory -Path $evidenceDirectory -Force | Out-Null
}

$modeLabel = if ($LocalDebugMode) { 'LOCAL_DEBUG (NO APTO PARA CIERRE T1.4)' } else { 'STRICT' }

$markdown = New-Object System.Collections.Generic.List[string]
$markdown.Add('# Evidencia T1.4 - Validacion de cabeceras HTTP')
$markdown.Add('')
$markdown.Add("- Fecha UTC: $timestampUtc")
$markdown.Add("- APP_ENV: $environmentName")
$markdown.Add("- EnvFile: $EnvFile")
$markdown.Add(("- BaseUrl: {0}" -f $baseUri.AbsoluteUri))
$markdown.Add("- Modo: $modeLabel")
$markdown.Add('')
$markdown.Add('| Ruta | Status esperado | Status observado | Status ok | Headers ok | Resultado |')
$markdown.Add('| --- | --- | --- | --- | --- | --- |')

foreach ($entry in $results) {
    $markdown.Add(("| {0} | {1} | {2} | {3} | {4} | {5} |" -f
        $entry.Path,
        $entry.ExpectedStatus,
        $entry.Status,
        $(if ($entry.StatusPass) { 'si' } else { 'no' }),
        $(if ($entry.HeadersPass) { 'si' } else { 'no' }),
        $(if ($entry.OverallPass) { 'PASS' } else { 'FAIL' })
    ))
}

$markdown.Add('')
$markdown.Add('## Cabeceras observadas por ruta')

foreach ($entry in $results) {
    $markdown.Add('')
    $markdown.Add(("### {0}" -f $entry.Path))
    $markdown.Add(("- RequestUri: {0}" -f $entry.RequestUri))
    $markdown.Add(("- Intentos: {0}" -f $entry.Attempts))
    $markdown.Add(("- X-Frame-Options: {0}" -f $entry.XFrameOptions))
    $markdown.Add(("- X-Content-Type-Options: {0}" -f $entry.XContentTypeOptions))
    $markdown.Add(("- Referrer-Policy: {0}" -f $entry.ReferrerPolicy))
    $markdown.Add(("- Permissions-Policy: {0}" -f $entry.PermissionsPolicy))
    $markdown.Add(("- Cross-Origin-Opener-Policy: {0}" -f $entry.CrossOriginOpenerPolicy))
    $markdown.Add(("- Cross-Origin-Resource-Policy: {0}" -f $entry.CrossOriginResourcePolicy))
    $markdown.Add(("- Content-Security-Policy(-Report-Only): {0}" -f $entry.ContentSecurityPolicy))
    $markdown.Add(("- Strict-Transport-Security: {0}" -f $entry.StrictTransportSecurity))
}

Set-Content -Path $EvidenceFile -Value $markdown -Encoding UTF8
Write-Host ("Evidencia guardada en: {0}" -f $EvidenceFile)

if ($failed.Count -gt 0) {
    throw ("La validacion T1.4 fallo en {0} ruta(s). Revisa la evidencia y corrige antes de cerrar checklist." -f $failed.Count)
}

Write-Host "Validacion T1.4 completada: PASS"
