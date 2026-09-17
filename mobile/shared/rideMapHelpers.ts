import { resolveRideCoords, toLatLng, toLatLngList, toNumber, type LatLng } from './coords';

import { bearingOnRoute, nearestPointOnRoute } from './routeGeometry';

import type { Ride } from './types';



export type RoutePhase = 'to_pickup' | 'to_destination' | 'full';



export function getRoutePhase(ride: Ride): RoutePhase {
  if (ride.status === 'driver_arrived') {
    return 'full';
  }

  if (
    ride.route_phase === 'to_pickup' ||
    ride.route_phase === 'to_destination' ||
    ride.route_phase === 'full'
  ) {
    return ride.route_phase;
  }

  if (ride.status === 'in_progress') return 'to_destination';

  if (ride.status === 'accepted') return 'to_pickup';

  return 'full';
}



export function getRouteTarget(ride: Ride): LatLng & { label: string } {

  const { lat, lng, destLat, destLng } = resolveRideCoords(ride);

  const phase = getRoutePhase(ride);



  if (ride.route_target) {

    const targetLat = toNumber(ride.route_target.latitude);

    const targetLng = toNumber(ride.route_target.longitude);

    if (Number.isFinite(targetLat) && Number.isFinite(targetLng)) {

      return {

        latitude: targetLat,

        longitude: targetLng,

        label: ride.route_target.label ?? (phase === 'to_pickup' ? 'Embarque' : 'Destino'),

      };

    }

  }



  if (phase === 'to_pickup') {

    return { latitude: lat, longitude: lng, label: 'Embarque' };

  }



  return { latitude: destLat, longitude: destLng, label: 'Destino' };

}



export function getDriverLocationFromRide(ride: Ride): LatLng | null {

  const fromApi = toLatLng(ride.driver_location);

  if (fromApi) return fromApi;



  const driver = ride.driver;

  if (driver?.current_lat != null && driver?.current_lng != null) {

    const fromDriver = toLatLng({ latitude: driver.current_lat, longitude: driver.current_lng });

    if (fromDriver) return fromDriver;

  }



  const routePoints = toLatLngList(ride.route_coordinates ?? []);

  if (routePoints.length >= 1) {

    return routePoints[0];

  }



  const city = driver?.city as { departure_lat?: unknown; departure_lng?: unknown } | undefined;

  if (city?.departure_lat != null && city?.departure_lng != null) {

    return toLatLng({ latitude: city.departure_lat, longitude: city.departure_lng });

  }



  return null;

}



export function getMapFitCoordinates(

  ride: Ride,

  driverLoc: LatLng | null,

  routePoints: LatLng[] = [],

): LatLng[] {

  const { lat, lng, destLat, destLng } = resolveRideCoords(ride);

  const phase = getRoutePhase(ride);

  const target = getRouteTarget(ride);

  const isActivePhase = phase === 'to_pickup' || phase === 'to_destination';



  if (routePoints.length >= 2) {

    if (isActivePhase) {

      return routePoints;

    }

    return [routePoints[0], routePoints[routePoints.length - 1]];

  }



  if (driverLoc && isActivePhase) {

    return [

      driverLoc,

      { latitude: target.latitude, longitude: target.longitude },

    ];

  }



  if (isActivePhase) {

    return [

      { latitude: lat, longitude: lng },

      { latitude: destLat, longitude: destLng },

    ];

  }



  return [

    { latitude: lat, longitude: lng },

    { latitude: destLat, longitude: destLng },

  ];

}



export function getDriverBearing(driverLoc: LatLng | null, route: LatLng[]): number | undefined {

  if (!driverLoc || route.length < 2) return undefined;



  const nearest = nearestPointOnRoute(driverLoc, route);

  if (!nearest) return undefined;



  return bearingOnRoute(route, nearest.index);

}



export function getPhaseLegendText(ride: Ride, perspective: 'passenger' | 'driver'): string {

  const phase = getRoutePhase(ride);

  const etaMin = toNumber(ride.driver_eta_min);

  const distanceKm = toNumber(ride.driver_distance_km);



  const etaStr =

    Number.isFinite(etaMin) && Number.isFinite(distanceKm)

      ? `~${Math.round(etaMin)} min · ${distanceKm.toFixed(1).replace('.', ',')} km`

      : null;



  if (phase === 'to_pickup') {

    const prefix = perspective === 'driver' ? 'Embarque' : 'Motorista';

    return etaStr ? `${prefix} a ${etaStr}` : perspective === 'driver' ? 'Indo buscar passageiro' : 'Motorista a caminho';

  }



  if (phase === 'to_destination') {

    return etaStr ? `Destino a ${etaStr}` : 'Indo ao destino';

  }



  return '';

}



export function getPhaseSubtitle(

  ride: Ride,

  perspective: 'passenger' | 'driver',

): string | undefined {

  if (ride.status === 'driver_arrived') {
    return perspective === 'driver'
      ? 'Passageiro embarca aqui'
      : 'Motorista no local de embarque';
  }

  const phase = getRoutePhase(ride);

  if (phase === 'to_pickup') {
    return perspective === 'driver' ? 'Buscar passageiro' : 'A caminho do embarque';
  }

  if (phase === 'to_destination') {
    return perspective === 'driver' ? 'Levar ao destino' : 'A caminho do destino';
  }

  return undefined;

}



export function openNavigationUrl(lat: number, lng: number, label?: string): string {

  const encoded = encodeURIComponent(label ?? 'Destino');

  return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&destination_place_id=${encoded}`;

}


