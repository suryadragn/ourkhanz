# SIMRS-Khanza Hospital Registration System - Technical Analysis

## Executive Summary

This document provides a comprehensive technical analysis of the SIMRS-Khanza hospital registration flow, covering the database schema, queue number (`noUrut`) generation, registration workflow, quota management, and data consistency rules.

---

## 1. Database Schema Structure

### 1.1 Core Registration Tables

#### **pcare_pendaftaran** (Patient Care Registration - Main Registration Table)
The primary table storing completed patient registrations.

**Key Fields:**
- `no_rawat` (VARCHAR 17) - Registration number (PRIMARY KEY)
- `tglDaftar` (DATE) - Registration date
- `no_rkm_medis` (VARCHAR 15) - Patient medical record number
- `nm_pasien` (VARCHAR 40) - Patient name
- `kdPoli` (VARCHAR 5) - Clinic code
- `nmPoli` (VARCHAR 30) - Clinic name
- `noUrut` (VARCHAR 5) - **Queue number** (critically important for queue display)
- `status` (ENUM) - Registration status
- Vital signs fields: `sistolik`, `diastolik`, `tinggi_badan`, `berat_badan`, `suhu_tubuh`, `tekanan_nadi`, `pernafasan`, `gula_darah`
- Clinical assessment fields: `keluhan_utama`, `diagnosis`, `penjab_perujuk`

**Important Characteristics:**
- `no_rawat` is the unique identifier (Format: YYYY/MM/DD/NNNNN)
- Registration data is permanent once created
- Queue number (`noUrut`) is already calculated and stored at registration time

---

#### **booking_registrasi** (Booking Registration - Pre-Registration)
Temporary table storing patient appointment bookings before actual registration.

**Key Fields:**
- `tanggal_booking` (DATE) - When booking was made
- `jam_booking` (TIME) - What time booking was made
- `no_rkm_medis` (VARCHAR 15) - Patient medical record number
- `tanggal_periksa` (DATE) - Appointment date
- `kd_dokter` (VARCHAR 20) - Doctor code
- `kd_poli` (VARCHAR 5) - Clinic code
- `no_reg` (VARCHAR 8) - Registration number (initially NULL)
- `kd_pj` (CHAR 3) - Payment method code (default 'A09')
- `limit_reg` (INT) - Registration limit (default 0)
- `waktu_kunjungan` (DATETIME) - Visit time
- `status` (ENUM) - Values: 'Terdaftar', 'Belum', 'Batal', 'Dokter Berhalangan'

**Primary Key:** (`no_rkm_medis`, `tanggal_periksa`)  
**Relationships:**
- FK to `dokter` (kd_dokter)
- FK to `poliklinik` (kd_poli)
- FK to `pasien` (no_rkm_medis)
- FK to `penjab` (kd_pj)

---

#### **reg_periksa** (Examination Registration)
Stores examination/visit details linked to patient registration.

**Key Fields:**
- `no_rawat` (VARCHAR 17) - Registration number (from pcare_pendaftaran)
- `kd_dokter` (VARCHAR 20) - Doctor code
- `jam_reg` (TIME) - Registration/examination time
- `stts` (VARCHAR) - Status: 'Sudah', 'Belum', 'Sudah Anamnesa', 'Batal', etc.
- `tgl_reg` (DATE) - Examination date

**Purpose:** Tracks the examination status and progress of registered patients

---

#### **jadwal** (Doctor Schedule/Availability)
Master table defining doctor availability and clinic capacity.

**Key Fields:**
- `kd_dokter` (VARCHAR 20) - Doctor code
- `hari_kerja` (VARCHAR 10) - Working day: 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'AKHAD'
- `jam_mulai` (TIME) - Schedule start time
- `jam_selesai` (TIME) - Schedule end time
- `kd_poli` (VARCHAR 5) - Clinic code
- `kuota` (INT) - Daily quota/capacity limit

**Relationships:**
- FK to `dokter` (kd_dokter)
- FK to `poliklinik` (kd_poli)

**Primary Key:** (`kd_dokter`, `hari_kerja`, `jam_mulai`)

---

