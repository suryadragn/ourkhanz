# SIMRS-Khanza Registration Logic - Complete Flow

## Overview

This document details the complete 3-phase registration workflow implemented in OurKhanz, following SIMRS-Khanza hospital management standards.

---

## Three-Phase Registration Workflow

```
┌──────────────────────────────────────────────────────────────────────┐
│                     COMPLETE REGISTRATION FLOW                       │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  PHASE 1: BOOKING                                                   │
│  ├─ Patient selects appointment online                             │
│  ├─ System validates quota available                               │
│  ├─ Quota decremented (kuota - 1)                                  │
│  ├─ booking_registrasi record created                              │
│  └─ Status: 'Belum' (pending registration)                         │
│                                                                      │
│              ↓ Patient arrives at clinic                             │
│                                                                      │
│  PHASE 2: REGISTRATION (Check-in)                                  │
│  ├─ Staff verifies booking exists                                  │
│  ├─ Registration record created in pcare_pendaftaran               │
│  ├─ Queue number assigned (noUrut) by database trigger             │
│  ├─ no_rawat generated: YYYY/MM/DD/NNNNN                           │
│  ├─ booking_registrasi status updated to 'Terdaftar'              │
│  └─ Patient enters queue                                           │
│                                                                      │
│              ↓ Patient called to examination room                    │
│                                                                      │
│  PHASE 3: EXAMINATION                                              │
│  ├─ Doctor creates exam record in reg_periksa                      │
│  ├─ Examination time recorded                                      │
│  ├─ Doctor examines patient                                        │
│  ├─ Status updated: 'Belum' → 'Sudah' / 'Sudah Anamnesa'         │
│  └─ Patient treatment completed                                    │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## PHASE 1: BOOKING

### When It Happens
- Patient selects appointment online via registration form
- Patient provides: medical record number, appointment date, doctor, clinic

### What Gets Stored

**Main Tables Updated:**
- `jadwal` - Quota decremented
- `booking_registrasi` - Booking record created

### Logic Flow

#### Step 1: Validate Patient Exists
```sql
SELECT * FROM pasien WHERE no_rkm_medis = '005457'
```
- **If not found**: Error → "Pasien tidak ditemukan"
- **If found**: Continue to next step

#### Step 2: Validate Schedule & Check Quota
```sql
SELECT j.*, d.nm_dokter, p.nm_poli
FROM jadwal j
LEFT JOIN dokter d ON d.kd_dokter = j.kd_dokter
LEFT JOIN poliklinik p ON p.kd_poli = j.kd_poli
WHERE j.kd_dokter = 'titis'
  AND j.hari_kerja = 'SELASA'
  AND j.kuota > 0
```

**Validation Checks:**
- Schedule exists for selected date/doctor/clinic ✓
- Doctor is available (schedule exists) ✓
- Quota > 0 (slots available) ✓

**If fails**: Error → "Jadwal tidak ditemukan atau kuota sudah habis"

#### Step 3: ATOMIC - Decrement Quota

```sql
UPDATE jadwal
SET kuota = kuota - 1
WHERE kd_dokter = 'titis'
  AND hari_kerja = 'SELASA'
  AND kuota > 0  ← ⭐ CRITICAL: Race condition guard
LIMIT 1
```

**Race Condition Protection:**
```
Scenario: 2 patients booking simultaneously
─────────────────────────────────────────
Patient A                          Patient B
│                                 │
├─ Check: kuota = 5 ✓             │
│                                 ├─ Check: kuota = 5 ✓
├─ UPDATE kuota = 4 ✓             │
│                                 ├─ UPDATE kuota = 4 ✓ ← Wrong! Should be 3
│
WITHOUT WHERE kuota > 0:
Both would succeed, quota = 4 (WRONG - should be 3)

