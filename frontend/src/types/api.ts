// TypeScript definitions mirroring backend Pydantic schemas

export interface User {
  id: string;
  email: string;
  full_name: string;
  role: string;
  is_active: boolean;
  created_at: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export interface VirtualLineCoords {
  x1: number;
  y1: number;
  x2: number;
  y2: number;
  direction_in: 'down' | 'up' | 'left' | 'right';
  offset: number;
}

export interface Camera {
  id: string;
  name: string;
  location: string;
  source_type: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
  source_uri_masked: string;
  resolution_width: number;
  resolution_height: number;
  fps: number;
  line_coords: VirtualLineCoords;
  is_active: boolean;
  connection_status: 'ONLINE' | 'OFFLINE' | 'DISCONNECTED' | 'CONNECTING' | 'RECONNECTING' | 'ERROR';
  processing_status: 'STOPPED' | 'STARTING' | 'RUNNING' | 'ERROR' | 'IDLE';
  last_connected_at?: string | null;
  last_frame_at?: string | null;
  connection_error?: string | null;
  username?: string | null;
  notes?: string | null;
  created_at: string;
  updated_at: string;
}

export interface DetectedWebcam {
  index: number;
  name: string;
  resolution: string;
  fps: number;
}

export interface CameraCreatePayload {
  name: string;
  location: string;
  source_type: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
  source_uri: string;
  username?: string;
  password?: string;
  resolution_width?: number;
  resolution_height?: number;
  fps?: number;
  line_coords: VirtualLineCoords;
  is_active?: boolean;
  notes?: string;
}

export interface CameraUpdatePayload {
  name?: string;
  location?: string;
  source_type?: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
  source_uri?: string;
  username?: string;
  password?: string;
  resolution_width?: number;
  resolution_height?: number;
  fps?: number;
  line_coords?: VirtualLineCoords;
  is_active?: boolean;
  notes?: string;
}

export interface CameraTestResult {
  camera_id: string;
  is_accessible: boolean;
  connection_status: 'ONLINE' | 'OFFLINE' | 'ERROR';
  resolution_width?: number;
  resolution_height?: number;
  fps?: number;
  message: string;
  error_detail?: string;
  checked_at: string;
}

export interface CameraProcessingStatus {
  camera_id: string;
  processing_status: 'STOPPED' | 'STARTING' | 'RUNNING' | 'ERROR' | 'IDLE';
  connection_status: string;
  session_id?: string | null;
  message: string;
}

export interface CountingSession {
  id: string;
  camera_id: string;
  camera_name?: string;
  mode: 'DEMO' | 'VIDEO_FILE' | 'LIVE_CCTV';
  status: 'RUNNING' | 'STOPPED' | 'COMPLETED' | 'FAILED';
  started_at: string;
  ended_at?: string | null;
  total_in: number;
  total_out: number;
  notes?: string | null;
}

export interface CountingEvent {
  id: string;
  camera_id: string;
  camera_name?: string;
  session_id?: string | null;
  event_timestamp: string;
  direction: 'IN' | 'OUT';
  count_value: number;
  track_id?: number | null;
  confidence?: number | null;
  source_mode: 'DEMO' | 'VIDEO' | 'LIVE';
  metadata_json?: Record<string, any> | null;
  created_at: string;
}

export interface CountingEventListResponse {
  total: number;
  page: number;
  page_size: number;
  items: CountingEvent[];
}

export interface DashboardSummary {
  total_today: number;
  total_today_in: number;
  total_today_out: number;
  total_week: number;
  average_daily: number;
  busiest_hour: string;
  busiest_hour_count: number;
  active_cameras: number;
  worker_status: 'RUNNING' | 'IDLE' | 'ERROR';
  source_mode: 'DEMO' | 'VIDEO' | 'LIVE';
  trend_today_vs_yesterday: number;
  trend_week_vs_last_week: number;
}

export interface HourlyTrafficItem {
  hour: string;
  count_in: number;
  count_out: number;
  total: number;
}

export interface DailyTrafficItem {
  date: string;
  day_name: string;
  count_in: number;
  count_out: number;
  total: number;
}

export interface PeakHourItem {
  time_window: string;
  count: number;
  occupancy_status: 'PADAT' | 'SEDANG' | 'LENGANG';
  percentage_of_daily: number;
}

export interface PeriodComparisonItem {
  period_label: string;
  current_period_count: number;
  previous_period_count: number;
  change_percentage: number;
}

export interface WorkerStatus {
  status: 'RUNNING' | 'IDLE' | 'OFFLINE';
  current_mode: 'DEMO' | 'VIDEO_FILE' | 'LIVE_CCTV';
  active_stream?: string | null;
  processed_fps?: number | null;
  last_frame_processed_at?: string | null;
}

export interface SystemStatus {
  backend_status: 'HEALTHY' | 'DEGRADED' | 'DOWN';
  database_connected: boolean;
  cv_worker: WorkerStatus;
  last_event_recorded_at?: string | null;
  server_time_wib: string;
  active_sessions_count: number;
}

export interface SystemLog {
  id: string;
  module: string;
  level: 'INFO' | 'WARNING' | 'ERROR';
  message: string;
  details?: Record<string, any> | null;
  created_at: string;
}

export interface DiscoveredCCTVDevice {
  device_id: string;
  name: string;
  ip_address: string;
  port: number;
  onvif_xaddr: string;
  manufacturer?: string | null;
  model?: string | null;
  location?: string | null;
  types: string[];
  scopes: string[];
  suggested_rtsp_uri: string;
  capabilities: Record<string, any>;
}

export interface DiscoveryResponse {
  success: boolean;
  device_count: number;
  devices: DiscoveredCCTVDevice[];
  duration_seconds: number;
  message: string;
  network_scope: string;
  error_detail?: string | null;
}

export interface DeviceValidationRequest {
  ip_address: string;
  port?: number;
  source_type: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
  rtsp_uri: string;
  username?: string;
  password?: string;
  timeout_seconds?: number;
  /** ONVIF device service endpoint — jika diisi, backend akan memanggil GetStreamUri SOAP
   *  untuk mendapatkan RTSP URL asli dari perangkat sebelum mencoba koneksi langsung. */
  onvif_xaddr?: string;
  /** Nama pabrikan (mis. "Hikvision") untuk menentukan kandidat URL RTSP yang tepat. */
  manufacturer?: string;
}

export interface DeviceValidationResponse {
  is_accessible: boolean;
  connection_status: 'ONLINE' | 'DISCONNECTED' | 'ERROR';
  resolution_width?: number | null;
  resolution_height?: number | null;
  fps?: number | null;
  message: string;
  error_detail?: string | null;
  /** URL RTSP yang benar-benar berhasil terhubung.
   *  Gunakan ini untuk mengisi form agar pengguna mendapat URL yang tepat. */
  working_rtsp_uri?: string | null;
  checked_at: string;
}

