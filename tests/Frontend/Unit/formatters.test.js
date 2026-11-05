import { describe, it, expect } from 'vitest';
import {
    formatRupiah,
    parseRupiah,
    formatNumber,
    formatDate,
    formatDateTime,
    formatPercentage,
} from '@/Utils/formatters';

describe('formatters.js utilitas frontend', () => {
    describe('formatRupiah()', () => {
        it('memformat angka ke format Rupiah standar', () => {
            expect(formatRupiah(55000000)).toBe('Rp 55.000.000');
            expect(formatRupiah(1500000)).toBe('Rp 1.500.000');
            expect(formatRupiah(0)).toBe('Rp 0');
        });

        it('mendukung opsi withPrefix = false', () => {
            expect(formatRupiah(55000000, false)).toBe('55.000.000');
            expect(formatRupiah(0, false)).toBe('0');
        });

        it('menangani angka negatif', () => {
            expect(formatRupiah(-50000)).toBe('-Rp 50.000');
            expect(formatRupiah(-50000, false)).toBe('-50.000');
        });

        it('menangani nilai null, undefined, atau string kosong', () => {
            expect(formatRupiah(null)).toBe('Rp 0');
            expect(formatRupiah(undefined)).toBe('Rp 0');
            expect(formatRupiah('')).toBe('Rp 0');
        });
    });

    describe('parseRupiah()', () => {
        it('mengonversi string Rupiah menjadi integer', () => {
            expect(parseRupiah('Rp 55.000.000')).toBe(55000000);
            expect(parseRupiah('1.500.000')).toBe(1500000);
            expect(parseRupiah('Rp 0')).toBe(0);
        });

        it('menangani string negatif', () => {
            expect(parseRupiah('-Rp 50.000')).toBe(-50000);
        });

        it('menangani input kosong atau number', () => {
            expect(parseRupiah(null)).toBe(0);
            expect(parseRupiah('')).toBe(0);
            expect(parseRupiah(25000)).toBe(25000);
        });
    });

    describe('formatNumber()', () => {
        it('memformat angka dengan titik ribuan', () => {
            expect(formatNumber(1250)).toBe('1.250');
            expect(formatNumber(1000000)).toBe('1.000.000');
            expect(formatNumber(0)).toBe('0');
            expect(formatNumber(null)).toBe('0');
        });
    });

    describe('formatDate()', () => {
        it('memformat tanggal ke format bahasa Indonesia panjang (default)', () => {
            // Gunakan date string dengan waktu spesifik untuk menghindari timezone shift
            const formatted = formatDate('2026-11-05T12:00:00Z', 'long');
            expect(formatted).toContain('November 2026');
            expect(formatted).toContain('5');
        });

        it('memformat tanggal ke format pendek (dd/mm/yyyy)', () => {
            const formatted = formatDate('2026-11-05T12:00:00Z', 'short');
            expect(formatted).toBe('05/11/2026');
        });

        it('memformat tanggal ke format medium', () => {
            const formatted = formatDate('2026-11-05T12:00:00Z', 'medium');
            expect(formatted).toContain('Nov 2026');
        });

        it('menangani nilai null, undefined, atau tanggal tidak valid', () => {
            expect(formatDate(null)).toBe('-');
            expect(formatDate('')).toBe('-');
            expect(formatDate('tanggal-rusak')).toBe('-');
        });
    });

    describe('formatDateTime()', () => {
        it('menghasilkan tanggal dan jam format Indonesia', () => {
            const result = formatDateTime('2026-11-05T09:05:00');
            expect(result).toContain('5 November 2026');
            expect(result).toContain('09:05');
        });

        it('menangani nilai tidak valid', () => {
            expect(formatDateTime(null)).toBe('-');
        });
    });

    describe('formatPercentage()', () => {
        it('memformat angka desimal ke string persentase', () => {
            expect(formatPercentage(75.5)).toBe('75.5%');
            expect(formatPercentage(100)).toBe('100%');
            expect(formatPercentage(0)).toBe('0%');
            expect(formatPercentage(33.333, 2)).toBe('33.33%');
        });

        it('menangani nilai null atau kosong', () => {
            expect(formatPercentage(null)).toBe('0%');
            expect(formatPercentage('')).toBe('0%');
        });
    });
});
