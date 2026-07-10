/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./blocks/google-map/view.js ***!
  \***********************************/
/**
 * Frontend JavaScript for Google Map block
 */

document.addEventListener('DOMContentLoaded', function () {
  // Check if Google Maps API is already loaded
  if (window.google && window.google.maps) {
    initializeMaps();
    return;
  }

  // Check if script is already being loaded
  const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
  if (existingScript) {
    // Wait for existing script to load
    const checkAndInitialize = () => {
      if (window.google && window.google.maps) {
        initializeMaps();
      } else {
        setTimeout(checkAndInitialize, 100);
      }
    };
    checkAndInitialize();
    return;
  }

  // Load Google Maps API with callback
  window.initializeGoogleMaps = initializeMaps;
  const script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCWIUdebNRovvJryUDibH8cwjkRsPI2M_8&sensor=false&callback=initializeGoogleMaps';
  script.async = true;
  script.defer = true;
  script.onerror = () => {
    console.error('Failed to load Google Maps API');
  };
  document.head.appendChild(script);
});
function initializeMaps() {
  const mapContainers = document.querySelectorAll('.google-map-container');
  mapContainers.forEach(function (container) {
    const mapElement = container.querySelector('.google-map');
    const lat = parseFloat(container.dataset.lat);
    const lng = parseFloat(container.dataset.lng);
    const address = container.dataset.address;
    if (!mapElement || !lat || !lng) {
      return;
    }

    // Create map
    const map = new window.google.maps.Map(mapElement, {
      zoom: 10,
      center: {
        lat,
        lng
      },
      scrollwheel: false,
      draggable: false,
      // Disable map dragging
      mapTypeId: window.google.maps.MapTypeId.TERRAIN,
      disableDefaultUI: true,
      zoomControl: true,
      zoomControlOptions: {
        style: window.google.maps.ZoomControlStyle.SMALL,
        position: window.google.maps.ControlPosition.LEFT_BOTTOM
      }
    });

    // Create marker
    const marker = new window.google.maps.Marker({
      position: {
        lat,
        lng
      },
      map,
      title: address || 'Location'
    });

    // Add info window if address is available
    if (address) {
      const infoWindow = new window.google.maps.InfoWindow({
        content: '<div style="padding: 10px;"><strong>' + address + '</strong></div>'
      });
      marker.addListener('click', function () {
        infoWindow.open(map, marker);
      });
    }
  });
}
/******/ })()
;
//# sourceMappingURL=view.js.map