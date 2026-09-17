import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  PanResponder,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { useScreenLayout } from './responsive';
import { theme } from './theme';

type Props = {
  renderMap: (mapHeight: number, expanded: boolean) => React.ReactNode;
  children: React.ReactNode;
  /** Altura do mapa no modo normal. */
  defaultMapHeight?: number;
  style?: StyleProp<ViewStyle>;
};

/** Mapa expansível: arraste a alça para baixo para ampliar o mapa. */
export default function ExpandableMapLayout({
  renderMap,
  children,
  defaultMapHeight,
  style,
}: Props) {
  const layout = useScreenLayout();

  const collapsedMapHeight = defaultMapHeight ?? layout.activeRideMapHeight;
  const peekSheetHeight = layout.isVeryCompact ? 100 : 116;
  const expandedMapHeight = Math.max(
    collapsedMapHeight + 40,
    layout.height - layout.safeTop - peekSheetHeight,
  );

  const [mapHeightPx, setMapHeightPx] = useState(collapsedMapHeight);
  const dragStartHeight = useRef(collapsedMapHeight);
  const mapHeightRef = useRef(mapHeightPx);
  mapHeightRef.current = mapHeightPx;

  useEffect(() => {
    setMapHeightPx((current) => {
      const mid = (collapsedMapHeight + expandedMapHeight) / 2;
      const wasExpanded = current >= mid;
      return wasExpanded ? expandedMapHeight : collapsedMapHeight;
    });
  }, [collapsedMapHeight, expandedMapHeight]);

  const expanded = mapHeightPx >= (collapsedMapHeight + expandedMapHeight) / 2;

  const snapTo = (height: number) => {
    const mid = (collapsedMapHeight + expandedMapHeight) / 2;
    const snapped = height >= mid ? expandedMapHeight : collapsedMapHeight;
    setMapHeightPx(snapped);
  };

  const toggle = () => {
    snapTo(expanded ? collapsedMapHeight - 1 : expandedMapHeight);
  };

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: (_, gesture) => Math.abs(gesture.dy) > 3,
        onPanResponderGrant: () => {
          dragStartHeight.current = mapHeightRef.current;
        },
        onPanResponderMove: (_, gesture) => {
          const next = dragStartHeight.current + gesture.dy;
          const clamped = Math.max(collapsedMapHeight, Math.min(expandedMapHeight, next));
          setMapHeightPx(clamped);
        },
        onPanResponderRelease: (_, gesture) => {
          const next = dragStartHeight.current + gesture.dy;
          const velocityBoost = gesture.vy * 40;
          snapTo(next + velocityBoost);
        },
        onPanResponderTerminationRequest: () => false,
      }),
    [collapsedMapHeight, expandedMapHeight],
  );

  const sheetMaxHeight = expanded
    ? peekSheetHeight + layout.safeBottom
    : Math.max(180, layout.height - mapHeightPx - layout.safeTop);

  return (
    <View style={[styles.root, style]}>
      <View style={[styles.mapShell, { height: mapHeightPx }]}>
        {renderMap(mapHeightPx, expanded)}
        <Pressable
          style={styles.expandHint}
          onPress={toggle}
          accessibilityRole="button"
          accessibilityLabel={expanded ? 'Recolher mapa' : 'Expandir mapa'}
        >
          <Text style={styles.expandHintText}>
            {expanded ? 'Recolher mapa' : 'Ampliar mapa'}
          </Text>
        </Pressable>
      </View>

      <ScrollView
        style={[
          styles.sheetScroll,
          { maxHeight: sheetMaxHeight },
          !expanded && { flex: 1 },
        ]}
        contentContainerStyle={[
          styles.sheet,
          {
            paddingHorizontal: layout.horizontalPadding,
            paddingBottom: layout.safeBottom + (expanded ? 8 : 16),
          },
        ]}
        keyboardShouldPersistTaps="handled"
        bounces={!expanded}
        scrollEnabled={!expanded}
        showsVerticalScrollIndicator={!expanded}
      >
        <View style={styles.handleRow} {...panResponder.panHandlers}>
          <View style={styles.handle} />
          <Text style={styles.handleHint}>
            {expanded ? 'Arraste para cima para ver o painel' : 'Arraste para baixo para ampliar o mapa'}
          </Text>
        </View>
        {expanded ? (
          <Pressable onPress={toggle}>
            <Text style={styles.peekText}>Toque em “Recolher mapa” ou na alça para ver detalhes</Text>
          </Pressable>
        ) : (
          children
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    width: '100%',
    overflow: 'hidden',
  },
  mapShell: {
    width: '100%',
    position: 'relative',
    zIndex: 2,
    backgroundColor: '#eef1f4',
  },
  expandHint: {
    position: 'absolute',
    top: 10,
    alignSelf: 'center',
    backgroundColor: 'rgba(17,17,17,0.78)',
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 999,
    zIndex: 800,
  },
  expandHintText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  sheetScroll: {
    flexGrow: 0,
    flexShrink: 1,
    zIndex: 3,
    elevation: 12,
  },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    paddingTop: 0,
    shadowColor: '#000',
    shadowOpacity: 0.12,
    shadowRadius: 12,
    elevation: 10,
  },
  handleRow: {
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  handle: {
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: '#D1D5DB',
    marginBottom: 6,
  },
  handleHint: {
    fontSize: 11,
    color: theme.textSecondary,
    textAlign: 'center',
  },
  peekText: {
    fontSize: 12,
    color: theme.textSecondary,
    textAlign: 'center',
    paddingBottom: 8,
    lineHeight: 16,
  },
});