#### **dokter** (Doctor Master)
**Key Fields:**
- `kd_dokter` (VARCHAR 20) - Doctor code (PRIMARY KEY)
- `nm_dokter` (VARCHAR 40) - Doctor name
- `no_ijn_praktek` (VARCHAR 20) - Practice license number

---

#### **poliklinik** (Clinic Master)
**Key Fields:**
- `kd_poli` (VARCHAR 5) - Clinic code (PRIMARY KEY)
- `nm_poli` (VARCHAR 30) - Clinic name

---

#### **pasien** (Patient Master)
**Key Fields:**
- `no_rkm_medis` (VARCHAR 15) - Medical record number (PRIMARY KEY)
- `nm_pasien` (VARCHAR 40) - Patient name
- `no_ktp` (VARCHAR 16) - ID card number
- `jk` (ENUM) - Gender: 'L' (Male) / 'P' (Female)
- `tgl_lahir` (DATE) - Birth date
- `no_tlp` (VARCHAR 20) - Phone number
- `alamat` (VARCHAR) - Address

---

## 2. Queue Number (noUrut) Generation - CRITICAL FINDING

### 2.1 Queue Number Generation Mechanism

Based on code analysis from `DefaultController.php`, **the `noUrut` (queue number) is NOT generated during the booking phase**. Instead:

1. **Generated When**: Queue number is created **after successful actual registration** (when data is inserted into `pcare_pendaftaran`)
2. **Source**: The queue number is retrieved from somewhere and stored in `pcare_pendaftaran.noUrut`
3. **Pattern**: Examples show: A1, A2, A3... B1, B2, B3... (prefix + sequence number)

### 2.2 Observed Queue Number Patterns

From database inspection:
- **Format**: `[PREFIX][SEQUENCE]` (e.g., "A1", "A5", "A50", "B12")
- **Prefixes**: 
  - 'A' = General clinic (POLI UMUM)
  - 'B' = Dental/Mouth clinic (POLI GIGI & MULUT)
  - Other prefixes potentially for other departments
  
### 2.3 Queue Number Assignment Logic

**Not found in PHP code**, likely implemented in:
1. **Stored Procedure** - Most probable, handles atomic counter increments per clinic/date
2. **Separate service** - Dedicated queue management system
3. **Auto-generated** - Possible database trigger on `pcare_pendaftaran` INSERT

**Hypothesis**: A counter table tracks:
```
clinic_code, date, last_queue_number, last_prefix
```
Each registration increments the counter and assigns the next queue number.

---

## 3. Complete Registration Workflow

### 3.1 Registration Flow Steps

#### **Phase 1: Pre-Registration (Booking)**

```
┌─────────────────────────────────────────┐
│ 1. Patient Selects Appointment          │
├─────────────────────────────────────────┤
│ Inputs:                                  │
│ - no_rkm_medis (patient ID)             │
│ - tanggal_periksa (appointment date)    │
│ - schedule_key (doctor|day|time)        │
└──────────────┬──────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│ 2. Validate Schedule & Quota             │
├──────────────────────────────────────────┤
│ Query: jadwal table                      │
│ - Check: kuota > 0                       │
│ - Verify: Doctor schedule exists         │
│ - Status: Active schedule only           │
└──────────────┬───────────────────────────┘
               │
       ┌───────┴──────────┐
       │                  │
       ▼                  ▼
   SUCCESS          QUOTA FULL
   (Continue)       (Error: "Kuota sudah habis")
       │
       ▼
┌──────────────────────────────────────────┐
│ 3. Begin Database Transaction            │
├──────────────────────────────────────────┤
│ MySQL: START TRANSACTION                 │
│ Isolation: READ COMMITTED                │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│ 4. Update Quota (CRITICAL OPERATION)     │
├──────────────────────────────────────────┤
│ UPDATE jadwal                            │
│ SET kuota = kuota - 1                    │
│ WHERE kd_dokter = ?                      │
│   AND hari_kerja = ?                     │
│   AND jam_mulai = ?                      │
│   AND kuota > 0  ← RACE CONDITION GUARD  │
│                                          │
│ Rows affected check:                     │
│ - 0 rows = Quota just ran out (error)   │
│ - 1 row = Success (continue)            │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│ 5. Insert Booking Record                 │
├──────────────────────────────────────────┤
│ INSERT INTO booking_registrasi:          │
│ - tanggal_booking = TODAY()              │
│ - jam_booking = NOW()                    │
│ - no_rkm_medis = (from input)            │
│ - tanggal_periksa = (from input)         │
│ - kd_dokter = (from schedule)            │
│ - kd_poli = (from schedule)              │
│ - no_reg = NULL (no registration yet)    │
│ - kd_pj = 'A09' (default payer)         │
│ - limit_reg = 0                          │
│ - waktu_kunjungan = NOW()                │
│ - status = 'Belum' (not yet registered)  │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│ 6. Commit Transaction                    │
├──────────────────────────────────────────┤
│ COMMIT - All changes become permanent    │
└──────────────┬───────────────────────────┘
               │
               ▼
         SUCCESS ✓
         Booking created
         Quota reduced by 1
```

