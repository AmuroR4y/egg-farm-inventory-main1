// VDVC Enterprise Dashboard - Analytics Engine
const STORAGE_KEYS = {
  users: 'vdvc-users',
  notifications: 'vdvc-notifications',
  reports: 'vdvc-reports',
  profile: 'vdvc-profile',
  inventory: 'vdvc-inventory',
  reservations: 'vdvc-reservations',
  deliveries: 'vdvc-deliveries',
  activityLog: 'vdvc-activity-log',
};

const DASHBOARD_SECTIONS = [
  'stats',
  'alerts',
  'reservationChart',
  'inventoryChart',
  'productInsights',
  'forecast',
  'notifications',
  'liveMetric',
  'activityLog',
  'smsMonitoring',
  'forecastInventory',
];

const DEMO_PASSWORD_PREFIX = 'sha256:';
const LEGACY_PASSWORD_PREFIX = 'legacy-djb2:';
const PASSWORD_SALT = 'vdvc-owner-module-salt-v1';
const AVATAR_MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
const AVATAR_MAX_DIMENSION = 256;
const AVATAR_MAX_STORAGE_BYTES = 180 * 1024;
const DATA_RETENTION_DAYS = 90;
const MAX_RESERVATION_RECORDS = 300;
const MAX_DELIVERY_RECORDS = 300;
const RETAINED_SCOPE_LABEL = `Last ${DATA_RETENTION_DAYS} days`;
const INVENTORY_FLOW_WINDOW_DAYS = 30;
const INVENTORY_API_URL = window.VDVC_INVENTORY_API_URL
  || '../egg-farm-inventory-main/egg-farm-inventory-main/inventory_api.php';

// Config fallback thresholds — no minimum_level column exists in MySQL today.
const EGG_SIZE_MINIMUM_LEVELS = {
  XS: 500,
  Small: 800,
  Medium: 2000,
  Large: 4000,
  XL: 1500,
  Jumbo: 1000,
  'Super Jumbo': 800,
  'Double Yolk': 500,
};
const SUPPLY_DEFAULT_MINIMUM_LEVEL = 50;

const initialProfile = {
  fullName: 'Admin Name',
  email: 'admin.email@domain.com',
  phone: '+63 900 000 0000',
  jobTitle: 'Enterprise Owner',
  passwordHash: '',
  avatar: '',
};

// Helper functions
function escapeHtml(value = '') {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function cloneData(value) {
  return JSON.parse(JSON.stringify(value));
}

function hashLegacyValue(value) {
  let hash = 0;
  for (let i = 0; i < value.length; i += 1) {
    hash = ((hash << 5) - hash) + value.charCodeAt(i);
    hash |= 0;
  }
  return `h${Math.abs(hash).toString(16)}`;
}

function normalizeProfile(profile = {}) {
  const legacyPassword = profile.password || '';
  const normalizedLegacy = legacyPassword
    ? (String(legacyPassword).startsWith('h') ? `${LEGACY_PASSWORD_PREFIX}${legacyPassword}` : '')
    : '';
  const nextProfile = {
    ...initialProfile,
    ...profile,
    passwordHash: profile.passwordHash || normalizedLegacy || '',
  };
  if ('password' in nextProfile) {
    delete nextProfile.password;
  }
  return nextProfile;
}

function saveProfileData(profile) {
  const payload = {
    ...profile,
    passwordHash: profile.passwordHash || '',
  };
  delete payload.password;
  return saveData(STORAGE_KEYS.profile, payload);
}

async function hashPassword(value) {
  if (!window.crypto?.subtle) {
    return `${LEGACY_PASSWORD_PREFIX}${hashLegacyValue(value)}`;
  }
  const encoded = new TextEncoder().encode(`${value}:${PASSWORD_SALT}`);
  const buffer = await window.crypto.subtle.digest('SHA-256', encoded);
  const hash = Array.from(new Uint8Array(buffer))
    .map(byte => byte.toString(16).padStart(2, '0'))
    .join('');
  return `${DEMO_PASSWORD_PREFIX}${hash}`;
}

async function hashPasswordWithoutSalt(value) {
  if (!window.crypto?.subtle) {
    return `${LEGACY_PASSWORD_PREFIX}${hashLegacyValue(value)}`;
  }
  const encoded = new TextEncoder().encode(value);
  const buffer = await window.crypto.subtle.digest('SHA-256', encoded);
  const hash = Array.from(new Uint8Array(buffer))
    .map(byte => byte.toString(16).padStart(2, '0'))
    .join('');
  return `${DEMO_PASSWORD_PREFIX}${hash}`;
}

async function verifyPassword(value, profile) {
  if (!profile.passwordHash) return true;
  if (profile.passwordHash.startsWith(LEGACY_PASSWORD_PREFIX)) {
    return `${LEGACY_PASSWORD_PREFIX}${hashLegacyValue(value)}` === profile.passwordHash;
  }
  const saltedHash = await hashPassword(value);
  if (saltedHash === profile.passwordHash) {
    return true;
  }
  return (await hashPasswordWithoutSalt(value)) === profile.passwordHash;
}

function loadData(key, fallback) {
  try {
    const data = localStorage.getItem(key);
    if (!data) {
      return key === STORAGE_KEYS.profile ? normalizeProfile(fallback) : cloneData(fallback);
    }
    const parsed = JSON.parse(data);
    return key === STORAGE_KEYS.profile ? normalizeProfile(parsed) : parsed;
  } catch {
    return key === STORAGE_KEYS.profile ? normalizeProfile(fallback) : cloneData(fallback);
  }
}

function saveData(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
    return true;
  } catch (error) {
    console.error(`Unable to persist "${key}" to localStorage.`, error);
    return false;
  }
}

function showToast(message, type = 'info') {
  const stack = document.getElementById('toast-stack');
  if (!stack) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  stack.appendChild(toast);
  setTimeout(() => toast.remove(), 2400);
}

function confirmAction(message) {
  return window.confirm(message);
}

function formatNumber(num) {
  return new Intl.NumberFormat('en-US').format(Number(num) || 0);
}

function formatCurrency(value) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatPercent(value, digits = 1) {
  return `${Number(value || 0).toFixed(digits)}%`;
}

function extractEggSize(value) {
  const normalized = String(value || '')
    .replace(/Extra Large/gi, 'XL')
    .replace(/\s+/g, ' ')
    .trim();
  if (!normalized) return '';
  const known = [
    'Super Jumbo',
    'Double Yolk',
    'Jumbo',
    'XL',
    'Large',
    'Medium',
    'Small',
    'XS',
  ];
  const match = known.find(size => new RegExp(`\\b${size}\\b`, 'i').test(normalized));
  return match || '';
}

function formatCompactNumber(value) {
  const numeric = Number(value) || 0;
  const absolute = Math.abs(numeric);
  if (absolute < 1000) return formatNumber(numeric);
  if (absolute < 1000000) {
    const short = absolute < 10000 ? (numeric / 1000).toFixed(1) : String(Math.round(numeric / 1000));
    return `${short.replace(/\.0$/, '')}K`;
  }
  const short = (numeric / 1000000).toFixed(1);
  return `${short.replace(/\.0$/, '')}M`;
}

function estimateDataUrlBytes(dataUrl) {
  const base64 = String(dataUrl).split(',')[1] || '';
  return Math.ceil((base64.length * 3) / 4);
}

function loadImageFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('The selected image could not be read.'));
    reader.onload = () => {
      const image = new Image();
      image.onerror = () => reject(new Error('The selected file is not a valid image.'));
      image.onload = () => resolve(image);
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

async function optimizeAvatarFile(file) {
  if (!file.type.startsWith('image/')) {
    throw new Error('Only image uploads are supported.');
  }
  if (file.size > AVATAR_MAX_UPLOAD_BYTES) {
    throw new Error('Avatar image must be 2 MB or smaller.');
  }

  const image = await loadImageFile(file);
  const scale = Math.min(1, AVATAR_MAX_DIMENSION / Math.max(image.width, image.height));
  const width = Math.max(1, Math.round(image.width * scale));
  const height = Math.max(1, Math.round(image.height * scale));
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('Image optimization is not supported in this browser.');
  }

  canvas.width = width;
  canvas.height = height;
  context.drawImage(image, 0, 0, width, height);

  let quality = 0.82;
  let result = canvas.toDataURL('image/jpeg', quality);
  while (estimateDataUrlBytes(result) > AVATAR_MAX_STORAGE_BYTES && quality > 0.45) {
    quality -= 0.08;
    result = canvas.toDataURL('image/jpeg', quality);
  }

  if (estimateDataUrlBytes(result) > AVATAR_MAX_STORAGE_BYTES) {
    throw new Error('Compressed avatar is still too large. Please choose a smaller image.');
  }

  return result;
}

function getInitials(name) {
  return name ? name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase() : 'A';
}

function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
  document.querySelector('.sidebar-overlay').classList.toggle('hidden');
}

function pad(value) {
  return String(value).padStart(2, '0');
}

