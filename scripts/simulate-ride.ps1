$ErrorActionPreference = 'Stop'
$Base = 'https://apichama.agilizeerp.com.br/api/v1'
$Headers = @{ 'X-Tenant-Slug' = 'chama-demo'; 'Accept' = 'application/json'; 'Content-Type' = 'application/json' }

function Get-Otp([string]$Phone) {
  Invoke-RestMethod -Method Post -Uri "$Base/auth/otp/send" -Headers $Headers -Body (@{ phone = $Phone; tenant_slug = 'chama-demo' } | ConvertTo-Json) | Out-Null
  Start-Sleep -Seconds 1
  $sql = "SELECT code FROM otp_codes WHERE phone = '$Phone' ORDER BY created_at DESC LIMIT 1;"
  Set-Content -Path "$env:TEMP\get-otp.sql" -Value $sql -Encoding ascii
  scp "$env:TEMP\get-otp.sql" web-i9tecinfo:/tmp/get-otp.sql | Out-Null
  $code = (ssh web-i9tecinfo "docker compose -f /opt/projeto-web/docker-compose.vps.yml exec -T db mariadb -N -uappchama -pAppChama_Vps_2026! appchama < /tmp/get-otp.sql").Trim()
  if ($code -notmatch '^\d{6}$') { throw "OTP invalido do banco: '$code'" }
  return $code
}

function Login([string]$Phone) {
  $code = Get-Otp $Phone
  Write-Host "OTP $Phone = $code"
  $res = Invoke-RestMethod -Method Post -Uri "$Base/auth/otp/verify" -Headers $Headers -Body (@{
    phone = $Phone; code = $code; tenant_slug = 'chama-demo'
  } | ConvertTo-Json)
  return $res.token
}

Write-Host '==> Login motorista'
$driverToken = Login '5511999990003'
$HDriver = $Headers.Clone(); $HDriver['Authorization'] = "Bearer $driverToken"

Write-Host '==> Motorista online + localizacao (SP)'
Invoke-RestMethod -Method Post -Uri "$Base/drivers/online" -Headers $HDriver | Out-Null
Invoke-RestMethod -Method Post -Uri "$Base/drivers/location" -Headers $HDriver -Body (@{ lat = -23.5505; lng = -46.6333 } | ConvertTo-Json) | Out-Null

Write-Host '==> Login passageiro'
$passToken = Login '5511999990002'
$HPass = $Headers.Clone(); $HPass['Authorization'] = "Bearer $passToken"

$cities = Invoke-RestMethod -Method Get -Uri "$Base/cities" -Headers $HPass
$cityId = $cities[0].id
Write-Host "Cidade: $($cities[0].name) ($cityId)"

Write-Host '==> Passageiro solicita corrida'
$ride = Invoke-RestMethod -Method Post -Uri "$Base/rides" -Headers $HPass -Body (@{
  city_id = $cityId
  origin_lat = -23.5505
  origin_lng = -46.6333
  origin_address = 'Av. Paulista, 1000'
  destination_lat = -23.5614
  destination_lng = -46.6558
  destination_address = 'Rua Augusta, 500'
} | ConvertTo-Json)
Write-Host "Corrida criada: $($ride.id) status=$($ride.status)"

# Aceitar enquanto ainda esta searching (apps tambem fazem poll de searching)
Start-Sleep -Seconds 2
Write-Host '==> Motorista aceita'
$ride = Invoke-RestMethod -Method Post -Uri "$Base/rides/$($ride.id)/accept" -Headers $HDriver
Write-Host "Aceita: status=$($ride.status)"

Write-Host '==> Motorista chega / inicia / completa'
$ride = Invoke-RestMethod -Method Post -Uri "$Base/rides/$($ride.id)/arrive" -Headers $HDriver
Write-Host "Chegou: status=$($ride.status)"
$ride = Invoke-RestMethod -Method Post -Uri "$Base/rides/$($ride.id)/start" -Headers $HDriver
Write-Host "Em andamento: status=$($ride.status) fare=$($ride.estimated_fare)"
$ride = Invoke-RestMethod -Method Post -Uri "$Base/rides/$($ride.id)/complete" -Headers $HDriver
Write-Host "Concluida: status=$($ride.status) final_fare=$($ride.final_fare)"

Write-Host ''
Write-Host 'SIMULACAO API OK'
Write-Host "Ride ID: $($ride.id)"