**Key Data Inserted in `booking_registrasi`:**
```
Example:
┌──────────────────┬──────────────┬──────────────┬──────────────┐
│ no_rkm_medis     │ tanggal_periksa │ kd_dokter  │ status       │
├──────────────────┼──────────────┼──────────────┼──────────────┤
│ '005457'         │ '2026-06-09' │ 'titis'      │ 'Belum'      │
└──────────────────┴──────────────┴──────────────┴──────────────┘
```

---

#### **Phase 2: Actual Registration (Check-in)**

The next phase (not yet implemented in the current code) would be when the patient arrives:

```
┌─────────────────────────────────────────┐
│ 1. Patient Arrives at Clinic             │
│ 2. Staff confirms booking exists         │
│ 3. Creates actual registration record    │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ INSERT INTO pcare_pendaftaran:           │
│ - no_rawat = GENERATE() [YYYY/MM/DD/###]│
│ - tglDaftar = TODAY()                   │
│ - no_rkm_medis = (from booking)         │
│ - nm_pasien = (from pasien table)       │
│ - kdPoli = (from booking)               │
│ - nmPoli = (from poliklinik)            │
│ - noUrut = ??? (GENERATED BY PROCEDURE) │
│ - status = 'Masuk'                      │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ UPDATE booking_registrasi:               │
│ SET no_reg = (new registration no_rawat)│
│ SET status = 'Terdaftar'                │
│ WHERE no_rkm_medis = ?                  │
│   AND tanggal_periksa = TODAY()         │
└─────────────────────────────────────────┘
```

---

### 3.2 Current Frontend Flow (actionIndex & actionSchedules)

**API: /pendaftaran/default/index**
- Displays available schedules for selected date
- Shows recent registrations (last 24 hours or current date)
- Allows booking submission

**API: /pendaftaran/default/schedules (JSON)**
- Returns: Available doctor schedules with remaining quota
- Includes: kuota_tersisa (remaining quota), jumlah_pendaftar (registrations)
- Calculates: `kuota_tersisa = kuota - COUNT(active bookings)`

**API: /pendaftaran/default/patients (JSON)**
- Autocomplete patient lookup
- Searches by: medical record number, name, ID card, phone, address

---

## 4. Quota Tracking & Management

### 4.1 Quota System Architecture

```
┌─────────────────────────────────────────────────┐
│           QUOTA MANAGEMENT SYSTEM                │
├─────────────────────────────────────────────────┤
│                                                  │
│  jadwal table                                    │
│  ┌────────────────────────────────────────┐     │
│  │ kd_dokter | hari_kerja | kuota (5)     │     │
│  │ titis     | SENIN      | 5             │     │
│  │ titis     | SENIN      | 3 (updated)   │     │
│  └────────────────────────────────────────┘     │
│           ▲                                      │
│           │ Decremented per booking             │
│           │                                      │
│  ┌─────────┴──────────────────────────────┐     │
│  │ booking_registrasi (active bookings)   │     │
│  │ COUNT WHERE:                           │     │
│  │ - tanggal_periksa = TODAY              │     │
│  │ - kd_dokter = 'titis'                 │     │
│  │ - status NOT IN ('Batal', 'Berhalangan')    │
│  │ Result: 2 active bookings             │     │
│  └────────────────────────────────────────┘     │
│                                                  │
│  Remaining Quota Calculation:                    │
│  kuota_tersisa = jadwal.kuota - active_bookings │
│               = 3 - 2 = 1                       │
└─────────────────────────────────────────────────┘
```