function toDateKey(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function toDateTimeKey(date) {
  return `${toDateKey(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
}

function daysAgo(days, hour = 9) {
  const date = new Date();
  date.setDate(date.getDate() - days);
  date.setHours(hour, 0, 0, 0);
  return toDateTimeKey(date);
}

function daysAgoDate(days) {
  const date = new Date();
  date.setDate(date.getDate() - days);
  date.setHours(0, 0, 0, 0);
  return toDateKey(date);
}

function monthsAgoDate(months, day = 8) {
  const date = new Date();
  date.setMonth(date.getMonth() - months, day);
  date.setHours(0, 0, 0, 0);
  return toDateKey(date);
}

function parseDate(value) {
  if (!value) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function getMostRelevantDate(item, fields) {
  for (const field of fields) {
    const parsed = parseDate(item?.[field]);
    if (parsed) {
      return parsed;
    }
  }
  return null;
}

function pruneTimeSeriesRecords(items, dateFields, maxLength) {
  const cutoff = Date.now() - (DATA_RETENTION_DAYS * 24 * 60 * 60 * 1000);
  return [...items]
    .filter(item => {
      const parsed = getMostRelevantDate(item, dateFields);
      return parsed && parsed.getTime() >= cutoff;
    })
    .sort((left, right) => {
      const leftTime = getMostRelevantDate(left, dateFields)?.getTime() || 0;
      const rightTime = getMostRelevantDate(right, dateFields)?.getTime() || 0;
      return rightTime - leftTime;
    })
    .slice(0, maxLength);
}

function normalizeOperationalStatus(value) {
  return String(value || '').trim().toLowerCase();
}

function normalizeReservationRecord(record) {
  return {
    ...record,
    status: normalizeOperationalStatus(record?.status),
  };
}

function normalizeDeliveryRecord(record) {
  return {
    ...record,
    status: normalizeOperationalStatus(record?.status),
  };
}

function isValidReservationRecord(record) {
  return Boolean(parseDate(record?.createdAt));
}

function isValidDeliveryRecord(record) {
  const createdAt = parseDate(record?.createdAt);
  if (!createdAt) return false;
  if (record?.status === 'completed') {
    return Boolean(parseDate(record?.completedAt));
  }
  return true;
}

function prepareReservationRecords(records) {
  const normalized = (Array.isArray(records) ? records : [])
    .map(normalizeReservationRecord)
    .filter(isValidReservationRecord);
  return pruneTimeSeriesRecords(normalized, ['createdAt', 'scheduledAt'], MAX_RESERVATION_RECORDS);
}

function prepareDeliveryRecords(records) {
  const normalized = (Array.isArray(records) ? records : [])
    .map(normalizeDeliveryRecord)
    .filter(isValidDeliveryRecord);
  return pruneTimeSeriesRecords(normalized, ['completedAt', 'createdAt'], MAX_DELIVERY_RECORDS);
}

function getShortDateLabel(value) {
  const parsed = parseDate(value);
  if (!parsed) return 'N/A';
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(parsed);
}

function getMonthLabel(value) {
  const parsed = parseDate(value);
  if (!parsed) return 'N/A';
  return new Intl.DateTimeFormat('en-US', { month: 'short' }).format(parsed);
}

function sumBy(items, mapper) {
  return items.reduce((total, item) => total + (Number(mapper(item)) || 0), 0);
}

function getDateRange(days) {
  return Array.from({ length: days }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() - (days - index - 1));
    date.setHours(0, 0, 0, 0);
    return date;
  });
}

function getMonthRange(months) {
  return Array.from({ length: months }, (_, index) => {
    const date = new Date();
    date.setDate(1);
    date.setMonth(date.getMonth() - (months - index - 1));
    date.setHours(0, 0, 0, 0);
    return date;
  });
}

function buildDailySeries(items, dateField, days, valueMapper = () => 1) {
  const buckets = new Map(getDateRange(days).map(date => [toDateKey(date), 0]));
  items.forEach(item => {
    const parsed = parseDate(item[dateField]);
    if (!parsed) return;
    const key = toDateKey(parsed);
    if (buckets.has(key)) {
      buckets.set(key, buckets.get(key) + (Number(valueMapper(item)) || 0));
    }
  });
  return Array.from(buckets.entries()).map(([key, value]) => ({ key, label: getShortDateLabel(key), value }));
}

function buildMonthlySeries(items, dateField, months, valueMapper = () => 1) {
  const buckets = new Map(getMonthRange(months).map(date => [`${date.getFullYear()}-${pad(date.getMonth() + 1)}`, 0]));
  items.forEach(item => {
    const parsed = parseDate(item[dateField]);
    if (!parsed) return;
    const key = `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}`;
    if (buckets.has(key)) {
      buckets.set(key, buckets.get(key) + (Number(valueMapper(item)) || 0));
    }
  });
  return Array.from(buckets.entries()).map(([key, value]) => ({
    key,
    label: getMonthLabel(`${key}-01`),
    value,
  }));
}

function groupCounts(items, mapper) {
  return items.reduce((accumulator, item) => {
    const key = mapper(item) || 'Unspecified';
    accumulator[key] = (accumulator[key] || 0) + 1;
    return accumulator;
  }, {});
}

function getPolylinePoints(values, width, height, padding = 6) {
  if (!values.length) return '';
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const spread = Math.max(max - min, 1);
  return values.map((value, index) => {
    const x = padding + ((width - (padding * 2)) * index / Math.max(values.length - 1, 1));
    const y = height - padding - (((value - min) / spread) * (height - (padding * 2)));
    return `${Number(x.toFixed(2))},${Number(y.toFixed(2))}`;
  }).join(' ');
}

function buildLineChartSvg(primaryValues, secondaryValues, options = {}) {
  const width = options.width || 160;
  const height = options.height || 70;
  const primaryColor = options.primaryColor || '#1a2b4a';
  const secondaryColor = options.secondaryColor || '#27ae60';
  const primaryPoints = getPolylinePoints(primaryValues, width, height);
  const secondaryPoints = getPolylinePoints(secondaryValues, width, height);
  const highlightIndex = options.highlightIndex ?? (primaryValues.length - 1);
  const highlightPoint = primaryPoints.split(' ')[highlightIndex] || '';
  const [cx, cy] = highlightPoint ? highlightPoint.split(',') : [];

  return `
    <svg aria-hidden="true" focusable="false" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
      <polyline fill="none" stroke="#eef0f6" stroke-width="1" points="6,${height - 6} ${width - 6},${height - 6}" />
      <polyline fill="none" stroke="${secondaryColor}" stroke-width="1.2" stroke-dasharray="3,2" points="${secondaryPoints}" />
      <polyline fill="none" stroke="${primaryColor}" stroke-width="2" points="${primaryPoints}" />
      ${cx && cy ? `<circle cx="${cx}" cy="${cy}" r="3" fill="#3a5fa0" />` : ''}
    </svg>
  `;
}

function updateLineChart(svg, primaryValues, secondaryValues, options = {}) {
  if (!svg) return false;
  const width = options.width || 160;
  const height = options.height || 70;
  const primaryPoints = getPolylinePoints(primaryValues, width, height);
  const secondaryPoints = getPolylinePoints(secondaryValues, width, height);
  const highlightIndex = options.highlightIndex ?? (primaryValues.length - 1);
  const highlightPoint = primaryPoints.split(' ')[highlightIndex] || '';
  const [cx, cy] = highlightPoint ? highlightPoint.split(',') : [];
  const baseline = svg.querySelector('[data-chart-line="baseline"]');
  const secondary = svg.querySelector('[data-chart-line="secondary"]');
  const primary = svg.querySelector('[data-chart-line="primary"]');
  const highlight = svg.querySelector('[data-chart-line="highlight"]');
  if (baseline) {
    baseline.setAttribute('points', `6,${height - 6} ${width - 6},${height - 6}`);
  }
  if (secondary) {
    secondary.setAttribute('points', secondaryPoints);
  }
  if (primary) {
    primary.setAttribute('points', primaryPoints);
  }
  if (highlight) {
    if (cx && cy) {
      highlight.setAttribute('cx', cx);
      highlight.setAttribute('cy', cy);
      highlight.classList.remove('hidden');
    } else {
      highlight.classList.add('hidden');
    }
  }
  return true;
}

// Seed data with updated categories (removed Predictor, added Analytics)
const initialUsers = [
  { id: 1, name: 'Astra Astiva', email: 'astra@vdvc.com', role: 'Manager', department: 'Mandaluyong Operations', status: 'active' },
  { id: 2, name: 'Maris Name', email: 'maris@vdvc.com', role: 'Manager', department: 'Mandaluyong Operations', status: 'active' },
  { id: 3, name: 'Farir Davis', email: 'farir@vdvc.com', role: 'Manager', department: 'Mandaluyong Operations', status: 'active' },
  { id: 4, name: 'John Mulin', email: 'john@vdvc.com', role: 'Manager', department: 'Laguna Operations', status: 'deactivated' },
  { id: 5, name: 'John Walk', email: 'johnw@vdvc.com', role: 'Manager', department: 'Cebu Operations', status: 'deactivated' },
  { id: 6, name: 'Jane Customer', email: 'jane@customer.com', role: 'Customer', department: 'N/A', status: 'active' },
  { id: 7, name: 'Bob Customer', email: 'bob@customer.com', role: 'Customer', department: 'N/A', status: 'active' },
];

const initialNotifications = [
  { id: 1, title: 'New Reservation - Large Order Confirmed', message: 'Reservation R-1008 moved to confirmed status.', type: 'info', read: false, createdAt: daysAgo(1, 10) },
  { id: 2, title: 'Low Stock Alert - Medium', message: 'Current stock is now below the minimum threshold.', type: 'warning', read: false, createdAt: daysAgo(2, 14) },
  { id: 3, title: 'Inventory Reconciled', message: 'Warehouse counts were reconciled for the current week.', type: 'success', read: true, createdAt: daysAgo(4, 9) },
  { id: 4, title: 'Delivery Completed', message: 'Route South-3 was completed and posted to revenue.', type: 'success', read: true, createdAt: daysAgo(6, 15) },
];

// Updated seed data with correct categories
const initialReports = [
  { id: 1, title: 'Feed Silo 1 Rainfall Date', category: 'Forecasting', date: monthsAgoDate(5, 7), status: 'Finalized' },
  { id: 2, title: 'Quarterly Production Usage', category: 'Analytics', date: monthsAgoDate(4, 15), status: 'Finalized' },
  { id: 3, title: 'Feed Silo 3 Levels Report', category: 'Inventory', date: monthsAgoDate(3, 12), status: 'In Review' },
  { id: 4, title: 'Reservation Route Optimization', category: 'Reservation', date: monthsAgoDate(2, 9), status: 'Draft' },
  { id: 5, title: 'Mixed Grade Demand Projection', category: 'Forecasting', date: monthsAgoDate(1, 18), status: 'Finalized' },
  { id: 6, title: 'Dispatch Variance Summary', category: 'Delivery', date: daysAgoDate(8), status: 'Finalized' },
];

const initialInventoryState = {
  eggs: [],
  supplies: [],
  source: 'mock',
  loadedAt: '',
  error: '',
};

function normalizeEggLedgerRow(row = {}) {
  return {
    id: Number(row.id) || 0,
    batch_id: String(row.batch_id || ''),
    harvest_date: String(row.harvest_date || ''),
    harvest_time: String(row.harvest_time || ''),
    egg_size: String(row.egg_size || ''),
    quantity: Number(row.quantity) || 0,
    current_stock: Number(row.current_stock) || 0,
    movement_type: String(row.movement_type || ''),
    reason: String(row.reason || ''),
    date_logged: String(row.date_logged || ''),
  };
}

function normalizeSupplyLedgerRow(row = {}) {
  return {
    id: Number(row.id) || 0,
    batch_id: String(row.batch_id || ''),
    item_category: String(row.item_category || ''),
    item_name: String(row.item_name || ''),
    quantity: Number(row.quantity) || 0,
    current_stock: Number(row.current_stock) || 0,
    action_type: String(row.action_type || ''),
    reason: String(row.reason || ''),
    date_logged: String(row.date_logged || ''),
  };
}

function normalizeInventoryState(payload = {}) {
  return {
    eggs: (Array.isArray(payload.eggs) ? payload.eggs : []).map(normalizeEggLedgerRow),
    supplies: (Array.isArray(payload.supplies) ? payload.supplies : []).map(normalizeSupplyLedgerRow),
    snapshotEggs: (Array.isArray(payload.snapshotEggs) ? payload.snapshotEggs : []).map(normalizeEggLedgerRow),
    snapshotSupplies: (Array.isArray(payload.snapshotSupplies) ? payload.snapshotSupplies : []).map(normalizeSupplyLedgerRow),
    source: payload.source || 'mock',
    loadedAt: payload.loadedAt || '',
    error: payload.error || '',
  };
}

async function fetchInventoryFromApi() {
  const fromDate = daysAgoDate(INVENTORY_FLOW_WINDOW_DAYS);
  const query = new URLSearchParams({
    resource: 'eggs',
    from: fromDate,
    limit: '500',
  });
  const supplyQuery = new URLSearchParams({
    resource: 'supplies',
    from: fromDate,
    limit: '500',
  });
  const snapshotQuery = new URLSearchParams({ resource: 'snapshot' });

  const [eggResponse, supplyResponse, snapshotResponse] = await Promise.all([
    fetch(`${INVENTORY_API_URL}?${query.toString()}`, { credentials: 'same-origin' }),
    fetch(`${INVENTORY_API_URL}?${supplyQuery.toString()}`, { credentials: 'same-origin' }),
    fetch(`${INVENTORY_API_URL}?${snapshotQuery.toString()}`, { credentials: 'same-origin' }),
  ]);

  if (!eggResponse.ok || !supplyResponse.ok || !snapshotResponse.ok) {
    throw new Error('Inventory API request failed.');
  }

  const eggPayload = await eggResponse.json();
  const supplyPayload = await supplyResponse.json();
  const snapshotPayload = await snapshotResponse.json();

  return normalizeInventoryState({
    eggs: eggPayload.rows || [],
    supplies: supplyPayload.rows || [],
    snapshotEggs: snapshotPayload.eggs || [],
    snapshotSupplies: snapshotPayload.supplies || [],
    source: 'api',
    loadedAt: toDateTimeKey(new Date()),
  });
}

function getLatestEggRowsBySize(eggRows) {
  const latest = new Map();
  [...eggRows]
    .sort((left, right) => Number(right.id) - Number(left.id))
    .forEach(row => {
      if (row.egg_size && !latest.has(row.egg_size)) {
        latest.set(row.egg_size, row);
      }
    });
  return Array.from(latest.values());
}

function getLatestSupplyRows(supplyRows) {
  const latest = new Map();
  [...supplyRows]
    .sort((left, right) => Number(right.id) - Number(left.id))
    .forEach(row => {
      const key = `${row.item_category}::${row.item_name}`;
      if (row.item_category && row.item_name && !latest.has(key)) {
        latest.set(key, row);
      }
    });
  return Array.from(latest.values());
}

function sumInventoryMovements(rows, movementField, movementType, days = INVENTORY_FLOW_WINDOW_DAYS) {
  const cutoff = Date.now() - (days * 24 * 60 * 60 * 1000);
  return sumBy(rows.filter(row => {
    const parsed = parseDate(row.date_logged);
    return parsed
      && parsed.getTime() >= cutoff
      && String(row[movementField]) === movementType;
  }), row => row.quantity);
}

function getEggMinimumLevel(eggSize) {
  return EGG_SIZE_MINIMUM_LEVELS[eggSize] ?? 1000;
}

function getSupplyMinimumLevel() {
  return SUPPLY_DEFAULT_MINIMUM_LEVEL;
}

const initialReservations = [
  { id: 1001, customerName: 'Sunrise Grocers', channel: 'Portal', product: 'Grade A Large', quantity: 720, amount: 10080, status: 'completed', createdAt: daysAgo(28, 9), scheduledAt: daysAgo(25, 6) },
  { id: 1002, customerName: 'Metro Mart', channel: 'Sales Team', product: 'Grade A Medium', quantity: 560, amount: 6720, status: 'completed', createdAt: daysAgo(23, 10), scheduledAt: daysAgo(20, 7) },
  { id: 1003, customerName: 'Quezon Retail Hub', channel: 'Portal', product: 'Grade B Large', quantity: 430, amount: 4300, status: 'confirmed', createdAt: daysAgo(18, 13), scheduledAt: daysAgo(15, 8) },
  { id: 1004, customerName: 'Laguna Foods', channel: 'Phone', product: 'Grade C Large', quantity: 380, amount: 3040, status: 'pending', createdAt: daysAgo(12, 11), scheduledAt: daysAgo(9, 6) },
  { id: 1005, customerName: 'Vista Fresh', channel: 'Walk-in', product: 'Grade B Medium', quantity: 310, amount: 2790, status: 'completed', createdAt: daysAgo(9, 14), scheduledAt: daysAgo(7, 9) },
  { id: 1006, customerName: 'Southline Traders', channel: 'Portal', product: 'Grade A Large', quantity: 890, amount: 12460, status: 'confirmed', createdAt: daysAgo(6, 8), scheduledAt: daysAgo(3, 6) },
  { id: 1007, customerName: 'Cebu Family Store', channel: 'Sales Team', product: 'Grade A Medium', quantity: 540, amount: 6480, status: 'completed', createdAt: daysAgo(4, 9), scheduledAt: daysAgo(2, 7) },
  { id: 1008, customerName: 'Northpoint Market', channel: 'Portal', product: 'Grade C Medium', quantity: 260, amount: 1820, status: 'confirmed', createdAt: daysAgo(2, 15), scheduledAt: daysAgo(1, 5) },
  { id: 1009, customerName: 'Bayanihan Foods', channel: 'Phone', product: 'Grade B Large', quantity: 350, amount: 3500, status: 'pending', createdAt: daysAgo(1, 12), scheduledAt: daysAgo(0, 7) },
];

const initialDeliveries = [
  { id: 2001, reservationId: 1001, customerName: 'Sunrise Grocers', product: 'Grade A Large', quantity: 720, revenue: 10080, status: 'completed', route: 'North-1', createdAt: daysAgo(26, 6), completedAt: daysAgo(25, 11) },
  { id: 2002, reservationId: 1002, customerName: 'Metro Mart', product: 'Grade A Medium', quantity: 560, revenue: 6720, status: 'completed', route: 'Metro-4', createdAt: daysAgo(21, 7), completedAt: daysAgo(20, 15) },
  { id: 2003, reservationId: 1003, customerName: 'Quezon Retail Hub', product: 'Grade B Large', quantity: 430, revenue: 4300, status: 'in_transit', route: 'East-2', createdAt: daysAgo(16, 8), completedAt: '' },
  { id: 2004, reservationId: 1005, customerName: 'Vista Fresh', product: 'Grade B Medium', quantity: 310, revenue: 2790, status: 'completed', route: 'South-1', createdAt: daysAgo(8, 9), completedAt: daysAgo(7, 13) },
  { id: 2005, reservationId: 1006, customerName: 'Southline Traders', product: 'Grade A Large', quantity: 890, revenue: 12460, status: 'scheduled', route: 'South-3', createdAt: daysAgo(4, 7), completedAt: '' },
  { id: 2006, reservationId: 1007, customerName: 'Cebu Family Store', product: 'Grade A Medium', quantity: 540, revenue: 6480, status: 'completed', route: 'Cebu-2', createdAt: daysAgo(3, 7), completedAt: daysAgo(2, 16) },
  { id: 2007, reservationId: 1008, customerName: 'Northpoint Market', product: 'Grade C Medium', quantity: 260, revenue: 1820, status: 'scheduled', route: 'North-5', createdAt: daysAgo(1, 8), completedAt: '' },
];

const initialActivityLog = [
  { id: 3001, entity: 'inventory', action: 'reconciled', detail: 'Weekly stock reconciliation completed.', createdAt: daysAgo(6, 15) },
  { id: 3002, entity: 'delivery', action: 'completed', detail: 'Route South-1 completed.', createdAt: daysAgo(7, 13) },
  { id: 3003, entity: 'reservation', action: 'confirmed', detail: 'Reservation R-1006 confirmed.', createdAt: daysAgo(6, 10) },
  { id: 3004, entity: 'report', action: 'published', detail: 'Mixed Grade Demand Projection finalized.', createdAt: daysAgo(3, 16) },
  { id: 3005, entity: 'reservation', action: 'created', detail: 'Reservation R-1008 created via portal.', createdAt: daysAgo(2, 15) },
  { id: 3006, entity: 'notification', action: 'reviewed', detail: 'Owner marked critical notices as read.', createdAt: daysAgo(1, 10) },
];

// State management with _stateVersion for cache
let state = {
  activePage: 'dashboard',
  users: loadData(STORAGE_KEYS.users, initialUsers),
  notifications: loadData(STORAGE_KEYS.notifications, initialNotifications),
  reports: loadData(STORAGE_KEYS.reports, initialReports),
  profile: normalizeProfile(loadData(STORAGE_KEYS.profile, initialProfile)),
  inventory: normalizeInventoryState(loadData(STORAGE_KEYS.inventory, initialInventoryState)),
  reservations: prepareReservationRecords(loadData(STORAGE_KEYS.reservations, initialReservations)),
  deliveries: prepareDeliveryRecords(loadData(STORAGE_KEYS.deliveries, initialDeliveries)),
  activityLog: loadData(STORAGE_KEYS.activityLog, initialActivityLog),
  userFilters: { query: '', status: 'all', role: 'all' },
  reportFilters: { query: '', category: 'all', date: '', period: 'all', status: 'all', size: 'all' },
  userPage: 1,
  reportPage: 1,
  perPage: 5,
  userSort: 'name',
  reportSort: 'date',
  _liveMetricInterval: null,
  _dashboardAnalyticsCache: null,
  _stateVersion: 0,
};

function invalidateDashboardAnalytics() {
  state._dashboardAnalyticsCache = null;
  state._stateVersion++;
}

function setCollection(key, storageKey, nextValue) {
  const previousValue = state[key];
  const preparedValue = key === 'reservations'
    ? prepareReservationRecords(nextValue)
    : key === 'deliveries'
      ? prepareDeliveryRecords(nextValue)
      : nextValue;
  state[key] = preparedValue;
  if (!saveData(storageKey, preparedValue)) {
    state[key] = previousValue;
    showToast('Unable to save changes locally. Please free browser storage and try again.', 'error');
    return false;
  }
  invalidateDashboardAnalytics();
  return true;
}

function addActivity(entity, action, detail) {
  const activity = {
    id: Date.now(),
    entity,
    action,
    detail,
    createdAt: toDateTimeKey(new Date()),
  };
  state.activityLog = [activity, ...state.activityLog].slice(0, 30);
  saveData(STORAGE_KEYS.activityLog, state.activityLog);
  invalidateDashboardAnalytics();
}

function getRelativeTimeLabel(value) {
  const parsed = parseDate(value);
  if (!parsed) return 'Just now';
  const deltaMinutes = Math.max(0, Math.round((Date.now() - parsed.getTime()) / 60000));
  if (deltaMinutes < 1) return 'Just now';
  if (deltaMinutes < 60) return `${deltaMinutes} min ago`;
  const deltaHours = Math.round(deltaMinutes / 60);
  if (deltaHours < 24) return `${deltaHours} hr ago`;
  const deltaDays = Math.round(deltaHours / 24);
  return `${deltaDays} day${deltaDays === 1 ? '' : 's'} ago`;
}

function getStatusClass(status) {
  const normalized = String(status || 'neutral')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-');
  const supported = new Set([
    'draft',
    'pending',
    'in-review',
    'approved',
    'finalized',
    'rejected',
    'archived',
    'scheduled',
    'neutral',
  ]);
  return `status-pill ${supported.has(normalized) ? `status-${normalized}` : 'status-neutral'}`;
}

function renderStatusPill(status) {
  return `<span class="${getStatusClass(status)}">${escapeHtml(status || 'Unknown')}</span>`;
}

// Analytics functions
function calculateUserAnalytics(users) {
  const activeUsers = users.filter(user => user.status === 'active').length;
  return {
    totalUsers: users.length,
    activeUsers,
    inactiveUsers: users.length - activeUsers,
    departments: new Set(users.map(user => user.department).filter(Boolean)).size,
  };
}

function calculateReservationAnalytics(reservations) {
  const activeStatuses = new Set(['pending', 'confirmed', 'scheduled']);
  return {
    totalReservations: reservations.length,
    activeReservations: reservations.filter(reservation => activeStatuses.has(reservation.status)).length,
    completedReservations: reservations.filter(reservation => reservation.status === 'completed').length,
    pendingReservations: reservations.filter(reservation => reservation.status === 'pending').length,
    cancelledReservations: reservations.filter(reservation => reservation.status === 'cancelled').length,
    confirmedReservations: reservations.filter(reservation => reservation.status === 'confirmed').length,
    totalQuantity: sumBy(reservations, reservation => reservation.quantity),
    totalRevenue: sumBy(reservations.filter(reservation => reservation.status !== 'cancelled'), reservation => reservation.amount),
    uniqueCustomers: new Set(reservations.map(reservation => reservation.customerName).filter(Boolean)).size,
    dailyReservations: buildDailySeries(reservations, 'createdAt', 7),
    sourceBreakdown: Object.entries(groupCounts(reservations, reservation => reservation.channel))
      .map(([label, value]) => ({ label, value }))
      .sort((left, right) => right.value - left.value),
  };
}

function calculateDeliveryAnalytics(deliveries) {
  const completedDeliveries = deliveries.filter(delivery => delivery.status === 'completed');
  return {
    totalDeliveries: deliveries.length,
    completedDeliveries: completedDeliveries.length,
    pendingDeliveries: deliveries.filter(delivery => delivery.status !== 'completed').length,
    totalRevenue: sumBy(completedDeliveries, delivery => delivery.revenue),
    totalQuantity: sumBy(deliveries, delivery => delivery.quantity),
    completionRate: deliveries.length ? (completedDeliveries.length / deliveries.length) * 100 : 0,
    deliverySeries: buildDailySeries(completedDeliveries, 'completedAt', 7),
  };
}

function calculateInventoryAnalytics(inventoryState, deliveries, reservations) {
  const eggRows = inventoryState.eggs || [];
  const supplyRows = inventoryState.supplies || [];
  const latestEggs = inventoryState.snapshotEggs?.length
    ? inventoryState.snapshotEggs
    : getLatestEggRowsBySize(eggRows);
  const latestSupplies = inventoryState.snapshotSupplies?.length
    ? inventoryState.snapshotSupplies
    : getLatestSupplyRows(supplyRows);

  const totalEggInventory = sumBy(latestEggs, row => row.current_stock);
  const totalSupplyInventory = sumBy(latestSupplies, row => row.current_stock);
  const stockIn = sumInventoryMovements(eggRows, 'movement_type', 'Stock In');
  const stockOut = sumInventoryMovements(eggRows, 'movement_type', 'Stock Out');
  const supplyStockIn = sumInventoryMovements(supplyRows, 'action_type', 'Stock In');
  const supplyStockOut = sumInventoryMovements(supplyRows, 'action_type', 'Stock Out');
  const reservedUnits = sumBy(
    reservations.filter(item => ['pending', 'confirmed', 'scheduled'].includes(item.status)),
    item => item.quantity,
  );

  const lowStockEggs = latestEggs
    .map(row => ({
      key: row.egg_size,
      label: row.egg_size,
      category: 'Eggs',
      quantity: row.current_stock,
      minimumLevel: getEggMinimumLevel(row.egg_size),
      batch_id: row.batch_id,
    }))
    .filter(item => item.quantity <= item.minimumLevel);

  const lowStockSupplies = latestSupplies
    .map(row => ({
      key: `${row.item_category}::${row.item_name}`,
      label: `${row.item_category} — ${row.item_name}`,
      category: row.item_category,
      quantity: row.current_stock,
      minimumLevel: getSupplyMinimumLevel(),
      batch_id: row.batch_id,
    }))
    .filter(item => item.quantity <= item.minimumLevel);

  const lowStockItems = [...lowStockEggs, ...lowStockSupplies];

  const productVolume = deliveries.reduce((accumulator, delivery) => {
    const eggSize = extractEggSize(delivery.product) || 'Unspecified';
    accumulator[eggSize] = (accumulator[eggSize] || 0) + (Number(delivery.quantity) || 0);
    return accumulator;
  }, {});
  const rankedProducts = Object.entries(productVolume)
    .map(([label, value]) => ({ label, value }))
    .sort((left, right) => right.value - left.value)
    .slice(0, 6);
  const totalSold = sumBy(rankedProducts, product => product.value) || 1;

  return {
    totalEggInventory,
    totalSupplyInventory,
    stockIn,
    stockOut,
    supplyStockIn,
    supplyStockOut,
    reservedUnits,
    availableInventory: Math.max(totalEggInventory - reservedUnits, 0),
    lowStockItems,
    lowStockEggs,
    lowStockSupplies,
    lowStockCount: lowStockItems.length,
    inventoryHealth: lowStockItems.length ? 'Needs attention' : 'Healthy',
    inventorySource: inventoryState.source || 'mock',
    inventoryLoadedAt: inventoryState.loadedAt || '',
    inventoryError: inventoryState.error || '',
    latestEggs,
    latestSupplies,
    productsWithShare: rankedProducts.map((product, index) => ({
      ...product,
      share: (product.value / totalSold) * 100,
      accent: index === 2 ? 'green' : '',
      opacity: index > 2 ? Math.max(0.35, 1 - (index * 0.12)) : 1,
    })),
  };
}

function calculateReportAnalytics(reports) {
  const sortedReports = [...reports].sort((left, right) => String(right.date).localeCompare(String(left.date)));
  return {
    totalReports: reports.length,
    finalized: reports.filter(report => report.status === 'Finalized').length,
    drafts: reports.filter(report => report.status === 'Draft').length,
    inReview: reports.filter(report => report.status === 'In Review').length,
    recentReports: sortedReports.slice(0, 4),
  };
}

// Fixed forecast calculation - no reportWeight
function calculateForecastAnalytics(reservations, deliveries) {
  const demandSeries = buildMonthlySeries(reservations, 'createdAt', 6, reservation => reservation.quantity);
  const recentActuals = demandSeries.slice(-3).map(point => point.value);
  const trailingAverage = recentActuals.length ? recentActuals.reduce((total, value) => total + value, 0) / recentActuals.length : 0;
  
  return {
    demandSeries,
    deliverySeries: buildMonthlySeries(deliveries.filter(delivery => delivery.status === 'completed'), 'completedAt', 6, delivery => delivery.quantity),
    trendSeries: demandSeries.map((point, index, list) => {
      const previous = list[index - 1]?.value ?? point.value;
      const next = list[index + 1]?.value ?? point.value;
      return Math.round((previous + point.value + next) / 3);
    }),
    nextForecast: Math.round(trailingAverage),
    strongestMonth: [...demandSeries].sort((left, right) => right.value - left.value)[0] || null,
  };
}

// Calculate inventory requirements for forecasting
function calculateForecastInventory(analytics, forecastAnalytics) {
  const projectedDemand = forecastAnalytics.nextForecast;
  const currentInventory = analytics.inventoryAnalytics.availableInventory;
  const reservedUnits = analytics.inventoryAnalytics.reservedUnits;
  const lowStockItems = analytics.inventoryAnalytics.lowStockItems;
  
  // Calculate reorder quantity (projected demand - available inventory + safety stock)
  const safetyStock = Math.round(projectedDemand * 0.2); // 20% safety stock
  const reorderQuantity = Math.max(0, projectedDemand - currentInventory + safetyStock);
  
  // Determine sufficiency
  const isSufficient = currentInventory >= (projectedDemand + reservedUnits);
  
  return {
    projectedDemand,
    currentInventory,
    reservedUnits,
    availableAfterReservations: Math.max(0, currentInventory - reservedUnits),
    reorderQuantity,
    safetyStock,
    lowStockCount: lowStockItems.length,
    sufficiency: isSufficient ? 'Sufficient' : 'Insufficient',
    sufficiencyClass: isSufficient ? 'sufficient' : 'insufficient',
    coverage: projectedDemand > 0 ? Math.round((currentInventory / projectedDemand) * 100) : (currentInventory > 0 ? 100 : 0),
  };
}

// Calculate SMS Monitoring metrics
function calculateSMSMonitoringAnalytics(alerts, inventoryAnalytics, notificationAnalytics) {
  // Low Stock Alerts
  const lowStockCount = inventoryAnalytics.lowStockItems.length;
  const lowStockCritical = inventoryAnalytics.lowStockItems.filter(item => 
    item.quantity < item.minimumLevel * 0.5
  ).length;
  
  // Reservation Alerts
  const pendingReservations = state.reservations.filter(r => r.status === 'pending').length;
  
  // Operational Alerts from the alerts panel
  const operationalAlerts = alerts.reduce((acc, group) => acc + group.items.length, 0);
  
  // Unread Notifications
  const unreadCount = notificationAnalytics.unreadCount;
  
  return {
    lowStock: {
      count: lowStockCount,
      critical: lowStockCritical,
      status: lowStockCritical > 0 ? 'Critical' : lowStockCount > 0 ? 'Warning' : 'OK',
    },
    reservationAlerts: {
      pending: pendingReservations,
      status: pendingReservations > 5 ? 'High' : pendingReservations > 0 ? 'Normal' : 'OK',
    },
    operationalAlerts: {
      count: operationalAlerts,
      status: operationalAlerts > 5 ? 'Critical' : operationalAlerts > 0 ? 'Warning' : 'OK',
    },
    unreadNotifications: {
      count: unreadCount,
      status: unreadCount > 10 ? 'High' : unreadCount > 0 ? 'Normal' : 'OK',
    },
  };
}

function calculateNotificationAnalytics(notifications) {
  const sortedNotifications = [...notifications].sort((left, right) => String(right.createdAt || '').localeCompare(String(left.createdAt || '')));
  return {
    unreadCount: notifications.filter(notification => !notification.read).length,
    recentNotifications: sortedNotifications.slice(0, 4),
  };
}

function calculateAlertAnalytics(inventoryAnalytics, deliveries, reports, notifications) {
  const lowInventoryAlerts = inventoryAnalytics.lowStockItems.map(item =>
    `${item.label}: ${formatNumber(item.quantity)} < ${formatNumber(item.minimumLevel)}`,
  );
  const deliveryAlerts = deliveries
    .filter(delivery => delivery.status !== 'completed')
    .map(delivery => `${delivery.route}: ${delivery.status.replace('_', ' ')} for ${delivery.customerName}`)
    .slice(0, 3);
  const reportAlerts = reports
    .filter(report => report.status !== 'Finalized')
    .map(report => `${report.title} is ${report.status.toLowerCase()}`)
    .slice(0, 3);
  const unreadAlerts = notifications
    .filter(notification => !notification.read)
    .map(notification => notification.title)
    .slice(0, 3);

  return [
    { title: 'Low Egg Stocks Alerts', badge: `${lowInventoryAlerts.length} critical`, items: lowInventoryAlerts },
    { title: 'Delivery Follow-ups', badge: `${deliveryAlerts.length} open`, items: deliveryAlerts },
    { title: 'Reports and Notices', badge: `${reportAlerts.length + unreadAlerts.length} pending`, items: [...reportAlerts, ...unreadAlerts].slice(0, 4) },
  ].filter(group => group.items.length);
}

function calculateLiveMetrics(userAnalytics, reservationAnalytics, deliveryAnalytics, reportAnalytics, activityLog) {
  const recentCutoff = Date.now() - (24 * 60 * 60 * 1000);
  const recentOperations = activityLog.filter(item => {
    const parsed = parseDate(item.createdAt);
    return parsed ? parsed.getTime() >= recentCutoff : false;
  }).length;

  return {
    headline: `${formatNumber(reservationAnalytics.activeReservations)} active reservations`,
    detail: `${formatNumber(recentOperations)} recent ops | ${formatNumber(deliveryAnalytics.pendingDeliveries)} pending deliveries | ${formatNumber(userAnalytics.activeUsers)} active users | ${formatNumber(reportAnalytics.drafts + reportAnalytics.inReview)} pending reports`,
  };
}

function calculateDashboardMetrics(analytics) {
  return [
    {
      key: 'customers',
      label: `Customers (${RETAINED_SCOPE_LABEL})`,
      value: formatNumber(analytics.reservationAnalytics.uniqueCustomers),
      badge: `${formatNumber(analytics.userAnalytics.activeUsers)} active users supporting accounts`,
      sub: `${formatCurrency(analytics.reservationAnalytics.totalRevenue)} reservation value`,
    },
    {
      key: 'reservations',
      label: `Reservations (${RETAINED_SCOPE_LABEL})`,
      value: formatNumber(analytics.reservationAnalytics.totalReservations),
      badge: `${formatNumber(analytics.reservationAnalytics.activeReservations)} active reservations`,
      sub: `${formatNumber(analytics.reservationAnalytics.totalQuantity)} eggs requested`,
    },
    {
      key: 'deliveries',
      label: `Deliveries (${RETAINED_SCOPE_LABEL})`,
      value: formatNumber(analytics.deliveryAnalytics.totalDeliveries),
      badge: `${formatPercent(analytics.deliveryAnalytics.completionRate)} completion rate`,
      sub: `${formatCurrency(analytics.deliveryAnalytics.totalRevenue)} realized revenue`,
    },
    {
      key: 'inventory',
      label: 'Total Egg Stocks',
      value: formatNumber(analytics.inventoryAnalytics.totalEggInventory),
      badge: `${analytics.inventoryAnalytics.inventoryHealth} inventory`,
      sub: `${formatNumber(analytics.inventoryAnalytics.availableInventory)} available after allocations • ${formatNumber(analytics.inventoryAnalytics.totalSupplyInventory)} supply units`,
    },
  ];
}

function getDashboardAnalytics() {
  // Use stateVersion for cache key instead of full state JSON
  if (state._dashboardAnalyticsCache?.version === state._stateVersion) {
    return state._dashboardAnalyticsCache.analytics;
  }

  const userAnalytics = calculateUserAnalytics(state.users);
  const reservationAnalytics = calculateReservationAnalytics(state.reservations);
  const deliveryAnalytics = calculateDeliveryAnalytics(state.deliveries);
  const inventoryAnalytics = calculateInventoryAnalytics(state.inventory, state.deliveries, state.reservations);
  const reportAnalytics = calculateReportAnalytics(state.reports);
  const forecastAnalytics = calculateForecastAnalytics(state.reservations, state.deliveries);
  const notificationAnalytics = calculateNotificationAnalytics(state.notifications);
  const alertAnalytics = calculateAlertAnalytics(inventoryAnalytics, state.deliveries, state.reports, state.notifications);
  const liveMetrics = calculateLiveMetrics(userAnalytics, reservationAnalytics, deliveryAnalytics, reportAnalytics, state.activityLog);
  const forecastInventory = calculateForecastInventory({ inventoryAnalytics, reservationAnalytics, deliveryAnalytics }, forecastAnalytics);
  const smsMonitoring = calculateSMSMonitoringAnalytics(alertAnalytics, inventoryAnalytics, notificationAnalytics);

  const analytics = {
    userAnalytics,
    reservationAnalytics,
    deliveryAnalytics,
    inventoryAnalytics,
    reportAnalytics,
    forecastAnalytics,
    notificationAnalytics,
    alertAnalytics,
    liveMetrics,
    forecastInventory,
    smsMonitoring,
  };

  analytics.dashboardMetrics = calculateDashboardMetrics(analytics);
  analytics.inventoryAnalytics.bestSeller = analytics.inventoryAnalytics.productsWithShare[0] || null;
  state._dashboardAnalyticsCache = { version: state._stateVersion, analytics };
  return analytics;
}

function getStatIcon(key) {
  if (key === 'customers') {
    return '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
  }
  if (key === 'reservations') {
    return '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>';
  }
  if (key === 'deliveries') {
    return '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>';
  }
  return '<ellipse cx="12" cy="9" rx="9" ry="5"/><path d="M3 9v7c0 2.76 4.03 5 9 5s9-2.24 9-5V9"/><path d="M3 13c0 2.76 4.03 5 9 5s9-2.24 9-5"/>';
}

// Render functions
function createResponsiveLineChart(primaryValues, secondaryValues, options = {}) {
  const width = options.width || 760;
  const height = options.height || 240;
  const padX = 42;
  const padTop = 18;
  const padBottom = 30;
  const plotW = width - padX * 2;
  const plotH = height - padTop - padBottom;
  const allValues = [...primaryValues, ...secondaryValues].map(Number).filter(Number.isFinite);
  const max = Math.max(...allValues, 1);
  const min = Math.min(...allValues, 0);
  const spread = Math.max(max - min, 1);
  const pointString = values => values.map((value, index) => {
    const x = padX + (plotW * index / Math.max(values.length - 1, 1));
    const y = padTop + plotH - (((Number(value) - min) / spread) * plotH);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(' ');
  const primaryPoints = pointString(primaryValues);
  const secondaryPoints = pointString(secondaryValues);
  const areaPoints = primaryValues.length ? `${padX},${height - padBottom} ${primaryPoints} ${width - padX},${height - padBottom}` : '';
  const grid = [0, .25, .5, .75, 1].map(ratio => {
    const y = padTop + plotH * ratio;
    return `<line x1="${padX}" y1="${y}" x2="${width - padX}" y2="${y}" class="chart-grid-line" />`;
  }).join('');
  const circles = primaryValues.map((value, index) => {
    const x = padX + (plotW * index / Math.max(primaryValues.length - 1, 1));
    const y = padTop + plotH - (((Number(value) - min) / spread) * plotH);
    return `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="4" class="chart-point" />`;
  }).join('');
  return `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true" focusable="false">
    ${grid}
    <polygon points="${areaPoints}" class="chart-area" />
    <polyline points="${secondaryPoints}" class="chart-secondary-line" />
    <polyline points="${primaryPoints}" class="chart-primary-line" />
    ${circles}
  </svg>`;
}

function renderDashboardStats(analytics) {
  const statsContainer = document.getElementById('dashboard-stats');
  if (!statsContainer) return;
  const metrics = analytics.dashboardMetrics;
  const iconMap = {
    customers: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    reservations: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    deliveries: '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
    inventory: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>'
  };
  statsContainer.innerHTML = metrics.map(item => `
    <article class="dashboard-kpi-card kpi-${item.key}">
      <div class="kpi-topline"><span class="kpi-icon"><svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">${iconMap[item.key] || iconMap.inventory}</svg></span><span class="kpi-label">${escapeHtml(item.label)}</span></div>
      <div class="kpi-value">${item.value}</div>
      <div class="kpi-status">${escapeHtml(item.badge)}</div>
      <div class="kpi-sub">${escapeHtml(item.sub)}</div>
    </article>
  `).join('');
}

function renderAlerts(analytics) {
  const alertsList = document.getElementById('alerts-list');
  const alertCount = document.getElementById('alert-count');
  if (!alertsList) return;
  const groups = analytics.alertAnalytics;
  const total = groups.reduce((sum, group) => sum + group.items.length, 0);
  if (alertCount) alertCount.textContent = total;
  if (!groups.length) {
    alertsList.innerHTML = `<div class="all-clear"><div class="all-clear-icon">✓</div><strong>Everything looks good</strong><span>No active operational alerts were detected.</span></div>`;
    return;
  }
  const iconFor = title => title.includes('Stock') ? '!' : title.includes('Delivery') ? '↗' : '•';
  alertsList.innerHTML = groups.map(group => `
    <div class="alert-block">
      <div class="alert-block-head"><span class="alert-icon">${iconFor(group.title)}</span><strong>${escapeHtml(group.title)}</strong><span class="alert-mini-badge">${escapeHtml(group.badge)}</span></div>
      <div class="alert-items">${group.items.map(item => `<div class="alert-modern-item">${escapeHtml(item)}</div>`).join('')}</div>
    </div>
  `).join('');
}

function renderReservationChart(analytics) {
  const chart = document.getElementById('reservation-trend-chart');
  const bars = document.getElementById('reservation-source-bars');
  const axis = document.getElementById('reservation-axis-labels');
  const kpi = document.getElementById('reservation-kpi');
  const trendValue = document.getElementById('reservation-trend-value');
  const topChannel = document.getElementById('reservation-top-channel');
  const insight = document.getElementById('reservation-insight');
  if (!chart || !bars || !axis) return;

  const reservationValues = analytics.reservationAnalytics.dailyReservations.map(point => point.value);
  const deliveryValues = analytics.deliveryAnalytics.deliverySeries.map(point => point.value);
  chart.innerHTML = reservationValues.some(Boolean)
    ? createResponsiveLineChart(reservationValues, deliveryValues)
    : '<div class="empty-state">No reservation activity in the last 7 days.</div>';
  axis.innerHTML = analytics.reservationAnalytics.dailyReservations.map(point => `<span>${escapeHtml(point.label)}</span>`).join('');

  const sourceBreakdown = analytics.reservationAnalytics.sourceBreakdown.slice(0, 5);
  const maxValue = Math.max(...sourceBreakdown.map(item => item.value), 1);
  bars.innerHTML = sourceBreakdown.length ? sourceBreakdown.map((item, index) => `
    <div class="channel-row"><span>${escapeHtml(item.label)}</span><div class="channel-track"><i style="width:${Math.max(6, (item.value / maxValue) * 100)}%"></i></div><strong>${formatNumber(item.value)}</strong></div>
  `).join('') : '<div class="empty-state">No booking channel data.</div>';

  if (kpi) kpi.textContent = formatNumber(analytics.reservationAnalytics.totalReservations);
  const recent = reservationValues.slice(-3).reduce((a, b) => a + b, 0);
  const previous = reservationValues.slice(-6, -3).reduce((a, b) => a + b, 0);
  const trend = recent > previous ? 'Increasing' : recent < previous ? 'Decreasing' : 'Stable';
  if (trendValue) trendValue.textContent = trend;
  const top = sourceBreakdown[0];
  if (topChannel) topChannel.textContent = top ? top.label : '—';
  if (insight) insight.textContent = top ? `${top.label} is the primary booking channel with ${formatNumber(top.value)} reservations in the retained dataset.` : 'Booking channel insights will appear when reservation data is available.';
}

function renderInventoryChart(analytics) {
  const chart = document.getElementById('inventory-flow-chart');
  const labels = document.getElementById('inventory-flow-labels');
  const kpi = document.getElementById('inventory-kpi');
  const available = document.getElementById('inventory-available');
  const reserved = document.getElementById('inventory-reserved');
  const badge = document.getElementById('inventory-health-badge');
  const insight = document.getElementById('inventory-insight');
  if (!chart || !labels) return;

  const inv = analytics.inventoryAnalytics;
  const max = Math.max(inv.stockIn, inv.stockOut, 1);
  chart.innerHTML = `
    <div class="flow-row"><div class="flow-row-head"><span>Stock in</span><strong>${formatNumber(inv.stockIn)}</strong></div><div class="flow-track"><i class="flow-in" style="width:${Math.max(5, (inv.stockIn / max) * 100)}%"></i></div></div>
    <div class="flow-row"><div class="flow-row-head"><span>Reserved</span><strong>${formatNumber(inv.reservedUnits)}</strong></div><div class="flow-track"><i class="flow-reserved" style="width:${Math.min(100, Math.max(5, (inv.reservedUnits / Math.max(inv.totalEggInventory, 1)) * 100))}%"></i></div></div>
    <div class="flow-row"><div class="flow-row-head"><span>Stock out</span><strong>${formatNumber(inv.stockOut)}</strong></div><div class="flow-track"><i class="flow-out" style="width:${Math.max(5, (inv.stockOut / max) * 100)}%"></i></div></div>
  `;
  labels.innerHTML = `<span>30-day movement</span><span>${inv.stockIn >= inv.stockOut ? 'Net building' : 'Net depleting'}</span>`;
  if (kpi) kpi.textContent = formatNumber(inv.totalEggInventory);
  if (available) available.textContent = formatNumber(inv.availableInventory);
  if (reserved) reserved.textContent = formatNumber(inv.reservedUnits);
  if (badge) {
    badge.textContent = inv.inventoryHealth;
    badge.className = `health-badge ${inv.lowStockCount ? 'warning' : 'healthy'}`;
  }
  if (insight) {
    const source = inv.inventorySource === 'api' ? 'live ledger' : 'local cache';
    insight.textContent = inv.lowStockCount > 0
      ? `${inv.lowStockCount} item(s) are below threshold from the ${source}. ${formatNumber(inv.reservedUnits)} eggs are reserved.`
      : `${formatNumber(inv.availableInventory)} eggs remain available after reservations from the ${source}.`;
  }
}

function renderSuppliesPanel(analytics) {
  const container = document.getElementById('supplies-overview');
  if (!container) return;
  const supplies = analytics.inventoryAnalytics.latestSupplies || [];
  if (!supplies.length) {
    container.innerHTML = '<div class="empty-state">No supply inventory rows returned from the ledger yet.</div>';
    return;
  }
  const inv = analytics.inventoryAnalytics;
  container.innerHTML = `
    <div class="supply-summary-strip">
      <div><span>Total units</span><strong>${formatNumber(inv.totalSupplyInventory)}</strong></div>
      <div><span>Stock in · 30d</span><strong>${formatNumber(inv.supplyStockIn)}</strong></div>
      <div><span>Stock out · 30d</span><strong>${formatNumber(inv.supplyStockOut)}</strong></div>
      <div class="supply-alert-stat"><span>Low stock</span><strong>${formatNumber(inv.lowStockSupplies.length)}</strong></div>
    </div>
    <div class="supply-table-wrap"><table class="supplies-modern-table"><thead><tr><th>Category</th><th>Item</th><th>Current stock</th><th>Batch</th></tr></thead><tbody>
      ${supplies.slice(0, 6).map(row => {
        const low = Number(row.current_stock) <= getSupplyMinimumLevel();
        return `<tr><td>${escapeHtml(row.item_category)}</td><td><strong>${escapeHtml(row.item_name)}</strong></td><td><span class="stock-value ${low ? 'low' : ''}">${formatNumber(row.current_stock)}</span></td><td>${escapeHtml(row.batch_id || '—')}</td></tr>`;
      }).join('')}
    </tbody></table></div>`;
}

function renderProductInsights(analytics) {
  const gauge = document.getElementById('product-insight-gauge');
  const bars = document.getElementById('product-insight-bars');
  const note = document.getElementById('product-insight-note');
  const kpi = document.getElementById('product-kpi');
  const leaderLabel = document.getElementById('product-leader');
  const trendIcon = document.getElementById('product-trend-icon');
  const trendValue = document.getElementById('product-trend-value');
  if (!gauge || !bars || !note) return;
  const leader = analytics.inventoryAnalytics.bestSeller;
  if (!leader) {
    gauge.innerHTML = '';
    bars.innerHTML = '<div class="empty-state">No completed delivery data yet.</div>';
    note.textContent = 'Product insights will appear once deliveries are completed.';
    if (kpi) kpi.textContent = '0%';
    if (leaderLabel) leaderLabel.textContent = 'No leader';
    return;
  }
  if (kpi) kpi.textContent = formatPercent(leader.share);
  if (leaderLabel) leaderLabel.textContent = leader.label;
  if (trendIcon && trendValue) {
    trendIcon.textContent = leader.share > 30 ? '↑' : '→';
    trendValue.textContent = leader.share > 30 ? 'Dominant' : leader.share > 15 ? 'Leading' : 'Competitive';
  }
  gauge.innerHTML = `<div class="leader-callout"><span>Top product</span><strong>${escapeHtml(leader.label)}</strong><b>${formatNumber(leader.value)} eggs</b></div>`;
  bars.innerHTML = analytics.inventoryAnalytics.productsWithShare.map((product, index) => `
    <div class="product-bar-row"><div class="product-bar-label"><span>${escapeHtml(product.label)}</span><strong>${formatPercent(product.share)}</strong></div><div class="product-bar-track"><i class="product-bar-fill ${index === 0 ? 'leader' : ''}" style="width:${Math.max(product.share, 4)}%;opacity:${product.opacity}"></i></div></div>
  `).join('');
  note.textContent = `${leader.label} leads with ${formatPercent(leader.share)} of completed delivery volume.`;
}

function renderForecast(analytics) {
  const chart = document.getElementById('forecast-summary-chart');
  const axis = document.getElementById('forecast-axis-labels');
  const predictions = document.getElementById('forecast-predictions');
  const inventorySection = document.getElementById('forecast-inventory-section');
  const kpi = document.getElementById('forecast-kpi');
  const insight = document.getElementById('forecast-insight');
  if (!chart || !axis || !predictions || !inventorySection) return;

  const demand = analytics.forecastAnalytics.demandSeries.map(point => point.value);
  const trend = analytics.forecastAnalytics.trendSeries.map(point => point.value);
  const hasData = demand.some(Boolean);
  const svg = chart.querySelector('svg');
  const empty = document.getElementById('forecast-summary-empty');
  if (svg) svg.classList.toggle('hidden', !hasData);
  if (empty) empty.classList.toggle('hidden', hasData);
  if (hasData && svg) svg.innerHTML = createResponsiveLineChart(demand, trend, { width: 760, height: 240 });
  axis.innerHTML = analytics.forecastAnalytics.demandSeries.map(point => `<span>${escapeHtml(point.label)}</span>`).join('');
  if (kpi) kpi.textContent = formatNumber(analytics.forecastAnalytics.nextForecast);
  const strongest = analytics.forecastAnalytics.strongestMonth;
  predictions.innerHTML = strongest
    ? `<strong>Peak historical demand: ${formatNumber(strongest.value)} units</strong><span>${escapeHtml(strongest.label)} · Next forecast is based on the trailing 3-month average.</span>`
    : '<span>No historical reservation demand is available yet.</span>';
  const f = analytics.forecastInventory;
  inventorySection.innerHTML = `
    <div class="readiness-header"><div><span class="card-kicker">INVENTORY READINESS</span><h3>${escapeHtml(f.sufficiency)}</h3></div><span class="readiness-badge ${f.sufficiencyClass}">${formatNumber(f.coverage)}% coverage</span></div>
    <div class="readiness-progress"><i style="width:${Math.min(100, Math.max(0, f.coverage))}%"></i></div>
    <div class="readiness-grid">
      <div><span>Projected demand</span><strong>${formatNumber(f.projectedDemand)}</strong></div>
      <div><span>Available now</span><strong>${formatNumber(f.currentInventory)}</strong></div>
      <div><span>Reorder quantity</span><strong class="${f.reorderQuantity > 0 ? 'danger-text' : 'success-text'}">${formatNumber(f.reorderQuantity)}</strong></div>
      <div><span>Safety stock</span><strong>${formatNumber(f.safetyStock)}</strong></div>
    </div>`;
  if (insight) insight.textContent = strongest ? `Peak demand is ${formatNumber(strongest.value)} units in ${strongest.label}; inventory readiness is currently ${f.sufficiency.toLowerCase()}.` : 'Demand forecasting based on historical reservation patterns.';
}

function renderSMSMonitoring(analytics) {
  const container = document.getElementById('sms-monitoring-content');
  if (!container) return;
  const d = analytics.smsMonitoring;
  const items = [
    ['Low stock', d.lowStock.count, d.lowStock.status],
    ['Pending reservations', d.reservationAlerts.pending, d.reservationAlerts.status],
    ['Operational alerts', d.operationalAlerts.count, d.operationalAlerts.status],
    ['Unread notifications', d.unreadNotifications.count, d.unreadNotifications.status],
  ];
  container.innerHTML = items.map(([label, value, status]) => `<div class="sms-modern-item ${String(status).toLowerCase()}"><span class="sms-dot"></span><div><span>${escapeHtml(label)}</span><strong>${formatNumber(value)}</strong></div><em>${escapeHtml(status)}</em></div>`).join('');
}

function renderNotifications(analytics) {
  const list = document.getElementById('notification-list');
  if (!list) return;
  const notifications = analytics.notificationAnalytics.recentNotifications;
  if (!notifications.length) {
    list.innerHTML = '<div class="empty-state">No notifications yet.</div>';
    return;
  }
  list.innerHTML = notifications.map(notification => `
    <div class="notification-modern ${notification.read ? 'read' : 'unread'}" data-notification-id="${notification.id}">
      <div class="notification-marker">${notification.read ? '✓' : '!'}</div>
      <div class="notification-body"><strong>${escapeHtml(notification.title)}</strong><span>${escapeHtml(notification.message || '')}</span><small>${getRelativeTimeLabel(notification.createdAt)}</small></div>
    </div>
  `).join('');
}

function renderActivityLog() {
  const list = document.getElementById('activity-log-list');
  if (!list) return;
  const activities = state.activityLog.slice(0, 6);
  if (!activities.length) {
    list.innerHTML = '<div class="empty-state">No recent activity.</div>';
    return;
  }
  list.innerHTML = activities.map(item => `
    <div class="timeline-item"><span class="timeline-dot"></span><div><strong>${escapeHtml(item.entity || 'System')}</strong><p>${escapeHtml(item.detail)}</p><small>${getRelativeTimeLabel(item.createdAt)}</small></div></div>
  `).join('');
}

function renderLiveMetric(analytics) {
  const liveMetric = document.getElementById('live-metric');
  const liveStatus = document.getElementById('dashboard-live-status');
  if (liveMetric) liveMetric.textContent = `${formatNumber(analytics.reservationAnalytics.activeReservations)} active reservations`;
  if (liveStatus) liveStatus.textContent = `${formatNumber(analytics.liveMetrics.headline.split(' ')[0] || 0)} active reservations · live operational data`;
}

// Report Category options
const REPORT_CATEGORIES = ['Inventory', 'Reservation', 'Delivery', 'Customer', 'Forecasting', 'Analytics'];

function renderReportCategoryOptions() {
  const select = document.getElementById('report-category-filter');
  if (!select) return;
  select.innerHTML = '<option value="all">All</option>' + 
    REPORT_CATEGORIES.map(cat => `<option value="${cat}">${cat}</option>`).join('');
}

function filterByPeriod(items, dateField, period) {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  
  return items.filter(item => {
    const itemDate = parseDate(item[dateField]);
    if (!itemDate) return false;
    
    switch (period) {
      case 'month':
        return itemDate.getMonth() === today.getMonth() && itemDate.getFullYear() === today.getFullYear();
      case '3months':
        const threeMonthsAgo = new Date(today);
        threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
        return itemDate >= threeMonthsAgo;
      case '6months':
        const sixMonthsAgo = new Date(today);
        sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6);
        return itemDate >= sixMonthsAgo;
      case 'year':
        return itemDate.getFullYear() === today.getFullYear();
      case 'lastyear':
        return itemDate.getFullYear() === today.getFullYear() - 1;
      default:
        return true;
    }
  });
}

function getFilteredReports() {
  let reservations = [...state.reservations];
  
  // Text search (customer name, product, channel)
  if (state.reportFilters.query) {
    const query = state.reportFilters.query.toLowerCase();
    reservations = reservations.filter(r => 
      (r.customerName && r.customerName.toLowerCase().includes(query)) || 
      (r.product && r.product.toLowerCase().includes(query)) ||
      (r.channel && r.channel.toLowerCase().includes(query))
    );
  }
  
  // Status filter
  if (state.reportFilters.status !== 'all') {
    reservations = reservations.filter(r => r.status === state.reportFilters.status);
  }
  
  // Egg size filter (extract size from product name)
  if (state.reportFilters.size !== 'all') {
    reservations = reservations.filter(r => {
      const size = extractEggSize(r.product);
      if (!size) return false;
      if (state.reportFilters.size === 'Jumbo') {
        return size === 'Jumbo';
      }
      return size === state.reportFilters.size;
    });
  }
  
  // Date filter
  if (state.reportFilters.date) {
    reservations = reservations.filter(r => {
      const dateStr = r.createdAt ? r.createdAt.split('T')[0] : '';
      return dateStr === state.reportFilters.date;
    });
  }
  
  // Period filter
  if (state.reportFilters.period !== 'all') {
    reservations = filterByPeriod(reservations, 'createdAt', state.reportFilters.period);
  }
  
  // Sort
  reservations.sort((a, b) => {
    if (state.reportSort === 'createdAt') {
      return String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
    }
    return String(a[state.reportSort] || '').localeCompare(String(b[state.reportSort] || ''));
  });
  
  return reservations;
}

function renderReportsTable() {
  const tbody = document.getElementById('reports-table-body');
  const pagination = document.getElementById('reports-pagination');
  if (!tbody) return;
  
  const reservations = getFilteredReports();
  const totalPages = Math.ceil(reservations.length / state.perPage) || 1;
  state.reportPage = Math.min(state.reportPage, totalPages);
  const startIndex = (state.reportPage - 1) * state.perPage;
  const pageReservations = reservations.slice(startIndex, startIndex + state.perPage);
  
  if (pageReservations.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No reservations found.</td></tr>';
  } else {
    tbody.innerHTML = pageReservations.map(r => `
      <tr>
        <td>${escapeHtml(r.createdAt ? r.createdAt.split('T')[0] : '')}</td>
        <td>${escapeHtml(r.customerName || '')}</td>
        <td>${escapeHtml(r.product || '')}</td>
        <td>${formatNumber(r.quantity || 0)}</td>
        <td>${formatCurrency(r.amount || 0)}</td>
        <td>${renderStatusPill(r.status)}</td>
        <td>${escapeHtml(r.channel || '')}</td>
      </tr>
    `).join('');
  }
  
  // Pagination
  if (pagination) {
    let pagesHtml = '';
    for (let i = 1; i <= totalPages; i++) {
      pagesHtml += `<button class="${i === state.reportPage ? 'active' : ''}" data-report-page="${i}" type="button">${i}</button>`;
    }
    pagination.innerHTML = pagesHtml;
  }
}

function getFilteredUsers() {
  // Manager Accounts table only shows Managers
  let users = state.users.filter(u => u.role === 'Manager');

  // Text search
  if (state.userFilters.query) {
    const query = state.userFilters.query.toLowerCase();
    users = users.filter(u =>
      u.name.toLowerCase().includes(query) ||
      u.email.toLowerCase().includes(query) ||
      u.department.toLowerCase().includes(query)
    );
  }

  // Status filter
  if (state.userFilters.status !== 'all') {
    users = users.filter(u => u.status === state.userFilters.status);
  }

  // Sort
  users.sort((a, b) => String(a[state.userSort] || '').localeCompare(String(b[state.userSort] || '')));

  return users;
}

function renderUsersTable() {
  const tbody = document.getElementById('users-table-body');
  const pagination = document.getElementById('users-pagination');
  const countLabel = document.getElementById('users-count');
  if (!tbody) return;
  
  const users = getFilteredUsers();
  if (countLabel) {
    countLabel.textContent = `${users.length} results`;
  }
  
  const totalPages = Math.ceil(users.length / state.perPage) || 1;
  state.userPage = Math.min(state.userPage, totalPages);
  const startIndex = (state.userPage - 1) * state.perPage;
  const pageUsers = users.slice(startIndex, startIndex + state.perPage);
  
  if (pageUsers.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No users found.</td></tr>';
  } else {
    tbody.innerHTML = pageUsers.map(user => `
      <tr>
        <td>${escapeHtml(user.name)}<br/><small>${escapeHtml(user.email)}</small></td>
        <td>${escapeHtml(user.department)}</td>
        <td>${escapeHtml(user.role)}</td>
        <td><span class="status-${user.status}">${escapeHtml(user.status)}</span></td>
        <td>
          <button class="toggle-sm ${user.status === 'active' ? 'on' : 'off'}" 
                  data-action="toggle-user-status" 
                  data-user-id="${user.id}"
                  type="button"
                  aria-label="${user.status === 'active' ? 'Deactivate' : 'Activate'} user"></button>
        </td>
        <td class="action-btns">
          <button class="btn-edit" data-action="edit-user" data-user-id="${user.id}" type="button">Edit</button>
          <button class="btn-del" data-action="delete-user" data-user-id="${user.id}" type="button">Delete</button>
        </td>
      </tr>
    `).join('');
  }
  
  // Pagination
  if (pagination) {
    let pagesHtml = '';
    for (let i = 1; i <= totalPages; i++) {
      pagesHtml += `<button class="${i === state.userPage ? 'active' : ''}" data-user-page="${i}" type="button">${i}</button>`;
    }
    pagination.innerHTML = pagesHtml;
  }

  renderGlobalControls();
}

function renderGlobalControls() {
  const activateBtn = document.querySelector('[data-action="global-activate-all"]');
  const deactivateBtn = document.querySelector('[data-action="global-deactivate-all"]');
  if (!activateBtn || !deactivateBtn) return;

  const managers = state.users.filter(u => u.role === 'Manager');
  const allActive = managers.length > 0 && managers.every(u => u.status === 'active');
  const allDeactivated = managers.length > 0 && managers.every(u => u.status === 'deactivated');

  // Activate All: ON when all managers are already active
  activateBtn.classList.toggle('on', allActive);
  activateBtn.classList.toggle('off', !allActive);

  // Deactivate All: ON when all managers are already deactivated
  deactivateBtn.classList.toggle('on', allDeactivated);
  deactivateBtn.classList.toggle('off', !allDeactivated);
}

// Render read-only Customer Accounts table
function renderCustomerAccounts() {
  const container = document.getElementById('customer-accounts-content');
  if (!container) return;
  
  const customers = state.users.filter(u => u.role === 'Customer');
  
  if (customers.length === 0) {
    container.innerHTML = '<div class="empty-state">No customer accounts found.</div>';
    return;
  }
  
  container.innerHTML = `
    <table class="um-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Department</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        ${customers.map(customer => `
          <tr>
            <td>${escapeHtml(customer.name)}</td>
            <td>${escapeHtml(customer.email)}</td>
            <td>${escapeHtml(customer.department)}</td>
            <td><span class="status-${customer.status}">${escapeHtml(customer.status)}</span></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

// Generate professional business report HTML
function generateReportHTML() {
  const reservations = getFilteredReports();
  const analytics = getDashboardAnalytics();
  const profile = state.profile;
  const now = new Date();
  const dateStr = now.toLocaleDateString();
  const timeStr = now.toLocaleTimeString();
  const ownerName = profile.fullName || 'Administrator';

  // Get current filter metadata
  const dateFilter = document.getElementById('report-date-filter')?.value || 'All';
  const statusFilter = document.getElementById('report-status-filter')?.value || 'All';
  const eggSizeFilter = document.getElementById('report-size-filter')?.value || 'All';
  const dateRange = dateFilter !== 'All' ? dateFilter : 'All time';

  // Get KPI data from analytics
  const resAnalytics = analytics.reservationAnalytics;
  const delAnalytics = analytics.deliveryAnalytics;
  const invAnalytics = analytics.inventoryAnalytics;

  const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>VDVC Business Analytics Report</title>
  <style>
    @page { 
      size: A4 landscape; 
      margin: 1cm;
      @bottom-left {
        content: "VDVC Business Analytics Report";
        font-size: 9pt;
        color: #555;
      }
      @bottom-center {
        content: "Generated by VDVC | Confidential";
        font-size: 9pt;
        color: #555;
      }
      @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-size: 9pt;
        color: #555;
      }
    }
    body { 
      font-family: 'Arial', sans-serif; 
      font-size: 10pt; 
      line-height: 1.4; 
      color: #333;
    }
    .report-header { 
      border-bottom: 3px solid #3a5fa0; 
      padding-bottom: 15px; 
      margin-bottom: 25px; 
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .logo { width: 80px; height: auto; }
    .header-text { flex-grow: 1; }
    .header-text h1 { 
      font-size: 20pt; 
      margin: 0 0 5px 0; 
      color: #3a5fa0;
    }
    .header-text h2 { 
      font-size: 14pt; 
      margin: 0 0 10px 0; 
      color: #666;
      font-weight: normal;
    }
    .metadata-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 8px;
      font-size: 9.5pt;
    }
    .metadata-item { display: flex; gap: 5px; }
    .metadata-label { font-weight: bold; color: #555; }
    
    .section { margin-bottom: 30px; page-break-inside: avoid; }
    .section-title { 
      font-size: 14pt; 
      color: #3a5fa0; 
      border-bottom: 1px solid #ddd; 
      padding-bottom: 5px; 
      margin-bottom: 15px;
    }
    
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
    }
    .kpi-card {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      padding: 12px;
      border-radius: 6px;
    }
    .kpi-label { font-size: 9pt; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-value { font-size: 16pt; font-weight: bold; color: #3a5fa0; margin: 4px 0; }
    .kpi-badge { font-size: 9pt; color: #555; }
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-top: 10px;
    }
    th { 
      background: #3a5fa0; 
      color: white; 
      font-weight: bold; 
      text-align: left; 
      padding: 10px; 
      border: 1px solid #2d4b7a;
    }
    td { 
      padding: 10px; 
      border: 1px solid #ddd; 
      vertical-align: top;
    }
    tr:nth-child(even) { background: #f8f9fa; }
    
    .status { padding: 3px 10px; border-radius: 12px; font-size: 8.5pt; font-weight: 500; }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #dbeafe; color: #1d4ed8; }
    .status-completed { background: #d4f5e2; color: #1a8a4a; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }
    
    .summary-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .summary-card {
      padding: 15px;
      background: #f8f9fa;
      border-radius: 6px;
    }
    .summary-item { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #eee; }
    .summary-item:last-child { border-bottom: none; }
  </style>
</head>
<body>
  <!-- Report Header -->
  <div class="report-header">
    <img src="vdvc_logo.png" alt="VDVC" class="logo">
    <div class="header-text">
      <h1>VDVC</h1>
      <h2>Business Analytics Report</h2>
      <div class="metadata-grid">
        <div class="metadata-item"><span class="metadata-label">Generated:</span><span>${dateStr} ${timeStr}</span></div>
        <div class="metadata-item"><span class="metadata-label">Prepared By:</span><span>${escapeHtml(ownerName)}</span></div>
        <div class="metadata-item"><span class="metadata-label">Date Range:</span><span>${escapeHtml(dateRange)}</span></div>
        <div class="metadata-item"><span class="metadata-label">Status:</span><span>${escapeHtml(statusFilter)}</span></div>
        <div class="metadata-item"><span class="metadata-label">Egg Size:</span><span>${escapeHtml(eggSizeFilter)}</span></div>
      </div>
    </div>
  </div>

  <!-- Executive Summary -->
  <div class="section">
    <div class="section-title">Executive Summary</div>
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">Total Reservations</div>
        <div class="kpi-value">${formatNumber(resAnalytics.totalReservations)}</div>
        <div class="kpi-badge">${formatNumber(resAnalytics.totalQuantity)} eggs requested</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Total Eggs Reserved</div>
        <div class="kpi-value">${formatNumber(resAnalytics.totalQuantity)}</div>
        <div class="kpi-badge">${formatCurrency(resAnalytics.totalRevenue)} value</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-value">${formatCurrency(resAnalytics.totalRevenue)}</div>
        <div class="kpi-badge">${formatCurrency(delAnalytics.totalRevenue)} realized</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Completed Deliveries</div>
        <div class="kpi-value">${formatNumber(delAnalytics.totalDeliveries)}</div>
        <div class="kpi-badge">${formatPercent(delAnalytics.completionRate)} completion</div>
      </div>
    </div>
    <div class="summary-grid" style="margin-top: 15px;">
      <div class="summary-card">
        <div class="summary-item">
          <span>Pending Reservations</span>
          <span style="font-weight: bold; color: #d97706;">${formatNumber(resAnalytics.pendingReservations)}</span>
        </div>
        <div class="summary-item">
          <span>Cancelled Reservations</span>
          <span style="font-weight: bold; color: #dc2626;">${formatNumber(resAnalytics.cancelledReservations)}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-item">
          <span>Low Stock Items</span>
          <span style="font-weight: bold; color: #d97706;">${formatNumber(invAnalytics.lowStockCount)}</span>
        </div>
        <div class="summary-item">
          <span>Total Inventory</span>
          <span style="font-weight: bold; color: #3a5fa0;">${formatNumber(invAnalytics.totalEggInventory)} eggs</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Reservation Statistics -->
  <div class="section">
    <div class="section-title">Reservation Statistics</div>
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-item">
          <span>Active Reservations</span>
          <span style="font-weight: bold;">${formatNumber(resAnalytics.activeReservations)}</span>
        </div>
        <div class="summary-item">
          <span>Completed Reservations</span>
          <span style="font-weight: bold; color: #1a8a4a;">${formatNumber(resAnalytics.completedReservations)}</span>
        </div>
        <div class="summary-item">
          <span>Unique Customers</span>
          <span style="font-weight: bold;">${formatNumber(resAnalytics.uniqueCustomers)}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-item">
          <span>Confirmed Status</span>
          <span style="font-weight: bold; color: #1d4ed8;">${formatNumber(resAnalytics.confirmedReservations)}</span>
        </div>
        <div class="summary-item">
          <span>Completion Rate</span>
          <span style="font-weight: bold; color: #3a5fa0;">${formatPercent(delAnalytics.completionRate)}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory Statistics -->
  <div class="section">
    <div class="section-title">Inventory Statistics</div>
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-item">
          <span>Total Egg Stock</span>
          <span style="font-weight: bold;">${formatNumber(invAnalytics.totalEggInventory)}</span>
        </div>
        <div class="summary-item">
          <span>Available After Allocation</span>
          <span style="font-weight: bold; color: #3a5fa0;">${formatNumber(invAnalytics.availableInventory)}</span>
        </div>
        <div class="summary-item">
          <span>Inventory Health</span>
          <span style="font-weight: bold;">${escapeHtml(invAnalytics.inventoryHealth)}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-item">
          <span>Low Stock Items</span>
          <span style="font-weight: bold; color: #d97706;">${formatNumber(invAnalytics.lowStockCount)}</span>
        </div>
        <div class="summary-item">
          <span>Best Seller</span>
          <span style="font-weight: bold; color: #1a8a4a;">${invAnalytics.bestSeller ? escapeHtml(invAnalytics.bestSeller.label) : 'N/A'}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Detailed Reservation Report -->
  <div class="section">
    <div class="section-title">Detailed Reservation Report</div>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Customer</th>
          <th>Egg Size</th>
          <th>Quantity</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Channel</th>
        </tr>
      </thead>
      <tbody>
        ${reservations.map(r => `
          <tr>
            <td>${escapeHtml(r.createdAt ? r.createdAt.split('T')[0] : '')}</td>
            <td>${escapeHtml(r.customerName || '')}</td>
            <td>${escapeHtml(r.product || '')}</td>
            <td>${formatNumber(r.quantity || 0)}</td>
            <td>${formatCurrency(r.amount || 0)}</td>
            <td><span class="status status-${r.status}">${escapeHtml(r.status)}</span></td>
            <td>${escapeHtml(r.channel || '')}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  </div>
</body>
</html>`;
  return htmlContent;
}

// PDF Export functionality
function handlePDFExport() {
  const reservations = getFilteredReports();
  if (reservations.length === 0) {
    showToast('No reservations to export.', 'error');
    return;
  }
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    showToast('Popup blocked. Please allow popups for this site.', 'error');
    return;
  }
  
  const htmlContent = generateReportHTML();
  
  printWindow.document.write(htmlContent);
  printWindow.document.close();
  
  // Auto-trigger print in the new window
  printWindow.onload = function() {
    setTimeout(() => {
      printWindow.print();
    }, 300);
  };
  
  // Log activity
  addActivity('report', 'exported', `Exported ${reservations.length} reservations to PDF`);
  showToast(`PDF export opened with ${reservations.length} reservations.`, 'success');
}

// Print reports functionality
function handlePrintReports() {
  const reservations = getFilteredReports();
  if (reservations.length === 0) {
    showToast('No reservations to print.', 'error');
    return;
  }
  
  // Save original page state
  const originalPage = state.activePage;
  
  // Open print window with shared report template
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    showToast('Popup blocked. Please allow popups for this site.', 'error');
    return;
  }
  
  const htmlContent = generateReportHTML();
  printWindow.document.write(htmlContent);
  printWindow.document.close();
  
  // Auto-trigger print
  printWindow.onload = function() {
    setTimeout(() => {
      printWindow.print();
    }, 300);
  };
  
  // Log activity
  addActivity('report', 'printed', `Printed ${reservations.length} reservations`);
  showToast('Print dialog opened.', 'success');
}

// Navigation
function navigateToPage(pageName) {
  state.activePage = pageName;
  
  // Update nav items
  document.querySelectorAll('.nav-item').forEach(item => {
    if (item.dataset.page === pageName) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });
  
  // Show/hide pages
  document.querySelectorAll('.page').forEach(page => {
    page.classList.remove('active');
  });
  const targetPage = document.getElementById(`page-${pageName}`);
  if (targetPage) {
    targetPage.classList.add('active');
  }
  
  // Render page-specific content
  if (pageName === 'dashboard') {
    renderDashboard();
  } else if (pageName === 'users') {
    renderUsersTable();
    renderCustomerAccounts();
  } else if (pageName === 'reports') {
    renderReportsTable();
  } else if (pageName === 'profile') {
    renderProfilePage();
  }
}

// Dashboard rendering
function renderDashboard() {
  if (state.activePage !== 'dashboard') return;
  const analytics = getDashboardAnalytics();
  renderDashboardStats(analytics);
  renderAlerts(analytics);
  renderReservationChart(analytics);
  renderInventoryChart(analytics);
  renderSuppliesPanel(analytics);
  renderProductInsights(analytics);
  renderForecast(analytics);
  renderNotifications(analytics);
  renderLiveMetric(analytics);
  renderActivityLog();
  renderSMSMonitoring(analytics);
}

// Profile page rendering
function renderProfilePage() {
  const profile = state.profile;
  
  // Set form values
  const fullNameInput = document.getElementById('profile-full-name');
  const emailInput = document.getElementById('profile-email');
  const phoneInput = document.getElementById('profile-phone');
  const jobTitleInput = document.getElementById('profile-job-title');
  const avatarPreview = document.getElementById('profile-avatar-preview');
  
  if (fullNameInput) fullNameInput.value = profile.fullName || '';
  if (emailInput) emailInput.value = profile.email || '';
  if (phoneInput) phoneInput.value = profile.phone || '';
  if (jobTitleInput) jobTitleInput.value = profile.jobTitle || '';
  
  if (avatarPreview) {
    if (profile.avatar) {
      avatarPreview.innerHTML = `<img src="${escapeHtml(profile.avatar)}" alt="Avatar" />`;
    } else {
      const initials = getInitials(profile.fullName || 'Admin');
      avatarPreview.textContent = initials;
    }
  }
}

function closeOpenModals() {
  document.querySelectorAll('.modal-overlay').forEach(modal => modal.classList.remove('open'));
}

function handleDelegatedUiClick(event) {
  const closeTrigger = event.target.closest('.modal-close, [data-close-modal]');
  if (closeTrigger) {
    closeOpenModals();
    return;
  }

  const userPageButton = event.target.closest('[data-user-page]');
  if (userPageButton) {
    state.userPage = Math.max(1, Number(userPageButton.dataset.userPage) || 1);
    renderUsersTable();
    return;
  }

  const reportPageButton = event.target.closest('[data-report-page]');
  if (reportPageButton) {
    state.reportPage = Math.max(1, Number(reportPageButton.dataset.reportPage) || 1);
    renderReportsTable();
  }
}

// Event delegation handler
function handleAction(event) {
  const target = event.target.closest('[data-action]');
  if (!target) return;
  
  const action = target.dataset.action;
  
  switch (action) {
    case 'toggle-user-status': {
      const userId = parseInt(target.dataset.userId, 10);
      const user = state.users.find(u => u.id === userId);
      if (user && user.role === 'Manager') {
        const newStatus = user.status === 'active' ? 'deactivated' : 'active';
        if (confirmAction(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} ${user.name}?`)) {
          const updatedUsers = state.users.map(u => u.id === userId ? { ...u, status: newStatus } : u);
          if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
            showToast(`User ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully.`, 'success');
            addActivity('user', newStatus === 'active' ? 'activated' : 'deactivated', `${user.name} was ${newStatus}.`);
            renderUsersTable();
            renderDashboard();
          }
        }
      }
      break;
    }
    
    case 'delete-user': {
      const userId = parseInt(target.dataset.userId, 10);
      const user = state.users.find(u => u.id === userId);
      if (user && user.role === 'Manager') {
        if (confirmAction(`Are you sure you want to delete ${user.name}?`)) {
          const updatedUsers = state.users.filter(u => u.id !== userId);
          if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
            showToast('User deleted successfully.', 'success');
            addActivity('user', 'deleted', `${user.name} was deleted.`);
            renderUsersTable();
            renderDashboard();
          }
        }
      }
      break;
    }
    
    case 'edit-user': {
      const userId = parseInt(target.dataset.userId, 10);
      const user = state.users.find(u => u.id === userId);
      if (user) {
        // Populate and show user modal
        document.getElementById('user-modal-title').textContent = 'Edit User';
        document.getElementById('user-name').value = user.name;
        document.getElementById('user-email').value = user.email;
        document.getElementById('user-department').value = user.department;
        document.getElementById('user-role').value = user.role;
        document.getElementById('user-status').value = user.status;
        document.getElementById('user-form').dataset.editId = userId;
        document.getElementById('user-modal').classList.add('open');
      }
      break;
    }
    
    case 'global-activate-all': {
      const managerUsers = state.users.filter(u => u.role === 'Manager');
      if (managerUsers.length === 0) {
        showToast('No manager accounts to activate.', 'info');
        return;
      }
      if (confirmAction(`Are you sure you want to activate all ${managerUsers.length} manager accounts?`)) {
        const updatedUsers = state.users.map(u => u.role === 'Manager' ? { ...u, status: 'active' } : u);
        if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
          showToast('All manager accounts activated.', 'success');
          addActivity('user', 'bulk-activated', `All ${managerUsers.length} manager accounts activated.`);
          renderUsersTable();
          renderDashboard();
        }
      }
      break;
    }
    
    case 'global-deactivate-all': {
      const managerUsers = state.users.filter(u => u.role === 'Manager');
      if (managerUsers.length === 0) {
        showToast('No manager accounts to deactivate.', 'info');
        return;
      }
      if (confirmAction(`Are you sure you want to deactivate all ${managerUsers.length} manager accounts?`)) {
        const updatedUsers = state.users.map(u => u.role === 'Manager' ? { ...u, status: 'deactivated' } : u);
        if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
          showToast('All manager accounts deactivated.', 'success');
          addActivity('user', 'bulk-deactivated', `All ${managerUsers.length} manager accounts deactivated.`);
          renderUsersTable();
          renderDashboard();
        }
      }
      break;
    }
    
    case 'preview-report': {
      const reportId = parseInt(target.dataset.reportId, 10);
      const report = state.reports.find(r => r.id === reportId);
      if (report) {
        document.getElementById('preview-title').textContent = report.title;
        document.getElementById('preview-category').textContent = report.category;
        document.getElementById('preview-date').textContent = report.date;
        document.getElementById('preview-status').innerHTML = renderStatusPill(report.status);
        document.getElementById('report-preview-modal').classList.add('open');
      }
      break;
    }
    
    case 'download-report': {
      const reportId = parseInt(target.dataset.reportId, 10);
      const report = state.reports.find(r => r.id === reportId);
      if (report) {
        const csvContent = `Title,Category,Date,Status\n"${report.title}","${report.category}","${report.date}","${report.status}"`;
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${report.title.replace(/\s+/g, '_')}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('Report downloaded.', 'success');
        addActivity('report', 'downloaded', `Downloaded report: ${report.title}`);
      }
      break;
    }
    
    case 'close-modal':
      closeOpenModals();
      break;
  }
}

// DEVELOPER ONLY: Generate Sample Data
function generateSampleData() {
  if (!confirmAction('This will replace existing reservations and deliveries with sample data. Continue?')) {
    return;
  }

  const customers = [
    'Sunrise Grocers', 'Metro Mart', 'Quezon Retail Hub', 'Laguna Foods',
    'Vista Fresh', 'Southline Traders', 'Cebu Family Store', 'Northpoint Market',
    'Bayanihan Foods', 'Manila Distribution Co.', 'Cavite Egg Suppliers',
    'Batangas Retailers', 'Bulacan Grocers', 'Rizal Market', 'Quezon City Stores'
  ];

  const eggSizes = ['XS', 'Small', 'Medium', 'Large', 'XL', 'Jumbo', 'Super Jumbo', 'Double Yolk'];
  const eggGrades = ['A', 'B', 'C'];
  const statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
  const channels = ['Portal', 'Sales Team', 'Phone', 'Walk-in'];
  const today = new Date();

  // Generate 40 reservations
  const sampleReservations = [];
  for (let i = 0; i < 40; i++) {
    const daysAgo = Math.floor(Math.random() * 45);
    const date = new Date(today);
    date.setDate(today.getDate() - daysAgo);
    const dateStr = toDateTimeKey(date);

    const customer = customers[Math.floor(Math.random() * customers.length)];
    const size = eggSizes[Math.floor(Math.random() * eggSizes.length)];
    const grade = eggGrades[Math.floor(Math.random() * eggGrades.length)];
    const quantity = Math.floor(Math.random() * 500) + 50;
    const unitPrice = grade === 'A' ? 14 : grade === 'B' ? 10 : 8;
    const amount = quantity * unitPrice;
    const status = statuses[Math.floor(Math.random() * statuses.length)];
    const channel = channels[Math.floor(Math.random() * channels.length)];

    sampleReservations.push({
      id: 10000 + i,
      customerName: customer,
      product: `Grade ${grade} ${size}`,
      quantity: quantity,
      amount: amount,
      status: status,
      channel: channel,
      createdAt: dateStr,
      scheduledAt: dateStr
    });
  }

  // Generate 30 deliveries from completed reservations
  const completedReservations = sampleReservations.filter(r => r.status === 'completed');
  const sampleDeliveries = completedReservations.slice(0, 30).map((r, i) => {
    const deliveryDate = new Date(r.createdAt);
    deliveryDate.setDate(deliveryDate.getDate() + 2);
    return {
      id: 20000 + i,
      reservationId: r.id,
      customerName: r.customerName,
      product: r.product,
      quantity: r.quantity,
      revenue: r.amount,
      status: 'completed',
      route: ['North-1', 'Metro-4', 'East-2', 'South-1', 'Cebu-2'][Math.floor(Math.random() * 5)],
      createdAt: toDateTimeKey(deliveryDate),
      completedAt: toDateTimeKey(deliveryDate)
    };
  });

  // Update state with new data
  setCollection('reservations', STORAGE_KEYS.reservations, sampleReservations);
  setCollection('deliveries', STORAGE_KEYS.deliveries, sampleDeliveries);

  // Refresh all dashboards/reports
  invalidateDashboardAnalytics();
  renderDashboard();
  renderReportsTable();

  showToast('Generated 40 sample reservations and 30 sample deliveries!', 'success');
  addActivity('system', 'generated-sample-data', 'Generated sample test data for development');
}

// Export function for CSV
function exportCSV() {
  const reservations = getFilteredReports();
  const analytics = getDashboardAnalytics();
  
  if (reservations.length === 0) {
    showToast('No reservations to export.', 'error');
    return;
  }
  
  // Summary section
  const summarySection = [
    'SUMMARY',
    'Metric,Value',
    `Reservations (${RETAINED_SCOPE_LABEL}),${analytics.reservationAnalytics.totalReservations}`,
    `Eggs Reserved (${RETAINED_SCOPE_LABEL}),${analytics.reservationAnalytics.totalQuantity}`,
    `Reservation Value (${RETAINED_SCOPE_LABEL}),${analytics.reservationAnalytics.totalRevenue}`,
    `Completed Deliveries (${RETAINED_SCOPE_LABEL}),${analytics.deliveryAnalytics.completedDeliveries}`,
    `Delivery Completion Rate (${RETAINED_SCOPE_LABEL}),"${formatPercent(analytics.deliveryAnalytics.completionRate)}"`,
    `Low Stock Items,${analytics.inventoryAnalytics.lowStockItems.length}`
  ];
  
  // Detailed reservations section
  const detailedHeaders = ['Date', 'Customer', 'Egg Size', 'Quantity', 'Amount', 'Status', 'Channel'];
  const detailedRows = reservations.map(r => [
    `"${r.createdAt ? r.createdAt.split('T')[0] : ''}"`,
    `"${r.customerName || ''}"`,
    `"${r.product || ''}"`,
    r.quantity || 0,
    r.amount || 0,
    `"${r.status || ''}"`,
    `"${r.channel || ''}"`
  ]);
  const detailedSection = [
    '',
    'DETAILED RESERVATIONS',
    detailedHeaders.join(','),
    ...detailedRows.map(r => r.join(','))
  ];
  
  // Combine all sections
  const csvContent = [...summarySection, ...detailedSection].join('\n');
  
  const blob = new Blob([csvContent], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `vdvc_export_${new Date().toISOString().split('T')[0]}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  
  showToast(`Exported ${reservations.length} reservations and summary metrics.`, 'success');
  addActivity('report', 'exported', `Exported ${reservations.length} reservations and summary metrics to CSV.`);
}

// Initialize notification badge
function initNotificationBadge() {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  
  const analytics = getDashboardAnalytics();
  const unreadCount = analytics.notificationAnalytics.unreadCount;
  
  badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
  badge.style.display = unreadCount > 0 ? 'flex' : 'none';
}

// Mark all notifications as read
function markAllNotificationsRead() {
  const updatedNotifications = state.notifications.map(n => ({ ...n, read: true }));
  if (setCollection('notifications', STORAGE_KEYS.notifications, updatedNotifications)) {
    showToast('All notifications marked as read.', 'success');
    addActivity('notification', 'marked-read', 'Marked all notifications as read.');
    const analytics = getDashboardAnalytics();
    renderNotifications(analytics);
    initNotificationBadge();
    renderDashboard();
  }
}

// Setup event listeners
function setupEventListeners() {
  // Sidebar navigation
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      const page = item.dataset.page;
      if (page) {
        navigateToPage(page);
      }
    });
  });
  
  // Sidebar toggle
  const sidebarToggle = document.querySelector('.sidebar-toggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
  }
  
  // Sidebar overlay
  const sidebarOverlay = document.querySelector('.sidebar-overlay');
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
      document.querySelector('.sidebar').classList.remove('open');
      sidebarOverlay.classList.add('hidden');
    });
  }
  
  // Event delegation for actions
  document.addEventListener('click', handleAction);
  document.addEventListener('click', handleDelegatedUiClick);
  
  // Add user button
  const addUserBtn = document.getElementById('add-user-btn');
  if (addUserBtn) {
    addUserBtn.addEventListener('click', () => {
      document.getElementById('user-modal-title').textContent = 'Add Manager Account';
      document.getElementById('user-form').reset();
      delete document.getElementById('user-form').dataset.editId;
      document.getElementById('user-modal').classList.add('open');
    });
  }
  
  // User form submit
  const userForm = document.getElementById('user-form');
  if (userForm) {
    userForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const formData = new FormData(userForm);
      const editId = userForm.dataset.editId;
      
      const userData = {
        name: formData.get('name'),
        email: formData.get('email'),
        department: formData.get('department'),
        role: formData.get('role'),
        status: formData.get('status'),
      };
      
      if (editId) {
        // Edit existing user
        const userId = parseInt(editId, 10);
        const updatedUsers = state.users.map(u => u.id === userId ? { ...u, ...userData } : u);
        if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
          showToast('User updated successfully.', 'success');
          addActivity('user', 'updated', `Updated user: ${userData.name}`);
          document.getElementById('user-modal').classList.remove('open');
          renderUsersTable();
          renderDashboard();
        }
      } else {
        // Create new user
        const newId = Math.max(...state.users.map(u => u.id), 0) + 1;
        const newUser = { id: newId, ...userData };
        const updatedUsers = [...state.users, newUser];
        if (setCollection('users', STORAGE_KEYS.users, updatedUsers)) {
          showToast('User created successfully.', 'success');
          addActivity('user', 'created', `Created user: ${userData.name}`);
          document.getElementById('user-modal').classList.remove('open');
          renderUsersTable();
          renderDashboard();
        }
      }
    });
  }
  
  // User search
  const userSearch = document.getElementById('user-search');
  if (userSearch) {
    userSearch.addEventListener('input', (e) => {
      state.userFilters.query = e.target.value;
      state.userPage = 1;
      renderUsersTable();
    });
  }
  
  // User status filter
  const userStatusFilter = document.getElementById('user-status-filter');
  if (userStatusFilter) {
    userStatusFilter.addEventListener('change', (e) => {
      state.userFilters.status = e.target.value;
      state.userPage = 1;
      renderUsersTable();
    });
  }
  
  // User role filter
  const userRoleFilter = document.getElementById('user-role-filter');
  if (userRoleFilter) {
    userRoleFilter.addEventListener('change', (e) => {
      state.userFilters.role = e.target.value;
      state.userPage = 1;
      renderUsersTable();
    });
  }
  
  // User sort
  document.querySelectorAll('[data-sort-user]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.userSort = btn.dataset.sortUser;
      renderUsersTable();
    });
  });
  
  // Report search
  const reportSearch = document.getElementById('report-search');
  if (reportSearch) {
    reportSearch.addEventListener('input', (e) => {
      state.reportFilters.query = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report category filter
  const reportCategoryFilter = document.getElementById('report-category-filter');
  if (reportCategoryFilter) {
    reportCategoryFilter.addEventListener('change', (e) => {
      state.reportFilters.category = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report date filter
  const reportDateFilter = document.getElementById('report-date-filter');
  if (reportDateFilter) {
    reportDateFilter.addEventListener('change', (e) => {
      state.reportFilters.date = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report period filter
  const reportPeriodFilter = document.getElementById('report-period-filter');
  if (reportPeriodFilter) {
    reportPeriodFilter.addEventListener('change', (e) => {
      state.reportFilters.period = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report status filter
  const reportStatusFilter = document.getElementById('report-status-filter');
  if (reportStatusFilter) {
    reportStatusFilter.addEventListener('change', (e) => {
      state.reportFilters.status = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report size filter
  const reportSizeFilter = document.getElementById('report-size-filter');
  if (reportSizeFilter) {
    reportSizeFilter.addEventListener('change', (e) => {
      state.reportFilters.size = e.target.value;
      state.reportPage = 1;
      renderReportsTable();
    });
  }
  
  // Report sort
  document.querySelectorAll('[data-sort-report]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.reportSort = btn.dataset.sortReport;
      renderReportsTable();
    });
  });
  
  // Generate Sample Data button (Developer Only)
  const generateSampleDataBtn = document.getElementById('generate-sample-data');
  if (generateSampleDataBtn) {
    generateSampleDataBtn.addEventListener('click', generateSampleData);
  }

  // Export CSV button
  const exportCsvBtn = document.getElementById('export-csv');
  if (exportCsvBtn) {
    exportCsvBtn.addEventListener('click', exportCSV);
  }
  
  // Print reports button
  const printReportsBtn = document.getElementById('print-reports');
  if (printReportsBtn) {
    printReportsBtn.addEventListener('click', handlePrintReports);
  }
  
  // Export PDF button
  const exportPdfBtn = document.getElementById('export-pdf-btn');
  if (exportPdfBtn) {
    exportPdfBtn.addEventListener('click', handlePDFExport);
  }
  
  // Mark all notifications as read
  const markAllReadBtn = document.getElementById('mark-all-read');
  if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', markAllNotificationsRead);
  }
  
  // Profile form submit
  const profileForm = document.getElementById('profile-form');
  if (profileForm) {
    profileForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const formData = new FormData(profileForm);
      const updatedProfile = {
        ...state.profile,
        fullName: formData.get('fullName'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        jobTitle: formData.get('jobTitle'),
      };
      
      if (saveProfileData(updatedProfile)) {
        state.profile = normalizeProfile(updatedProfile);
        showToast('Profile updated successfully.', 'success');
        addActivity('profile', 'updated', 'Profile information updated.');
        renderProfilePage();
      }
    });
  }
  
  // Profile photo upload
  const profilePhotoInput = document.getElementById('profile-photo');
  if (profilePhotoInput) {
    profilePhotoInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      try {
        const optimizedImage = await optimizeAvatarFile(file);
        const updatedProfile = {
          ...state.profile,
          avatar: optimizedImage,
        };
        
        if (saveProfileData(updatedProfile)) {
          state.profile = normalizeProfile(updatedProfile);
          showToast('Profile photo updated.', 'success');
          addActivity('profile', 'photo-updated', 'Profile photo updated.');
          renderProfilePage();
        }
      } catch (error) {
        showToast(error.message, 'error');
      }
    });
  }
  
  // Password form submit
  const passwordForm = document.getElementById('password-form');
  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const currentPassword = document.getElementById('current-password').value;
      const newPassword = document.getElementById('new-password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      
      // Clear previous errors
      document.getElementById('current-password-error').textContent = '';
      document.getElementById('new-password-error').textContent = '';
      document.getElementById('confirm-password-error').textContent = '';
      
      // Validate current password
      const isCurrentValid = await verifyPassword(currentPassword, state.profile);
      if (!isCurrentValid) {
        document.getElementById('current-password-error').textContent = 'Current password is incorrect.';
        document.getElementById('current-password')?.focus();
        return;
      }
      
      // Validate new password
      if (newPassword.length < 8) {
        document.getElementById('new-password-error').textContent = 'Password must be at least 8 characters.';
        document.getElementById('new-password')?.focus();
        return;
      }
      
      if (!/[!@#$%^&*(),.?":{}|<>]/.test(newPassword)) {
        document.getElementById('new-password-error').textContent = 'Password must contain at least one special character.';
        document.getElementById('new-password')?.focus();
        return;
      }
      
      // Validate password confirmation
      if (newPassword !== confirmPassword) {
        document.getElementById('confirm-password-error').textContent = 'Passwords do not match.';
        document.getElementById('confirm-password')?.focus();
        return;
      }
      
      // Hash and save new password
      const newPasswordHash = await hashPassword(newPassword);
      const updatedProfile = {
        ...state.profile,
        passwordHash: newPasswordHash,
      };
      
      if (saveProfileData(updatedProfile)) {
        state.profile = normalizeProfile(updatedProfile);
        passwordForm.reset();
        showToast('Password updated successfully.', 'success');
        addActivity('profile', 'password-changed', 'Password was changed.');
      }
    });
  }
  
  // Password visibility toggles
  document.querySelectorAll('.input-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
      const input = toggle.parentElement.querySelector('input');
      if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
      }
    });
  });
}

// Initialize the application
async function loadInventoryData() {
  try {
    const nextInventory = await fetchInventoryFromApi();
    state.inventory = nextInventory;
    saveData(STORAGE_KEYS.inventory, nextInventory);
    invalidateDashboardAnalytics();
    return true;
  } catch (error) {
    console.warn('Inventory API unavailable; using cached inventory state.', error);
    state.inventory = normalizeInventoryState({
      ...state.inventory,
      error: error.message || 'Inventory API unavailable',
    });
    return false;
  }
}

function init() {
  saveData(STORAGE_KEYS.reservations, state.reservations);
  saveData(STORAGE_KEYS.deliveries, state.deliveries);

  const loadingOverlay = document.getElementById('loading-overlay');
  if (loadingOverlay) {
    setTimeout(() => {
      loadingOverlay.style.display = 'none';
    }, 500);
  }

  renderReportCategoryOptions();
  setupEventListeners();
  navigateToPage('dashboard');
  initNotificationBadge();

  loadInventoryData().finally(() => {
    if (state.activePage === 'dashboard') {
      renderDashboard();
      initNotificationBadge();
    }
  });

  state._liveMetricInterval = setInterval(() => {
    const analytics = getDashboardAnalytics();
    renderLiveMetric(analytics);
  }, 30000);

  addActivity('system', 'initialized', 'Dashboard application initialized.');
}

// Start the application when DOM is ready
document.addEventListener('DOMContentLoaded', init);
