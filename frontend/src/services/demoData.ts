import {
  DashboardSummary,
  HourlyTrafficItem,
  DailyTrafficItem,
  PeakHourItem,
  PeriodComparisonItem,
  CountingEvent,
  Camera,
  CountingSession,
  SystemStatus,
  SystemLog,
  DiscoveredCCTVDevice
} from '../types/api';

export const DEMO_CAMERAS: Camera[] = [
  {
    id: "c0000000-0000-0000-0000-000000000001",
    name: "CCTV Pintu Masuk Utama (IP Camera Live)",
    location: "Pintu Depan Area Kasir & Dining",
    source_type: "RTSP",
    source_uri_masked: "rtsp://admin:***@192.168.1.100:554/live/ch0",
    resolution_width: 1920,
    resolution_height: 1080,
    fps: 25,
    line_coords: {
      x1: 150,
      y1: 380,
      x2: 1130,
      y2: 380,
      direction_in: "down",
      offset: 35
    },
    is_active: true,
    connection_status: "ONLINE",
    processing_status: "RUNNING",
    last_connected_at: new Date(Date.now() - 300000).toISOString(),
    last_frame_at: new Date(Date.now() - 5000).toISOString(),
    notes: "Kamera IP live utama area pintu depan dan kasir",
    created_at: new Date(Date.now() - 7 * 86400000).toISOString(),
    updated_at: new Date().toISOString()
  },
  {
    id: "c0000000-0000-0000-0000-000000000002",
    name: "Kamera Laptop / USB Webcam Uji Coba",
    location: "Meja Kasir & Laptop Penguji",
    source_type: "USB_WEBCAM",
    source_uri_masked: "Kamera Perangkat #0 (DirectShow Live)",
    resolution_width: 640,
    resolution_height: 480,
    fps: 30,
    line_coords: {
      x1: 50,
      y1: 240,
      x2: 590,
      y2: 240,
      direction_in: "down",
      offset: 25
    },
    is_active: true,
    connection_status: "ONLINE",
    processing_status: "STOPPED",
    last_connected_at: new Date(Date.now() - 600000).toISOString(),
    last_frame_at: new Date(Date.now() - 60000).toISOString(),
    notes: "Webcam bawaan laptop untuk pengujian real-time",
    created_at: new Date(Date.now() - 2 * 86400000).toISOString(),
    updated_at: new Date().toISOString()
  },
  {
    id: "c0000000-0000-0000-0000-000000000003",
    name: "CCTV NVR Channel 2 (Area Dining)",
    location: "Tangga Masuk Dining Atas",
    source_type: "NVR_CHANNEL",
    source_uri_masked: "rtsp://operator:***@192.168.1.50:554/Streaming/Channels/201",
    resolution_width: 1920,
    resolution_height: 1080,
    fps: 30,
    line_coords: {
      x1: 200,
      y1: 540,
      x2: 1720,
      y2: 540,
      direction_in: "down",
      offset: 40
    },
    is_active: false,
    connection_status: "DISCONNECTED",
    processing_status: "STOPPED",
    last_connected_at: null,
    last_frame_at: null,
    notes: "Stream channel NVR Hikvision/Dahua lantai 2",
    created_at: new Date(Date.now() - 5 * 86400000).toISOString(),
    updated_at: new Date().toISOString()
  }
];

export const DEMO_SUMMARY: DashboardSummary = {
  total_today: 482,
  total_today_in: 254,
  total_today_out: 228,
  total_week: 3120,
  average_daily: 445.7,
  busiest_hour: "18:00 - 19:00",
  busiest_hour_count: 86,
  active_cameras: 1,
  worker_status: "RUNNING",
  source_mode: "DEMO",
  trend_today_vs_yesterday: 12.4,
  trend_week_vs_last_week: 8.1
};

export const DEMO_HOURLY: HourlyTrafficItem[] = [
  { hour: "10:00", count_in: 12, count_out: 4, total: 16 },
  { hour: "11:00", count_in: 28, count_out: 14, total: 42 },
  { hour: "12:00", count_in: 68, count_out: 42, total: 110 },
  { hour: "13:00", count_in: 54, count_out: 62, total: 116 },
  { hour: "14:00", count_in: 18, count_out: 28, total: 46 },
  { hour: "15:00", count_in: 14, count_out: 16, total: 30 },
  { hour: "16:00", count_in: 19, count_out: 15, total: 34 },
  { hour: "17:00", count_in: 36, count_out: 22, total: 58 },
  { hour: "18:00", count_in: 86, count_out: 48, total: 134 },
  { hour: "19:00", count_in: 78, count_out: 74, total: 152 },
  { hour: "20:00", count_in: 42, count_out: 58, total: 100 },
  { hour: "21:00", count_in: 16, count_out: 32, total: 48 },
];