WITH WHERE kuota > 0:
After first UPDATE, kuota becomes 4
Second UPDATE doesn't match WHERE condition
Rows affected = 0 → Rollback ✓ (CORRECT)
```

**Verification:**
- If rows affected > 0: Success ✓ Continue to next step
- If rows affected = 0: Error → "Kuota sudah habis"

#### Step 4: Create Booking Record

```sql
INSERT INTO booking_registrasi (
  tanggal_booking,      -- TODAY()
  jam_booking,          -- NOW()
  no_rkm_medis,         -- '005457'
  tanggal_periksa,      -- '2026-06-09'
  kd_dokter,            -- 'titis'
  kd_poli,              -- '001'
  no_reg,               -- NULL (not yet registered)
  kd_pj,                -- 'A09' (default payment)
  status                -- 'Belum' (pending)
) VALUES (...)
```

**Fields Explained:**
| Field | Value | Meaning |
|-------|-------|---------|
| `tanggal_booking` | 2026-06-08 | When booking was made |
| `jam_booking` | 14:30:15 | What time booking was made |
| `status` | 'Belum' | Not yet registered (patient hasn't arrived) |
| `no_reg` | NULL | Will be filled during Phase 2 |

#### Step 5: Commit Transaction

```
COMMIT
- All changes become permanent
- Quota reduction locked
- Booking record saved
```

### Success Response

```json
{
  "success": true,
  "message": "Booking berhasil untuk Dr. Titis Suryadi pada 09-06-2026 jam 08:00",
  "booking_data": {
    "no_rkm_medis": "005457",
    "nm_pasien": "Budi Santoso",
    "tanggal_periksa": "2026-06-09",
    "kd_dokter": "titis",
    "nm_dokter": "Dr. Titis Suryadi",
    "kd_poli": "001",
    "nm_poli": "POLI UMUM"
  }
}
```

### Database State After Phase 1

```
booking_registrasi:
┌──────────────────┬──────────────────┬──────────────┬─────────┐
│ no_rkm_medis     │ tanggal_periksa  │ kd_dokter    │ status  │
├──────────────────┼──────────────────┼──────────────┼─────────┤
│ 005457           │ 2026-06-09       │ titis        │ Belum   │
└──────────────────┴──────────────────┴──────────────┴─────────┘

jadwal (after quota decrement):
┌──────────┬───────────┬───────────┬─────────┐
│ kd_dokter│ hari_kerja│ jam_mulai │ kuota   │
├──────────┼───────────┼───────────┼─────────┤
│ titis    │ SELASA    │ 08:00:00  │ 4 ← was 5
└──────────┴───────────┴───────────┴─────────┘
```

---

## PHASE 2: REGISTRATION (Check-in)

### When It Happens
- Patient arrives at clinic on appointment date
- Receptionist/staff verifies booking and creates registration record
- **Patient gets queue number (noUrut)**

### What Gets Stored

**Main Tables Updated:**
- `pcare_pendaftaran` - Registration record created with queue number
- `booking_registrasi` - Status updated to 'Terdaftar'

### Logic Flow

#### Step 1: Verify Booking Exists

```sql
SELECT * FROM booking_registrasi
WHERE no_rkm_medis = '005457'
  AND tanggal_periksa = '2026-06-09'
  AND status = 'Belum'  ← Must be pending, not yet registered
```

**Validation:**
- Booking exists for this patient/date ✓
- Status is 'Belum' (not cancelled or already registered) ✓

**If fails**: Error → "Booking tidak ditemukan atau sudah terdaftar"

#### Step 2: Get Patient Data

```sql
SELECT * FROM pasien WHERE no_rkm_medis = '005457'
```

**Used for:**
- Verify patient exists in system
- Get patient name (nm_pasien) for registration record
- Denormalization (faster display)

#### Step 3: Generate Registration Number

```
Format: YYYY/MM/DD/NNNNN

Logic:
1. Get today's registrations
2. Find max counter for today
3. Increment by 1

Example:
Today is 2026-06-09
Last registration: 2026/06/09/00005
New counter: 5 + 1 = 6
New no_rawat: 2026/06/09/00006