### 4.2 Quota Calculation SQL (from DefaultController)

```php
// Active bookings per doctor/schedule
$activeBookings = (new Query())
    ->from(['br' => 'booking_registrasi'])
    ->select([
        'br.kd_dokter',
        'br.kd_poli',
        'COUNT(*) AS jumlah_pendaftar',
    ])
    ->where(['br.tanggal_periksa' => $tanggalPeriksa])
    ->andWhere(['not in', 'br.status', ['Batal', 'Dokter Berhalangan']])
    ->groupBy(['br.kd_dokter', 'br.kd_poli']);

// Calculate remaining quota
$rows = (new Query())
    ->from(['j' => 'jadwal'])
    ->select([
        'j.kuota',
        'COALESCE(b.jumlah_pendaftar, 0) AS jumlah_pendaftar',
        'GREATEST(j.kuota - COALESCE(b.jumlah_pendaftar, 0), 0) AS kuota_tersisa',
        // ...
    ])
    ->leftJoin(['b' => $activeBookings], 
        'b.kd_dokter = j.kd_dokter AND b.kd_poli = j.kd_poli')
    ->where(['j.hari_kerja' => $hariKerja])
    ->andWhere(['>', 'j.kuota', 0])
    ->all(Yii::$app->db);
```

### 4.3 Quota Statuses

- **Terdaftar** - Confirmed registration (counts toward quota)
- **Belum** - Pending registration (counts toward quota)
- **Batal** - Cancelled (does NOT count)
- **Dokter Berhalangan** - Doctor unable (does NOT count)

---

## 5. Relationships & Foreign Keys

### 5.1 Data Flow Diagram

```
┌──────────────────┐
│    pasien        │ (Patient Master)
│ (no_rkm_medis)   │
└────────┬─────────┘
         │ 1:M
         │
         ├─────────────────────────────────┐
         │                                 │
         ▼                                 ▼
    ┌─────────────┐                  ┌──────────────┐
    │ booking_    │                  │ pcare_       │
    │ registrasi  │                  │ pendaftaran  │
    │ (booking)   │                  │ (registration)
    └──┬──────────┘                  └──┬───────────┘
       │ M:1                            │ M:1
       │                                │
       └─────────┬──────────────────────┘
                 │
                 ▼
    ┌─────────────────────┐
    │   reg_periksa       │ (Examination)
    │  (no_rawat)         │
    └──────────┬──────────┘
               │ M:1
               │
               ├─────────────────────────────┐
               │                             │
               ▼                             ▼
        ┌─────────────┐            ┌──────────────┐
        │   dokter    │            │  poliklinik  │
        │ (kd_dokter) │            │  (kd_poli)   │
        └─────────────┘            └──────────────┘
               ▲                           ▲
               │ 1:M                      │ 1:M
               │                          │
               └──────────┬───────────────┘
                          │
                    ┌─────▼─────┐
                    │   jadwal   │ (Schedule)
                    └────────────┘
```

### 5.2 Foreign Key Relationships

| From Table | Field | To Table | Field | Action |
|-----------|-------|----------|-------|--------|
| booking_registrasi | kd_dokter | dokter | kd_dokter | CASCADE |
| booking_registrasi | kd_poli | poliklinik | kd_poli | CASCADE |
| booking_registrasi | no_rkm_medis | pasien | no_rkm_medis | CASCADE |
| booking_registrasi | kd_pj | penjab | kd_pj | CASCADE |
| reg_periksa | no_rawat | pcare_pendaftaran | no_rawat | CASCADE |
| reg_periksa | kd_dokter | dokter | kd_dokter | CASCADE |
| jadwal | kd_dokter | dokter | kd_dokter | CASCADE |
| jadwal | kd_poli | poliklinik | kd_poli | CASCADE |

---