export const DEMO_DAILY: DailyTrafficItem[] = [
  { date: "2026-10-07", day_name: "Rabu", count_in: 210, count_out: 204, total: 414 },
  { date: "2026-10-08", day_name: "Kamis", count_in: 224, count_out: 218, total: 442 },
  { date: "2026-10-09", day_name: "Jumat", count_in: 298, count_out: 286, total: 584 },
  { date: "2026-10-10", day_name: "Sabtu", count_in: 380, count_out: 372, total: 752 },
  { date: "2026-10-11", day_name: "Minggu", count_in: 412, count_out: 405, total: 817 },
  { date: "2026-10-12", day_name: "Senin", count_in: 192, count_out: 188, total: 380 },
  { date: "2026-10-13", day_name: "Selasa", count_in: 254, count_out: 228, total: 482 },
];

export const DEMO_PEAK_HOURS: PeakHourItem[] = [
  { time_window: "18:00 - 19:00 (Makan Malam)", count: 134, occupancy_status: "PADAT", percentage_of_daily: 27.8 },
  { time_window: "19:00 - 20:00 (Makan Malam)", count: 152, occupancy_status: "PADAT", percentage_of_daily: 31.5 },
  { time_window: "12:00 - 13:00 (Makan Siang)", count: 110, occupancy_status: "PADAT", percentage_of_daily: 22.8 },
  { time_window: "13:00 - 14:00 (Makan Siang)", count: 116, occupancy_status: "PADAT", percentage_of_daily: 24.1 },
  { time_window: "15:00 - 16:00 (Sore Hari)", count: 30, occupancy_status: "LENGANG", percentage_of_daily: 6.2 },
];

export const DEMO_COMPARISON: PeriodComparisonItem[] = [
  { period_label: "Senin", current_period_count: 380, previous_period_count: 360, change_percentage: 5.5 },
  { period_label: "Selasa", current_period_count: 482, previous_period_count: 430, change_percentage: 12.1 },
  { period_label: "Rabu", current_period_count: 414, previous_period_count: 402, change_percentage: 3.0 },
  { period_label: "Kamis", current_period_count: 442, previous_period_count: 415, change_percentage: 6.5 },
  { period_label: "Jumat", current_period_count: 584, previous_period_count: 520, change_percentage: 12.3 },
  { period_label: "Sabtu (Weekend)", current_period_count: 752, previous_period_count: 680, change_percentage: 10.6 },
  { period_label: "Minggu (Weekend)", current_period_count: 817, previous_period_count: 745, change_percentage: 9.7 },
];

export const generateDemoEvents = (count: number = 40): CountingEvent[] => {
  const events: CountingEvent[] = [];
  const now = Date.now();

  for (let i = 0; i < count; i++) {
    const timeOffset = i * 4.5 * 60 * 1000; // minutes ago
    const dir = (i % 2 === 0 || i % 5 === 0) ? "IN" : "OUT";
    events.push({
      id: `evt-${1000 + i}`,
      camera_id: "c0000000-0000-0000-0000-000000000001",
      camera_name: "CCTV Pintu Masuk Utama (Ayam Bang Jungko)",
      session_id: "e0000000-0000-0000-0000-000000000001",
      event_timestamp: new Date(now - timeOffset).toISOString(),
      direction: dir,
      count_value: 1,
      track_id: 101 + i,
      confidence: Number((0.87 + (i % 12) * 0.01).toFixed(2)),
      source_mode: "DEMO",
      metadata_json: {
        detector: "YOLOv8n",
        tracker: "ByteTrack",
        bounding_box_w: 120,
        bounding_box_h: 280
      },
      created_at: new Date(now - timeOffset).toISOString()
    });
  }
  return events;
};

export const DEMO_EVENTS = generateDemoEvents(40);