Query:
SELECT no_rawat FROM pcare_pendaftaran
WHERE tglDaftar = '2026-06-09'
ORDER BY no_rawat DESC
LIMIT 1
```

**Result:** `no_rawat = '2026/06/09/00001'`

#### Step 4: Get Clinic Info

```sql
SELECT * FROM poliklinik WHERE kd_poli = '001'
```

**Used for:**
- Get clinic name (nm_poli)
- Verify clinic exists

#### Step 5: Create Registration Record

```sql
INSERT INTO pcare_pendaftaran (
  no_rawat,        -- '2026/06/09/00001'
  tglDaftar,       -- '2026-06-09'
  no_rkm_medis,    -- '005457'
  nm_pasien,       -- 'Budi Santoso'
  kdPoli,          -- '001'
  nmPoli,          -- 'POLI UMUM'
  noUrut,          -- '' (will be auto-filled by trigger!)
  status,          -- 'Masuk' (checked in)
  sistolik,        -- NULL (nurse fills later)
  diastolik,       -- NULL
  ... vital signs
) VALUES (...)
```

**Key Points:**
- `no_rawat`: Uniquely identifies this registration
- `noUrut`: **Left empty - database trigger assigns it!**
- `status`: 'Masuk' = patient checked in
- Vital signs: Blank for now (nurse records during triage)

#### Step 6: Update Booking Status

```sql
UPDATE booking_registrasi
SET no_reg = '2026/06/09/00001',
    status = 'Terdaftar'  ← Now marked as registered
WHERE no_rkm_medis = '005457'
  AND tanggal_periksa = '2026-06-09'
```

**What changed:**
- `no_reg`: Now linked to the registration number
- `status`: Changed from 'Belum' → 'Terdaftar'

#### Step 7: Database Trigger Assigns Queue Number

```sql
-- TRIGGER: When pcare_pendaftaran is inserted
-- Automatically assigns noUrut

TRIGGER: pcare_pendaftaran AFTER INSERT
BEGIN
  DECLARE clinic_prefix VARCHAR(1);
  DECLARE next_counter INT;
  
  -- Get clinic prefix (A=001, B=002, etc)
  SELECT GetClinicPrefix(NEW.kdPoli) INTO clinic_prefix;
  
  -- Get next counter for this clinic today
  SELECT COALESCE(MAX(CAST(SUBSTRING(noUrut, 2) AS INT)), 0) + 1
  INTO next_counter
  FROM pcare_pendaftaran
  WHERE kdPoli = NEW.kdPoli AND tglDaftar = NEW.tglDaftar;
  
  -- Assign queue number
  UPDATE pcare_pendaftaran
  SET noUrut = CONCAT(clinic_prefix, next_counter)
  WHERE no_rawat = NEW.no_rawat;
END
```

**Result:** `noUrut = 'A1'` (assuming first patient for clinic 001 today)

#### Step 8: Retrieve Complete Registration

```sql
SELECT * FROM pcare_pendaftaran
WHERE no_rawat = '2026/06/09/00001'
```

**Returns:** Complete registration data including assigned `noUrut`

### Success Response

```json
{
  "success": true,
  "message": "Pendaftaran berhasil. Nomor antrian: A1",
  "registration_data": {
    "no_rawat": "2026/06/09/00001",
    "noUrut": "A1",
    "nm_pasien": "Budi Santoso",
    "no_rkm_medis": "005457",
    "nmPoli": "POLI UMUM",
    "status": "Masuk",
    "tglDaftar": "2026-06-09"
  }
}
```

### Database State After Phase 2

```
pcare_pendaftaran (NEW):
┌──────────────────┬──────────┬──────────────┬──────┬─────────┐
│ no_rawat         │ noUrut   │ nm_pasien    │ status
│ nmPoli           │          │              │       │
├──────────────────┼──────────┼──────────────┼──────┼─────────┤
│ 2026/06/09/00001 │ A1       │ Budi Santoso│ Masuk│ POLI UMUM│
└──────────────────┴──────────┴──────────────┴──────┴─────────┘

