import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { APP_LAYOUT_VERSION } from '../../../shared/appVersion';
import { useScreenLayout } from '../../../shared/responsive';
import { setWebSearchMode, useVisualViewport } from '../../../shared/webMobile';
import { AddressSuggestions } from '../components/AddressSuggestions';
import { CitySearchPicker, type NearbyCity } from '../components/CitySearchPicker';
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
import { toNumber } from '../../../shared/coords';
import {
  cityDepartureCenter,
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

function rideOrigin(
  activeCity: City,
  pickup: { latitude: number; longitude: number },
): { pickup: { latitude: number; longitude: number }; usedCityCenter: boolean } {
  return resolvePickupPoint(pickup, activeCity);
}

export default function HomeScreen({ onRideActive }: Props) {
  const { user } = useAuth();
  const layout = useScreenLayout();
  const viewport = useVisualViewport();
  const [region, setRegion] = useState({
    latitude: -28.8436,
    longitude: -51.8908,
    latitudeDelta: 0.08,
    longitudeDelta: 0.08,
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
  const [nearbyCities, setNearbyCities] = useState<NearbyCity[]>([]);
  const [citiesLoading, setCitiesLoading] = useState(true);
  const [nearbyLoading, setNearbyLoading] = useState(false);
  const [citiesError, setCitiesError] = useState<string | null>(null);
  const [gpsOutsideArea, setGpsOutsideArea] = useState(false);
  const [cityLocked, setCityLocked] = useState(false);
  const [fareEstimate, setFareEstimate] = useState<RideEstimate | null>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const regionTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const cityLockedRef = useRef(false);
  const skipSearchRef = useRef(false);
  const searchSeqRef = useRef(0);
  const destinationAnchorRef = useRef<View>(null);
  const sheetScrollRef = useRef<ScrollView>(null);
  const [destinationFocused, setDestinationFocused] = useState(false);

  cityLockedRef.current = cityLocked;

  const activePickup = pickupPoint ?? {
    latitude: region.latitude,
    longitude: region.longitude,
  };

  const searchOrigin = useMemo(() => {
    if (city) {
      const center = cityDepartureCenter(city);
      if (center) {
        return center;
      }
    }
    return activePickup;
  }, [city, activePickup.latitude, activePickup.longitude]);

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

  const fitCoords = useMemo(() => {
    if (routePoints.length >= 2) {
      return routePoints;
    }
    if (destinationPoint) {
      return [activePickup, destinationPoint];
    }
    return undefined;
  }, [routePoints, destinationPoint, activePickup]);

  const mapOverlay = useMemo(() => {
    if (!destinationPoint) return null;
    return (
      <>
        <AppMarker coordinate={activePickup} title="Embarque" variant="origin" />
        <AppMarker coordinate={destinationPoint} title="Destino" variant="destination" />
        {routePoints.length >= 2 ? <AppRoute coordinates={routePoints} /> : null}
      </>
    );
  }, [destinationPoint, activePickup, routePoints]);

  const resolveCityAt = async (
    coords: { latitude: number; longitude: number },
    options?: { cityId?: string; manual?: boolean },
  ): Promise<{ city: City | null; withinServiceArea: boolean }> => {
    const cityId = options?.cityId ?? (cityLocked && city ? city.id : undefined);
    const useSelect = Boolean(options?.manual || (cityLocked && cityId));

    try {
      const resolved = await api.nearestCity(coords.latitude, coords.longitude, cityId, useSelect);
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
      if (cityId && city && cityLocked) {
        return { city, withinServiceArea: true };
      }
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
    setPickupPoint(pickup);
    const label = await reverseGeocode(pickup.latitude, pickup.longitude);
    setPickupLabel(label);
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
      await loadNearbyCities(coords);
      if (cityLocked && city) {
        await resolveCityAt(coords, { cityId: city.id, manual: true });
        await applyPickup(coords, city);
      } else {
        setRegion((r) => ({
          ...r,
          latitude: coords.latitude,
          longitude: coords.longitude,
        }));
        setPickupPoint({ latitude: coords.latitude, longitude: coords.longitude });
        const label = await reverseGeocode(coords.latitude, coords.longitude);
        setPickupLabel(label);
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
    if (destinationPoint || cityLockedRef.current) return;

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

  const loadOperatingCities = useCallback(async () => {
    setCitiesLoading(true);
    setCitiesError(null);
    try {
      const cities = await api.listCities();
      const list = Array.isArray(cities) ? cities : [];
      setOperatingCities(list);
      if (list.length === 0) {
        setCitiesError('Nenhuma cidade disponível no momento.');
        return list;
      }
      return list;
    } catch (e: unknown) {
      setOperatingCities([]);
      setCitiesError(
        e instanceof Error ? e.message : 'Não foi possível carregar as cidades.',
      );
      return [];
    } finally {
      setCitiesLoading(false);
    }
  }, []);

  const loadNearbyCities = useCallback(
    async (coords?: { latitude: number; longitude: number }) => {
      if (operatingCities.length === 0) return;

      setNearbyLoading(true);
      try {
        let point = coords;
        if (!point) {
          const homeLat = user?.home_lat != null ? Number(user.home_lat) : null;
          const homeLng = user?.home_lng != null ? Number(user.home_lng) : null;
          if (
            homeLat != null &&
            homeLng != null &&
            !Number.isNaN(homeLat) &&
            !Number.isNaN(homeLng)
          ) {
            point = { latitude: homeLat, longitude: homeLng };
          } else {
            point = (await requestCurrentLocation().catch(() => null)) ?? undefined;
          }
        }

        if (point) {
          const response = await api.nearbyCities(point.latitude, point.longitude);
          setMaxRadiusKm(response.max_radius_km);
          const list = Array.isArray(response.cities) ? response.cities : [];
          if (list.length > 0) {
            setNearbyCities(list);
            setGpsOutsideArea(false);
            return list;
          }
        }

        setGpsOutsideArea(true);
        setNearbyCities(operatingCities);
        return operatingCities;
      } catch {
        setGpsOutsideArea(true);
        setNearbyCities(operatingCities);
        return operatingCities;
      } finally {
        setNearbyLoading(false);
      }
    },
    [operatingCities, user?.home_lat, user?.home_lng],
  );

  useEffect(() => {
    void loadOperatingCities();
  }, [loadOperatingCities]);

  useEffect(() => {
    if (citiesLoading || operatingCities.length === 0) return;
    void loadNearbyCities();
  }, [citiesLoading, operatingCities, loadNearbyCities]);

  useEffect(() => {
    if (citiesLoading || nearbyLoading || cityLockedRef.current) return;

    void (async () => {
      try {
        const matchedCity = matchCityByName(user?.home_city);
        if (matchedCity) {
          await selectOperatingCity(matchedCity);
          return;
        }

        if (nearbyCities.length === 1) {
          await selectOperatingCity(nearbyCities[0]);
          return;
        }

        const homeLat = user?.home_lat != null ? Number(user.home_lat) : null;
        const homeLng = user?.home_lng != null ? Number(user.home_lng) : null;
        if (homeLat != null && homeLng != null && !Number.isNaN(homeLat) && !Number.isNaN(homeLng)) {
          const match = nearbyCities.find((c) => c.id === city?.id);
          if (match) {
            await applyPickup({ latitude: homeLat, longitude: homeLng }, match);
            if (user?.home_address) {
              setPickupLabel(user.home_address);
            }
          }
        }
      } catch {
        // seleção manual permanece disponível
      }
    })();
  }, [
    citiesLoading,
    nearbyLoading,
    nearbyCities,
    user?.home_city,
    user?.home_lat,
    user?.home_lng,
    user?.home_address,
  ]);

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    const q = destination.trim();

    if (skipSearchRef.current) {
      skipSearchRef.current = false;
      return;
    }

    if (q.length < 3 || destinationPoint?.label === q) {
      setSuggestions([]);
      if (destinationPoint?.label === q) {
        setGeocodeError(null);
      }
      return;
    }

    if (!city || !cityLocked) {
      setSuggestions([]);
      setGeocodeError('Selecione a cidade de operação acima antes de buscar o destino.');
      return;
    }

    const seq = ++searchSeqRef.current;

    searchTimer.current = setTimeout(async () => {
      setSearching(true);
      setGeocodeError(null);
      try {
        const { results, cityId, cityName, maxRadiusKm: radiusKm, filteredCount } =
          await searchAddresses(q, searchOrigin, city.id);
        if (seq !== searchSeqRef.current) return;

        if (radiusKm != null) setMaxRadiusKm(radiusKm);
        if (cityId && city.id !== cityId) {
          const matched = operatingCities.find((item) => item.id === cityId);
          if (matched && cityLockedRef.current) {
            setCity(matched);
          }
        }
        setSuggestions(results);
        if (results.length === 0) {
          const label = cityName ?? city.name ?? 'operação';
          const radius = radiusKm ?? maxRadiusKm;
          const filteredHint =
            filteredCount && filteredCount > 0
              ? ` (${filteredCount} endereço(s) fora do raio foram ignorados)`
              : '';
          setGeocodeError(
            `Nenhum endereço em ${label} (raio ${radius} km). Inclua rua e bairro ou tente o nome completo.${filteredHint}`,
          );
        }
      } catch (e: unknown) {
        if (seq !== searchSeqRef.current) return;
        setSuggestions([]);
        setGeocodeError(e instanceof Error ? e.message : 'Falha na busca de endereços.');
      } finally {
        if (seq === searchSeqRef.current) {
          setSearching(false);
        }
      }
    }, 450);

    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    };
  }, [
    destination,
    searchOrigin.latitude,
    searchOrigin.longitude,
    destinationPoint?.label,
    city?.id,
    cityLocked,
    operatingCities,
  ]);

  useEffect(() => {
    if (!city || !destinationPoint) {
      setFareEstimate(null);
      setEstimateError(null);
      return;
    }

    const rawOrigin = pickupPoint ?? activePickup;
    const { pickup: origin, usedCityCenter } = rideOrigin(city, rawOrigin);

    let cancelled = false;
    setEstimating(true);
    setEstimateError(null);
    setPickupAreaHint(
      usedCityCenter
        ? `Sua localização está fora da área. Embarque definido em ${city.name}.`
        : null,
    );

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
          routePoints:
            (estimate.coordinates?.length ?? 0) >= 2
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

  const selectDestination = useCallback((item: GeocodeResult) => {
    searchSeqRef.current += 1;
    if (searchTimer.current) {
      clearTimeout(searchTimer.current);
      searchTimer.current = null;
    }
    skipSearchRef.current = true;
    setSearching(false);

    const frozenPickup = city
      ? rideOrigin(city, activePickup).pickup
      : {
          latitude: activePickup.latitude,
          longitude: activePickup.longitude,
        };

    setPickupPoint(frozenPickup);
    setDestination(item.label);
    setDestinationPoint({
      label: item.label,
      latitude: toNumber(item.latitude),
      longitude: toNumber(item.longitude),
    });
    setSuggestions([]);
    setGeocodeError(null);
    Keyboard.dismiss();

    void reverseGeocode(frozenPickup.latitude, frozenPickup.longitude).then(setPickupLabel);
  }, [city, activePickup.latitude, activePickup.longitude]);

  const clearDestination = () => {
    setDestination('');
    setDestinationPoint(null);
    setPickupPoint(null);
    setPickupLabel(null);
    setFareEstimate(null);
    setEstimateError(null);
    setSuggestions([]);
  };

  const canRequestRide =
    Boolean(city && destinationPoint) &&
    (!areaError || (cityLocked && city && destinationPoint));

  const requestRide = async () => {
    if (areaError && !(cityLocked && city && destinationPoint)) {
      Alert.alert('Fora da área', areaError);
      return;
    }
    if (!destinationPoint || !city) {
      Alert.alert('Destino', 'Selecione um endereço de destino na lista.');
      return;
    }

    setLoading(true);
    try {
      const { city: activeCity, withinServiceArea } = await resolveCityAt(activePickup, {
        manual: cityLocked,
      });
      if (!activeCity || !withinServiceArea) {
        Alert.alert('Fora da área', areaError ?? 'Localização fora das áreas de operação.');
        return;
      }

      const { pickup: origin, usedCityCenter } = rideOrigin(activeCity, activePickup);
      if (usedCityCenter) {
        setPickupAreaHint(
          `Sua localização está fora da área. Embarque definido em ${activeCity.name}.`,
        );
      }

      const originAddress =
        usedCityCenter && activeCity.departure_address
          ? activeCity.departure_address
          : pickupLabel ?? (await reverseGeocode(origin.latitude, origin.longitude));
      const ride = await api.createRide({
        city_id: activeCity.id,
        origin_lat: origin.latitude,
        origin_lng: origin.longitude,
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

  const compactSearch =
    destinationFocused ||
    viewport.keyboardOpen ||
    (destination.trim().length > 0 && !destinationPoint);

  useEffect(() => {
    if (Platform.OS !== 'web') return;
    setWebSearchMode(compactSearch);
    return () => setWebSearchMode(false);
  }, [compactSearch]);

  useEffect(() => {
    if (Platform.OS !== 'web' || !compactSearch) return;
    window.scrollTo(0, 0);
  }, [compactSearch, viewport.offsetTop, viewport.keyboardOpen, viewport.height]);

  const compactSearchBody = (
    <>
      <View style={styles.searchHeader}>
        <Text style={styles.searchTitle}>
          Para onde vamos?
          {Platform.OS === 'web' ? (
            <Text style={styles.versionTag}> · v{APP_LAYOUT_VERSION}</Text>
          ) : null}
        </Text>
        {city && cityLocked ? (
          <View style={styles.compactContext}>
            <View style={styles.searchOriginRow}>
              <View style={[styles.routeBadge, styles.routeBadgeFrom, styles.searchBadge]}>
                <Text style={styles.routeBadgeText}>Início</Text>
              </View>
              <Text style={styles.compactOrigin} numberOfLines={1}>
                {pickupLabel ?? 'Minha localização'}
              </Text>
            </View>
            {cityLocked ? (
              <MapLocateButton
                variant="inline"
                label="Minha localização"
                onPress={centerOnUser}
                loading={locating}
              />
            ) : null}
          </View>
        ) : null}
      </View>
      <View style={[styles.routeRow, styles.searchDestinationRow]}>
        <View style={[styles.routeBadge, styles.routeBadgeTo, styles.searchBadge]}>
          <Text style={styles.routeBadgeText}>Destino</Text>
        </View>
        <View
          ref={destinationAnchorRef}
          style={[styles.routeInputWrap, styles.searchInputWrap]}
          collapsable={false}
        >
          <TextInput
            style={[
              styles.routeInput,
              styles.searchRouteInput,
              !cityLocked && styles.routeInputDisabled,
            ]}
            placeholder={
              cityLocked ? 'Buscar endereço de destino' : 'Selecione a cidade acima primeiro'
            }
            placeholderTextColor={theme.textMuted}
            editable={cityLocked}
            value={destination}
            onFocus={() => setDestinationFocused(true)}
            onBlur={() => {
              setTimeout(() => setDestinationFocused(false), 150);
            }}
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
      {suggestions.length > 0 && !destinationPoint ? (
        <AddressSuggestions
          items={suggestions}
          onSelect={selectDestination}
          anchorRef={destinationAnchorRef}
        />
      ) : null}
    </>
  );

  const sheetBody = (
    <>
      {!compactSearch ? (
        <>
          <Text style={[styles.greet, layout.isCompact && styles.greetCompact]}>
            Para onde vamos?
            {Platform.OS === 'web' ? (
              <Text style={styles.versionTag}> · v{APP_LAYOUT_VERSION}</Text>
            ) : null}
          </Text>
          <CitySearchPicker
            cities={nearbyCities.length > 0 ? nearbyCities : operatingCities}
            selectedCityId={city?.id}
            loading={citiesLoading || nearbyLoading}
            locating={locating}
            maxRadiusKm={maxRadiusKm}
            gpsOutsideArea={gpsOutsideArea}
            listMaxHeight={layout.cityListMaxHeight}
            collapsed={cityLocked}
            onExpand={() => {
              setCityLocked(false);
              setDestination('');
              setDestinationPoint(null);
              setSuggestions([]);
              setGeocodeError(null);
            }}
            error={citiesError}
            onRetry={() => {
              void (async () => {
                await loadOperatingCities();
                await loadNearbyCities();
              })();
            }}
            onSelect={(item) => void selectOperatingCity(item)}
          />
          {city && cityLocked ? (
            <Text style={styles.cityHint}>
              Área de {city.name} · até {maxRadiusKm} km
            </Text>
          ) : null}
          <View style={styles.routeRow}>
            <View style={[styles.routeBadge, styles.routeBadgeFrom]}>
              <Text style={styles.routeBadgeText}>Início</Text>
            </View>
            <View style={styles.routeOriginCol}>
              <Text style={[styles.routeValue, styles.routeValueText]} numberOfLines={2}>
                {pickupLabel ?? 'Minha localização'}
              </Text>
              {cityLocked ? (
                <MapLocateButton
                  variant="inline"
                  label="Minha localização"
                  onPress={centerOnUser}
                  loading={locating}
                />
              ) : null}
            </View>
          </View>
        </>
      ) : (
        <View style={styles.searchHeader}>
          <Text style={styles.searchTitle}>Para onde vamos?</Text>
          {city && cityLocked ? (
            <View style={styles.compactContext}>
              <View style={styles.searchOriginRow}>
                <View style={[styles.routeBadge, styles.routeBadgeFrom, styles.searchBadge]}>
                  <Text style={styles.routeBadgeText}>Início</Text>
                </View>
                <Text style={styles.compactOrigin} numberOfLines={1}>
                  {pickupLabel ?? 'Minha localização'}
                </Text>
              </View>
            </View>
          ) : null}
        </View>
      )}

      <View style={[styles.routeRow, compactSearch && styles.searchDestinationRow]}>
        {!compactSearch ? (
          <View style={[styles.routeBadge, styles.routeBadgeTo]}>
            <Text style={styles.routeBadgeText}>Destino</Text>
          </View>
        ) : (
          <View style={[styles.routeBadge, styles.routeBadgeTo, styles.searchBadge]}>
            <Text style={styles.routeBadgeText}>Destino</Text>
          </View>
        )}
        <View
          ref={destinationAnchorRef}
          style={[styles.routeInputWrap, compactSearch && styles.searchInputWrap]}
          collapsable={false}
        >
          <TextInput
            style={[
              styles.routeInput,
              compactSearch && styles.searchRouteInput,
              !cityLocked && styles.routeInputDisabled,
            ]}
            placeholder={
              cityLocked ? 'Buscar endereço de destino' : 'Selecione a cidade acima primeiro'
            }
            placeholderTextColor={theme.textMuted}
            editable={cityLocked}
            value={destination}
            onFocus={() => setDestinationFocused(true)}
            onBlur={() => {
              setTimeout(() => setDestinationFocused(false), 150);
            }}
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
      {suggestions.length > 0 && !destinationPoint ? (
        <AddressSuggestions
          items={suggestions}
          onSelect={selectDestination}
          anchorRef={destinationAnchorRef}
        />
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
      {!compactSearch || destinationPoint ? (
        <Pressable style={styles.button} onPress={requestRide} disabled={loading || !canRequestRide}>
          {loading ? (
            <ActivityIndicator color={theme.ink} />
          ) : (
            <Text style={styles.buttonText}>Pedir corrida</Text>
          )}
        </Pressable>
      ) : null}
    </>
  );

  const Root = Platform.OS === 'web' ? View : SafeAreaView;
  const rootProps =
    Platform.OS === 'web' ? {} : ({ edges: ['top'] as const } satisfies { edges: ('top')[] });

  if (Platform.OS === 'web' && compactSearch) {
    const mapH = viewport.keyboardOpen ? 0 : layout.searchMapHeight;
    const panelTop = viewport.offsetTop + mapH;
    const panelHeight = Math.max(220, viewport.height - mapH);

    return (
      <Root style={styles.container} {...rootProps}>
        {mapH > 0 ? (
          <View
            style={[
              styles.webSearchMap,
              { top: viewport.offsetTop, height: mapH },
            ]}
          >
            <AppMap
              style={styles.map}
              region={mapRegion}
              onRegionChangeComplete={destinationPoint ? undefined : handleRegionChangeComplete}
              pinMode={destinationPoint ? 'marker' : 'center'}
              fitToCoordinates={fitCoords}
            >
              {mapOverlay}
            </AppMap>
            <MapLocateButton onPress={centerOnUser} loading={locating} />
          </View>
        ) : null}
        <View
          style={[
            styles.webSearchPanel,
            {
              top: panelTop,
              height: panelHeight,
              borderTopLeftRadius: mapH > 0 ? 20 : 0,
              borderTopRightRadius: mapH > 0 ? 20 : 0,
            },
            viewport.keyboardOpen && styles.webSearchPanelKeyboard,
          ]}
        >
          <View
            style={[
              styles.webSearchPanelInner,
              viewport.keyboardOpen && styles.webSearchPanelInnerKeyboard,
            ]}
          >
            {compactSearchBody}
          </View>
        </View>
      </Root>
    );
  }

  return (
    <Root style={styles.container} {...rootProps}>
      <View style={styles.fill}>
      <View
        style={[
          styles.mapWrap,
          {
            height: compactSearch
              ? layout.searchMapHeight || 0
              : layout.mapHeight,
          },
          compactSearch && !layout.searchMapHeight && styles.mapHidden,
        ]}
      >
        {(compactSearch ? layout.searchMapHeight > 0 : true) ? (
          <>
            <AppMap
              style={styles.map}
              region={mapRegion}
              onRegionChangeComplete={destinationPoint ? undefined : handleRegionChangeComplete}
              pinMode={destinationPoint ? 'marker' : 'center'}
              fitToCoordinates={fitCoords}
            >
              {mapOverlay}
            </AppMap>
            <MapLocateButton onPress={centerOnUser} loading={locating} />
          </>
        ) : null}
      </View>

      {Platform.OS === 'web' ? (
        <ScrollView
          ref={sheetScrollRef}
          style={[
            styles.sheetScroll,
            styles.sheetScrollWeb,
            compactSearch ? styles.sheetScrollExpanded : { maxHeight: layout.sheetMaxHeight },
          ]}
          contentContainerStyle={[
            styles.sheet,
            compactSearch && styles.sheetCompact,
            compactSearch && { paddingTop: layout.searchContentTopPad },
            { paddingBottom: layout.safeBottom + 24 },
          ]}
          keyboardShouldPersistTaps="always"
          nestedScrollEnabled
          horizontal={false}
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          bounces={false}
        >
          {!compactSearch && areaError ? (
            <View style={styles.areaErrorBannerInline}>
              <Text style={styles.areaErrorText}>{areaError}</Text>
            </View>
          ) : null}
          {!compactSearch && pickupAreaHint ? (
            <View style={styles.locationBannerInline}>
              <Text style={styles.locationBannerText}>{pickupAreaHint}</Text>
            </View>
          ) : null}
          {!compactSearch && locationError ? (
            <View style={styles.locationBannerInline}>
              <Text style={styles.locationBannerText}>{locationError}</Text>
            </View>
          ) : null}
          {sheetBody}
        </ScrollView>
      ) : (
        <KeyboardAvoidingView
          style={styles.sheetScroll}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          keyboardVerticalOffset={layout.keyboardVerticalOffset}
        >
          <ScrollView
            ref={sheetScrollRef}
            style={compactSearch ? styles.sheetScrollExpanded : { maxHeight: layout.sheetMaxHeight }}
            contentContainerStyle={[
              styles.sheet,
              compactSearch && styles.sheetCompact,
              compactSearch && { paddingTop: layout.searchContentTopPad },
              { paddingBottom: layout.safeBottom + 16 },
            ]}
            keyboardShouldPersistTaps="always"
            nestedScrollEnabled
          >
            {!compactSearch && areaError ? (
              <View style={styles.areaErrorBannerInline}>
                <Text style={styles.areaErrorText}>{areaError}</Text>
              </View>
            ) : null}
            {!compactSearch && pickupAreaHint ? (
              <View style={styles.locationBannerInline}>
                <Text style={styles.locationBannerText}>{pickupAreaHint}</Text>
              </View>
            ) : null}
            {!compactSearch && locationError ? (
              <View style={styles.locationBannerInline}>
                <Text style={styles.locationBannerText}>{locationError}</Text>
              </View>
            ) : null}
            {sheetBody}
          </ScrollView>
        </KeyboardAvoidingView>
      )}
      </View>
    </Root>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, width: '100%', maxWidth: '100%', overflow: 'hidden' },
  fill: { flex: 1, width: '100%', maxWidth: '100%', overflow: 'hidden' },
  mapWrap: { position: 'relative', flexShrink: 0, width: '100%', overflow: 'hidden' },
  map: { flex: 1, width: '100%' },
  locationBanner: {
    backgroundColor: '#FEF3C7',
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  locationBannerInline: {
    backgroundColor: '#FEF3C7',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginBottom: 10,
  },
  locationBannerText: { color: '#92400E', fontSize: 12, fontWeight: '600' },
  areaErrorBanner: {
    backgroundColor: '#FEE2E2',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  areaErrorBannerInline: {
    backgroundColor: '#FEE2E2',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginBottom: 10,
  },
  areaErrorText: { color: '#991B1B', fontSize: 12, fontWeight: '600', lineHeight: 16 },
  sheetScroll: {
    flexGrow: 1,
    flexShrink: 1,
    zIndex: 1000,
    elevation: 12,
  },
  sheetScrollWeb: {
    flex: 1,
    minHeight: 0,
    width: '100%',
  },
  sheetScrollExpanded: {
    flex: 1,
    minHeight: 0,
    width: '100%',
    maxHeight: undefined,
  },
  sheetCompact: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    marginTop: -14,
    flexGrow: 1,
    shadowColor: '#000',
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 16,
  },
  mapHidden: {
    height: 0,
    minHeight: 0,
    overflow: 'hidden',
  },
  webSearchMap: {
    position: 'fixed',
    left: 0,
    right: 0,
    zIndex: 1000,
    overflow: 'hidden',
  },
  webSearchPanel: {
    position: 'fixed',
    left: 0,
    right: 0,
    zIndex: 2000,
    backgroundColor: theme.paper,
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 16,
    flexDirection: 'column',
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 16,
    elevation: 16,
  },
  webSearchPanelKeyboard: {
    paddingTop: 12,
    paddingBottom: 12,
    overflow: 'auto',
  },
  webSearchPanelInner: {
    width: '100%',
  },
  webSearchPanelInnerKeyboard: {
    flex: 1,
    justifyContent: 'center',
    minHeight: 0,
  },
  searchHeader: {
    marginBottom: 14,
  },
  searchTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.ink,
    marginBottom: 12,
  },
  compactContext: {
    marginBottom: 4,
  },
  searchOriginRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  compactOrigin: {
    flex: 1,
    fontSize: 14,
    color: theme.textSecondary,
  },
  searchBadge: {
    minWidth: 40,
    paddingHorizontal: 8,
    paddingVertical: 6,
  },
  searchDestinationRow: {
    marginBottom: 12,
  },
  searchInputWrap: {
    backgroundColor: theme.bg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.line,
    minHeight: 50,
  },
  searchRouteInput: {
    fontSize: 16,
    paddingVertical: 14,
  },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 16,
    paddingBottom: 24,
    width: '100%',
    maxWidth: '100%',
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 8,
  },
  greet: { fontSize: 16, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  greetCompact: { fontSize: 15, marginBottom: 2 },
  versionTag: { fontSize: 11, fontWeight: '600', color: theme.textMuted },
  cityPicker: { marginBottom: 10 },
  cityPickerLabel: { fontSize: 12, color: theme.textSecondary, marginBottom: 6, fontWeight: '600' },
  cityChipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  citiesErrorBox: { marginBottom: 8 },
  retryBtn: {
    alignSelf: 'flex-start',
    marginTop: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: theme.mintBg,
  },
  retryBtnText: { fontSize: 12, fontWeight: '700', color: theme.mint },
  cityChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: theme.bg,
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
    width: '100%',
    maxWidth: '100%',
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
  routeOriginCol: {
    flex: 1,
    minWidth: 0,
    gap: 6,
  },
  routeValue: {
    flex: 1,
    minWidth: 0,
    backgroundColor: theme.bg,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  routeValueText: {
    fontSize: 13,
    color: theme.textSecondary,
  },
  routeInputWrap: {
    flex: 1,
    minWidth: 0,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.bg,
    borderRadius: 10,
    paddingRight: 4,
  },
  routeInput: {
    flex: 1,
    minWidth: 0,
    fontSize: 16,
    color: theme.ink,
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  routeInputDisabled: {
    color: theme.textMuted,
    opacity: 0.85,
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