## 6. Data Creation Timeline & Dependencies

### 6.1 When Data is Created in Each Table

```
SEQUENCE OF EVENTS:

Day 0 (Administration):
├─ pasien (Patient added to system)
├─ dokter (Doctor added to system)
├─ poliklinik (Clinic configured)
└─ jadwal (Doctor schedules created - repeating)
   Example: dr. titis works every Monday 08:00-12:00 at clinic 001
           quota = 5 patients per Monday

Day N (Patient Appointment):
├─ booking_registrasi (Patient books appointment)
│  └─ TIME: Booking immediately → status='Belum'
│     DATA: (no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, status)
│
└─ jadwal.kuota DECREMENTED
   └─ TIME: Same transaction as booking

Day N (Patient Arrives):
├─ pcare_pendaftaran (Actual registration record created)
│  └─ TIME: When patient checks in
│     DATA: (no_rawat, tglDaftar, noUrut, status='Masuk')
│     NOTE: no_rawat is generated, noUrut is assigned
│
└─ reg_periksa (Examination tracking record created)
   └─ TIME: When patient starts examination
      DATA: (no_rawat, kd_dokter, jam_reg, stts='Belum')
```

### 6.2 Dependency Chain

```
pasien → booking_registrasi → pcare_pendaftaran → reg_periksa
                    ↓
               jadwal (quota check)
                    ↓
               dokter (doctor info)
                    ↓
             poliklinik (clinic info)
```

---

## 7. Data Consistency Rules & Constraints

### 7.1 Booking Phase Constraints

```javascript
{
  "no_rkm_medis": "Must exist in pasien table",
  "tanggal_periksa": {
    "format": "YYYY-MM-DD",
    "constraint": "Must be a working day for selected doctor",
    "future": "Must be TODAY or in future (not past)",
    "business_hours": "Only valid during clinic hours"
  },
  "kd_dokter": {
    "constraint": "Must exist in dokter table",
    "schedule": "Must have active schedule for selected day",
    "availability": "Must have kuota > 0",
    "status": "Must be marked as active/available"
  },
  "kd_poli": {
    "constraint": "Must exist in poliklinik table",
    "must_match": "Must match schedule's kd_poli"
  },
  "status": "Enum: 'Belum', 'Terdaftar', 'Batal', 'Dokter Berhalangan'",
  "duplicate_prevention": {
    "rule": "One booking per (no_rkm_medis, tanggal_periksa)",
    "index": "PRIMARY KEY (no_rkm_medis, tanggal_periksa)",
    "behavior": "Cannot book same appointment twice"
  }
}
```

### 7.2 Registration Phase Constraints

```javascript
{
  "no_rawat": {
    "generation": "Format: YYYY/MM/DD/NNNNN",
    "uniqueness": "PRIMARY KEY in pcare_pendaftaran",
    "timestamp": "Date portion = tglDaftar"
  },
  "noUrut": {
    "format": "VARCHAR 5 (e.g., 'A123', 'B5')",
    "uniqueness_scope": "Per clinic per date",
    "assignment": "Calculated by stored procedure (not PHP code)",
    "sequence": "Auto-incrementing counter per clinic/date",
    "reset": "Counter resets daily"
  },
  "tglDaftar": "Must match tanggal_periksa from booking",
  "booking_link": "Must have matching booking_registrasi record",
  "must_exist": {
    "no_rkm_medis": "In pasien table",
    "kdPoli": "In poliklinik table",
    "kd_dokter": "If available, in dokter table"
  }
}
```

### 7.3 Quota Management Constraints

```javascript
{
  "atomic_operations": {
    "rule": "Quota decrement is atomic with booking insert",
    "isolation": "ACID transaction",
    "race_condition_guard": "WHERE kuota > 0 in UPDATE",
    "failure_handling": "If rows affected = 0, rollback and show error"
  },
  "quota_reconciliation": {
    "formula": "kuota_tersisa = jadwal.kuota - COUNT(active bookings)",
    "active_bookings": "status NOT IN ('Batal', 'Dokter Berhalangan')",
    "count_scope": "WHERE tanggal_periksa = target date"
  },
  "display_rules": {
    "show_schedule": "Only if kuota_tersisa > 0",
    "hide_if": "GREATEST(kuota - pending, 0) = 0"
  },
  "status_transitions": {
    "booking": "'Belum' → 'Terdaftar' (when registered)",
    "registration": "'Belum' → 'Masuk' (on patient check-in)",
    "cancellation": "Any status → 'Batal' (if cancelled)",
    "doctor_unable": "Any status → 'Dokter Berhalangan' (if doc absent)"
  }
}
```

