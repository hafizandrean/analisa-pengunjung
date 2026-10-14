import axios, { AxiosError } from 'axios';
import {
  DashboardSummary,
  HourlyTrafficItem,
  DailyTrafficItem,
  PeakHourItem,
  PeriodComparisonItem,
  CountingEventListResponse,
  Camera,
  CountingSession,
  SystemStatus,
  SystemLog,
  User,
  CountingEvent,
  CameraCreatePayload,
  CameraUpdatePayload,
  CameraTestResult,
  CameraProcessingStatus,
  DetectedWebcam,
  DiscoveredCCTVDevice,
  DiscoveryResponse,
  DeviceValidationRequest,
  DeviceValidationResponse
} from '../types/api';
import {
  DEMO_SUMMARY,
  DEMO_HOURLY,
  DEMO_DAILY,
  DEMO_PEAK_HOURS,
  DEMO_COMPARISON,
  DEMO_EVENTS,
  DEMO_CAMERAS,
  DEMO_SESSIONS,
  DEMO_SYSTEM_STATUS,
  DEMO_LOGS,
  DEMO_DISCOVERED_DEVICES
} from './demoData';

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api/v1';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 8000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Attach token interceptor
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('abj_auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// App mode check: true if user selected local demo fallback
export const isDemoModeActive = (): boolean => {
  const setting = localStorage.getItem('abj_use_demo_mode');
  return setting === null || setting === 'true'; // Default to demo mode enabled for instant preview
};

export const setDemoModeActive = (active: boolean): void => {
  localStorage.setItem('abj_use_demo_mode', active ? 'true' : 'false');
};

export const api = {
  // Authentication
  auth: {
    async login(email: string, password: string): Promise<{ token: string; user: User }> {
      try {
        const response = await apiClient.post('/auth/login', { email, password });
        const token = response.data.access_token;
        localStorage.setItem('abj_auth_token', token);
        const meRes = await apiClient.get('/auth/me');
        return { token, user: meRes.data };
      } catch (err) {
        // Deterministic fallback for demo authentication
        if (email === 'admin@ayambangjungko.com' && password === 'password123') {
          const fakeUser: User = {
            id: 'a0000000-0000-0000-0000-000000000001',
            email,
            full_name: 'Manager Operasional Ayam Bang Jungko',
            role: 'manager',
            is_active: true,
            created_at: new Date().toISOString()
          };
          localStorage.setItem('abj_auth_token', 'demo-token');
          return { token: 'demo-token', user: fakeUser };
        }
        throw new Error('Email atau password tidak sesuai.');
      }
    },

    async getMe(): Promise<User> {
      try {
        const response = await apiClient.get('/auth/me');
        return response.data;
      } catch (err) {
        return {
          id: 'a0000000-0000-0000-0000-000000000001',
          email: 'admin@ayambangjungko.com',
          full_name: 'Manager Operasional Ayam Bang Jungko',
          role: 'manager',
          is_active: true,
          created_at: new Date().toISOString()
        };
      }
    },

    logout() {
      localStorage.removeItem('abj_auth_token');
    }
  },

  // Dashboard
  dashboard: {
    async getSummary(cameraId?: string): Promise<DashboardSummary> {
      if (isDemoModeActive()) {
        return DEMO_SUMMARY;
      }
      try {
        const res = await apiClient.get<DashboardSummary>('/dashboard/summary', {
          params: { camera_id: cameraId, source_mode: 'DEMO' }
        });
        return res.data;
      } catch {
        return DEMO_SUMMARY;
      }
    }
  },

  // Analytics
  analytics: {
    async getHourly(date?: string): Promise<HourlyTrafficItem[]> {
      if (isDemoModeActive()) {
        return DEMO_HOURLY;
      }
      try {
        const res = await apiClient.get<HourlyTrafficItem[]>('/analytics/hourly', {
          params: { date }
        });
        return res.data;
      } catch {
        return DEMO_HOURLY;
      }
    },

    async getDaily(days: number = 7): Promise<DailyTrafficItem[]> {
      if (isDemoModeActive()) {
        return DEMO_DAILY;
      }
      try {
        const res = await apiClient.get<DailyTrafficItem[]>('/analytics/daily', {
          params: { days }
        });
        return res.data;
      } catch {
        return DEMO_DAILY;
      }
    },

    async getPeakHours(date?: string): Promise<PeakHourItem[]> {
      if (isDemoModeActive()) {
        return DEMO_PEAK_HOURS;
      }
      try {
        const res = await apiClient.get<PeakHourItem[]>('/analytics/peak-hours', {
          params: { date }
        });
        return res.data;
      } catch {
        return DEMO_PEAK_HOURS;
      }
    },

    async getComparison(periodType: string = 'weekly'): Promise<PeriodComparisonItem[]> {
      if (isDemoModeActive()) {
        return DEMO_COMPARISON;
      }
      try {
        const res = await apiClient.get<PeriodComparisonItem[]>('/analytics/comparison', {
          params: { period_type: periodType }
        });
        return res.data;
      } catch {
        return DEMO_COMPARISON;
      }
    }
  },

  // Events History
  events: {
    async getList(params: {
      page?: number;
      page_size?: number;
      direction?: string;
      camera_id?: string;
      source_mode?: string;
      search?: string;
    }): Promise<CountingEventListResponse> {
      if (isDemoModeActive()) {
        let items = [...DEMO_EVENTS];
        if (params.direction) {
          items = items.filter(e => e.direction === params.direction);
        }
        if (params.search) {
          const s = params.search.toLowerCase();
          items = items.filter(e =>
            (e.track_id && e.track_id.toString().includes(s)) ||
            (e.camera_name && e.camera_name.toLowerCase().includes(s)) ||
            e.direction.toLowerCase().includes(s)
          );
        }
        const page = params.page || 1;
        const pageSize = params.page_size || 10;
        const start = (page - 1) * pageSize;
        return {
          total: items.length,
          page,
          page_size: pageSize,
          items: items.slice(start, start + pageSize)
        };
      }
      try {
        const res = await apiClient.get<CountingEventListResponse>('/events', { params });
        return res.data;
      } catch {
        return {
          total: DEMO_EVENTS.length,
          page: params.page || 1,
          page_size: params.page_size || 10,
          items: DEMO_EVENTS.slice(0, params.page_size || 10)
        };
      }
    }
  },

  // Cameras
  cameras: {
    async getList(): Promise<Camera[]> {
      if (isDemoModeActive()) {
        return DEMO_CAMERAS;
      }
      try {
        const res = await apiClient.get<Camera[]>('/cameras');
        return res.data;
      } catch {
        return DEMO_CAMERAS;
      }
    },

    async getById(cameraId: string): Promise<Camera> {
      if (isDemoModeActive()) {
        const found = DEMO_CAMERAS.find(c => c.id === cameraId);
        if (found) return found;
        throw new Error('Kamera tidak ditemukan');
      }
      const res = await apiClient.get<Camera>(`/cameras/${cameraId}`);
      return res.data;
    },

    async create(payload: CameraCreatePayload): Promise<Camera> {
      if (isDemoModeActive()) {
        const newCam: Camera = {
          id: `demo-${Date.now()}`,
          name: payload.name,
          location: payload.location,
          source_type: payload.source_type,
          source_uri_masked: payload.username ? `${payload.source_type.toLowerCase()}://${payload.username}:***@host` : payload.source_uri,
          resolution_width: payload.resolution_width || 1280,
          resolution_height: payload.resolution_height || 720,
          fps: payload.fps || 25,
          line_coords: payload.line_coords,
          is_active: payload.is_active ?? true,
          connection_status: 'OFFLINE',
          processing_status: 'IDLE',
          notes: payload.notes || null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
        DEMO_CAMERAS.push(newCam);
        return newCam;
      }
      const res = await apiClient.post<Camera>('/cameras', payload);
      return res.data;
    },

    async update(cameraId: string, data: CameraUpdatePayload): Promise<Camera> {
      if (isDemoModeActive()) {
        const idx = DEMO_CAMERAS.findIndex(c => c.id === cameraId);
        if (idx >= 0) {
          DEMO_CAMERAS[idx] = { ...DEMO_CAMERAS[idx], ...data, updated_at: new Date().toISOString() };
          return DEMO_CAMERAS[idx];
        }
        throw new Error('Kamera tidak ditemukan');
      }
      const res = await apiClient.patch<Camera>(`/cameras/${cameraId}`, data);
      return res.data;
    },

    async delete(cameraId: string): Promise<void> {
      if (isDemoModeActive()) {
        const idx = DEMO_CAMERAS.findIndex(c => c.id === cameraId);
        if (idx >= 0) {
          DEMO_CAMERAS.splice(idx, 1);
        }
        return;
      }
      await apiClient.delete(`/cameras/${cameraId}`);
    },

    async testConnection(cameraId: string): Promise<CameraTestResult> {
      if (isDemoModeActive()) {
        const cam = DEMO_CAMERAS.find(c => c.id === cameraId);
        const isOnline = cam?.source_type === 'USB_WEBCAM' || cam?.name.includes('Utama');
        if (cam) {
          cam.connection_status = isOnline ? 'ONLINE' : 'OFFLINE';
          cam.last_connected_at = isOnline ? new Date().toISOString() : null;
        }
        return {
          camera_id: cameraId,
          is_accessible: Boolean(isOnline),
          connection_status: isOnline ? 'ONLINE' : 'OFFLINE',
          resolution_width: cam?.resolution_width,
          resolution_height: cam?.resolution_height,
          fps: cam?.fps,
          message: isOnline ? 'Koneksi live kamera berhasil diverifikasi (Mode Demo).' : 'Kamera live tidak dapat dihubungi (Mode Demo).',
          checked_at: new Date().toISOString()
        };
      }
      const res = await apiClient.post<CameraTestResult>(`/cameras/${cameraId}/test`);
      return res.data;
    },

    async startProcessing(cameraId: string): Promise<CameraProcessingStatus> {
      if (isDemoModeActive()) {
        const cam = DEMO_CAMERAS.find(c => c.id === cameraId);
        if (cam) cam.processing_status = 'RUNNING';
        return {
          camera_id: cameraId,
          processing_status: 'RUNNING',
          connection_status: cam?.connection_status || 'ONLINE',
          message: 'Pemrosesan live AI YOLOv8 + ByteTrack dimulai (Mode Demo).'
        };
      }
      const res = await apiClient.post<CameraProcessingStatus>(`/cameras/${cameraId}/start-processing`);
      return res.data;
    },

    async stopProcessing(cameraId: string): Promise<CameraProcessingStatus> {
      if (isDemoModeActive()) {
        const cam = DEMO_CAMERAS.find(c => c.id === cameraId);
        if (cam) cam.processing_status = 'STOPPED';
        return {
          camera_id: cameraId,
          processing_status: 'STOPPED',
          connection_status: cam?.connection_status || 'OFFLINE',
          message: 'Pemrosesan live AI dihentikan (Mode Demo).'
        };
      }
      const res = await apiClient.post<CameraProcessingStatus>(`/cameras/${cameraId}/stop-processing`);
      return res.data;
    },

    async getStatus(cameraId: string): Promise<CameraProcessingStatus> {
      if (isDemoModeActive()) {
        const cam = DEMO_CAMERAS.find(c => c.id === cameraId);
        return {
          camera_id: cameraId,
          processing_status: cam?.processing_status || 'STOPPED',
          connection_status: cam?.connection_status || 'OFFLINE',
          message: 'Status Mode Demo'
        };
      }
      const res = await apiClient.get<CameraProcessingStatus>(`/cameras/${cameraId}/status`);
      return res.data;
    },

    async detectWebcams(): Promise<DetectedWebcam[]> {
      if (isDemoModeActive()) {
        return [
          { index: 0, name: "Kamera #0 (Laptop / Built-in)", resolution: "640x480", fps: 30 },
          { index: 1, name: "Kamera #1 (USB External HD Webcam)", resolution: "1280x720", fps: 30 }
        ];
      }
      const res = await apiClient.get<{ count: number; devices: DetectedWebcam[] }>('/cameras/webcams/detect');
      return res.data.devices;
    },

    async discoverNetwork(timeout: number = 3.0, simulate: boolean = false): Promise<DiscoveryResponse> {
      if (isDemoModeActive()) {
        return {
          success: true,
          device_count: DEMO_DISCOVERED_DEVICES.length,
          devices: DEMO_DISCOVERED_DEVICES,
          duration_seconds: 0.45,
          message: `Berhasil menemukan ${DEMO_DISCOVERED_DEVICES.length} profil perangkat CCTV ONVIF (Mode Demo).`,
          network_scope: "Jaringan Lokal (UDP Multicast WS-Discovery 239.255.255.250:3702)"
        };
      }
      const res = await apiClient.post<DiscoveryResponse>('/cameras/discovery/scan', null, {
        params: { timeout, simulate }
      });
      return res.data;
    },

    async validateDevice(req: DeviceValidationRequest): Promise<DeviceValidationResponse> {
      if (isDemoModeActive()) {
        const isOnline = req.ip_address.startsWith('192.168.1') || req.ip_address === '127.0.0.1';
        return {
          is_accessible: isOnline,
          connection_status: isOnline ? 'ONLINE' : 'ERROR',
          resolution_width: 1920,
          resolution_height: 1080,
          fps: 25,
          message: isOnline
            ? 'Verifikasi sukses! Feed live CCTV aktif (Mode Demo).'
            : 'Perangkat tidak merespons stream RTSP pada IP yang dipilih.',
          checked_at: new Date().toISOString()
        };
      }
      const res = await apiClient.post<DeviceValidationResponse>('/cameras/discovery/validate', req);
      return res.data;
    },

    getPreviewUrl(cameraId: string): string {
      const base = API_BASE_URL.startsWith('http') ? API_BASE_URL : window.location.origin + API_BASE_URL;
      return `${base}/cameras/${cameraId}/preview`;
    }
  },

  // Sessions
  sessions: {
    async getList(): Promise<CountingSession[]> {
      if (isDemoModeActive()) {
        return DEMO_SESSIONS;
      }
      try {
        const res = await apiClient.get<CountingSession[]>('/sessions');
        return res.data;
      } catch {
        return DEMO_SESSIONS;
      }
    }
  },

  // System
  system: {
    async getStatus(): Promise<SystemStatus> {
      if (isDemoModeActive()) {
        return DEMO_SYSTEM_STATUS;
      }
      try {
        const res = await apiClient.get<SystemStatus>('/system/status');
        return res.data;
      } catch {
        return DEMO_SYSTEM_STATUS;
      }
    },

    async getLogs(): Promise<SystemLog[]> {
      if (isDemoModeActive()) {
        return DEMO_LOGS;
      }
      try {
        const res = await apiClient.get<SystemLog[]>('/system/logs');
        return res.data;
      } catch {
        return DEMO_LOGS;
      }
    },

    async getHealth(): Promise<{ status: string; timezone: string }> {
      try {
        const res = await apiClient.get('/health');
        return res.data;
      } catch {
        return { status: 'ok (demo)', timezone: 'Asia/Jakarta' };
      }
    }
  }
};
