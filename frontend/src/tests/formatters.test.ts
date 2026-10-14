import { describe, it, expect } from 'vitest';
import {
  formatIndonesianDate,
  formatIndonesianDateTime,
  formatTimeOnly,
  formatNumber,
  formatPercent
} from '../utils/formatters';

describe('Indonesian Formatter Utilities', () => {
  it('formats numbers with thousand separators in Indonesian format', () => {
    expect(formatNumber(1250)).toBe('1.250');
    expect(formatNumber(45000)).toBe('45.000');
  });

  it('formats percentage strings with signs', () => {
    expect(formatPercent(12.4)).toBe('+12.4%');
    expect(formatPercent(-5.2)).toBe('-5.2%');
    expect(formatPercent(0)).toBe('0.0%');
  });

  it('formats dates in Indonesian locale without errors', () => {
    const testDate = new Date('2026-10-13T12:00:00Z');
    const result = formatIndonesianDate(testDate);
    expect(result).toBeTruthy();
    expect(result).toContain('2026');
  });

  it('formats date time with WIB suffix', () => {
    const testDate = new Date('2026-10-13T12:00:00Z');
    const result = formatIndonesianDateTime(testDate);
    expect(result).toContain('WIB');
  });

  it('handles invalid dates gracefully', () => {
    expect(formatIndonesianDate('invalid-date')).toBe('-');
    expect(formatIndonesianDateTime('invalid-date')).toBe('-');
    expect(formatTimeOnly('invalid-date')).toBe('-');
  });
});
