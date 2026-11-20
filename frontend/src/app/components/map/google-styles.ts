/// <reference types="google.maps" />
type Style = google.maps.MapTypeStyle;

export const GOOGLE_STYLES: Record<'dark' | 'light', Style[]> = {

  dark: [

    { elementType: 'geometry', stylers: [{ color: '#06417a' }] },

    { elementType: 'labels.text.fill', stylers: [{ color: '#5cdb95' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#05386b' }] },
    { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#1a6aa8' }] },

    { featureType: 'poi', stylers: [{ visibility: 'off' }] },

    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ visibility: 'on' }, { color: '#07508f' }] },
    { featureType: 'transit', stylers: [{ visibility: 'off' }] },

    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#0b5ba6' }] },
    { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#05386b' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#1672c2' }] },

    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#042b57' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#3a8f68' }] },
  ],

  light: [
    { elementType: 'geometry', stylers: [{ color: '#7fe6ae' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#05386b' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#a6f0c9' }] },
    { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#3fb883' }] },
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ visibility: 'on' }, { color: '#59d393' }] },
    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#b9f3d3' }] },
    { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#7fe6ae' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#e3fcef' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#2fa77a' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#05386b' }] },
  ],
};
