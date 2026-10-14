import { describe, it, expect } from 'vitest';
import {
  DEMO_SUMMARY,
  DEMO_HOURLY,
  DEMO_DAILY,
  DEMO_PEAK_HOURS,
  DEMO_CAMERAS,
  generateDemoEvents
} from '../services/demoData';

describe('Demo Data Integrity & Contracts', () => {
  it('validates summary metrics consistency', () => {
    expect(DEMO_SUMMARY.total_today).toBe(DEMO_SUMMARY.total_today_in + DEMO_SUMMARY.total_today_out);
    expect(DEMO_SUMMARY.total_today).toBeGreaterThan(0);
    expect(DEMO_SUMMARY.source_mode).toBe('DEMO');
  });

  it('verifies hourly traffic coverage throughout operational day', () => {
    expect(DEMO_HOURLY.length).toBeGreaterThanOrEqual(10);
    DEMO_HOURLY.forEach((hourItem) => {
      expect(hourItem.total).toBe(hourItem.count_in + hourItem.count_out);
    });
  });

  it('verifies camera configurations have valid coordinates', () => {
    expect(DEMO_CAMERAS.length).toBeGreaterThanOrEqual(1);
    const primaryCam = DEMO_CAMERAS[0];
    expect(primaryCam.line_coords.x1).toBeLessThan(primaryCam.line_coords.x2);
    expect(primaryCam.line_coords.y1).toBe(primaryCam.line_coords.y2);
  });

  it('generates deterministic counting events with proper tracking IDs', () => {
    const events = generateDemoEvents(20);
    expect(events.length).toBe(20);
    expect(events[0].track_id).toBeDefined();
    expect(events[0].source_mode).toBe('DEMO');
    expect(['IN', 'OUT']).toContain(events[0].direction);
  });
});
