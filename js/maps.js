// ============================================
// Shelah — Google Maps / GPS Location Picker
// ============================================

let mapsApiLoaded = false;
let mapsApiLoading = false;
let mapsCallbacks = [];

async function loadMapsApi() {
  if (mapsApiLoaded) return;
  if (mapsApiLoading) {
    return new Promise(resolve => mapsCallbacks.push(resolve));
  }
  mapsApiLoading = true;
  try {
    const data = await getMapsKey();
    if (!data.key) {
      console.warn('No Maps API key configured, using fallback');
      mapsApiLoaded = true;
      mapsCallbacks.forEach(cb => cb());
      return;
    }
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${data.key}&libraries=places`;
      script.async = true;
      script.defer = true;
      script.onload = () => {
        mapsApiLoaded = true;
        mapsCallbacks.forEach(cb => cb());
        resolve();
      };
      script.onerror = () => {
        console.warn('Failed to load Google Maps API');
        mapsApiLoaded = true;
        mapsCallbacks.forEach(cb => cb());
        resolve();
      };
      document.head.appendChild(script);
    });
  } catch (e) {
    console.warn('Maps key fetch failed, using fallback');
    mapsApiLoaded = true;
    mapsCallbacks.forEach(cb => cb());
  }
}

/**
 * Initialize a location picker in a container.
 * Falls back to GPS-only if Google Maps isn't available.
 */
async function initLocationPicker(containerId, onLocationSelected, initialLat, initialLng) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const defaultLat = initialLat || 30.0444; // Cairo
  const defaultLng = initialLng || 31.2357;

  await loadMapsApi();

  if (typeof google !== 'undefined' && google.maps) {
    const map = new google.maps.Map(container, {
      center: { lat: defaultLat, lng: defaultLng },
      zoom: 13,
      styles: [
        { featureType: 'poi', stylers: [{ visibility: 'simplified' }] },
        { featureType: 'water', stylers: [{ color: '#D6E4F0' }] }
      ],
      disableDefaultUI: true,
      zoomControl: true,
      mapTypeControl: false
    });

    const marker = new google.maps.Marker({
      position: { lat: defaultLat, lng: defaultLng },
      map: map,
      draggable: true
    });

    marker.addListener('dragend', () => {
      const pos = marker.getPosition();
      onLocationSelected({ lat: pos.lat(), lng: pos.lng() });
    });

    map.addListener('click', (e) => {
      marker.setPosition(e.latLng);
      onLocationSelected({ lat: e.latLng.lat(), lng: e.latLng.lng() });
    });

    // Store references for GPS button
    container._map = map;
    container._marker = marker;

    if (initialLat && initialLng) {
      onLocationSelected({ lat: defaultLat, lng: defaultLng });
    }
  } else {
    // Fallback: show coordinate inputs
    container.innerHTML = `
      <div style="padding:20px;text-align:center;color:var(--text-secondary)">
        <p style="font-size:2rem;margin-bottom:8px">📍</p>
        <p>Map unavailable. Use GPS or enter coordinates manually.</p>
        <div style="margin-top:12px;display:flex;gap:8px;justify-content:center">
          <input type="number" step="any" id="${containerId}-lat" placeholder="Latitude" class="form-input" style="width:140px" value="${defaultLat}">
          <input type="number" step="any" id="${containerId}-lng" placeholder="Longitude" class="form-input" style="width:140px" value="${defaultLng}">
        </div>
      </div>`;
    const latInput = document.getElementById(`${containerId}-lat`);
    const lngInput = document.getElementById(`${containerId}-lng`);
    const emitChange = () => {
      const lat = parseFloat(latInput.value);
      const lng = parseFloat(lngInput.value);
      if (!isNaN(lat) && !isNaN(lng)) onLocationSelected({ lat, lng });
    };
    latInput.addEventListener('change', emitChange);
    lngInput.addEventListener('change', emitChange);
    if (initialLat && initialLng) {
      onLocationSelected({ lat: defaultLat, lng: defaultLng });
    }
  }
}

function tryGPS(onSuccess, onFail) {
  if (!navigator.geolocation) {
    if (onFail) onFail('Geolocation not supported');
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      onSuccess({ lat: pos.coords.latitude, lng: pos.coords.longitude });
    },
    (err) => {
      if (onFail) onFail(err.message || 'GPS failed');
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

/**
 * Center the map and marker on a specific location
 */
function setMapLocation(containerId, lat, lng) {
  const container = document.getElementById(containerId);
  if (!container) return;

  if (container._map && container._marker) {
    const pos = { lat, lng };
    container._map.setCenter(pos);
    container._marker.setPosition(pos);
  } else {
    const latInput = document.getElementById(`${containerId}-lat`);
    const lngInput = document.getElementById(`${containerId}-lng`);
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
  }
}