booking_registrasi (UPDATED):
┌──────────────────┬──────────────────┬─────────────────┬──────────┐
│ no_rkm_medis     │ tanggal_periksa  │ no_reg          │ status   │
├──────────────────┼──────────────────┼─────────────────┼──────────┤
│ 005457           │ 2026-06-09       │ 2026/06/09/00001│ Terdaftar│
└──────────────────┴──────────────────┴─────────────────┴──────────┘
```

---

## PHASE 3: EXAMINATION

### When It Happens
- Patient is called by doctor/nurse
- Doctor starts examining patient
- Examination results recorded and completed

### What Gets Stored

**Main Tables Updated:**
- `reg_periksa` - Examination record created/updated with status

### Logic Flow

#### Step 1: Verify Registration Exists

```sql
SELECT * FROM pcare_pendaftaran WHERE no_rawat = '2026/06/09/00001'
```

**Validation:**
- Registration exists ✓

#### Step 2: Check if Exam Already Started

```sql
SELECT * FROM reg_periksa WHERE no_rawat = '2026/06/09/00001'
```

**Options:**
- **No existing record**: Create new exam record
- **Existing with status='Belum'**: Can continue
- **Existing with status='Sudah'**: Error → "Sudah selesai"

#### Step 3: Create Examination Record (First Time)

```sql
INSERT INTO reg_periksa (
  no_rawat,      -- '2026/06/09/00001'
  kd_dokter,     -- 'titis'
  jam_reg,       -- '09:15:00' (when exam started)
  tgl_reg,       -- '2026-06-09'
  stts           -- 'Belum' (examination started but not completed)
) VALUES (...)
```

**Status Timeline:**
| Status | Meaning | Action |
|--------|---------|--------|
| 'Belum' | Not examined yet | Doctor starts exam |
| 'Sudah Anamnesa' | Anamnesis (history) completed | Continuing exam |
| 'Sudah' | Examination completed | Doctor finished |
| 'Batal' | Cancelled | Patient cancelled or couldn't be examined |

### Step 4: Complete Examination

#### When examination is done:

```sql
UPDATE reg_periksa
SET stts = 'Sudah'  -- or 'Sudah Anamnesa'
WHERE no_rawat = '2026/06/09/00001'
```

#### Also update registration status:

```sql
UPDATE pcare_pendaftaran
SET status = 'Pulang'  -- Patient discharged
WHERE no_rawat = '2026/06/09/00001'
```

### Success Response

```json
{
  "success": true,
  "message": "Pemeriksaan selesai",
  "examination_data": {
    "no_rawat": "2026/06/09/00001",
    "kd_dokter": "titis",
    "jam_reg": "09:15:00",
    "status": "Sudah"
  }
}
```

### Database State After Phase 3

```
reg_periksa (NEW/UPDATED):
┌──────────────────┬──────────┬───────────┬──────────┐
│ no_rawat         │ kd_dokter│ jam_reg   │ stts     │
├──────────────────┼──────────┼───────────┼──────────┤
│ 2026/06/09/00001 │ titis    │ 09:15:00  │ Sudah    │
└──────────────────┴──────────┴───────────┴──────────┘

pcare_pendaftaran (UPDATED):
┌──────────────────┬──────────┐
│ no_rawat         │ status   │
├──────────────────┼──────────┤
│ 2026/06/09/00001 │ Pulang   │
└──────────────────┴──────────┘
```

---

## Queue Number (noUrut) Generation

### Current Implementation

**Location:** Database trigger (NOT in PHP code)

**Format:** `[CLINIC_PREFIX][DAILY_COUNTER]`

### Clinic Prefix Assignment

| Clinic Code | Clinic Name | Prefix |
|------------|-------------|--------|
| 001 | POLI UMUM | A |
| 002 | POLI GIGI | B |
| 003 | POLI JANTUNG | C |
| ... | ... | ... |

### Daily Counter Reset

```
Queue Assignment by Time:
────────────────────────

