# Starts LiveKit for local built-in camera streaming (Windows).
# Auto-downloads livekit-server on first run.

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ToolsDir = Join-Path $Root "tools"
$Bin = Join-Path $ToolsDir "livekit-server.exe"
$Config = Join-Path $Root "livekit.yaml"
$Version = "1.13.2"
$VersionFile = Join-Path $ToolsDir "livekit-server.version"
$DownloadUrl = "https://github.com/livekit/livekit/releases/download/v$Version/livekit_${Version}_windows_amd64.zip"

if (-not (Test-Path $Config)) {
    Write-Error "Missing livekit.yaml at $Config"
    exit 1
}

if (-not (Test-Path $ToolsDir)) {
    New-Item -ItemType Directory -Path $ToolsDir | Out-Null
}

$installedVersion = if (Test-Path $VersionFile) { (Get-Content $VersionFile -Raw).Trim() } else { '' }
$needsDownload = -not (Test-Path $Bin) -or $installedVersion -ne $Version
if ($needsDownload -and (Test-Path $Bin)) {
    Remove-Item $Bin -Force -ErrorAction SilentlyContinue
}

if ($needsDownload) {
    Write-Host "LiveKit server not found. Downloading v$Version ..."
    $zipPath = Join-Path $env:TEMP "livekit-server.zip"
    Invoke-WebRequest -Uri $DownloadUrl -OutFile $zipPath -UseBasicParsing
    Expand-Archive -Path $zipPath -DestinationPath $ToolsDir -Force
    Remove-Item $zipPath -Force

    $extracted = Get-ChildItem -Path $ToolsDir -Filter "livekit-server.exe" -Recurse | Select-Object -First 1
    if ($extracted -and $extracted.FullName -ne $Bin) {
        Move-Item -Path $extracted.FullName -Destination $Bin -Force
        Get-ChildItem -Path $ToolsDir -Directory | Where-Object { $_.Name -ne "livekit-server.exe" } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }

    if (-not (Test-Path $Bin)) {
        Write-Error "Download failed. Place livekit-server.exe in $ToolsDir manually."
        exit 1
    }
    Write-Host "LiveKit server installed to $Bin"
    Set-Content -Path $VersionFile -Value $Version -NoNewline
}

Write-Host 'Starting LiveKit on ws://localhost:7880 (Ctrl+C to stop)...'
& $Bin --config $Config