### 7.4 Timestamp & Status Values

```
STATUS VALUES IN booking_registrasi:
├─ 'Belum' (Not yet registered - initial state)
├─ 'Terdaftar' (Confirmed/Registered)
├─ 'Batal' (Cancelled - does not consume quota)
└─ 'Dokter Berhalangan' (Doctor unable - does not consume quota)

STATUS VALUES IN reg_periksa:
├─ 'Belum' (Not yet examined)
├─ 'Sudah' (Examined/Completed)
├─ 'Sudah Anamnesa' (Anamnesis done)
└─ 'Batal' (Cancelled)

TIMESTAMPS:
├─ tanggal_booking (DATE) - When booking was made
├─ jam_booking (TIME) - Booking creation time
├─ tanggal_periksa (DATE) - Appointment date
├─ waktu_kunjungan (DATETIME) - Expected visit datetime
└─ tglDaftar (DATE) - Actual registration date
```

---

## 8. Queue Display Logic

### 8.1 Queue Number Display Flow

```
┌─────────────────────────────────────────┐
│ Patient arrives / Staff checks status   │
└──────────────┬──────────────────────────┘
               │
               ▼
┌────────────────────────────────────────────┐
│ Query pcare_pendaftaran:                   │
│ SELECT noUrut                              │
│ WHERE tglDaftar = TODAY()                  │
│ AND kdPoli = (selected clinic)             │
│ ORDER BY no_rawat DESC / noUrut ASC        │
└──────────────┬─────────────────────────────┘
               │
               ▼
┌────────────────────────────────────────────┐
│ Join with reg_periksa:                     │
│ SELECT stts (examination status)           │
│ To show: "Being examined" / "Waiting"      │
└──────────────┬─────────────────────────────┘
               │
               ▼
┌────────────────────────────────────────────┐
│ Display Queue Screen:                      │
│                                            │
│ Currently Served: A5                       │
│ ┌──────────────────────────────────┐      │
│ │ A1  - Being examined             │      │
│ │ A2  - Waiting at clinic          │      │
│ │ A3  - Not yet arrived            │      │
│ │ A4  - Being examined (OTHER DR)  │      │
│ │ A5  - NEXT                       │      │
│ │ A6  - Waiting...                 │      │
│ └──────────────────────────────────┘      │
│                                            │
│ Notes:                                     │
│ - Refreshes: Every 5-10 seconds           │
│ - Filters: Today's registrations only     │
│ - Sorting: By queue number                │
└────────────────────────────────────────────┘
```

### 8.2 Queue Status Mapping

```
FROM reg_periksa.stts  →  DISPLAY AS
├─ 'Belum'            →  "Waiting in Queue"
├─ 'Sudah'            →  "Treatment Completed"
├─ 'Sudah Anamnesa'   →  "Under Examination"
└─ 'Batal'            →  "Cancelled"
```

---

## 9. Integration Points & APIs

### 9.1 Endpoint: actionIndex

**URL:** `/pendaftaran/default/index`  
**Method:** GET / POST  
**Purpose:** Display booking page and handle booking submissions

**GET Parameters:**
- `tanggal_periksa` (DATE) - Selected date, defaults to today

**POST Parameters (Booking Submission):**
- `no_rkm_medis` - Patient medical record number
- `tanggal_periksa` - Appointment date
- `schedule_key` - Format: `kd_dokter|hari_key|jam_mulai`

**Response:**
- HTML page with:
  - Available schedules for date
  - Recent registrations list
  - Booking form

---

### 9.2 Endpoint: actionSchedules (JSON API)

**URL:** `/pendaftaran/default/schedules`  
**Method:** GET  
**Response Format:** JSON