export const DEMO_SESSIONS: CountingSession[] = [
  {
    id: "e0000000-0000-0000-0000-000000000001",
    camera_id: "c0000000-0000-0000-0000-000000000001",
    camera_name: "CCTV Pintu Masuk Utama (Ayam Bang Jungko)",
    mode: "DEMO",
    status: "RUNNING",
    started_at: new Date(Date.now() - 4 * 3600000).toISOString(),
    ended_at: null,
    total_in: 254,
    total_out: 228,
    notes: "Sesi operasional demo shift siang & malam berjalan"
  },
  {
    id: "e0000000-0000-0000-0000-000000000002",
    camera_id: "c0000000-0000-0000-0000-000000000001",
    camera_name: "CCTV Pintu Masuk Utama (IP Camera Live)",
    mode: "LIVE_CCTV",
    status: "COMPLETED",
    started_at: new Date(Date.now() - 28 * 3600000).toISOString(),
    ended_at: new Date(Date.now() - 25 * 3600000).toISOString(),
    total_in: 185,
    total_out: 179,
    notes: "Pengujian live stream RTSP shift pagi"
  }
];

export const DEMO_SYSTEM_STATUS: SystemStatus = {
  backend_status: "HEALTHY",
  database_connected: true,
  cv_worker: {
    status: "RUNNING",
    current_mode: "DEMO",
    active_stream: "CCTV Pintu Masuk Utama (Ayam Bang Jungko)",
    processed_fps: 24.8,
    last_frame_processed_at: new Date().toISOString()
  },
  last_event_recorded_at: new Date(Date.now() - 2 * 60000).toISOString(),
  server_time_wib: new Date().toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' }) + " WIB",
  active_sessions_count: 1
};

export const DEMO_LOGS: SystemLog[] = [
  {
    id: "log-1",
    module: "VISION_WORKER",
    level: "INFO",
    message: "Pipeline ByteTrack aktif melacak person crossing pada garis virtual Y=380",
    details: { fps: 24.8, active_tracks: 3 },
    created_at: new Date(Date.now() - 1 * 60000).toISOString()
  },
  {
    id: "log-2",
    module: "API",
    level: "INFO",
    message: "Permintaan statistik dashboard /api/v1/dashboard/summary diproses sukses",
    details: { response_time_ms: 12 },
    created_at: new Date(Date.now() - 3 * 60000).toISOString()
  },
  {
    id: "log-3",
    module: "DATABASE",
    level: "INFO",
    message: "Sinkronisasi batch counting_events (40 baris) selesai",
    details: { latency_ms: 4.2 },
    created_at: new Date(Date.now() - 7 * 60000).toISOString()
  }
];

export const DEMO_DISCOVERED_DEVICES: DiscoveredCCTVDevice[] = [
  {
    device_id: "urn:uuid:550e8400-e29b-41d4-a716-446655440001",
    name: "Hikvision DS-2CD2043G0-I (Pintu Masuk)",
    ip_address: "192.168.1.64",
    port: 80,
    onvif_xaddr: "http://192.168.1.64:80/onvif/device_service",
    manufacturer: "Hikvision",
    model: "DS-2CD2043G0-I",
    location: "Pintu Masuk Utama Restoran",
    types: ["dn:NetworkVideoTransmitter", "tds:Device"],
    scopes: [
      "onvif://www.onvif.org/type/video_encoder",
      "onvif://www.onvif.org/name/Hikvision_Camera_Entrance",
      "onvif://www.onvif.org/hardware/DS-2CD2043G0-I",
      "onvif://www.onvif.org/location/Main_Entrance"
    ],
    suggested_rtsp_uri: "rtsp://192.168.1.64:554/Streaming/Channels/101",
    capabilities: { profiles: ["ProfileS", "ProfileT"], audio: false, ptz: false }
  },
  {
    device_id: "urn:uuid:550e8400-e29b-41d4-a716-446655440002",
    name: "Dahua IPC-HFW2431S (Area Kasir & Antrean)",
    ip_address: "192.168.1.68",
    port: 80,
    onvif_xaddr: "http://192.168.1.68:80/onvif/device_service",
    manufacturer: "Dahua",
    model: "IPC-HFW2431S",
    location: "Kasir & Display Antrean",
    types: ["dn:NetworkVideoTransmitter"],
    scopes: [
      "onvif://www.onvif.org/type/video_encoder",
      "onvif://www.onvif.org/name/Dahua_Cashier_Cam",
      "onvif://www.onvif.org/hardware/IPC-HFW2431S"
    ],
    suggested_rtsp_uri: "rtsp://192.168.1.68:554/cam/realmonitor?channel=1&subtype=0",
    capabilities: { profiles: ["ProfileS"], audio: true, ptz: false }
  }
];
