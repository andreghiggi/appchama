import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import {
  boundsForPoints,
  buildRoutePoints,
  reverseGeocode,
  searchAddresses,
  type GeocodeResult,
} from '../../../shared/geocode';
import { formatBrl } from '../../../shared/fare';
import { requestCurrentLocation } from '../../../shared/location';
import { MapLocateButton } from '../../../shared/MapLocateButton';
import {
  resolvePickupPoint,
} from '../../../shared/serviceArea';
import { AppMap, AppMarker, AppRoute } from '../../../shared/SimpleMap';
import { theme } from '../../../shared/theme';
import type { City, Ride } from '../../../shared/types';
import { useAuth } from '../context/AuthContext';

type Props = {
  onRideActive: (ride: Ride) => void;
};

type RideEstimate = {
  distanceKm: number;
  durationMin: number;
  fare: number;
  routePoints: Array<{ latitude: number; longitude: number }>;
};

export default function HomeScreen({ onRideActive }: Props) {
  const { user } = useAuth();
  const [region, setRegion] = useState({
    latitude: -23.5505,
    longitude: -46.6333,
    latitudeDelta: 0.04,
    longitudeDelta: 0.04,
  });
  const [pickupPoint, setPickupPoint] = useState<{ latitude: number; longitude: number } | null>(
    null,
  );
  const [pickupLabel, setPickupLabel] = useState<string | null>(null);
  const [destination, setDestination] = useState('');
  const [destinationPoint, setDestinationPoint] = useState<GeocodeResult | null>(null);
  const [suggestions, setSuggestions] = useState<GeocodeResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [loading, setLoading] = useState(false);
  const [locating, setLocating] = useState(false);
  const [estimating, setEstimating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);
  const [geocodeError, setGeocodeError] = useState<string | null>(null);
  const [estimateError, setEstimateError] = useState<string | null>(null);
  const [pickupAreaHint, setPickupAreaHint] = useState<string | null>(null);
  const [areaError, setAreaError] = useState<string | null>(null);
  const [maxRadiusKm, setMaxRadiusKm] = useState(50);
  const [city, setCity] = useState<City | null>(null);
  const [operatingCities, setOperatingCities] = useState<City[]>([]);
  const [cityLocked, setCityLocked] = useState(false);
  const [fareEstimate, setFareEstimate] = useState<RideEstimate | null>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const regionTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const activePickup = pickupPoint ?? {
    latitude: region.latitude,
    longitude: region.longitude,
  };

  const routePoints = useMemo(() => {
    if (fareEstimate?.routePoints.length) return fareEstimate.routePoints;
    if (!destinationPoint) return [];
    return buildRoutePoints(activePickup, destinationPoint);
  }, [fareEstimate, destinationPoint, activePickup]);

  const mapRegion = useMemo(
    () =>
      destinationPoint ? boundsForPoints([activePickup, destinationPoint]) : region,
    [destinationPoint, activePickup, region],
  );

  const fitCoords = useMemo(
    () => (destinationPoint ? [activePickup, destinationPoint] : undefined),
    [destinationPoint, activePickup],
  );

  const resolveCityAt = async (
    coords: { latitude: number; longitude: number },
    options?: { cityId?: string; manual?: boolean },
  ): Promise<{ city: City | null; withinServiceArea: boolean }> => {
    const cityId = options?.cityId ?? (cityLocked && city ? city.id : undefined);

    try {
      const resolved = await api.nearestCity(coords.latitude, coords.longitude, cityId);
      setCity(resolved.city);
      setMaxRadiusKm(resolved.max_radius_km);

      const withinServiceArea = resolved.within_service_area !== false;

      if (!withinServiceArea) {
        setAreaError(
          `Mova o mapa para dentro de ${resolved.max_radius_km} km de ${resolved.city.name}.`,
        );
        return { city: resolved.city, withinServiceArea: false };
      }

      setAreaError(null);
      if (options?.manual) {
        setCityLocked(true);
      }
      return { city: resolved.city, withinServiceArea: true };
    } catch (e: unknown) {
      if (cityId && city) {
        return { city, withinServiceArea: false };
      }
      setCity(null);
      setAreaError(e instanceof Error ? e.message : 'Fora da área de operação.');
      return { city: null, withinServiceArea: false };
    }
  };

  const selectOperatingCity = async (selected: City) => {
    setCityLocked(true);
    setAreaError(null);
    setPickupAreaHint(null);

    try {
      const resolved = await api.nearestCity(0, 0, selected.id, true);
      const center = resolved.departure_center;

      setCity(resolved.city);
      setMaxRadiusKm(resolved.max_radius_km);
      setRegion({
        latitude: center.latitude,
        longitude: center.longitude,
        latitudeDelta: 0.08,
        longitudeDelta: 0.08,
      });
      setPickupPoint(null);
      setPickupLabel(center.label ?? resolved.city.departure_address ?? resolved.city.name);
      setAreaError(null);
    } catch (e: unknown) {
      setCityLocked(false);
      setCity(null);
      Alert.alert(
        'Cidade indisponível',
        e instanceof Error ? e.message : 'Não foi possível carregar a área de operação.',
      );
    }
  };

  const matchCityByName = (name?: string | null): City | undefined => {
    if (!name?.trim()) return undefined;
    const normalized = name.trim().toLowerCase();
    return operatingCities.find((item) => item.name.trim().toLowerCase() === normalized);
  };

  const applyPickup = async (
    coords: { latitude: number; longitude: number },
    activeCity: City,
  ) => {
    const { pickup, usedCityCenter } = resolvePickupPoint(coords, activeCity);
    setRegion((r) => ({
      ...r,
      latitude: pickup.latitude,
      longitude: pickup.longitude,
    }));
    if (!destinationPoint) {
      setPickupPoint(null);
      const label = await reverseGeocode(pickup.latitude, pickup.longitude);
      setPickupLabel(label);
    }
    setPickupAreaHint(
      usedCityCenter
        ? `Sua localização está fora da área. Embarque definido em ${activeCity.name}.`
        : null,
    );
  };

  const centerOnUser = async () => {
    setLocating(true);
    setLocationError(null);
    try {
      const coords = await requestCurrentLocation();
      const { city: activeCity, withinServiceArea } = await resolveCityAt(coords);
      if (activeCity && withinServiceArea) {
        await applyPickup(coords, activeCity);
      } else {
        setRegion((r) => ({
          ...r,
          latitude: coords.latitude,
          longitude: coords.longitude,
        }));
        if (!destinationPoint) {
          setPickupPoint(null);
          const label = await reverseGeocode(coords.latitude, coords.longitude);
          setPickupLabel(label);
        }
      }
    } catch (e: unknown) {
      setLocationError(e instanceof Error ? e.message : 'Não foi possível obter sua localização.');
    } finally {
      setLocating(false);
    }
  };

  const handleRegionChangeComplete = (next: {
    latitude: number;
    longitude: number;
    latitudeDelta?: number;
    longitudeDelta?: number;
  }) => {
    setRegion(next);
    if (destinationPoint) return;

    if (regionTimer.current) clearTimeout(regionTimer.current);
    regionTimer.current = setTimeout(() => {
      void resolveCityAt({ latitude: next.latitude, longitude: next.longitude });
    }, 600);
  };

  useEffect(() => {
    return () => {
      if (regionTimer.current) clearTimeout(regionTimer.current);
    };
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const cities = await api.listCities();
        setOperatingCities(cities);
      } catch {
        setOperatingCities([]);
      }
    })();
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const homeLat = user?.home_lat != null ? Number(user.home_lat) : null;
        const homeLng = user?.home_lng != null ? Number(user.home_lng) : null;

        if (homeLat != null && homeLng != null && !Number.isNaN(homeLat) && !Number.isNaN(homeLng)) {
          const { city: activeCity, withinServiceArea } = await resolveCityAt({
            latitude: homeLat,
            longitude: homeLng,
          });
          if (activeCity && withinServiceArea) {
            await applyPickup({ latitude: homeLat, longitude: homeLng }, activeCity);
            if (user?.home_address) {
              setPickupLabel(user.home_address);
            }
            return;
          }
        }

        const matchedCity = matchCityByName(user?.home_city);
        if (matchedCity) {
          await selectOperatingCity(matchedCity);
          return;
        }

        const coords = await requestCurrentLocation().catch(() => null);
        if (coords) {
          const { city: activeCity, withinServiceArea } = await resolveCityAt(coords);
          if (activeCity && withinServiceArea) {
            await applyPickup(coords, activeCity);
            return;
          }
        }

        if (operatingCities.length === 1) {
          await selectOperatingCity(operatingCities[0]);
        }
      } catch {
        if (operatingCities.length === 1) {
          await selectOperatingCity(operatingCities[0]);
        }
      }
    })();
  }, [user?.home_lat, user?.home_lng, user?.home_address, user?.home_city, operatingCities.length]);

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    const q = destination.trim();
    if (q.length < 3 || destinationPoint?.label === q) {
      setSuggestions([]);
      setGeocodeError(null);
      return;
    }

    searchTimer.current = setTimeout(async () => {
      setSearching(true);
      setGeocodeError(null);
      try {
        const { results, cityId, cityName, maxRadiusKm: radiusKm } = await searchAddresses(
          q,
          activePickup,
          city?.id,
        );
        if (radiusKm != null) setMaxRadiusKm(radiusKm);
        if (cityId && city?.id !== cityId) {
          await resolveCityAt(activePickup, { cityId });
        }
        setSuggestions(results);
        if (results.length === 0) {
          const label = cityName ?? city?.name ?? 'operação';
          const radius = radiusKm ?? maxRadiusKm;
          setGeocodeError(
            `Nenhum endereço encontrado dentro de ${radius} km de ${label}.`,
          );
        }
      } catch (e: unknown) {
        setSuggestions([]);
        setGeocodeError(e instanceof Error ? e.message : 'Falha na busca de endereços.');
      } finally {
        setSearching(false);
      }
    }, 450);

    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    };
  }, [destination, activePickup.latitude, activePickup.longitude, destinationPoint?.label, city?.id]);

  useEffect(() => {
    if (!city || !destinationPoint) {
      setFareEstimate(null);
      setEstimateError(null);
      return;
    }

    const origin = pickupPoint ?? activePickup;

    let cancelled = false;
    setEstimating(true);
    setEstimateError(null);

    (async () => {
      try {
        const estimate = await api.estimateRide({
          city_id: city.id,
          origin_lat: origin.latitude,
          origin_lng: origin.longitude,
          destination_lat: destinationPoint.latitude,
          destination_lng: destinationPoint.longitude,
        });

        if (cancelled) return;

        setFareEstimate({
          distanceKm: estimate.distance_km,
          durationMin: estimate.duration_min,
          fare: estimate.estimated_fare,
          routePoints: estimate.coordinates?.length
            ? estimate.coordinates
            : buildRoutePoints(origin, destinationPoint),
        });
        setEstimateError(null);
      } catch (e: unknown) {
        if (!cancelled) {
          setFareEstimate(null);
          setEstimateError(e instanceof Error ? e.message : 'Não foi possível estimar a corrida.');
        }
      } finally {
        if (!cancelled) setEstimating(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [city, destinationPoint, pickupPoint, activePickup.latitude, activePickup.longitude]);

  const selectDestination = async (item: GeocodeResult) => {
    const frozenPickup = {
      latitude: activePickup.latitude,
      longitude: activePickup.longitude,
    };
    setPickupPoint(frozenPickup);
    setDestination(item.label);
    setDestinationPoint(item);
    setSuggestions([]);

    const label = await reverseGeocode(frozenPickup.latitude, frozenPickup.longitude);
    setPickupLabel(label);
  };

  const clearDestination = () => {
    setDestination('');
    setDestinationPoint(null);
    setPickupPoint(null);
    setPickupLabel(null);
    setFareEstimate(null);
    setEstimateError(null);
    setSuggestions([]);
  };

  const requestRide = async () => {
    if (areaError) {
      Alert.alert('Fora da área', areaError);
      return;
    }
    if (!destinationPoint) {
      Alert.alert('Destino', 'Selecione um endereço de destino na lista.');
      return;
    }

    setLoading(true);
    try {
      const { city: activeCity, withinServiceArea } = await resolveCityAt(activePickup);
      if (!activeCity || !withinServiceArea) {
        Alert.alert('Fora da área', areaError ?? 'Localização fora das áreas de operação.');
        return;
      }

      const originAddress =
        pickupLabel ?? (await reverseGeocode(activePickup.latitude, activePickup.longitude));
      const ride = await api.createRide({
        city_id: activeCity.id,
        origin_lat: activePickup.latitude,
        origin_lng: activePickup.longitude,
        origin_address: originAddress,
        destination_lat: destinationPoint.latitude,
        destination_lng: destinationPoint.longitude,
        destination_address: destinationPoint.label,
      });
      onRideActive(ride);
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Não foi possível solicitar corrida');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.mapWrap}>
        <AppMap
          style={styles.map}
          region={mapRegion}
          onRegionChangeComplete={destinationPoint ? undefined : handleRegionChangeComplete}
          pinMode={destinationPoint ? 'marker' : 'center'}
          fitToCoordinates={fitCoords}
        >
          {destinationPoint ? (
            <>
              <AppMarker coordinate={activePickup} title="Embarque" variant="origin" />
              <AppMarker coordinate={destinationPoint} title="Destino" variant="destination" />
              <AppRoute coordinates={routePoints} />
            </>
          ) : null}
        </AppMap>
        <MapLocateButton onPress={centerOnUser} loading={locating} />
      </View>

      {areaError ? (
        <View style={styles.areaErrorBanner}>
          <Text style={styles.areaErrorText}>{areaError}</Text>
        </View>
      ) : null}
      {pickupAreaHint ? (
        <View style={styles.locationBanner}>
          <Text style={styles.locationBannerText}>{pickupAreaHint}</Text>
        </View>
      ) : null}
      {locationError ? (
        <View style={styles.locationBanner}>
          <Text style={styles.locationBannerText}>{locationError}</Text>
        </View>
      ) : null}

      <ScrollView
        style={styles.sheetScroll}
        contentContainerStyle={styles.sheet}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={styles.greet}>Escolha o destino</Text>
        {operatingCities.length > 0 ? (
          <View style={styles.cityPicker}>
            <Text style={styles.cityPickerLabel}>Cidade de operação</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {operatingCities.map((item) => {
                const selected = city?.id === item.id;
                return (
                  <Pressable
                    key={item.id}
                    style={[styles.cityChip, selected && styles.cityChipActive]}
                    onPress={() => void selectOperatingCity(item)}
                  >
                    <Text style={[styles.cityChipText, selected && styles.cityChipTextActive]}>
                      {item.name}
                    </Text>
                  </Pressable>
                );
              })}
            </ScrollView>
          </View>
        ) : null}
        {city ? (
          <Text style={styles.cityHint}>
            Área de {city.name} · até {maxRadiusKm} km
          </Text>
        ) : null}

        <View style={styles.routeRow}>
          <View style={[styles.routeBadge, styles.routeBadgeFrom]}>
            <Text style={styles.routeBadgeText}>De</Text>
          </View>
          <Text style={styles.routeValue} numberOfLines={2}>
            {pickupLabel ?? 'Minha localização'}
          </Text>
        </View>

        <View style={styles.routeRow}>
          <View style={[styles.routeBadge, styles.routeBadgeTo]}>
            <Text style={styles.routeBadgeText}>Para</Text>
          </View>
          <View style={styles.routeInputWrap}>
            <TextInput
              style={styles.routeInput}
              placeholder="Buscar endereço"
              placeholderTextColor={theme.textMuted}
              value={destination}
              onChangeText={(text) => {
                setDestination(text);
                if (destinationPoint && text !== destinationPoint.label) {
                  setDestinationPoint(null);
                  setPickupPoint(null);
                  setPickupLabel(null);
                  setFareEstimate(null);
                }
              }}
            />
            {destinationPoint ? (
              <Pressable style={styles.clearBtn} onPress={clearDestination}>
                <Text style={styles.clearBtnText}>✕</Text>
              </Pressable>
            ) : null}
          </View>
        </View>
        {searching ? <ActivityIndicator color={theme.ink} style={{ marginBottom: 8 }} /> : null}
        {geocodeError ? <Text style={styles.inlineError}>{geocodeError}</Text> : null}
        {suggestions.length > 0 ? (
          <View style={styles.suggestions}>
            {suggestions.map((item) => (
              <Pressable
                key={`${item.latitude}-${item.longitude}`}
                style={styles.suggestionItem}
                onPress={() => selectDestination(item)}
              >
                <Text style={styles.suggestionText} numberOfLines={2}>
                  {item.label}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}
        {estimating ? (
          <ActivityIndicator color={theme.mint} style={{ marginBottom: 8 }} />
        ) : null}
        {estimateError ? <Text style={styles.inlineError}>{estimateError}</Text> : null}
        {fareEstimate && fareEstimate.fare > 0 ? (
          <View style={styles.fareBox}>
            <Text style={styles.fareTitle}>Estimativa</Text>
            <Text style={styles.fareValue}>{formatBrl(fareEstimate.fare)}</Text>
            <Text style={styles.fareMeta}>
              {fareEstimate.distanceKm.toFixed(1)} km · ~{fareEstimate.durationMin} min
            </Text>
            <Text style={styles.fareMeta}>
              Bandeirada {formatBrl(Number(city?.base_fare ?? 0))} +{' '}
              {formatBrl(Number(city?.price_per_km ?? 0))}/km
            </Text>
          </View>
        ) : null}
        <Pressable style={styles.button} onPress={requestRide} disabled={loading || Boolean(areaError)}>
          {loading ? (
            <ActivityIndicator color={theme.ink} />
          ) : (
            <Text style={styles.buttonText}>Pedir corrida</Text>
          )}
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, minHeight: '100%' as unknown as number },
  mapWrap: { flex: 1, minHeight: 280, maxHeight: '58%' as unknown as number, position: 'relative' },
  map: { flex: 1, minHeight: 240 },
  locationBanner: {
    backgroundColor: '#FEF3C7',
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  locationBannerText: { color: '#92400E', fontSize: 12, fontWeight: '600' },
  areaErrorBanner: {
    backgroundColor: '#FEE2E2',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  areaErrorText: { color: '#991B1B', fontSize: 12, fontWeight: '600', lineHeight: 16 },
  sheetScroll: {
    flexGrow: 0,
    flexShrink: 1,
    maxHeight: '42%' as unknown as number,
  },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 18,
    paddingBottom: 24,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 8,
  },
  greet: { fontSize: 16, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  cityPicker: { marginBottom: 10 },
  cityPickerLabel: { fontSize: 12, color: theme.textSecondary, marginBottom: 6, fontWeight: '600' },
  cityChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: theme.bg,
    marginRight: 8,
    borderWidth: 1,
    borderColor: theme.line,
  },
  cityChipActive: {
    backgroundColor: theme.mintBg,
    borderColor: theme.mint,
  },
  cityChipText: { fontSize: 13, fontWeight: '600', color: theme.textSecondary },
  cityChipTextActive: { color: theme.mint },
  cityHint: { fontSize: 12, color: theme.mint, fontWeight: '600', marginBottom: 14 },
  routeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
    gap: 10,
  },
  routeBadge: {
    minWidth: 44,
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 6,
    alignItems: 'center',
  },
  routeBadgeFrom: { backgroundColor: theme.ink },
  routeBadgeTo: { backgroundColor: theme.danger },
  routeBadgeText: { color: '#fff', fontSize: 12, fontWeight: '700' },
  routeValue: {
    flex: 1,
    fontSize: 13,
    color: theme.textSecondary,
    backgroundColor: theme.bg,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  routeInputWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.bg,
    borderRadius: 10,
    paddingRight: 4,
  },
  routeInput: {
    flex: 1,
    fontSize: 13,
    color: theme.ink,
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  clearBtn: {
    marginLeft: 8,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: theme.bg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  clearBtnText: { fontSize: 14, color: theme.textSecondary, fontWeight: '700' },
  inlineError: { color: theme.danger, fontSize: 12, marginBottom: 8 },
  suggestions: {
    maxHeight: 120,
    marginBottom: 8,
    borderRadius: 12,
    backgroundColor: theme.bg,
    overflow: 'hidden',
  },
  suggestionItem: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.line,
  },
  suggestionText: { fontSize: 13, color: theme.ink },
  fareBox: {
    backgroundColor: theme.mintBg,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  fareTitle: { fontSize: 12, color: '#0B6E62', fontWeight: '600' },
  fareValue: { fontSize: 22, fontWeight: '700', color: theme.ink, marginTop: 4 },
  fareMeta: { fontSize: 12, color: theme.textSecondary, marginTop: 2 },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 14,
    alignItems: 'center',
  },
  buttonText: { color: theme.ink, fontWeight: '700', fontSize: 15 },
});