2026-06-09 08:00 → A1 ✓
2026-06-09 08:15 → A2 ✓
2026-06-09 08:30 → A3 ✓
...
2026-06-09 16:00 → A15 ✓

2026-06-10 08:00 → A1 ✓ (counter resets daily)
2026-06-10 08:15 → A2 ✓
```

### How to Find Queue Number

After registration, query the queue number:

```sql
SELECT noUrut FROM pcare_pendaftaran 
WHERE no_rawat = '2026/06/09/00001'
```

**Display on:**
- Queue board
- Patient receipt
- Registration confirmation
- Queue management screen

---

## Key Protection Mechanisms

### 1. Atomic Transactions

**Used for:** Booking & Registration

```php
BEGIN TRANSACTION;
  // ... operations ...
COMMIT; // All or nothing
```

**If error occurs:**
```php
ROLLBACK; // Undo all changes
```

### 2. Race Condition Guard

```sql
UPDATE jadwal SET kuota = kuota - 1
WHERE ... AND kuota > 0  ← ⭐ CRITICAL
```

**Prevents:** Two simultaneous bookings from reducing quota twice

### 3. Foreign Key Constraints

```sql
FOREIGN KEY (no_rkm_medis) REFERENCES pasien(no_rkm_medis)
  ON DELETE CASCADE

FOREIGN KEY (kd_dokter) REFERENCES dokter(kd_dokter)
  ON DELETE CASCADE
```

**Ensures:** Referential integrity - no orphaned records

### 4. Unique Constraints

```sql
PRIMARY KEY (no_rawat)  -- No duplicate registrations
PRIMARY KEY (no_rkm_medis, tanggal_periksa)  -- No double bookings
```

---

## Status Flow Diagram

```
                    BOOKING FLOW
                         │
                         ▼
    booking_registrasi.status = 'Belum'
    (Patient booked, waiting to arrive)
                         │
         Patient arrives at clinic
                         │
                         ▼
    REGISTRATION PHASE - pcare_pendaftaran created
    │ noUrut assigned (A1, B2, etc)
    │ pcare_pendaftaran.status = 'Masuk'
    └─ booking_registrasi.status = 'Terdaftar'
                         │
         Patient called to examination room
                         │
                         ▼
    EXAMINATION PHASE - reg_periksa created
    │ reg_periksa.stts = 'Belum'
    │ Doctor examines patient
    └─ reg_periksa.stts = 'Sudah' / 'Sudah Anamnesa'
                         │
                         ▼
    pcare_pendaftaran.status = 'Pulang'
    (Patient discharged)

CANCELLATION PATH (anytime):
                         │
    booking_registrasi.status = 'Batal'
    (Quota restored if not yet registered)
```

---

## Error Scenarios & Recovery

### Scenario 1: Quota Exhausted During Booking

```
Situation:
- Last slot available
- Two patients click "Book" simultaneously
- One gets through, other gets error

Error Message:
"Kuota jadwal sudah habis. Silakan pilih jadwal lain."

Recovery:
- Patient tries another doctor/time
- Or cancels and rebooks later
```

### Scenario 2: Double Booking Attempt

```
Situation:
- Patient books appointment
- Patient tries to book same appointment again (refresh page)

Error Message:
"Booking tidak ditemukan atau sudah terdaftar."
(If trying Phase 2 with existing registration)

Why:
- PRIMARY KEY (no_rkm_medis, tanggal_periksa)
- Prevents duplicate booking
```

### Scenario 3: Invalid Doctor Schedule

```
Situation:
- Patient selects appointment on doctor's day off
- Or selects time outside schedule

Error Message:
"Jadwal dokter tidak ditemukan atau kuota sudah habis."

