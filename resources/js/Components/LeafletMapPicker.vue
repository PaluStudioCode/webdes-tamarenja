<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerIconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { MapPin, RotateCcw } from 'lucide-vue-next';

// Perbaiki resolusi path ikon marker bawaan Leaflet pada bundler Vite
if (L?.Icon?.Default) {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl: markerIconUrl,
        iconRetinaUrl: markerIconRetinaUrl,
        shadowUrl: markerShadowUrl,
    });
}

const props = defineProps({
    latitude: {
        type: [Number, String],
        default: null,
    },
    longitude: {
        type: [Number, String],
        default: null,
    },
    defaultCenter: {
        type: Object,
        default: () => ({
            latitude: -0.5835,
            longitude: 119.7932,
        }),
    },
    zoom: {
        type: Number,
        default: 15,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:latitude', 'update:longitude', 'change']);

const mapContainerRef = ref(null);
const isMapReady = ref(false);
let mapInstance = null;
let markerInstance = null;

const hasValidCoordinates = computed(() => {
    if (
        props.latitude === null ||
        props.latitude === undefined ||
        props.latitude === '' ||
        props.longitude === null ||
        props.longitude === undefined ||
        props.longitude === ''
    ) {
        return false;
    }

    const lat = Number(props.latitude);
    const lng = Number(props.longitude);
    return (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat >= -90 &&
        lat <= 90 &&
        lng >= -180 &&
        lng <= 180
    );
});

function formatCoord(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
        return null;
    }
    return Number(num.toFixed(6));
}

function setCoordinates(lat, lng) {
    if (props.disabled) {
        return;
    }

    const formattedLat = formatCoord(lat);
    const formattedLng = formatCoord(lng);

    emit('update:latitude', formattedLat);
    emit('update:longitude', formattedLng);
    emit('change', {
        latitude: formattedLat,
        longitude: formattedLng,
    });

    syncMarkerOnMap(formattedLat, formattedLng);
}

function handleLatInput(event) {
    const raw = event.target.value;
    const nextLat = raw === '' ? null : Number(raw);
    emit('update:latitude', raw === '' || Number.isNaN(nextLat) ? raw : nextLat);
    emit('change', {
        latitude: raw === '' || Number.isNaN(nextLat) ? null : nextLat,
        longitude:
            props.longitude === '' || props.longitude === null
                ? null
                : Number(props.longitude),
    });
}

function handleLngInput(event) {
    const raw = event.target.value;
    const nextLng = raw === '' ? null : Number(raw);
    emit('update:longitude', raw === '' || Number.isNaN(nextLng) ? raw : nextLng);
    emit('change', {
        latitude:
            props.latitude === '' || props.latitude === null
                ? null
                : Number(props.latitude),
        longitude: raw === '' || Number.isNaN(nextLng) ? null : nextLng,
    });
}

function resetToDefaultCenter() {
    if (props.disabled) {
        return;
    }
    const defLat = Number(props.defaultCenter?.latitude ?? -0.5835);
    const defLng = Number(props.defaultCenter?.longitude ?? 119.7932);
    setCoordinates(defLat, defLng);
}

function syncMarkerOnMap(lat, lng) {
    if (!mapInstance) {
        return;
    }

    if (
        lat === null ||
        lng === null ||
        !Number.isFinite(Number(lat)) ||
        !Number.isFinite(Number(lng))
    ) {
        if (markerInstance) {
            mapInstance.removeLayer(markerInstance);
            markerInstance = null;
        }
        return;
    }

    const latLng = [Number(lat), Number(lng)];

    if (!markerInstance) {
        markerInstance = L.marker(latLng, {
            draggable: !props.disabled,
        }).addTo(mapInstance);

        markerInstance.on('dragend', (event) => {
            const pos = event.target.getLatLng();
            if (pos) {
                setCoordinates(pos.lat, pos.lng);
            }
        });
    } else {
        markerInstance.setLatLng(latLng);
    }

    if (typeof mapInstance.panTo === 'function') {
        mapInstance.panTo(latLng);
    }
}

onMounted(() => {
    if (!mapContainerRef.value) {
        return;
    }

    try {
        const initLat = hasValidCoordinates.value
            ? Number(props.latitude)
            : Number(props.defaultCenter?.latitude ?? -0.5835);
        const initLng = hasValidCoordinates.value
            ? Number(props.longitude)
            : Number(props.defaultCenter?.longitude ?? 119.7932);

        mapInstance = L.map(mapContainerRef.value, {
            center: [initLat, initLng],
            zoom: props.zoom,
        });

        // Lapisan Citra Satelit Hybrid (Foto Satelit + Label Nama Tempat/Jalan)
        const satelliteLayer = L.tileLayer(
            'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
            {
                subdomains: ['0', '1', '2', '3'],
                maxZoom: 20,
                attribution: '&copy; Google Maps',
            },
        );

        // Lapisan Peta Jalan Standar (OpenStreetMap)
        const streetLayer = L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            },
        );

        // Pasang citra satelit sebagai basemap utama
        satelliteLayer.addTo(mapInstance);

        // Kontrol Pengalih Lapisan Peta di sudut kanan atas
        if (typeof L.control?.layers === 'function') {
            L.control
                .layers(
                    {
                        'Citra Satelit': satelliteLayer,
                        'Peta Jalan': streetLayer,
                    },
                    null,
                    { position: 'topright' },
                )
                .addTo(mapInstance);
        }

        mapInstance.on('click', (event) => {
            if (!props.disabled && event?.latlng) {
                setCoordinates(event.latlng.lat, event.latlng.lng);
            }
        });

        if (hasValidCoordinates.value) {
            syncMarkerOnMap(Number(props.latitude), Number(props.longitude));
        }

        isMapReady.value = true;

        setTimeout(() => {
            if (mapInstance && typeof mapInstance.invalidateSize === 'function') {
                mapInstance.invalidateSize();
            }
        }, 200);
    } catch {
        // Fallback aman untuk lingkungan non-canvas / JSDOM
        isMapReady.value = true;
    }
});

