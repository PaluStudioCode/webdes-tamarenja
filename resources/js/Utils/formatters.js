/**
 * Utilitas pemformatan data frontend Sistem Desa Tamarenja
 * Mematuhi PRD 04: Rupiah titik, tanggal Indonesia baku, dan persentase tabular.
 */

const BULAN_INDONESIA = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const BULAN_INDONESIA_PENDEK = [
    'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
];

/**
 * Memformat angka ke format Rupiah Indonesia (misal: "Rp 55.000.000")
 *
 * @param {number|string|null} value
 * @param {boolean} withPrefix Menampilkan "Rp " di depan (default: true)
 * @returns {string}
 */
export function formatRupiah(value, withPrefix = true) {
    if (value === null || value === undefined || value === '') {
        return withPrefix ? 'Rp 0' : '0';
    }

    const num = Number(value);
    if (isNaN(num)) {
        return withPrefix ? 'Rp 0' : '0';
    }

    const isNegative = num < 0;
    const absVal = Math.round(Math.abs(num));
    const parts = absVal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    const prefix = withPrefix ? 'Rp ' : '';
    return isNegative ? `-${prefix}${parts}` : `${prefix}${parts}`;
}

/**
 * Mengonversi string format Rupiah kembali menjadi angka integer
 * Misal: "Rp 55.000.000" -> 55000000
 *
 * @param {string|number|null} value
 * @returns {number}
 */
export function parseRupiah(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Math.round(value);
    }

    const isNegative = value.includes('-');
    const clean = value.replace(/\D/g, '');
    const num = clean === '' ? 0 : parseInt(clean, 10);

    return isNegative ? -num : num;
}

/**
 * Memformat angka bulat dengan pemisah ribuan titik (misal: "1.250")
 *
 * @param {number|string|null} value
 * @returns {string}
 */
export function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const num = Number(value);
    if (isNaN(num)) {
        return '0';
    }

    const isNegative = num < 0;
    const absVal = Math.round(Math.abs(num));
    const formatted = absVal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return isNegative ? `-${formatted}` : formatted;
}

/**
 * Memformat tanggal ke format bahasa Indonesia
 *
 * @param {string|Date|null} dateInput Tanggal ISO atau Date object
 * @param {'long'|'short'|'medium'} format Gaya format (default: 'long')
 * @returns {string}
 */
export function formatDate(dateInput, format = 'long') {
    if (!dateInput) {
        return '-';
    }

    const d = new Date(dateInput);
    if (isNaN(d.getTime())) {
        return '-';
    }

    const day = d.getDate();
    const monthIdx = d.getMonth();
    const year = d.getFullYear();

    if (format === 'short') {
        const dd = String(day).padStart(2, '0');
        const mm = String(monthIdx + 1).padStart(2, '0');
        return `${dd}/${mm}/${year}`;
    }

    if (format === 'medium') {
        return `${day} ${BULAN_INDONESIA_PENDEK[monthIdx]} ${year}`;
    }

    // Default 'long': "5 November 2026"
    return `${day} ${BULAN_INDONESIA[monthIdx]} ${year}`;
}

export const formatDateId = formatDate;

/**
 * Memformat tanggal dan waktu Indonesia (misal: "5 November 2026, 09:05")
 *
 * @param {string|Date|null} dateInput
 * @returns {string}
 */
export function formatDateTime(dateInput) {
    if (!dateInput) {
        return '-';
    }

    const d = new Date(dateInput);
    if (isNaN(d.getTime())) {
        return '-';
    }

    const dateStr = formatDate(d, 'long');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${dateStr}, ${hours}:${minutes}`;
}

/**
 * Memformat angka persentase (misal: 75.5 -> "75.5%" atau 100 -> "100%")
 *
 * @param {number|string|null} value
 * @param {number} decimals Jumlah desimal (default: 1)
 * @returns {string}
 */
export function formatPercentage(value, decimals = 1) {
    if (value === null || value === undefined || value === '') {
        return '0%';
    }

    const num = Number(value);
    if (isNaN(num)) {
        return '0%';
    }

    // Jika bulat atau decimals = 0
    if (Number.isInteger(num) || decimals === 0) {
        return `${Math.round(num)}%`;
    }

    return `${num.toFixed(decimals)}%`;
}
