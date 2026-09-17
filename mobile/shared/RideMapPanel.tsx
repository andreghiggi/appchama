import React, { useEffect, useMemo, useRef, useState } from 'react';

import { Pressable, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { resolveRideCoords, toLatLng, toLatLngList, toNumber } from './coords';

import { boundsForPoints, buildRoutePoints } from './geocode';

import {

  getDriverBearing,

  getDriverLocationFromRide,

  getMapFitCoordinates,

  getRoutePhase,

  getRouteTarget,

} from './rideMapHelpers';

import { snapDriverToRoute } from './routeGeometry';

import { useAnimatedLocation } from './useAnimatedLocation';

import { AppMap, AppMarker, AppRoute } from './SimpleMap';

import type { Ride } from './types';
import type { LatLng } from './coords';
import type { RoutePhase } from './rideMapHelpers';

type Props = {

  ride: Ride;

  driverLocation?: { latitude: number; longitude: number } | null;

  mapHeight?: number;

  style?: StyleProp<ViewStyle>;

  perspective?: 'passenger' | 'driver';

  showRecenter?: boolean;

  /** Dispara novo enquadramento quando o mapa muda de tamanho. */

  fitTrigger?: number | string | boolean;

};

function buildPhaseFallbackRoute(
  phase: RoutePhase,
  origin: LatLng,
  destination: LatLng,
  driverPoint: LatLng | null,
): LatLng[] {
  if (phase === 'to_pickup') {
    return buildRoutePoints(driverPoint ?? origin, origin);
  }

  if (phase === 'to_destination') {
    return buildRoutePoints(driverPoint ?? origin, destination);
  }

  return buildRoutePoints(origin, destination);
}

export default function RideMapPanel({

  ride,

  driverLocation = null,

  mapHeight,

  style,

  perspective = 'passenger',

  showRecenter = true,

  fitTrigger,

}: Props) {

  const [recenterToken, setRecenterToken] = useState(0);
  const lastRouteRef = useRef<LatLng[]>([]);

  const isDriverView = perspective === 'driver';



  const { lat, lng, destLat, destLng } = resolveRideCoords(ride);

  const phase = getRoutePhase(ride);

  const target = getRouteTarget(ride);

  const etaMin = toNumber(ride.driver_eta_min);



  const origin = { latitude: lat, longitude: lng };

  const destination = { latitude: destLat, longitude: destLng };



  const driverLocRaw = isDriverView ? driverLocation : getDriverLocationFromRide(ride);



  const routeCoords = useMemo(() => {
    const fromApi = toLatLngList(ride.route_coordinates ?? []);
    const tripFromApi = toLatLngList(ride.trip_route_coordinates ?? []);
    const driverPoint = toLatLng(driverLocRaw);
    const pickupTarget = {
      latitude: target.latitude,
      longitude: target.longitude,
    };

    const mergeLiveDriver = (route: LatLng[]) => {
      if (!driverPoint || route.length < 2) {
        return route;
      }
      return [driverPoint, ...route.slice(1)];
    };

    const fallbackRoute = () =>
      buildPhaseFallbackRoute(phase, origin, destination, driverPoint);

    let resolved: LatLng[] = [];

    if (isDriverView) {
      if (fromApi.length >= 2) {
        resolved = mergeLiveDriver(fromApi);
      } else if (phase === 'full' && tripFromApi.length >= 2) {
        resolved = mergeLiveDriver(tripFromApi);
      } else {
        resolved = fallbackRoute();
      }
    } else if (fromApi.length >= 2) {
      resolved = fromApi;
    } else if (phase === 'full' && tripFromApi.length >= 2) {
      resolved = tripFromApi;
    } else if (phase === 'to_destination' && tripFromApi.length >= 2 && !driverPoint) {
      resolved = tripFromApi;
    } else if (phase === 'to_pickup') {
      resolved = buildRoutePoints(driverPoint ?? origin, pickupTarget);
    } else {
      resolved = fallbackRoute();
    }

    if (resolved.length >= 2) {
      lastRouteRef.current = resolved;
      return resolved;
    }

    if (lastRouteRef.current.length >= 2) {
      return lastRouteRef.current;
    }

    return buildRoutePoints(origin, destination);
  }, [
    isDriverView,
    ride.route_coordinates,
    ride.trip_route_coordinates,
    lat,
    lng,
    destLat,
    destLng,
    phase,
    driverLocRaw,
    target.latitude,
    target.longitude,
    origin.latitude,
    origin.longitude,
    destination.latitude,
    destination.longitude,
  ]);



  const driverLocSnapped = useMemo(

    () => (isDriverView ? null : snapDriverToRoute(driverLocRaw, routeCoords)),

    [isDriverView, driverLocRaw, routeCoords],

  );



  const animatedDriver = useAnimatedLocation(isDriverView ? null : driverLocSnapped);

  const driverBearing = useMemo(

    () => getDriverBearing(animatedDriver ?? driverLocSnapped, routeCoords),

    [animatedDriver, driverLocSnapped, routeCoords],

  );



  const fitCoords = useMemo(() => {

    if (isDriverView) {
      if (routeCoords.length >= 2) {
        return routeCoords;
      }
      return [origin, destination];
    }

    return getMapFitCoordinates(ride, animatedDriver ?? driverLocSnapped, routeCoords);

  }, [isDriverView, ride, animatedDriver, driverLocSnapped, routeCoords, origin, destination]);



  const initialRegion = useMemo(() => {

    if (fitCoords.length >= 2) {

      return boundsForPoints(fitCoords);

    }

    return {

      latitude: (lat + destLat) / 2,

      longitude: (lng + destLng) / 2,

      latitudeDelta: 0.02,

      longitudeDelta: 0.02,

    };

  }, [fitCoords, lat, lng, destLat, destLng]);



  useEffect(() => {
    if (fitTrigger == null) return;
    setRecenterToken((value) => value + 1);
  }, [fitTrigger]);

  useEffect(() => {
    setRecenterToken((value) => value + 1);
  }, [phase, ride.route_coordinates?.length]);



  const showDriverOnMap =

    !isDriverView &&

    Boolean(animatedDriver ?? driverLocSnapped) &&

    ride.status !== 'searching' &&

    ['accepted', 'driver_arrived', 'in_progress'].includes(ride.status);



  const isActiveRide = ['accepted', 'driver_arrived', 'in_progress'].includes(ride.status);

  const showTargetWithEta =
    !isDriverView && (phase === 'to_pickup' || phase === 'to_destination');

  const showOriginPin =
    (isActiveRide || phase === 'full' || phase === 'to_pickup') &&
    !(showTargetWithEta && phase === 'to_pickup');

  const showDestinationPin =
    (isActiveRide || phase === 'full' || phase === 'to_destination') &&
    !(showTargetWithEta && phase === 'to_destination');



  return (

    <View style={[styles.wrap, mapHeight != null && { height: mapHeight }, style]}>

      <AppMap

        style={styles.map}

        region={initialRegion}

        fitToCoordinates={fitCoords}

        pinMode="marker"

        recenterToken={recenterToken}

      >

        {showOriginPin ? (

          <AppMarker coordinate={origin} title="Embarque" variant="origin" />

        ) : null}

        {showDestinationPin ? (

          <AppMarker coordinate={destination} title="Destino" variant="destination" />

        ) : null}

        {showTargetWithEta ? (

          <AppMarker

            coordinate={{ latitude: target.latitude, longitude: target.longitude }}

            title={target.label}

            variant={phase === 'to_pickup' ? 'origin' : 'destination'}

            etaMinutes={Number.isFinite(etaMin) ? etaMin : undefined}

            etaLabel="Chegada"

          />

        ) : null}

        {showDriverOnMap && (animatedDriver ?? driverLocSnapped) ? (

          <AppMarker

            coordinate={animatedDriver ?? driverLocSnapped!}

            title="Motorista"

            variant="driver"

            bearing={driverBearing}

            skipAnimation

          />

        ) : null}

        <AppRoute coordinates={routeCoords} />

      </AppMap>



      {showRecenter ? (

        <Pressable

          style={styles.recenterFab}

          onPress={() => setRecenterToken((value) => value + 1)}

          accessibilityLabel="Centralizar mapa"

        >

          <View style={styles.recenterIconOuter}>

            <View style={styles.recenterIconInner} />

          </View>

        </Pressable>

      ) : null}

    </View>

  );

}



const styles = StyleSheet.create({

  wrap: {

    position: 'relative',

    flexShrink: 0,

    width: '100%',

    overflow: 'hidden',

  },

  map: { flex: 1 },

  recenterFab: {

    position: 'absolute',

    bottom: 16,

    right: 16,

    zIndex: 700,

    width: 48,

    height: 48,

    borderRadius: 24,

    backgroundColor: '#111',

    alignItems: 'center',

    justifyContent: 'center',

    shadowColor: '#000',

    shadowOpacity: 0.25,

    shadowRadius: 8,

    elevation: 6,

  },

  recenterIconOuter: {

    width: 18,

    height: 18,

    borderRadius: 9,

    borderWidth: 2,

    borderColor: '#fff',

    alignItems: 'center',

    justifyContent: 'center',

  },

  recenterIconInner: {

    width: 4,

    height: 4,

    borderRadius: 2,

    backgroundColor: '#fff',

  },

});