**Parameters:**
- `tanggal_periksa` (DATE) - Query date
- `recent_page` (INT) - Pagination
- `recent_search` (STRING) - Search filter

**Response Structure:**
```json
{
  "success": true,
  "tanggal_periksa": "2026-06-09",
  "hari_kerja": "SELASA",
  "schedules": [
    {
      "kd_dokter": "titis",
      "nm_dokter": "Dr. Titis",
      "hari_kerja": "SELASA",
      "jam_mulai": "08:00:00",
      "jam_selesai": "12:00:00",
      "kd_poli": "001",
      "nm_poli": "POLI UMUM",
      "kuota": 5,
      "kuota_tersisa": 3,
      "jumlah_pendaftar": 2,
      "no_ijn_praktek": "..."
    }
  ],
  "recent_registrations": [
    {
      "no_rawat": "2026/06/09/00001",
      "noUrut": "A1",
      "nm_pasien": "Patient Name",
      "status": "Masuk",
      "stts_periksa": "Belum",
      "status_penanganan": "Belum Ditangani",
      "status_penanganan_class": "warning"
    }
  ]
}
```

---

### 9.3 Endpoint: actionPatients (JSON API)

**URL:** `/pendaftaran/default/patients`  
**Method:** GET  
**Response Format:** JSON (DataTables format)

**Parameters:**
- `draw` - DataTables draw counter
- `start` - Offset
- `length` - Page size (max 25)
- `search[value]` - Search query

**Returns:** Patient list for autocomplete

---

## 10. Critical Issues & Recommendations

### 10.1 Missing Implementation

**Issue:** Queue number generation logic is NOT in PHP code
- **Impact**: Cannot display queue numbers
- **Solution**: Likely handled by database trigger or stored procedure
- **Recommendation**: Document the actual `noUrut` generation procedure

### 10.2 Race Condition Mitigation

✅ **Good:** WHERE clause includes `kuota > 0` guard  
✅ **Good:** Transaction isolation prevents double-booking  
⚠️ **Concern:** Concurrent requests might still reach error state  
**Recommendation:** Implement retry logic on client side

### 10.3 Data Integrity

✅ **Foreign keys** properly enforce relationships  
✅ **Primary keys** prevent duplicates  
✅ **ACID transactions** ensure consistency  
⚠️ **Concern:** Manual data entry could corrupt status values  
**Recommendation:** Add CHECK constraints on status ENUMs

### 10.4 Performance Considerations

- **Index on:** `booking_registrasi(tanggal_periksa, kd_dokter, status)`
- **Index on:** `pcare_pendaftaran(tglDaftar, kdPoli)`
- **Materialized view:** For daily queue summaries
- **Cache:** Doctor schedules and clinic info (low change frequency)

---

## 11. Summary Table

| Aspect | Details |
|--------|---------|
| **Queue Number Generation** | Database procedure (not in PHP code) |
| **Queue Format** | `[CLINIC_PREFIX][SEQUENCE]` (A1, B5, etc) |
| **Booking Flow** | Quota check → Update → Insert → Commit |
| **Registration Flow** | Booking complete → Patient arrives → Registration created |
| **Quota Management** | Real-time count of active bookings |
| **Primary Transactions** | Booking, Registration, Examination |
| **Key Constraints** | ACID compliance, FK relationships, Status enum |
| **Rate Limiting** | Per doctor per date per clinic |
| **Cancellation** | Moves to 'Batal' status, doesn't reduce used quota |
| **Doctor Absence** | Status 'Dokter Berhalangan', doesn't count in quota |

---

## 12. Conclusion

The SIMRS-Khanza registration system implements a robust queue management system with:

1. **Pre-registration phase** using `booking_registrasi` to manage quotas
2. **Atomic quota updates** with race-condition guards
3. **Flexible status tracking** for different booking/registration states
4. **Daily queue numbering** with clinic-specific prefixes
5. **Real-time quota calculation** based on active bookings

The critical missing documentation is the **queue number generation procedure**, which must be a stored procedure or trigger in the database layer. Comprehensive testing of concurrent booking scenarios and queue display reliability is recommended.

