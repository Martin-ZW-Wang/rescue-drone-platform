param(
    [string] $VmName = "OpenWrtDryRun",
    [switch] $Gui,
    [bool] $ShutdownVmOnExit = $true,
    [switch] $ShutdownExistingVmOnExit
)

$ErrorActionPreference = "Stop"

function Read-DotEnvValue {
    param(
        [string] $Path,
        [string] $Key,
        [string] $Default
    )

    if (-not (Test-Path $Path)) {
        return $Default
    }

    $line = Get-Content $Path | Where-Object { $_ -match "^\s*$Key\s*=" } | Select-Object -First 1
    if (-not $line) {
        return $Default
    }

    return (($line -split "=", 2)[1]).Trim().Trim('"').Trim("'")
}

function Find-VBoxManage {
    if ($env:VBOXMANAGE -and (Test-Path $env:VBOXMANAGE)) {
        return $env:VBOXMANAGE
    }

    $candidates = @(
        "E:\VirtualBox\VBoxManage.exe",
        "C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    throw "VBoxManage.exe was not found. Set VBOXMANAGE to your VBoxManage.exe path."
}

function Test-TcpPort {
    param(
        [string] $HostName,
        [int] $Port,
        [int] $TimeoutMs = 1200
    )

    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $result = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $result.AsyncWaitHandle.WaitOne($TimeoutMs, $false)) {
            return $false
        }
        $client.EndConnect($result)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Get-VmState {
    param(
        [string] $VBoxManage,
        [string] $Name
    )

    $info = & $VBoxManage showvminfo $Name --machinereadable 2>$null
    $stateLine = $info | Where-Object { $_ -match '^VMState=' } | Select-Object -First 1
    if ($stateLine -match 'VMState="([^"]+)"') {
        return $Matches[1]
    }

    return "unknown"
}

function Stop-OpenWrtVm {
    param(
        [string] $VBoxManage,
        [string] $Name,
        [string] $HostName,
        [int] $Port,
        [string] $KeyPath
    )

    $state = Get-VmState -VBoxManage $VBoxManage -Name $Name
    if ($state -ne "running") {
        Write-Host "OpenWrt VM is not running."
        return
    }

    Write-Host "Stopping OpenWrt VM..."
    if ((Test-Path $KeyPath) -and (Test-TcpPort -HostName $HostName -Port $Port -TimeoutMs 800)) {
        & ssh -i $KeyPath -o BatchMode=yes -o ConnectTimeout=4 -o StrictHostKeyChecking=accept-new root@$HostName -p $Port "poweroff" 2>$null | Out-Null
        Start-Sleep -Seconds 3
    }

    $state = Get-VmState -VBoxManage $VBoxManage -Name $Name
    if ($state -ne "running") {
        Write-Host "OpenWrt VM stopped."
        return
    }

    & $VBoxManage controlvm $Name acpipowerbutton | Out-Host

    $deadline = (Get-Date).AddSeconds(30)
    while ((Get-Date) -lt $deadline) {
        $state = Get-VmState -VBoxManage $VBoxManage -Name $Name
        if ($state -ne "running") {
            Write-Host "OpenWrt VM stopped."
            return
        }
        Start-Sleep -Seconds 2
    }

    Write-Host "OpenWrt VM did not stop after graceful requests. Powering it off..."
    & $VBoxManage controlvm $Name poweroff | Out-Host
}

$root = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $root ".env"
$openwrtHost = Read-DotEnvValue -Path $envPath -Key "OPENWRT_HOST" -Default "127.0.0.1"
$openwrtPort = [int] (Read-DotEnvValue -Path $envPath -Key "OPENWRT_PORT" -Default "2222")
$sshKey = Read-DotEnvValue -Path $envPath -Key "OPENWRT_SSH_KEY" -Default "$env:USERPROFILE\.ssh\openwrt_dry_run"
$vboxManage = Find-VBoxManage

Write-Host "Using VM: $VmName"
Write-Host "Using OpenWrt SSH: ${openwrtHost}:${openwrtPort}"

$vmInfo = & $vboxManage showvminfo $VmName --machinereadable 2>$null
if ($LASTEXITCODE -ne 0) {
    throw "VirtualBox VM '$VmName' was not found."
}

$isRunning = $vmInfo -match 'VMState="running"'
$startedByScript = $false
if ($isRunning) {
    Write-Host "OpenWrt VM is already running."
} else {
    $startType = if ($Gui) { "gui" } else { "headless" }
    Write-Host "Starting OpenWrt VM as $startType..."
    & $vboxManage startvm $VmName --type $startType
    $startedByScript = $true
}

try {
    Write-Host "Waiting for OpenWrt SSH port..."
    $deadline = (Get-Date).AddSeconds(60)
    while ((Get-Date) -lt $deadline) {
        if (Test-TcpPort -HostName $openwrtHost -Port $openwrtPort) {
            Write-Host "OpenWrt SSH port is reachable."
            break
        }
        Start-Sleep -Seconds 2
    }

    if (-not (Test-TcpPort -HostName $openwrtHost -Port $openwrtPort)) {
        throw "OpenWrt SSH port did not become reachable at ${openwrtHost}:${openwrtPort}."
    }

    if (Test-Path $sshKey) {
        Write-Host "Checking OpenWrt SSH key..."
        $sshOk = $false
        $sshDeadline = (Get-Date).AddSeconds(90)
        while ((Get-Date) -lt $sshDeadline) {
            & ssh -i $sshKey -o BatchMode=yes -o ConnectTimeout=8 -o ConnectionAttempts=1 -o StrictHostKeyChecking=accept-new root@$openwrtHost -p $openwrtPort "echo SSH_OK" | Out-Host
            if ($LASTEXITCODE -eq 0) {
                $sshOk = $true
                break
            }
            Write-Host "OpenWrt SSH is not ready yet; retrying..."
            Start-Sleep -Seconds 5
        }

        if (-not $sshOk) {
            throw "OpenWrt SSH key check failed. Reinstall the public key or check the VM."
        }
    } else {
        Write-Host "SSH key was not found at $sshKey. Starting app anyway."
    }

    Write-Host "Starting rescue drone platform..."
    Set-Location $root
    composer dev:drone
} finally {
    if ($ShutdownVmOnExit -and ($startedByScript -or $ShutdownExistingVmOnExit)) {
        Stop-OpenWrtVm -VBoxManage $vboxManage -Name $VmName -HostName $openwrtHost -Port $openwrtPort -KeyPath $sshKey
    } elseif ($ShutdownVmOnExit) {
        Write-Host "OpenWrt VM was already running before this script, so it was left running."
    }
}
