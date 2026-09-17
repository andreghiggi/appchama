import React from 'react';

import { StyleSheet, Text, View } from 'react-native';



type EtaProps = {

  etaMinutes?: number;

  label?: string;

};



export function OriginMapPin() {

  return (

    <View style={styles.wrap}>

      <View style={styles.dotOuter}>

        <View style={styles.dotInner} />

      </View>

    </View>

  );

}



export function DriverMapPin() {

  return (

    <View style={styles.wrap}>

      <View style={styles.driverDot} />

    </View>

  );

}



export function DestinationMapPin({ etaMinutes, label = 'Chegada' }: EtaProps) {

  const showEta = etaMinutes != null && Number.isFinite(etaMinutes);



  return (

    <View style={styles.wrap}>

      {showEta ? (

        <View style={styles.bubble}>

          <Text style={styles.bubbleLabel}>{label}</Text>

          <Text style={styles.bubbleEta}>{Math.round(etaMinutes!)} min</Text>

        </View>

      ) : null}

      <View style={styles.dotOuter}>

        <View style={styles.dotInner} />

      </View>

    </View>

  );

}



const styles = StyleSheet.create({

  wrap: {

    alignItems: 'center',

  },

  bubble: {

    backgroundColor: '#fff',

    borderRadius: 10,

    paddingHorizontal: 14,

    paddingVertical: 8,

    marginBottom: 6,

    minWidth: 72,

    alignItems: 'center',

    shadowColor: '#000',

    shadowOpacity: 0.18,

    shadowRadius: 8,

    shadowOffset: { width: 0, height: 4 },

    elevation: 6,

  },

  bubbleLabel: {

    fontSize: 11,

    fontWeight: '600',

    color: '#6B7280',

  },

  bubbleEta: {

    fontSize: 20,

    fontWeight: '800',

    color: '#111',

    marginTop: 2,

  },

  dotOuter: {

    width: 16,

    height: 16,

    borderRadius: 8,

    backgroundColor: '#111',

    borderWidth: 3,

    borderColor: '#fff',

    alignItems: 'center',

    justifyContent: 'center',

    shadowColor: '#000',

    shadowOpacity: 0.35,

    shadowRadius: 4,

    elevation: 4,

  },

  dotInner: {

    width: 5,

    height: 5,

    borderRadius: 2.5,

    backgroundColor: '#fff',

  },

  driverDot: {

    width: 14,

    height: 14,

    borderRadius: 7,

    backgroundColor: '#1A73E8',

    borderWidth: 3,

    borderColor: '#fff',

    shadowColor: '#000',

    shadowOpacity: 0.35,

    shadowRadius: 4,

    elevation: 4,

  },

});


