# Activate the project-hosted SSH key for this repository.
#
# Usage (run from anywhere; path is resolved relative to this script):
#     powershell -ExecutionPolicy Bypass -File .ssh/setup.ps1
#
# Effect: sets repo-level core.sshCommand to use .ssh/id_ed25519,
#         so git push / git fetch use the key stored with the project.
#
# NOTE: This file is intentionally ASCII-only. Windows PowerShell 5.1 reads
#       UTF-8 files without BOM as ANSI, which corrupts non-ASCII text and
#       can break parsing. Keep messages in English.

$ErrorActionPreference = 'Stop'

$sshDir = $PSScriptRoot
$repoRoot = Split-Path -Parent $sshDir
$keyFile = Join-Path $sshDir 'id_ed25519'

if (-not (Test-Path -LiteralPath $keyFile)) {
    Write-Host ('[ERR] Private key not found: {0}' -f $keyFile) -ForegroundColor Red
    Write-Host '      Copy id_ed25519 and id_ed25519.pub into .ssh/ first.' -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path -LiteralPath (Join-Path $repoRoot '.git'))) {
    Write-Host ('[ERR] Not a git repository: {0}' -f $repoRoot) -ForegroundColor Red
    exit 1
}

# git on Windows expects forward slashes in configured paths
$keyPath = $keyFile -replace '\\', '/'
$sshCommand = 'ssh -i {0} -o IdentitiesOnly=yes' -f $keyPath

& git -C $repoRoot config core.sshCommand $sshCommand

Write-Host '[OK]  core.sshCommand configured' -ForegroundColor Green
Write-Host ('      {0}' -f $sshCommand)
Write-Host ''
Write-Host 'Verify connectivity with:'
Write-Host ('      ssh -i {0} -o IdentitiesOnly=yes -T git@github.com' -f $keyPath)