Recovery:
- Select valid doctor/date from available schedules
```

### Scenario 4: Patient Not in System

```
Situation:
- New patient tries to book
- Medical record number doesn't exist

Error Message:
"Pasien dengan nomor RM 'XXXXX' tidak ditemukan."

Recovery:
- Patient must register first (get medical record)
- Then book appointment
```

---

## Implementation in Code

### Service Class: `RegistrationService.php`

Located: `frontend/modules/pendaftaran/services/RegistrationService.php`

**Methods:**

```php
// PHASE 1: Booking
createBooking(noRkmMedis, tanggalPeriksa, kdDokter, kdPoli)
  ↓ Returns: booking_data with success message

// PHASE 2: Registration
registerPatient(noRkmMedis, tanggalPeriksa)
  ↓ Returns: registration_data with noUrut (queue number!)

// PHASE 3: Examination
startExamination(noRawat, kdDokter)
  ↓ Returns: examination_data

completeExamination(noRawat, status)
  ↓ Returns: completion confirmation

// Utilities
getRegistrationStatus(noRawat)
  ↓ Returns: complete status with all details

cancelBooking(noRkmMedis, tanggalPeriksa)
  ↓ Returns: cancellation confirmation
```

---

## Usage Examples

### Example 1: Complete Booking Flow

```php
$service = new RegistrationService();

// Phase 1: Patient books
try {
    $result = $service->createBooking(
        '005457',           // Patient RM
        '2026-06-09',       // Appointment date
        'titis',            // Doctor code
        '001'               // Clinic code
    );
    // ✓ Booking created, quota reduced
} catch (BadRequestHttpException $e) {
    // ✗ Error: quota exhausted or patient not found
}

// Phase 2: Patient arrives
try {
    $result = $service->registerPatient('005457', '2026-06-09');
    echo $result['registration_data']['noUrut'];  // Output: "A1"
    // ✓ Registered, queue number assigned!
} catch (BadRequestHttpException $e) {
    // ✗ Error: booking not found
}

// Phase 3: Doctor examines
try {
    $service->startExamination('2026/06/09/00001', 'titis');
    // ... doctor examines ...
    $service->completeExamination('2026/06/09/00001', 'Sudah');
    // ✓ Examination completed
} catch (BadRequestHttpException $e) {
    // ✗ Error: registration not found
}
```

### Example 2: Get Status Anytime

```php
$service = new RegistrationService();

$status = $service->getRegistrationStatus('2026/06/09/00001');

echo $status['noUrut'];               // "A1"
echo $status['nm_pasien'];            // "Budi Santoso"
echo $status['status_penanganan'];    // "Belum Ditangani" / "Sudah Ditangani"
```

---

## Database Indices for Performance

```sql
-- Recommended indices

-- booking_registrasi
CREATE INDEX idx_br_tanggal_status 
  ON booking_registrasi(tanggal_periksa, status);

CREATE INDEX idx_br_patient_date 
  ON booking_registrasi(no_rkm_medis, tanggal_periksa);

-- pcare_pendaftaran
CREATE INDEX idx_pp_tglDaftar_kdPoli 
  ON pcare_pendaftaran(tglDaftar, kdPoli);

CREATE INDEX idx_pp_noUrut_date 
  ON pcare_pendaftaran(noUrut, tglDaftar);

-- jadwal
CREATE INDEX idx_jd_harikerja_kdpoli 
  ON jadwal(hari_kerja, kd_poli);
```

---

## Conclusion

The complete 3-phase workflow ensures:

✅ **Data Integrity** - ACID transactions prevent data corruption  
✅ **Quota Management** - Real-time quota tracking with race-condition guards  
✅ **Queue System** - Automatic queue number assignment per clinic  
✅ **Audit Trail** - Complete history from booking to examination  
✅ **Error Handling** - Graceful recovery from failed operations  
✅ **Performance** - Optimized queries with proper indexing  

Patient journey: **Booking** → **Queue Number** → **Examination** ✓
