<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerIconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { formatRupiah } from '@/Utils/formatters';

if (L?.Icon?.Default) {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl: markerIconUrl,
        iconRetinaUrl: markerIconRetinaUrl,
        shadowUrl: markerShadowUrl,
    });
}

const props = defineProps({
    projects: {
        type: Array,
        default: () => [],
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
        default: 14,
    },
});

const emit = defineEmits(['select-project']);

const mapContainerRef = ref(null);
const selectedProject = ref(null);
let mapInstance = null;
let markersLayer = null;

const geotaggedProjects = computed(() =>
    (props.projects || []).filter(
        (p) =>
            p.latitude !== null &&
            p.latitude !== undefined &&
            p.longitude !== null &&
            p.longitude !== undefined &&
            Number.isFinite(Number(p.latitude)) &&
            Number.isFinite(Number(p.longitude)),
    ),
);

function selectProject(project) {
    selectedProject.value = project;
    emit('select-project', project);
}

function renderMarkers() {
    if (!mapInstance) {
        return;
    }

    if (markersLayer) {
        markersLayer.clearLayers();
    } else if (typeof L.layerGroup === 'function') {
        markersLayer = L.layerGroup().addTo(mapInstance);
    }

    geotaggedProjects.value.forEach((project) => {
        const lat = Number(project.latitude);
        const lng = Number(project.longitude);
        const marker = L.marker([lat, lng]);

        const popupHtml = `
            <div style="min-width: 190px; font-family: Inter, sans-serif;">
                <div style="font-weight: 600; font-size: 13px; color: #0f172a;">${project.nama_kegiatan}</div>
                <div style="font-size: 11px; color: #475569; margin-top: 2px;">${project.lokasi_dusun} &bull; Fisik: <strong>${project.progres_fisik}%</strong></div>
                <div style="font-size: 11px; color: #1d4ed8; font-weight: 600; margin-top: 4px;">Pagu: ${formatRupiah(project.pagu_anggaran)}</div>
            </div>
        `;

        if (typeof marker.bindPopup === 'function') {
            marker.bindPopup(popupHtml);
        }

        if (typeof marker.on === 'function') {
            marker.on('click', () => selectProject(project));
        }

        if (markersLayer && typeof markersLayer.addLayer === 'function') {
            markersLayer.addLayer(marker);
        } else if (typeof marker.addTo === 'function') {
            marker.addTo(mapInstance);
        }
    });
}

onMounted(() => {
    if (!mapContainerRef.value) {
        return;
    }

    try {
        const first = geotaggedProjects.value[0];
        const centerLat = first
            ? Number(first.latitude)
            : Number(props.defaultCenter?.latitude ?? -0.5835);
        const centerLng = first
            ? Number(first.longitude)
            : Number(props.defaultCenter?.longitude ?? 119.7932);

        mapInstance = L.map(mapContainerRef.value, {
            center: [centerLat, centerLng],
            zoom: props.zoom,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(mapInstance);

        renderMarkers();
    } catch {
        // Fallback aman di lingkungan JSDOM
    }
});

watch(
    () => props.projects,
    () => {
        renderMarkers();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (mapInstance && typeof mapInstance.remove === 'function') {
        mapInstance.remove();
        mapInstance = null;
        markersLayer = null;
    }
});
</script>

<template>
    <div data-testid="leaflet-map-view" class="space-y-3">
        <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
            <div
                ref="mapContainerRef"
                data-testid="leaflet-map-view-container"
                class="h-80 w-full"
            />
        </div>

        <!-- Daftar Titik Geotag Ringkas (Memudahkan Aksesibilitas & Uji Klik Marker) -->
        <div
            v-if="geotaggedProjects.length > 0"
            class="flex flex-wrap items-center gap-2"
        >
            <button
                v-for="proj in geotaggedProjects"
                :key="proj.id"
                type="button"
                :data-testid="`map-marker-chip-${proj.id}`"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-blue-600 hover:text-blue-700"
                @click="selectProject(proj)"
            >
                <span class="h-2 w-2 rounded-full bg-blue-700" />
                <span>{{ proj.nama_kegiatan }}</span>
                <span class="tabular-nums text-slate-500">({{ proj.progres_fisik }}%)</span>
            </button>
        </div>
    </div>
</template>