watch(
    () => [props.latitude, props.longitude],
    ([newLat, newLng]) => {
        if (hasValidCoordinates.value) {
            syncMarkerOnMap(Number(newLat), Number(newLng));
        }
    },
);

onBeforeUnmount(() => {
    if (mapInstance && typeof mapInstance.remove === 'function') {
        mapInstance.remove();
        mapInstance = null;
        markerInstance = null;
    }
});

defineExpose({
    setCoordinates,
    resetToDefaultCenter,
    invalidateSize: () => {
        if (mapInstance && typeof mapInstance.invalidateSize === 'function') {
            mapInstance.invalidateSize();
        }
    },
});
</script>

<template>
    <div data-testid="leaflet-map-picker" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <MapPin class="h-4 w-4 text-blue-700" :stroke-width="2" />
                <span>Titik Koordinat Lokasi Proyek (Geotagging)</span>
            </div>

            <button
                v-if="!disabled"
                type="button"
                data-testid="map-picker-default-btn"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                @click="resetToDefaultCenter"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                <span>Pusatkan ke Kantor Desa</span>
            </button>
        </div>

        <!-- Kontainer Peta Interaktif -->
        <div class="relative overflow-hidden rounded-lg border border-slate-300 bg-slate-100">
            <div
                v-if="!isMapReady"
                class="h-72 w-full animate-pulse bg-slate-100"
            />
            <div
                ref="mapContainerRef"
                data-testid="leaflet-map-container"
                class="h-72 w-full"
            />
        </div>

        <p v-if="!disabled" class="text-xs text-slate-500">
            Klik langsung pada peta untuk menandai titik lokasi proyek fisik atau ketik koordinat lintang dan bujur di bawah ini.
        </p>

        <!-- Input Koordinat Dua Arah -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <label
                    for="input-latitude"
                    class="block text-sm font-medium text-slate-700"
                >
                    Latitude (Lintang)
                </label>
                <input
                    id="input-latitude"
                    type="number"
                    step="0.000001"
                    min="-90"
                    max="90"
                    data-testid="input-latitude"
                    placeholder="-0.583500"
                    :value="latitude ?? ''"
                    :disabled="disabled"
                    :class="[
                        'h-10 w-full rounded-md border bg-white px-3 text-sm tabular-nums text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2',
                        errors?.latitude
                            ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                            : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                        disabled ? 'cursor-not-allowed bg-slate-100 text-slate-500' : '',
                    ]"
                    @input="handleLatInput"
                />
                <p
                    v-if="errors?.latitude"
                    data-testid="error-latitude"
                    class="text-xs font-medium text-red-600"
                >
                    {{ errors.latitude }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label
                    for="input-longitude"
                    class="block text-sm font-medium text-slate-700"
                >
                    Longitude (Bujur)
                </label>
                <input
                    id="input-longitude"
                    type="number"
                    step="0.000001"
                    min="-180"
                    max="180"
                    data-testid="input-longitude"
                    placeholder="119.793200"
                    :value="longitude ?? ''"
                    :disabled="disabled"
                    :class="[
                        'h-10 w-full rounded-md border bg-white px-3 text-sm tabular-nums text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2',
                        errors?.longitude
                            ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                            : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                        disabled ? 'cursor-not-allowed bg-slate-100 text-slate-500' : '',
                    ]"
                    @input="handleLngInput"
                />
                <p
                    v-if="errors?.longitude"
                    data-testid="error-longitude"
                    class="text-xs font-medium text-red-600"
                >
                    {{ errors.longitude }}
                </p>
            </div>
        </div>
    </div>
</template>
