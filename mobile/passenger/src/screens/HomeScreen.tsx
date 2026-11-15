import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Keyboard,
  Linking,
  Platform,
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
import { distanciaMetros } from '../../../shared/geo';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import { regiaoPadrao } from '../../../shared/mapaPadrao';
import { MapLocateButton } from '../../../shared/MapLocateButton';
import {
  resolvePickupPoint,
} from '../../../shared/serviceArea';
import { AppMap, AppMarker, AppNearbyCars, AppRoute, type NearbyCar } from '../../../shared/SimpleMap';
import { theme } from '../../../shared/theme';
import { useAlturaTeclado } from '../../../shared/TelaSegura';
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
  const alturaTeclado = useAlturaTeclado();
  const [region, setRegion] = useState(() => regiaoPadrao());
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
  const [nearbyCars, setNearbyCars] = useState<NearbyCar[]>([]);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const regionTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Embarque: acompanha o GPS do passageiro ate ele digitar outro endereco ou mover o mapa.
  const [followGps, setFollowGpsState] = useState(false);
  const followRef = useRef(false);
  const lastGpsRef = useRef<{ latitude: number; longitude: number } | null>(null);
  const lastLabelPointRef = useRef<{ latitude: number; longitude: number } | null>(null);
  const originCustomRef = useRef(false);
  const destinationRef = useRef(false);
  const [originEditing, setOriginEditing] = useState(false);
  const [originText, setOriginText] = useState('');
  const [originSuggestions, setOriginSuggestions] = useState<GeocodeResult[]>([]);
  const originTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const setFollow = (value: boolean) => {
    followRef.current = value;
    setFollowGpsState(value);
  };

  destinationRef.current = Boolean(destinationPoint);

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
      latitude: pickup.latitude,
      longitude: pickup.longitude,
      latitudeDelta: Math.min(r.latitudeDelta ?? 0.04, 0.03),
      longitudeDelta: Math.min(r.longitudeDelta ?? 0.04, 0.03),
    }));
    if (!destinationPoint) {
      setPickupPoint(null);
      const label = await reverseGeocode(pickup.latitude, pickup.longitude);
      setPickupLabel(label);
      lastLabelPointRef.current = pickup;
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
      lastGpsRef.current = coords;
      originCustomRef.current = false;
      setFollow(true);
      setOriginEditing(false);
      const { city: activeCity, withinServiceArea } = await resolveCityAt(coords);
      if (activeCity && withinServiceArea) {
        await applyPickup(coords, activeCity);
      } else {
        setRegion((r) => ({
          latitude: coords.latitude,
          longitude: coords.longitude,
          latitudeDelta: Math.min(r.latitudeDelta ?? 0.04, 0.08),
          longitudeDelta: Math.min(r.longitudeDelta ?? 0.04, 0.08),
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

    // Mapa arrastado para longe: deixa de seguir o GPS e de usar o endereco digitado.
    const gps = lastGpsRef.current;
    if (followRef.current && gps && distanciaMetros(next, gps) > 120) setFollow(false);
    if (pickupPoint && distanciaMetros(next, pickupPoint) > 80) {
      setPickupPoint(null);
      setPickupLabel(null);
      originCustomRef.current = false;
    }

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

  // Carrinhos dos motoristas livres perto do embarque, atualizados a cada 5 s.
  const pickupLat = Math.round(activePickup.latitude * 1000) / 1000;
  const pickupLng = Math.round(activePickup.longitude * 1000) / 1000;
  useEffect(() => {
    let vivo = true;
    const buscar = async () => {
      try {
        const { drivers } = await api.nearbyDrivers(pickupLat, pickupLng, 5);
        if (vivo) setNearbyCars(drivers);
      } catch {
        /* sem rede: mantém os carrinhos anteriores */
      }
    };
    void buscar();
    const timer = setInterval(buscar, 5000);
    return () => {
      vivo = false;
      clearInterval(timer);
    };
  }, [pickupLat, pickupLng]);

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
        if (originCustomRef.current) return;

        // 1) GPS real do passageiro vem primeiro; o endereco do cadastro e so reserva.
        const gps = await requestCurrentLocation().catch((e: unknown) => {
          setLocationError(e instanceof Error ? e.message : 'Não foi possível obter sua localização.');
          return null;
        });
        if (gps) {
          lastGpsRef.current = gps;
          setLocationError(null);
          const { city: gpsCity, withinServiceArea: dentro } = await resolveCityAt(gps);
          if (gpsCity && dentro) {
            setFollow(true);
            await applyPickup(gps, gpsCity);
            return;
          }
          setPickupAreaHint(
            'Sua localização está fora da área de operação. Escolha a cidade ou digite o endereço de embarque.',
          );
        }

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

  // Segue o GPS enquanto o passageiro nao escolheu outro embarque.
  useEffect(() => {
    const stop = startLocationWatch((coords) => {
      lastGpsRef.current = coords;
      if (!followRef.current || destinationRef.current || originCustomRef.current) return;
      const anterior = lastLabelPointRef.current;
      if (anterior && distanciaMetros(anterior, coords) < 20) return;
      lastLabelPointRef.current = coords;
      setPickupPoint(null);
      setRegion((r) => ({ ...r, latitude: coords.latitude, longitude: coords.longitude }));
      void reverseGeocode(coords.latitude, coords.longitude).then((label) => {
        if (followRef.current && !destinationRef.current) setPickupLabel(label);
      });
    }, 8000);
    return stop;
  }, []);

  // Busca de endereco para o campo "De" (o passageiro pode digitar o embarque).
  useEffect(() => {
    if (originTimer.current) clearTimeout(originTimer.current);
    const q = originText.trim();
    if (!originEditing || q.length < 3) {
      setOriginSuggestions([]);
      return;
    }
    originTimer.current = setTimeout(async () => {
      try {
        const { results } = await searchAddresses(q, activePickup, city?.id);
        setOriginSuggestions(results);
      } catch {
        setOriginSuggestions([]);
      }
    }, 450);
    return () => {
      if (originTimer.current) clearTimeout(originTimer.current);
    };
  }, [originText, originEditing]);

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

  const selectOrigin = async (item: GeocodeResult) => {
    Keyboard.dismiss();
    const ponto = { latitude: item.latitude, longitude: item.longitude };
    originCustomRef.current = true;
    setFollow(false);
    setOriginEditing(false);
    setOriginSuggestions([]);
    setOriginText('');
    setPickupPoint(ponto);
    setPickupLabel(item.label);
    setRegion((r) => ({ ...r, latitude: ponto.latitude, longitude: ponto.longitude, latitudeDelta: 0.02, longitudeDelta: 0.02 }));
    await resolveCityAt(ponto);
  };

  const selectDestination = async (item: GeocodeResult) => {
    Keyboard.dismiss();
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
    <View style={[styles.container, alturaTeclado > 0 ? { paddingBottom: alturaTeclado } : null]}>
      <View style={styles.mapWrap}>
        <AppMap
          style={styles.map}
          region={mapRegion}
          onRegionChangeComplete={destinationPoint ? undefined : handleRegionChangeComplete}
          pinMode={destinationPoint ? 'marker' : 'center'}
          fitToCoordinates={fitCoords}
        >
          <AppNearbyCars cars={nearbyCars} />
          {destinationPoint ? (
            <>
              <AppMarker coordinate={activePickup} title="Embarque" variant="origin" />
              <AppMarker
                coordinate={destinationPoint}
                title="Destino"
                variant="destination"
                etaMin={fareEstimate?.durationMin ?? null}
                label="Chegada"
              />
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
          {Platform.OS !== 'web' ? (
            <Pressable style={styles.locationBtn} onPress={() => void Linking.openSettings()} hitSlop={8}>
              <Text style={styles.locationBtnText}>Ativar localização</Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}

      <ScrollView
        style={styles.sheetScroll}
        contentContainerStyle={styles.sheet}
        keyboardShouldPersistTaps="handled"
      >
        {operatingCities.length > 0 ? (
          <View style={styles.cityPicker}>
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
          <View style={styles.routeInputWrap}>
            <TextInput
              style={styles.routeInput}
              placeholder="Digite outro endereço de embarque"
              placeholderTextColor={theme.textMuted}
              value={originEditing ? originText : (pickupLabel ?? (followGps ? 'Minha localização' : 'Ponto escolhido no mapa'))}
              onFocus={() => {
                setOriginEditing(true);
                setOriginText('');
              }}
              onBlur={() => setTimeout(() => setOriginEditing(false), 200)}
              onChangeText={setOriginText}
            />
            <Pressable style={styles.clearBtn} onPress={() => void centerOnUser()} hitSlop={6}>
              <Text style={styles.clearBtnText}>◎</Text>
            </Pressable>
          </View>
        </View>
        {followGps && !originEditing ? (
          <Text style={styles.gpsHint}>● Usando sua localização atual · toque no endereço para digitar outro</Text>
        ) : null}
        {originSuggestions.length > 0 ? (
          <View style={styles.suggestions}>
            {originSuggestions.map((item) => (
              <Pressable
                key={`o-${item.latitude}-${item.longitude}`}
                style={styles.suggestionItem}
                onPress={() => void selectOrigin(item)}
              >
                <Text style={styles.suggestionText} numberOfLines={2}>
                  {item.label}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}

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
      </ScrollView>
      <View style={styles.footer}>
        <Pressable style={styles.button} onPress={requestRide} disabled={loading || Boolean(areaError)}>
          {loading ? (
            <ActivityIndicator color={theme.ink} />
          ) : (
            <Text style={styles.buttonText}>Pedir corrida</Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, minHeight: '100%' as unknown as number },
  mapWrap: { flex: 1, minHeight: 200, maxHeight: '50%' as unknown as number, position: 'relative' },
  map: { flex: 1, minHeight: 240 },
  locationBanner: {
    backgroundColor: '#FEF3C7',
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  locationBannerText: { color: '#92400E', fontSize: 12, fontWeight: '600' },
  locationBtn: {
    alignSelf: 'flex-start',
    backgroundColor: '#92400E',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginTop: 8,
  },
  locationBtnText: { color: '#fff', fontWeight: '700', fontSize: 12 },
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
    paddingBottom: 8,
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
  cityHint: { fontSize: 12, color: theme.mint, fontWeight: '600', marginBottom: 10 },
  gpsHint: { fontSize: 11, color: theme.mint, fontWeight: '600', marginTop: -4, marginBottom: 10 },
  footer: { backgroundColor: theme.paper, paddingHorizontal: 18, paddingTop: 8, paddingBottom: 14 },
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
