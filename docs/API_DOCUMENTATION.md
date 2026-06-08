# SIMRS-Khanza API Documentation

## Overview

OurKhanz provides RESTful JSON APIs for patient registration, schedule management, and queue operations. All endpoints require HTTP GET or POST requests and return JSON responses.

---

## Authentication

**Current Status:** No authentication required (development mode)  
**Production Recommendation:** Implement JWT or session-based authentication

---

## Endpoints

### 1. Get Doctor Schedules & Queue

**Endpoint:** `GET /pendaftaran/default/schedules`

**Description:** Retrieve available doctor schedules for a specific date with remaining quota and recent registrations

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tanggal_periksa` | DATE (YYYY-MM-DD) | Yes | Target appointment date |
| `recent_page` | INT | No | Page number for recent registrations (default: 0) |
| `recent_search` | STRING | No | Search filter for recent queue |

**Response Format:** JSON

**Example Request:**
```
GET /pendaftaran/default/schedules?tanggal_periksa=2026-06-09&recent_page=0&recent_search=
```

**Example Response:**
```json
{
  "success": true,
  "tanggal_periksa": "2026-06-09",
  "hari_kerja": "SELASA",
  "hari_label": "Selasa",
  "schedules": [
    {
      "kd_dokter": "titis",
      "hari_kerja": "SELASA",
      "jam_mulai": "08:00:00",
      "jam_selesai": "12:00:00",
      "kd_poli": "001",
      "kuota": 5,
      "kuota_tersisa": 3,
      "jumlah_pendaftar": 2,
      "nm_dokter": "Dr. Titis Suryadi",
      "no_ijn_praktek": "12345/2020",
      "nm_poli": "POLI UMUM"
    },
    {
      "kd_dokter": "siti",
      "hari_kerja": "SELASA",
      "jam_mulai": "13:00:00",
      "jam_selesai": "17:00:00",
      "kd_poli": "001",
      "kuota": 4,
      "kuota_tersisa": 4,
      "jumlah_pendaftar": 0,
      "nm_dokter": "Dr. Siti Nurhaliza",
      "no_ijn_praktek": "54321/2020",
      "nm_poli": "POLI UMUM"
    }
  ],
  "recent_registrations": [
    {
      "no_rawat": "2026/06/09/00001",
      "tglDaftar": "2026-06-09",
      "no_rkm_medis": "005457",
      "nm_pasien": "Budi Santoso",
      "kd_dokter": "titis",
      "nm_dokter": "Dr. Titis Suryadi",
      "jam_registrasi": "09:15:00",
      "jam_booking": "09:10:00",
      "kdPoli": "001",
      "nmPoli": "POLI UMUM",
      "noUrut": "A1",
      "status": "Masuk",
      "stts_periksa": "Belum",
      "status_penanganan": "Belum Ditangani",
      "status_penanganan_class": "warning"
    }
  ],
  "recent_pagination": {
    "page": 0,
    "pageCount": 1,
    "pageSize": 8,
    "totalCount": 1
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `success` | BOOL | Operation success flag |
| `tanggal_periksa` | DATE | Queried date |
| `hari_kerja` | STRING | Day code (SENIN, SELASA, etc) |
| `hari_label` | STRING | Day name in Indonesian |
| `schedules` | ARRAY | Available doctor schedules |
| `schedules[].kuota_tersisa` | INT | Remaining available slots |
| `schedules[].jumlah_pendaftar` | INT | Current booking count |
| `recent_registrations` | ARRAY | Queue data for the date |
| `recent_registrations[].noUrut` | STRING | Queue number |
| `recent_registrations[].status_penanganan` | STRING | Treatment status |
| `recent_pagination` | OBJECT | Pagination metadata |

**Status Codes:**
- `200 OK` - Success
- `400 Bad Request` - Invalid tanggal_periksa format
- `500 Internal Server Error` - Database error

---

### 2. Search Patients

**Endpoint:** `GET /pendaftaran/default/patients`

**Description:** Autocomplete patient search for registration form. Returns DataTables-compatible format.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `draw` | INT | Yes | DataTables draw counter |
| `start` | INT | Yes | Record offset (pagination) |
| `length` | INT | Yes | Number of records (max 25) |
| `search[value]` | STRING | No | Search query (RM, name, ID, phone, address) |

**Response Format:** JSON (DataTables format)

**Example Request:**
```
GET /pendaftaran/default/patients?draw=1&start=0&length=10&search[value]=005
```

**Example Response:**
```json
{
  "draw": 1,
  "recordsTotal": 1250,
  "recordsFiltered": 3,
  "data": [
    {
      "no_rkm_medis": "000457",
      "nm_pasien": "Budi Santoso",
      "no_ktp": "3273015009900001",
      "jk": "L",
      "tgl_lahir": "1990-09-01",
      "no_tlp": "085123456789",
      "alamat": "Jl. Merdeka No. 15, Jakarta",
      "display_name": "000457 - Budi Santoso"
    },
    {
      "no_rkm_medis": "000505",
      "nm_pasien": "Ani Wijaya",
      "no_ktp": "3271012508850002",
      "jk": "P",
      "tgl_lahir": "1985-08-25",
      "no_tlp": "081987654321",
      "alamat": "Jl. Sudirman No. 42, Jakarta",
      "display_name": "000505 - Ani Wijaya"
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `draw` | INT | Echo of draw parameter |
| `recordsTotal` | INT | Total records in table |
| `recordsFiltered` | INT | Records matching search |
| `data` | ARRAY | Patient records |
| `data[].no_rkm_medis` | STRING | Medical record number |
| `data[].display_name` | STRING | Formatted display (RM - Name) |

**Search Behavior:**
- Searches across: no_rkm_medis, nm_pasien, no_ktp, no_tlp, alamat
- Case-insensitive
- Partial matching (LIKE operator)
- Max 25 results per page

**Status Codes:**
- `200 OK` - Success
- `400 Bad Request` - Invalid pagination parameters
- `500 Internal Server Error` - Database error

---

### 3. Submit Booking/Registration

**Endpoint:** `POST /pendaftaran/default/index`

**Description:** Create a new patient appointment booking

**Parameters (POST Form Data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `no_rkm_medis` | STRING | Yes | Patient medical record number |
| `tanggal_periksa` | DATE | Yes | Appointment date (YYYY-MM-DD) |
| `schedule_key` | STRING | Yes | Schedule key (format: `kd_dokter\|hari_key\|jam_mulai`) |

**Example Request:**
```html
POST /pendaftaran/default/index
Content-Type: application/x-www-form-urlencoded

no_rkm_medis=005457&tanggal_periksa=2026-06-09&schedule_key=titis%7CSELASA%7C08%3A00%3A00
```

**Success Response:** Redirect to index page with flash message

**Error Response:** Returns HTML form with error message:
```html
<!-- Fields will show error message -->
<div class="alert alert-danger">Kuota jadwal sudah habis atau jadwal tidak ditemukan.</div>
```

**Error Messages:**

| Message | Cause | Action |
|---------|-------|--------|
| `Lengkapi nomor RM, tanggal periksa, dan jadwal dokter.` | Missing required fields | Fill all fields |
| `Jadwal dokter yang dipilih tidak valid.` | Invalid schedule_key format | Select valid schedule |
| `Kuota jadwal sudah habis atau jadwal tidak ditemukan.` | No quota or schedule missing | Choose another date/doctor |
| `Gagal menyimpan pendaftaran.` | Database error | Contact admin |

**Success Message:** 
```
Pendaftaran berhasil untuk Dr. Titis Suryadi pada 09-06-2026 jam 08:00. 
Kuota dokter sudah berkurang 1 slot.
```

**Status Codes:**
- `302 Found` - Redirect after success
- `400 Bad Request` - Invalid input
- `500 Internal Server Error` - Database error

---

## Data Types & Formats

### Dates & Times

- **Date Format**: `YYYY-MM-DD` (e.g., `2026-06-09`)
- **Time Format**: `HH:MM:SS` (24-hour, e.g., `08:00:00`)
- **DateTime Format**: `YYYY-MM-DD HH:MM:SS`

### Enums

**Clinic Days (hari_kerja):**
```
SENIN    - Monday
SELASA   - Tuesday
RABU     - Wednesday
KAMIS    - Thursday
JUMAT    - Friday
SABTU    - Saturday
AKHAD    - Sunday
```

**Booking Status (booking_registrasi.status):**
```
Belum                - Pending registration
Terdaftar            - Confirmed/Registered
Batal                - Cancelled
Dokter Berhalangan   - Doctor unavailable
```

**Examination Status (reg_periksa.stts):**
```
Belum            - Awaiting examination
Sudah            - Examination completed
Sudah Anamnesa   - Anamnesis completed
Batal            - Cancelled
```

**Treatment Status (display only):**
```
Belum Ditangani  - Not yet treated (warning - yellow)
Sudah Ditangani  - Treatment complete (success - green)
Dibatalkan       - Cancelled (secondary - gray)
```

---

## Common Use Cases

### Use Case 1: Display Available Schedules

```javascript
async function loadSchedules(date) {
  const response = await fetch(`/pendaftaran/default/schedules?tanggal_periksa=${date}`);
  const data = await response.json();
  
  data.schedules.forEach(schedule => {
    console.log(`${schedule.nm_dokter} - ${schedule.nm_poli}`);
    console.log(`  Jam: ${schedule.jam_mulai} - ${schedule.jam_selesai}`);
    console.log(`  Kuota Tersisa: ${schedule.kuota_tersisa}/${schedule.kuota}`);
  });
}
```

### Use Case 2: Patient Search Modal

```javascript
async function searchPatients(query) {
  const params = new URLSearchParams({
    draw: 1,
    start: 0,
    length: 10,
    'search[value]': query
  });
  
  const response = await fetch(`/pendaftaran/default/patients?${params}`);
  const data = await response.json();
  
  return data.data.map(p => ({
    id: p.no_rkm_medis,
    label: p.display_name
  }));
}
```

### Use Case 3: Submit Booking

```javascript
async function submitBooking(noRkmMedis, tanggalPeriksa, scheduleKey) {
  const formData = new FormData();
  formData.append('no_rkm_medis', noRkmMedis);
  formData.append('tanggal_periksa', tanggalPeriksa);
  formData.append('schedule_key', scheduleKey);
  
  const response = await fetch('/pendaftaran/default/index', {
    method: 'POST',
    body: formData
  });
  
  if (response.ok) {
    console.log('Booking successful');
  } else {
    console.log('Booking failed');
  }
}
```

---

## Rate Limiting

**Current Status:** No rate limiting implemented  
**Recommendation:** Implement for production:

- Max 100 requests per minute per IP
- Max 10 booking attempts per patient per day
- Queue queries: 1000 requests per minute per IP

---

## CORS Policy

**Current Status:** CORS disabled (same-origin only)  
**Headers:**
```
Access-Control-Allow-Origin: Not set (same-origin required)
Access-Control-Allow-Methods: GET, POST
Access-Control-Allow-Headers: Content-Type
```

---

## Error Handling

All endpoints return JSON error responses on failure:

```json
{
  "success": false,
  "error": "Error message describing what went wrong",
  "code": "ERROR_CODE"
}
```

**Common Error Codes:**
- `INVALID_DATE` - Date format invalid
- `INVALID_SCHEDULE` - Schedule key malformed
- `QUOTA_EXHAUSTED` - No slots available
- `PATIENT_NOT_FOUND` - Medical record doesn't exist
- `DATABASE_ERROR` - Server-side error

---

## Testing Endpoints

### Quick Test with cURL

```bash
# Get schedules for 2026-06-09
curl "http://localhost:8080/pendaftaran/default/schedules?tanggal_periksa=2026-06-09"

# Search patients
curl "http://localhost:8080/pendaftaran/default/patients?draw=1&start=0&length=5&search%5Bvalue%5D=005"

# Submit booking
curl -X POST "http://localhost:8080/pendaftaran/default/index" \
  -d "no_rkm_medis=005457" \
  -d "tanggal_periksa=2026-06-09" \
  -d "schedule_key=titis|SELASA|08:00:00"
```

### Testing with JavaScript/Fetch

```javascript
// Test GET endpoint
fetch('/pendaftaran/default/schedules?tanggal_periksa=2026-06-09')
  .then(r => r.json())
  .then(console.log);

// Test POST endpoint
fetch('/pendaftaran/default/index', {
  method: 'POST',
  body: new FormData(document.querySelector('form'))
})
.then(r => r.text())
.then(console.log);
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-06-08 | Initial API documentation |

---

## Support & Feedback

For API issues or feature requests:
- GitHub Issues: https://github.com/suryadragn/ourkhanz/issues
- Code: [frontend/modules/pendaftaran/controllers/DefaultController.php](../frontend/modules/pendaftaran/controllers/DefaultController.php)
