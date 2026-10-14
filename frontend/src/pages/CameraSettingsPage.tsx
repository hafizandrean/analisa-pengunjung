import React, { useState, useEffect } from 'react';
import {
  Camera as CameraIcon,
  Video,
  Upload,
  Save,
  CheckCircle2,
  AlertCircle,
  Sliders,
  ShieldCheck,
  Play,
  Square,
  Plus,
  Trash2,
  Edit2,
  RefreshCw,
  Activity,
  Wifi,
  WifiOff,
  Server,
  Layers,
  Info,
  X,
  Eye,
  EyeOff,
  Laptop,
  Search,
  Check
} from 'lucide-react';
import { api } from '../services/api';
import {
  Camera,
  VirtualLineCoords,
  CountingSession,
  CameraCreatePayload,
  CameraUpdatePayload,
  DetectedWebcam,
  DiscoveredCCTVDevice,
  DeviceValidationResponse
} from '../types/api';
import { VirtualLineCanvas } from '../components/VirtualLineCanvas';
import { formatIndonesianDateTime } from '../utils/formatters';

export const CameraSettingsPage: React.FC = () => {
  const [cameras, setCameras] = useState<Camera[]>([]);
  const [selectedCamera, setSelectedCamera] = useState<Camera | null>(null);
  const [sessions, setSessions] = useState<CountingSession[]>([]);
  const [lineCoords, setLineCoords] = useState<VirtualLineCoords>({
    x1: 150,
    y1: 380,
    x2: 1130,
    y2: 380,
    direction_in: 'down',
    offset: 35
  });

  // UI States
  const [isLoading, setIsLoading] = useState(true);
  const [savedSuccess, setSavedSuccess] = useState(false);
  const [feedbackMessage, setFeedbackMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [testingCameraId, setTestingCameraId] = useState<string | null>(null);
  const [actionProcessingId, setActionProcessingId] = useState<string | null>(null);

  // Modal State for Add / Edit Camera
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCamera, setEditingCamera] = useState<Camera | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  // Delete Modal State
  const [cameraToDelete, setCameraToDelete] = useState<Camera | null>(null);

  // Webcam scanning
  const [detectedWebcams, setDetectedWebcams] = useState<DetectedWebcam[]>([]);
  const [isScanningWebcams, setIsScanningWebcams] = useState(false);

  // Tab Mode for Add Modal
  const [modalTab, setModalTab] = useState<'DISCOVERY' | 'MANUAL_RTSP' | 'WEBCAM' | 'NVR'>('DISCOVERY');

  // Network ONVIF Discovery States
  const [isScanningNetwork, setIsScanningNetwork] = useState(false);
  const [discoverySimulate, setDiscoverySimulate] = useState(false);
  const [discoveredDevices, setDiscoveredDevices] = useState<DiscoveredCCTVDevice[]>([]);
  const [selectedDiscoveredDevice, setSelectedDiscoveredDevice] = useState<DiscoveredCCTVDevice | null>(null);
  const [isValidatingDevice, setIsValidatingDevice] = useState(false);
  const [deviceValidationResult, setDeviceValidationResult] = useState<DeviceValidationResponse | null>(null);

  // Form State
  const [formData, setFormData] = useState<{
    name: string;
    location: string;
    source_type: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
    source_uri: string;
    username: string;
    password: string;
    resolution_width: number;
    resolution_height: number;
    fps: number;
    is_active: boolean;
    notes: string;
  }>({
    name: '',
    location: '',
    source_type: 'RTSP',
    source_uri: '',
    username: '',
    password: '',
    resolution_width: 1280,
    resolution_height: 720,
    fps: 25,
    is_active: true,
    notes: ''
  });

  const showToast = (type: 'success' | 'error', text: string) => {
    setFeedbackMessage({ type, text });
    setTimeout(() => setFeedbackMessage(null), 4000);
  };

  const handleScanWebcams = async () => {
    setIsScanningWebcams(true);
    try {
      const devices = await api.cameras.detectWebcams();
      setDetectedWebcams(devices);
      if (devices.length > 0) {
        showToast('success', `Ditemukan ${devices.length} perangkat kamera live.`);
        if (!formData.source_uri) {
          setFormData((prev) => ({
            ...prev,
            source_uri: devices[0].index.toString()
          }));
        }
      } else {
        showToast('error', 'Tidak ada perangkat webcam terdeteksi.');
      }
    } catch {
      showToast('error', 'Gagal memindai perangkat webcam.');
    } finally {
      setIsScanningWebcams(false);
    }
  };

  const loadCameraData = async () => {
    setIsLoading(true);
    try {
      const [camsRes, sessRes] = await Promise.all([
        api.cameras.getList(),
        api.sessions.getList()
      ]);
      setCameras(camsRes);
      if (camsRes.length > 0) {
        // If current selected camera is still in list, keep it; otherwise select first
        const currentId = selectedCamera?.id;
        const matched = camsRes.find((c) => c.id === currentId);
        if (matched) {
          setSelectedCamera(matched);
          setLineCoords(matched.line_coords);
        } else {
          setSelectedCamera(camsRes[0]);
          setLineCoords(camsRes[0].line_coords);
        }
      } else {
        setSelectedCamera(null);
      }
      setSessions(sessRes);
    } catch (err) {
      console.error('Gagal mengambil data kamera:', err);
      showToast('error', 'Gagal memuat data kamera dari server.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadCameraData();
  }, []);

  const handleSelectCamera = (cam: Camera) => {
    setSelectedCamera(cam);
    setLineCoords(cam.line_coords);
    setSavedSuccess(false);
  };

  const handleQuickSelectWebcam = async (deviceUri = '0', deviceName = 'Kamera Laptop / USB Webcam Uji Coba') => {
    // 1. Check if webcam camera already exists in loaded cameras
    const existing = cameras.find(
      (c) => c.source_type === 'USB_WEBCAM' || c.source_uri_masked?.includes('Webcam') || c.source_uri_masked?.includes(deviceUri)
    );
    if (existing) {
      handleSelectCamera(existing);
      document.getElementById('canvas-section')?.scrollIntoView({ behavior: 'smooth' });
      showToast('success', `Kamera '${existing.name}' dipilih! Menampilkan feed video live.`);
      return;
    }

    // 2. If not registered yet, create it automatically so user has instant live access
    try {
      const newCam = await api.cameras.create({
        name: deviceName,
        location: 'Meja Kasir & Laptop Penguji',
        source_type: 'USB_WEBCAM',
        source_uri: deviceUri,
        resolution_width: 640,
        resolution_height: 480,
        fps: 30,
        line_coords: {
          x1: 50,
          y1: 240,
          x2: 590,
          y2: 240,
          direction_in: 'down',
          offset: 25
        },
        is_active: true,
        notes: 'Webcam laptop untuk verifikasi deteksi real-time'
      });
      await loadCameraData();
      handleSelectCamera(newCam);
      document.getElementById('canvas-section')?.scrollIntoView({ behavior: 'smooth' });
      showToast('success', `Kamera '${newCam.name}' berhasil ditambahkan dan dipilih!`);
    } catch (err: any) {
      console.error('Error creating webcam camera:', err);
      showToast('error', 'Gagal mendaftarkan webcam: ' + (err.message || 'Error'));
    }
  };

  const handleScanNetwork = async (useSimulation = discoverySimulate) => {
    setIsScanningNetwork(true);
    setSelectedDiscoveredDevice(null);
    setDeviceValidationResult(null);
    try {
      const res = await api.cameras.discoverNetwork(3.0, useSimulation);
      setDiscoveredDevices(res.devices);
      if (res.devices.length > 0) {
        showToast('success', res.message);
      } else {
        showToast('error', res.message);
      }
    } catch (err: any) {
      console.error('Scan network error:', err);
      showToast('error', 'Gagal memindai jaringan: ' + (err.message || 'Error'));
    } finally {
      setIsScanningNetwork(false);
    }
  };

  const handleSelectDiscoveredDevice = (dev: DiscoveredCCTVDevice) => {
    setSelectedDiscoveredDevice(dev);
    setDeviceValidationResult(null);
    setFormData((prev) => ({
      ...prev,
      name: dev.name,
      location: dev.location || 'Area Restoran',
      source_type: 'RTSP',
      source_uri: dev.suggested_rtsp_uri,
      username: 'admin',
      password: '',
      notes: `ONVIF Discovery: ${dev.manufacturer || 'IP Camera'} (${dev.ip_address})`
    }));
  };

  const handleValidateDiscoveredDevice = async () => {
    if (!formData.source_uri && !selectedDiscoveredDevice?.onvif_xaddr) return;
    setIsValidatingDevice(true);
    setDeviceValidationResult(null);
    try {
      const targetIp =
        selectedDiscoveredDevice?.ip_address ||
        formData.source_uri.split('://')[1]?.split(/[:/@]/)[0] ||
        '127.0.0.1';

      const res = await api.cameras.validateDevice({
        ip_address: targetIp,
        port: selectedDiscoveredDevice?.port || 554,
        source_type: formData.source_type,
        rtsp_uri: formData.source_uri || `rtsp://${targetIp}:554/live/ch0`,
        username: formData.username || undefined,
        password: formData.password || undefined,
        timeout_seconds: 8.0,
        // Kirim ONVIF xaddr agar backend bisa memanggil GetStreamUri SOAP
        onvif_xaddr: selectedDiscoveredDevice?.onvif_xaddr || undefined,
        manufacturer: selectedDiscoveredDevice?.manufacturer || undefined,
      });

      setDeviceValidationResult(res);

      if (res.is_accessible) {
        // Auto-fill form dengan URL RTSP yang benar-benar berhasil
        if (res.working_rtsp_uri && res.working_rtsp_uri !== formData.source_uri) {
          setFormData((prev) => ({ ...prev, source_uri: res.working_rtsp_uri! }));
          showToast(
            'success',
            `${res.message}\nURL RTSP telah diperbarui otomatis: ${res.working_rtsp_uri}`
          );
        } else {
          showToast('success', res.message);
        }
        // Auto-fill resolusi dari hasil validasi
        if (res.resolution_width && res.resolution_height) {
          setFormData((prev) => ({
            ...prev,
            resolution_width: res.resolution_width!,
            resolution_height: res.resolution_height!,
            fps: res.fps || prev.fps,
          }));
        }
      } else {
        showToast('error', res.message);
      }
    } catch (err: any) {
      console.error('Validation error:', err);
      showToast('error', 'Gagal menguji koneksi: ' + (err.message || 'Error'));
    } finally {
      setIsValidatingDevice(false);
    }
  };

  const handleOpenDiscoveryModal = () => {
    setEditingCamera(null);
    setModalTab('DISCOVERY');
    setSelectedDiscoveredDevice(null);
    setDeviceValidationResult(null);
    setFormData({
      name: '',
      location: '',
      source_type: 'RTSP',
      source_uri: '',
      username: '',
      password: '',
      resolution_width: 1280,
      resolution_height: 720,
      fps: 25,
      is_active: true,
      notes: ''
    });
    setIsModalOpen(true);
    if (discoveredDevices.length === 0) {
      handleScanNetwork(false);
    }
  };

  // Open Modal for Add
  const handleOpenAddModal = () => {
    setEditingCamera(null);
    setModalTab('MANUAL_RTSP');
    setSelectedDiscoveredDevice(null);
    setDeviceValidationResult(null);
    setFormData({
      name: '',
      location: '',
      source_type: 'RTSP',
      source_uri: '',
      username: '',
      password: '',
      resolution_width: 1280,
      resolution_height: 720,
      fps: 25,
      is_active: true,
      notes: ''
    });
    setShowPassword(false);
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (cam: Camera, e: React.MouseEvent) => {
    e.stopPropagation();
    setEditingCamera(cam);
    setModalTab(
      cam.source_type === 'USB_WEBCAM'
        ? 'WEBCAM'
        : cam.source_type === 'NVR_CHANNEL'
        ? 'NVR'
        : 'MANUAL_RTSP'
    );
    setSelectedDiscoveredDevice(null);
    setDeviceValidationResult(null);
    setFormData({
      name: cam.name,
      location: cam.location,
      source_type: (cam.source_type as any) || 'RTSP',
      source_uri: cam.source_uri_masked || '',
      username: cam.username || '',
      password: '', // Never populate existing password plaintext
      resolution_width: cam.resolution_width || 1280,
      resolution_height: cam.resolution_height || 720,
      fps: cam.fps || 25,
      is_active: cam.is_active,
      notes: cam.notes || ''
    });
    setShowPassword(false);
    setIsModalOpen(true);
  };

  // Handle Form Submission
  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.location.trim()) {
      alert('Nama kamera dan lokasi wajib diisi.');
      return;
    }
    if (!formData.source_uri.trim()) {
      alert('Alamat/identifier sumber koneksi wajib diisi.');
      return;
    }

    try {
      if (editingCamera) {
        // Edit
        const payload: CameraUpdatePayload = {
          name: formData.name,
          location: formData.location,
          source_type: formData.source_type,
          source_uri: formData.source_uri.includes('***') ? undefined : formData.source_uri,
          username: formData.username || undefined,
          password: formData.password ? formData.password : undefined,
          resolution_width: formData.resolution_width,
          resolution_height: formData.resolution_height,
          fps: formData.fps,
          is_active: formData.is_active,
          notes: formData.notes
        };
        const updated = await api.cameras.update(editingCamera.id, payload);
        showToast('success', `Konfigurasi kamera '${updated.name}' berhasil diperbarui.`);
      } else {
        // Create
        const defaultCoords: VirtualLineCoords = {
          x1: Math.round(formData.resolution_width * 0.1),
          y1: Math.round(formData.resolution_height * 0.5),
          x2: Math.round(formData.resolution_width * 0.9),
          y2: Math.round(formData.resolution_height * 0.5),
          direction_in: 'down',
          offset: 30
        };

        const payload: CameraCreatePayload = {
          name: formData.name,
          location: formData.location,
          source_type: formData.source_type,
          source_uri: formData.source_uri,
          username: formData.username || undefined,
          password: formData.password || undefined,
          resolution_width: formData.resolution_width,
          resolution_height: formData.resolution_height,
          fps: formData.fps,
          line_coords: defaultCoords,
          is_active: formData.is_active,
          notes: formData.notes
        };
        const created = await api.cameras.create(payload);
        showToast('success', `Kamera '${created.name}' berhasil ditambahkan ke sistem.`);
      }
      setIsModalOpen(false);
      await loadCameraData();
    } catch (err: any) {
      console.error('Error saving camera:', err);
      alert(err.response?.data?.detail || err.message || 'Gagal menyimpan kamera.');
    }
  };

  // Delete Camera
  const handleDeleteCamera = async () => {
    if (!cameraToDelete) return;
    try {
      await api.cameras.delete(cameraToDelete.id);
      showToast('success', `Kamera '${cameraToDelete.name}' berhasil dihapus.`);
      setCameraToDelete(null);
      await loadCameraData();
    } catch (err: any) {
      alert(err.response?.data?.detail || err.message || 'Gagal menghapus kamera.');
    }
  };

  // Test Camera Connection
  const handleTestConnection = async (cam: Camera, e: React.MouseEvent) => {
    e.stopPropagation();
    setTestingCameraId(cam.id);
    try {
      const result = await api.cameras.testConnection(cam.id);
      if (result.is_accessible) {
        showToast('success', `[ONLINE] ${result.message}`);
      } else {
        showToast('error', `[OFFLINE] ${result.message}`);
      }
      await loadCameraData();
    } catch (err: any) {
      showToast('error', 'Gagal melakukan uji koneksi kamera.');
    } finally {
      setTestingCameraId(null);
    }
  };

  // Toggle AI Processing (Start / Stop)
  const handleToggleProcessing = async (cam: Camera, e: React.MouseEvent) => {
    e.stopPropagation();
    setActionProcessingId(cam.id);
    try {
      if (cam.processing_status === 'RUNNING') {
        const res = await api.cameras.stopProcessing(cam.id);
        showToast('success', res.message);
      } else {
        const res = await api.cameras.startProcessing(cam.id);
        showToast('success', res.message);
      }
      await loadCameraData();
    } catch (err: any) {
      alert(err.response?.data?.detail || err.message || 'Gagal mengubah status pemrosesan.');
    } finally {
      setActionProcessingId(null);
    }
  };

  // Save Virtual Line Coordinates for Selected Camera
  const handleSaveCoords = async () => {
    if (!selectedCamera) return;
    try {
      await api.cameras.update(selectedCamera.id, {
        line_coords: lineCoords
      });
      setSavedSuccess(true);
      showToast('success', `Koordinat garis virtual untuk '${selectedCamera.name}' berhasil disimpan!`);
      setTimeout(() => setSavedSuccess(false), 3000);
      await loadCameraData();
    } catch (err) {
      alert('Gagal menyimpan koordinat garis.');
    }
  };

  // Helper for status badge styling
  const getConnectionBadge = (status: string) => {
    switch (status) {
      case 'ONLINE':
        return (
          <span className="flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            ONLINE
          </span>
        );
      case 'CONNECTING':
        return (
          <span className="flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-semibold text-blue-800">
            <RefreshCw className="h-3 w-3 animate-spin text-blue-600" />
            CONNECTING
          </span>
        );
      case 'ERROR':
        return (
          <span className="flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-800">
            <AlertCircle className="h-3 w-3 text-rose-600" />
            ERROR
          </span>
        );
      default:
        return (
          <span className="flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">
            <WifiOff className="h-3 w-3 text-slate-400" />
            OFFLINE
          </span>
        );
    }
  };

  const getProcessingBadge = (status: string) => {
    if (status === 'RUNNING') {
      return (
        <span className="flex items-center gap-1.5 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800">
          <Activity className="h-3 w-3 text-indigo-600 animate-pulse" />
          AI RUNNING
        </span>
      );
    }
    if (status === 'STARTING') {
      return (
        <span className="flex items-center gap-1.5 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-800">
          <RefreshCw className="h-3 w-3 text-blue-600 animate-spin" />
          STARTING
        </span>
      );
    }
    return (
      <span className="flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
        STOPPED
      </span>
    );
  };

  return (
    <div className="space-y-6">
      {/* Toast Notification Banner */}
      {feedbackMessage && (
        <div
          className={`flex items-center justify-between rounded-xl px-4 py-3 text-xs font-semibold shadow-sm transition ${
            feedbackMessage.type === 'success'
              ? 'border border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border border-rose-200 bg-rose-50 text-rose-800'
          }`}
        >
          <div className="flex items-center gap-2">
            {feedbackMessage.type === 'success' ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            ) : (
              <AlertCircle className="h-4 w-4 text-rose-600" />
            )}
            <span>{feedbackMessage.text}</span>
          </div>
          <button
            onClick={() => setFeedbackMessage(null)}
            className="text-slate-400 hover:text-slate-600"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Top Header Bar: Actions & Summary */}
      <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
            <CameraIcon className="h-5 w-5 text-blue-600" />
            Manajemen Sumber CCTV & Garis Virtual
          </h2>
          <p className="text-xs text-slate-500 mt-0.5">
            Kelola kamera IP RTSP, USB Webcam, atau berkas video rekaman serta atur koordinat garis crossing.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <button
            onClick={loadCameraData}
            disabled={isLoading}
            className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition shadow-sm"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
            <span>Segarkan</span>
          </button>

          <button
            onClick={handleOpenDiscoveryModal}
            className="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold text-blue-700 shadow-xs hover:bg-blue-100 transition"
          >
            <Search className="h-4 w-4 text-blue-600" />
            <span>Cari CCTV di Jaringan</span>
          </button>

          <button
            onClick={handleOpenAddModal}
            className="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah CCTV</span>
          </button>
        </div>
      </div>

      {/* Cameras Grid / Dynamic List */}
      <div>
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <Layers className="h-3.5 w-3.5 text-slate-400" />
            Daftar Sumber Kamera Terdaftar ({cameras.length})
          </h3>
          <span className="text-[11px] text-slate-400">
            Klik kartu kamera untuk memilih konfigurasi garis virtual
          </span>
        </div>

        {cameras.length === 0 ? (
          <div className="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 mb-3">
              <CameraIcon className="h-7 w-7" />
            </div>
            <h4 className="text-sm font-bold text-slate-800">Belum Ada Kamera Terdaftar</h4>
            <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
              Daftarkan kamera CCTV RTSP restoran, hubungkan USB Webcam, atau gunakan berkas rekaman video untuk mulai menghitung pengunjung.
            </p>
            <button
              onClick={handleOpenAddModal}
              className="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition"
            >
              <Plus className="h-4 w-4" />
              <span>Tambah CCTV Pertama</span>
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {cameras.map((cam) => {
              const isSelected = selectedCamera?.id === cam.id;
              const isTesting = testingCameraId === cam.id;
              const isProcessingAction = actionProcessingId === cam.id;

              return (
                <div
                  key={cam.id}
                  onClick={() => {
                    handleSelectCamera(cam);
                    document.getElementById('canvas-section')?.scrollIntoView({ behavior: 'smooth' });
                  }}
                  className={`relative cursor-pointer rounded-2xl border p-5 transition shadow-sm ${
                    isSelected
                      ? 'border-blue-500 bg-blue-50/40 ring-2 ring-blue-500/20'
                      : 'border-slate-200 bg-white hover:border-slate-300'
                  }`}
                >
                  {/* Card Header */}
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3">
                      <div
                        className={`flex h-10 w-10 items-center justify-center rounded-xl ${
                          cam.connection_status === 'ONLINE'
                            ? 'bg-emerald-50 text-emerald-600'
                            : 'bg-slate-100 text-slate-400'
                        }`}
                      >
                        <CameraIcon className="h-5 w-5" />
                      </div>
                      <div>
                        <h4 className="text-sm font-bold text-slate-900 leading-tight">{cam.name}</h4>
                        <p className="text-xs text-slate-500">{cam.location}</p>
                      </div>
                    </div>
                    {getConnectionBadge(cam.connection_status)}
                  </div>

                  {/* Badges & Source Type */}
                  <div className="mt-3 flex flex-wrap items-center gap-2">
                    {isSelected && (
                      <span className="rounded-md bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                        Terpilih di Kanvas
                      </span>
                    )}
                    <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 uppercase">
                      {cam.source_type}
                    </span>
                    {getProcessingBadge(cam.processing_status)}
                    <span className="text-[11px] text-slate-400 font-mono">
                      {cam.resolution_width}x{cam.resolution_height} @ {cam.fps}fps
                    </span>
                  </div>

                  {/* Masked Source URI */}
                  <div className="mt-3 rounded-lg bg-slate-50 p-2.5 text-[11px] font-mono text-slate-600 border border-slate-100 truncate">
                    <span className="text-slate-400 block text-[9px] uppercase font-sans font-semibold">
                      Endpoint Stream (Masked):
                    </span>
                    {cam.source_uri_masked}
                  </div>

                  {/* Last connection timestamp or error */}
                  <div className="mt-2 text-[11px] text-slate-400">
                    {cam.connection_error ? (
                      <span className="text-rose-500 flex items-center gap-1 line-clamp-1">
                        <AlertCircle className="h-3 w-3 shrink-0" /> {cam.connection_error}
                      </span>
                    ) : cam.last_connected_at ? (
                      <span>Koneksi terakhir: {formatIndonesianDateTime(cam.last_connected_at)}</span>
                    ) : (
                      <span>Belum pernah tersambung</span>
                    )}
                  </div>

                  {/* Card Actions Footer */}
                  <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-1 text-xs">
                    <div className="flex items-center gap-1.5">
                      {/* Test Connection Button */}
                      <button
                        onClick={(e) => handleTestConnection(cam, e)}
                        disabled={isTesting}
                        title="Uji keterjangkauan sumber CCTV"
                        className="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                      >
                        <RefreshCw className={`h-3 w-3 ${isTesting ? 'animate-spin text-blue-600' : ''}`} />
                        <span>{isTesting ? 'Menguji...' : 'Uji Koneksi'}</span>
                      </button>

                      {/* Start / Stop AI Processing Button */}
                      <div className="flex flex-col items-center">
                        <button
                          onClick={(e) => handleToggleProcessing(cam, e)}
                          disabled={isProcessingAction || !cam.is_active}
                          title={cam.processing_status === 'RUNNING' ? 'Hentikan AI' : 'Mulai AI'}
                          className={`flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition ${
                            cam.processing_status === 'RUNNING'
                              ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200'
                              : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                          } disabled:opacity-50`}
                        >
                          {cam.processing_status === 'RUNNING' ? (
                            <>
                              <Square className="h-3 w-3" />
                              <span>Stop AI</span>
                            </>
                          ) : (
                            <>
                              <Play className="h-3 w-3" />
                              <span>Mulai AI</span>
                            </>
                          )}
                        </button>
                        <p className="text-[10px] text-slate-400 text-center mt-0.5">
                          {cam.processing_status === 'RUNNING'
                            ? 'AI berjalan otomatis'
                            : 'AI akan auto-start saat server dimulai'}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-1">
                      {/* Edit Button */}
                      <button
                        onClick={(e) => handleOpenEditModal(cam, e)}
                        title="Edit konfigurasi kamera"
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                      >
                        <Edit2 className="h-3.5 w-3.5" />
                      </button>

                      {/* Delete Button */}
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          setCameraToDelete(cam);
                        }}
                        title="Hapus kamera"
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Virtual Line Coordinates Configuration Section */}
      {selectedCamera && (
        <div id="canvas-section" className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm scroll-mt-6">
          <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
            <div>
              <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <Sliders className="h-5 w-5 text-blue-600" />
                Konfigurasi Garis Virtual: {selectedCamera.name}
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Geser lingkaran titik P1 dan P2 pada kanvas di bawah untuk memosisikan garis hitung pengunjung.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              {/* Quick Camera Switcher Select */}
              <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 shadow-xs">
                <label htmlFor="camera-switcher" className="text-xs font-semibold text-slate-600">
                  Pilih Kamera:
                </label>
                <select
                  id="camera-switcher"
                  value={selectedCamera.id}
                  onChange={(e) => {
                    const cam = cameras.find((c) => c.id === e.target.value);
                    if (cam) handleSelectCamera(cam);
                  }}
                  className="rounded-lg border-0 bg-transparent text-xs font-bold text-slate-800 focus:outline-none cursor-pointer"
                >
                  {cameras.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.source_type})
                    </option>
                  ))}
                </select>
              </div>

              {savedSuccess && (
                <span className="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                  <CheckCircle2 className="h-4 w-4" /> Tersimpan!
                </span>
              )}
              <button
                onClick={handleSaveCoords}
                className="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition"
              >
                <Save className="h-4 w-4" />
                <span>Simpan Koordinat</span>
              </button>
            </div>
          </div>

          {/* Visual Canvas Component */}
          <VirtualLineCanvas
            coords={lineCoords}
            onChange={setLineCoords}
            videoWidth={selectedCamera.resolution_width || 1280}
            videoHeight={selectedCamera.resolution_height || 720}
            previewUrl={selectedCamera ? api.cameras.getPreviewUrl(selectedCamera.id) : undefined}
            isOnline={selectedCamera?.connection_status === 'ONLINE'}
            sourceType={selectedCamera.source_type}
            processingStatus={selectedCamera.processing_status}
          />
        </div>
      )}

      {/* Video Testing & Active Processing Session */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Panduan Integrasi CCTV Live & Uji Coba Cepat Webcam */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
            <Laptop className="h-5 w-5 text-blue-600" />
            <h3 className="text-base font-bold text-slate-900">Uji Coba Kamera Laptop / Webcam</h3>
          </div>

          <p className="mt-3 text-xs text-slate-500 leading-relaxed">
            Sistem mendukung pengujian langsung menggunakan kamera internal laptop atau USB Webcam tanpa berkas rekaman (live camera streams only).
          </p>

          <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4 space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-xs font-bold text-slate-800">Uji Cepat: Webcam Bawaan (#0)</h4>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Langsung pilih dan aktifkan kamera webcam lokal pada kanvas di atas untuk live feed dan uji coba deteksi real-time.
                </p>
              </div>
              <button
                type="button"
                onClick={() => handleQuickSelectWebcam('0', 'Kamera Laptop / USB Webcam Uji Coba')}
                className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition shrink-0"
              >
                <CameraIcon className="h-3.5 w-3.5" />
                <span>Pilih Webcam #0</span>
              </button>
            </div>

            <div className="pt-2 border-t border-blue-100/80 flex items-center justify-between text-[11px] text-slate-600">
              <span className="font-medium">Deteksi Otomatis Perangkat Webcam:</span>
              <button
                type="button"
                onClick={handleScanWebcams}
                disabled={isScanningWebcams}
                className="inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline disabled:opacity-50"
              >
                <RefreshCw className={`h-3 w-3 ${isScanningWebcams ? 'animate-spin' : ''}`} />
                <span>{isScanningWebcams ? 'Memindai...' : 'Pindai Kamera'}</span>
              </button>
            </div>

            {detectedWebcams.length > 0 && (
              <div className="space-y-2 pt-1">
                {detectedWebcams.map((dev) => (
                  <div
                    key={dev.index}
                    onClick={() => handleQuickSelectWebcam(dev.index.toString(), dev.name)}
                    className="flex items-center justify-between rounded-xl bg-white p-3 border border-slate-200 text-xs hover:border-blue-400 hover:bg-blue-50/50 cursor-pointer transition shadow-xs"
                  >
                    <div>
                      <span className="font-bold text-slate-800">{dev.name}</span>
                      <span className="ml-2 font-mono text-[11px] text-slate-400">
                        {dev.resolution} @ {dev.fps}fps
                      </span>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 text-[11px] font-semibold hover:bg-blue-600 hover:text-white transition">
                      Pilih & Tampilkan di Kanvas
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Current Active Counting Sessions */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
            <Play className="h-5 w-5 text-emerald-600" />
            <h3 className="text-base font-bold text-slate-900">Sesi Pemrosesan Aktif</h3>
          </div>

          <div className="mt-4 space-y-3">
            {sessions.length === 0 ? (
              <p className="text-xs text-slate-400 text-center py-6">Belum ada sesi pemrosesan aktif saat ini.</p>
            ) : (
              sessions.slice(0, 3).map((sess) => (
                <div key={sess.id} className="rounded-xl border border-slate-100 bg-slate-50 p-4">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-slate-800">{sess.camera_name || 'Kamera Utama'}</span>
                    <span
                      className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                        sess.status === 'RUNNING'
                          ? 'bg-emerald-100 text-emerald-800'
                          : 'bg-slate-200 text-slate-700'
                      }`}
                    >
                      {sess.status}
                    </span>
                  </div>
                  <div className="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div>
                      Mode: <strong className="text-slate-800">{sess.mode}</strong>
                    </div>
                    <div>
                      Mulai: <strong className="text-slate-800">{formatIndonesianDateTime(sess.started_at)}</strong>
                    </div>
                    <div>
                      Akumulasi Masuk: <strong className="text-emerald-600">+{sess.total_in}</strong>
                    </div>
                    <div>
                      Akumulasi Keluar: <strong className="text-blue-600">+{sess.total_out}</strong>
                    </div>
                  </div>
                  {sess.notes && (
                    <p className="mt-2 text-[11px] text-slate-500 italic">"{sess.notes}"</p>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {/* ========================================================= */}
      {/* MODAL: TAMBAH / EDIT SUMBER CCTV (DENGAN ONVIF WS-DISCOVERY) */}
      {/* ========================================================= */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4">
              <div className="flex items-center gap-2.5">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white">
                  <CameraIcon className="h-4 w-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">
                    {editingCamera ? 'Edit Konfigurasi CCTV' : 'Tambah Sumber CCTV Baru'}
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    {editingCamera
                      ? `Memperbarui parameter kamera: ${editingCamera.name}`
                      : 'Pilih metode penambahan CCTV di bawah (Otomatis ONVIF, Manual RTSP, Webcam, DVR/NVR)'}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* 4 Mode Tabs when adding a new camera */}
            {!editingCamera && (
              <div className="border-b border-slate-200 bg-slate-50/80 px-6 pt-2 flex gap-1.5 overflow-x-auto">
                <button
                  type="button"
                  onClick={() => {
                    setModalTab('DISCOVERY');
                    setFormData((prev) => ({ ...prev, source_type: 'RTSP' }));
                    if (discoveredDevices.length === 0) handleScanNetwork(false);
                  }}
                  className={`flex items-center gap-1.5 border-b-2 px-3.5 py-2 text-xs font-bold transition whitespace-nowrap ${
                    modalTab === 'DISCOVERY'
                      ? 'border-blue-600 text-blue-700 bg-white rounded-t-lg shadow-2xs'
                      : 'border-transparent text-slate-500 hover:text-slate-700'
                  }`}
                >
                  <Search className="h-3.5 w-3.5 text-blue-600" />
                  <span>Cari CCTV di Jaringan</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setModalTab('MANUAL_RTSP');
                    setFormData((prev) => ({ ...prev, source_type: 'RTSP' }));
                  }}
                  className={`flex items-center gap-1.5 border-b-2 px-3.5 py-2 text-xs font-bold transition whitespace-nowrap ${
                    modalTab === 'MANUAL_RTSP'
                      ? 'border-blue-600 text-blue-700 bg-white rounded-t-lg shadow-2xs'
                      : 'border-transparent text-slate-500 hover:text-slate-700'
                  }`}
                >
                  <Server className="h-3.5 w-3.5 text-slate-600" />
                  <span>Tambah IP/RTSP Manual</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setModalTab('WEBCAM');
                    setFormData((prev) => ({
                      ...prev,
                      source_type: 'USB_WEBCAM',
                      source_uri: prev.source_uri && !prev.source_uri.includes('rtsp') ? prev.source_uri : '0'
                    }));
                  }}
                  className={`flex items-center gap-1.5 border-b-2 px-3.5 py-2 text-xs font-bold transition whitespace-nowrap ${
                    modalTab === 'WEBCAM'
                      ? 'border-blue-600 text-blue-700 bg-white rounded-t-lg shadow-2xs'
                      : 'border-transparent text-slate-500 hover:text-slate-700'
                  }`}
                >
                  <CameraIcon className="h-3.5 w-3.5 text-slate-600" />
                  <span>Laptop Camera / USB Webcam</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setModalTab('NVR');
                    setFormData((prev) => ({
                      ...prev,
                      source_type: 'NVR_CHANNEL',
                      source_uri:
                        prev.source_uri && prev.source_uri.includes('Streaming')
                          ? prev.source_uri
                          : 'rtsp://192.168.1.50:554/Streaming/Channels/101'
                    }));
                  }}
                  className={`flex items-center gap-1.5 border-b-2 px-3.5 py-2 text-xs font-bold transition whitespace-nowrap ${
                    modalTab === 'NVR'
                      ? 'border-blue-600 text-blue-700 bg-white rounded-t-lg shadow-2xs'
                      : 'border-transparent text-slate-500 hover:text-slate-700'
                  }`}
                >
                  <Layers className="h-3.5 w-3.5 text-slate-600" />
                  <span>Tambah DVR/NVR</span>
                </button>
              </div>
            )}

            {/* Modal Body / Form */}
            <form onSubmit={handleFormSubmit} className="p-6 space-y-4 overflow-y-auto flex-1">
              {/* TAB 1: AUTOMATIC ONVIF DISCOVERY */}
              {modalTab === 'DISCOVERY' && !editingCamera && (
                <div className="space-y-4">
                  {/* Info Banner & Scan Control */}
                  <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                      <div>
                        <h4 className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                          <Search className="h-4 w-4 text-blue-600" />
                          Pencarian ONVIF WS-Discovery Otomatis
                        </h4>
                        <p className="text-[11px] text-slate-500 mt-0.5">
                          Memindai kamera IP & DVR/NVR di subnet lokal restoran (UDP Multicast 239.255.255.250:3702).
                        </p>
                      </div>
                      <button
                        type="button"
                        onClick={() => handleScanNetwork(discoverySimulate)}
                        disabled={isScanningNetwork}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 transition shrink-0"
                      >
                        <RefreshCw className={`h-3.5 w-3.5 ${isScanningNetwork ? 'animate-spin' : ''}`} />
                        <span>{isScanningNetwork ? 'Memindai (3s)...' : 'Pindai Jaringan'}</span>
                      </button>
                    </div>

                    <div className="mt-3 pt-3 border-t border-blue-100 flex flex-wrap items-center justify-between text-[11px] text-slate-500 gap-2">
                      <span className="flex items-center gap-1 text-slate-600">
                        <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                        Aman: Hanya memindai IP privat lokal (RFC 1918) untuk mencegah SSRF.
                      </span>
                      <label className="flex items-center gap-1.5 text-slate-600 cursor-pointer font-medium hover:text-slate-800">
                        <input
                          type="checkbox"
                          checked={discoverySimulate}
                          onChange={(e) => {
                            setDiscoverySimulate(e.target.checked);
                            if (e.target.checked) handleScanNetwork(true);
                          }}
                          className="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span>Mode Simulasi / Lab</span>
                      </label>
                    </div>
                  </div>

                  {/* Device List Section */}
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <label className="block text-xs font-bold text-slate-800">
                        Daftar Perangkat Ditemukan ({discoveredDevices.length})
                      </label>
                      <span className="text-[10px] text-slate-400">
                        Klik kartu untuk memilih dan menguji feed
                      </span>
                    </div>

                    {isScanningNetwork ? (
                      <div className="rounded-xl border border-dashed border-blue-200 bg-blue-50/20 p-8 text-center space-y-2">
                        <RefreshCw className="h-6 w-6 text-blue-600 animate-spin mx-auto" />
                        <p className="text-xs font-semibold text-slate-700">Sedang mengirimkan probe WS-Discovery...</p>
                        <p className="text-[11px] text-slate-400">Menunggu respons ProbeMatches dari perangkat di jaringan lokal</p>
                      </div>
                    ) : discoveredDevices.length === 0 ? (
                      <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-6 text-center space-y-2">
                        <div className="h-9 w-9 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center mx-auto">
                          <WifiOff className="h-4 w-4" />
                        </div>
                        <h5 className="text-xs font-bold text-slate-800">Tidak Ada Perangkat ONVIF Ditemukan</h5>
                        <p className="text-[11px] text-slate-500 max-w-md mx-auto leading-relaxed">
                          Pastikan kamera terhubung ke LAN/Wi-Fi yang sama, fitur ONVIF aktif di menu kamera, dan router mengizinkan multicast UDP port 3702. Anda juga dapat menggunakan tab <strong>Tambah IP/RTSP Manual</strong>.
                        </p>
                        <div className="pt-2 flex items-center justify-center gap-2">
                          <button
                            type="button"
                            onClick={() => handleScanNetwork(false)}
                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                          >
                            <RefreshCw className="h-3 w-3" /> Pindai Ulang
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setDiscoverySimulate(true);
                              handleScanNetwork(true);
                            }}
                            className="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                          >
                            Coba Profil Simulasi
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                        {discoveredDevices.map((dev) => {
                          const isSelected = selectedDiscoveredDevice?.device_id === dev.device_id;
                          return (
                            <div
                              key={dev.device_id}
                              onClick={() => handleSelectDiscoveredDevice(dev)}
                              className={`p-3 rounded-xl border text-xs cursor-pointer transition flex items-start justify-between gap-3 ${
                                isSelected
                                  ? 'border-blue-500 bg-blue-50/60 ring-2 ring-blue-500/20'
                                  : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/40'
                              }`}
                            >
                              <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                  <span className="font-bold text-slate-900">{dev.name}</span>
                                  {dev.manufacturer && (
                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                      {dev.manufacturer}
                                    </span>
                                  )}
                                  {isSelected && (
                                    <span className="rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                      Dipilih
                                    </span>
                                  )}
                                </div>
                                <div className="flex flex-wrap items-center gap-x-3 text-[11px] text-slate-500 font-mono">
                                  <span>IP: {dev.ip_address}:{dev.port}</span>
                                  <span>RTSP: {dev.suggested_rtsp_uri}</span>
                                </div>
                                <div className="text-[10px] text-slate-400">
                                  Lokasi: {dev.location || 'LAN Restoran'} • Endpoint: {dev.onvif_xaddr}
                                </div>
                              </div>
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleSelectDiscoveredDevice(dev);
                                }}
                                className={`shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition ${
                                  isSelected
                                    ? 'bg-blue-600 text-white'
                                    : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                }`}
                              >
                                {isSelected ? 'Terpilih' : 'Pilih'}
                              </button>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>

                  {/* Configuration & Pre-registration Test Box */}
                  {selectedDiscoveredDevice && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50/30 p-4 space-y-3">
                      <div className="flex items-center justify-between border-b border-blue-100 pb-2">
                        <div className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                          Konfigurasi & Uji Perangkat: {selectedDiscoveredDevice.name}
                        </div>
                      </div>

                      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-700">Nama Kamera *</label>
                          <input
                            type="text"
                            required
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-700">Lokasi Penempatan *</label>
                          <input
                            type="text"
                            required
                            value={formData.location}
                            onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                          />
                        </div>
                      </div>

                      <div>
                        <label className="block text-[11px] font-semibold text-slate-700">URL RTSP Stream *</label>
                        <input
                          type="text"
                          required
                          value={formData.source_uri}
                          onChange={(e) => setFormData({ ...formData, source_uri: e.target.value })}
                          className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:outline-none"
                        />
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-700">Username CCTV</label>
                          <input
                            type="text"
                            value={formData.username}
                            onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                            placeholder="admin"
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-slate-700">Password CCTV</label>
                          <div className="relative mt-1">
                            <input
                              type={showPassword ? 'text' : 'password'}
                              value={formData.password}
                              onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                              placeholder="Kata sandi kamera"
                              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                            />
                            <button
                              type="button"
                              onClick={() => setShowPassword(!showPassword)}
                              className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                              {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                            </button>
                          </div>
                        </div>
                      </div>

                      {/* Connection Test before Registration */}
                      <div className="pt-2 flex flex-wrap items-center justify-between gap-2 border-t border-blue-100">
                        <button
                          type="button"
                          onClick={handleValidateDiscoveredDevice}
                          disabled={isValidatingDevice || !formData.source_uri}
                          className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-50 disabled:opacity-50 transition"
                        >
                          <RefreshCw className={`h-3.5 w-3.5 ${isValidatingDevice ? 'animate-spin text-blue-600' : ''}`} />
                          <span>{isValidatingDevice ? 'Menguji Stream...' : 'Uji Koneksi Kamera'}</span>
                        </button>

                        {deviceValidationResult && (
                          <span className={`inline-flex items-center gap-1 text-xs font-bold ${
                            deviceValidationResult.is_accessible ? 'text-emerald-700' : 'text-rose-600'
                          }`}>
                            {deviceValidationResult.is_accessible ? (
                              <>
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                <span>ONLINE ({deviceValidationResult.resolution_width}x{deviceValidationResult.resolution_height} @ {deviceValidationResult.fps}fps)</span>
                              </>
                            ) : (
                              <>
                                <AlertCircle className="h-4 w-4 text-rose-600" />
                                <span>Gagal Terhubung</span>
                              </>
                            )}
                          </span>
                        )}
                      </div>

                      {deviceValidationResult && !deviceValidationResult.is_accessible && (
                        <p className="text-[11px] text-rose-600 bg-rose-50 p-2 rounded-lg border border-rose-200">
                          {deviceValidationResult.message} {deviceValidationResult.error_detail && `— ${deviceValidationResult.error_detail}`}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* TABS 2, 3, 4 & EDIT MODE: MANUAL CONFIGURATION FORMS */}
              {(modalTab !== 'DISCOVERY' || editingCamera) && (
                <>
                  {/* Camera Name & Location */}
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700">Nama Kamera *</label>
                      <input
                        type="text"
                        required
                        value={formData.name}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        placeholder="Contoh: CCTV Pintu Depan Kasir"
                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-slate-700">Lokasi Penempatan *</label>
                      <input
                        type="text"
                        required
                        value={formData.location}
                        onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                        placeholder="Contoh: Pintu Masuk Luar Dining"
                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                      />
                    </div>
                  </div>

                  {/* Source Configuration Section */}
                  <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                    <div className="text-[11px] font-bold uppercase text-slate-500 tracking-wider flex items-center gap-1.5">
                      <Info className="h-3.5 w-3.5 text-blue-600" />
                      Parameter Koneksi: {modalTab === 'WEBCAM' ? 'USB / Laptop Webcam' : modalTab === 'NVR' ? 'DVR/NVR Channel' : 'IP RTSP Stream'}
                    </div>

                    {/* Manual RTSP or NVR */}
                    {(modalTab === 'MANUAL_RTSP' || modalTab === 'NVR' || (editingCamera && (formData.source_type === 'RTSP' || formData.source_type === 'NVR_CHANNEL'))) && (
                      <>
                        {modalTab === 'NVR' && (
                          <div className="p-3 bg-white rounded-xl border border-slate-200 space-y-2">
                            <label className="block text-[11px] font-bold text-slate-700">Preset URL Channel NVR Pabrikan:</label>
                            <div className="flex flex-wrap gap-1.5">
                              {[
                                { name: 'Hikvision (Ch 1)', url: 'rtsp://192.168.1.50:554/Streaming/Channels/101' },
                                { name: 'Hikvision (Ch 2)', url: 'rtsp://192.168.1.50:554/Streaming/Channels/201' },
                                { name: 'Dahua (Ch 1)', url: 'rtsp://192.168.1.50:554/cam/realmonitor?channel=1&subtype=0' },
                                { name: 'Dahua (Ch 2)', url: 'rtsp://192.168.1.50:554/cam/realmonitor?channel=2&subtype=0' },
                                { name: 'Generic /ch1', url: 'rtsp://192.168.1.50:554/ch1' }
                              ].map((preset) => (
                                <button
                                  key={preset.name}
                                  type="button"
                                  onClick={() => setFormData({ ...formData, source_uri: preset.url })}
                                  className="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700 hover:bg-blue-50 hover:border-blue-300 font-mono"
                                >
                                  {preset.name}
                                </button>
                              ))}
                            </div>
                          </div>
                        )}

                        <div>
                          <label className="block text-xs font-semibold text-slate-700">
                            {modalTab === 'NVR' ? 'RTSP NVR Channel Stream URL *' : 'RTSP Stream URL *'}
                          </label>
                          <input
                            type="text"
                            required
                            value={formData.source_uri}
                            onChange={(e) => setFormData({ ...formData, source_uri: e.target.value })}
                            placeholder="rtsp://192.168.1.100:554/live/ch0"
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:outline-none"
                          />
                          <p className="mt-1 text-[11px] text-slate-400">
                            Format: rtsp://[IP_KAMERA]:[PORT]/[STREAM_PATH]
                          </p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                          <div>
                            <label className="block text-xs font-semibold text-slate-700">Username (Opsional)</label>
                            <input
                              type="text"
                              value={formData.username}
                              onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                              placeholder="admin"
                              className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                            />
                          </div>
                          <div>
                            <label className="block text-xs font-semibold text-slate-700">Password (Opsional)</label>
                            <div className="relative mt-1">
                              <input
                                type={showPassword ? 'text' : 'password'}
                                value={formData.password}
                                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                placeholder={editingCamera ? '(Biarkan kosong jika tidak diubah)' : 'Kata sandi CCTV'}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                              />
                              <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                              >
                                {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                              </button>
                            </div>
                          </div>
                        </div>
                      </>
                    )}

                    {/* USB / Laptop Webcam */}
                    {(modalTab === 'WEBCAM' || (editingCamera && formData.source_type === 'USB_WEBCAM')) && (
                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <label className="block text-xs font-semibold text-slate-700">Device Index Identifier *</label>
                          <button
                            type="button"
                            onClick={handleScanWebcams}
                            disabled={isScanningWebcams}
                            className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 hover:underline disabled:opacity-50"
                          >
                            <RefreshCw className={`h-3 w-3 ${isScanningWebcams ? 'animate-spin' : ''}`} />
                            <span>{isScanningWebcams ? 'Memindai...' : 'Pindai Kamera'}</span>
                          </button>
                        </div>
                        <input
                          type="text"
                          required
                          value={formData.source_uri}
                          onChange={(e) => setFormData({ ...formData, source_uri: e.target.value })}
                          placeholder="0 (Webcam Laptop Default)"
                          className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:outline-none"
                        />
                        {detectedWebcams.length > 0 && (
                          <div className="pt-1 flex flex-wrap gap-1.5">
                            {detectedWebcams.map((dev) => (
                              <button
                                key={dev.index}
                                type="button"
                                onClick={() => setFormData({ ...formData, source_uri: dev.index.toString() })}
                                className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-700 hover:bg-blue-50 hover:border-blue-300"
                              >
                                #{dev.index}: {dev.name} ({dev.resolution})
                              </button>
                            ))}
                          </div>
                        )}
                        <p className="text-[11px] text-slate-400">
                          Gunakan <code className="text-slate-600">0</code> untuk kamera bawaan laptop, atau <code className="text-slate-600">1, 2</code> untuk USB webcam eksternal.
                        </p>
                      </div>
                    )}
                  </div>
                </>
              )}

              {/* Resolution & FPS Settings */}
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700">Lebar (px)</label>
                  <input
                    type="number"
                    value={formData.resolution_width}
                    onChange={(e) => setFormData({ ...formData, resolution_width: parseInt(e.target.value) || 1280 })}
                    className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700">Tinggi (px)</label>
                  <input
                    type="number"
                    value={formData.resolution_height}
                    onChange={(e) => setFormData({ ...formData, resolution_height: parseInt(e.target.value) || 720 })}
                    className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700">FPS Target</label>
                  <input
                    type="number"
                    value={formData.fps}
                    onChange={(e) => setFormData({ ...formData, fps: parseInt(e.target.value) || 25 })}
                    className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                  />
                </div>
              </div>

              {/* Notes & Active Status */}
              <div>
                <label className="block text-xs font-semibold text-slate-700">Catatan Operasional (Opsional)</label>
                <input
                  type="text"
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  placeholder="Contoh: Sudut pandang atas pintu masuk kasir"
                  className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="camera-active-checkbox"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <label htmlFor="camera-active-checkbox" className="text-xs font-medium text-slate-700 cursor-pointer">
                  Aktifkan kamera ini untuk operasi sistem
                </label>
              </div>

              {/* Modal Footer */}
              <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="rounded-xl bg-blue-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition"
                >
                  {editingCamera ? 'Simpan Perubahan' : 'Daftarkan CCTV'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* MODAL: KONFIRMASI HAPUS KAMERA */}
      {/* ========================================================= */}
      {cameraToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div className="flex items-center gap-3 text-rose-600 mb-3">
              <div className="rounded-xl bg-rose-50 p-2">
                <AlertCircle className="h-6 w-6" />
              </div>
              <h3 className="text-sm font-bold text-slate-900">Konfirmasi Hapus Kamera</h3>
            </div>
            <p className="text-xs text-slate-600 leading-relaxed">
              Apakah Anda yakin ingin menghapus kamera <strong>"{cameraToDelete.name}"</strong>? Konfigurasi koordinat garis virtual dan riwayat sesi terkait akan dihapus dari sistem.
            </p>
            <div className="mt-6 flex items-center justify-end gap-2">
              <button
                onClick={() => setCameraToDelete(null)}
                className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                onClick={handleDeleteCamera}
                className="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-700"
              >
                Hapus Kamera
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
