<?php
$id = '01a0ab71-ecd4-7383-a9cd-4710d43610ce';
$r = App\Models\Ride::withoutGlobalScope(App\Models\Scopes\TenantScope::class)->find($id);
if (!$r) { echo "not found\n"; return; }
echo json_encode($r->only(['id','status','driver_id','passenger_id']), JSON_UNESCAPED_UNICODE)."\n";
$t = app(App\Domains\Rides\Services\RideTrackingService::class)->forRide($r->load('driver.city'));
echo json_encode(['phase'=>$t['route_phase'],'count'=>count($t['route_coordinates'])], JSON_UNESCAPED_UNICODE)."\n";
$w = app(App\Filament\Widgets\LiveMapWidget::class);
$ref = new ReflectionClass($w); $m=$ref->getMethod('buildActiveTrips'); $m->setAccessible(true);
$trips=$m->invoke($w,-28.84,-51.89);
echo 'widget trips='.count($trips)."\n";
