# SIMRS-Khanza OurKhanz Documentation

Complete technical documentation for the hospital management system built with Yii2 Advanced.

## Quick Navigation

### 📋 Getting Started
- **[Installation Guide](../README.md#installation)** - Setup and deployment
- **[Project Overview](../README.md)** - Features and architecture

### 🗄️ System Architecture

#### Database & Data Flow
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** - Complete schema reference with:
  - Table structures and relationships
  - Foreign key constraints
  - Field descriptions and data types
  - Query patterns and performance tips
  - Partition strategy for large datasets

- **[REGISTRATION_FLOW_ANALYSIS.md](REGISTRATION_FLOW_ANALYSIS.md)** - In-depth technical analysis:
  - Patient registration workflow (3 phases)
  - Queue number (noUrut) generation
  - Quota tracking and management
  - Data consistency rules
  - Integration points
  - Critical issues and recommendations

### 🔌 API Reference

- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** - REST API endpoints:
  - `/pendaftaran/default/schedules` - Get doctor schedules
  - `/pendaftaran/default/patients` - Search patients
  - `/pendaftaran/default/index` - Submit bookings
  - Response formats and examples
  - Error handling
  - Testing utilities

### 🎯 Key Topics

#### Queue Management System

**How Queue Numbers Are Assigned:**

Queue numbers (`noUrut`) in the SIMRS-Khanza system are **generated at the database layer**, not in PHP code:

```
Clinic Clinic Code → Queue Format
─────────────────────────────────
POLI UMUM    001      A1, A2, A3, ... (resets daily)
POLI GIGI    002      B1, B2, B3, ... (resets daily)
```

**When Queue Number is Created:**
1. Patient makes a booking → stored in `booking_registrasi` (status = 'Belum')
2. Patient arrives → registration record created in `pcare_pendaftaran`
3. Queue number assigned automatically (via trigger/procedure) during pcare_pendaftaran INSERT

**Quota Calculation:**

```sql
Available = jadwal.kuota - COUNT(active bookings)
          = 5 - 2 = 3 slots remaining

Active bookings = booking_registrasi records where:
  - tanggal_periksa = target date
  - status NOT IN ('Batal', 'Dokter Berhalangan')
```

---

#### Registration Workflow

```
PHASE 1: BOOKING (Patient books online)
├─ Select date, doctor, clinic
├─ System validates quota > 0
├─ UPDATE jadwal: kuota -= 1
├─ INSERT booking_registrasi (status='Belum')
└─ COMMIT transaction

PHASE 2: REGISTRATION (Patient arrives)
├─ Staff confirms booking exists
├─ INSERT pcare_pendaftaran (auto-assign noUrut)
├─ UPDATE booking_registrasi (status='Terdaftar')
└─ Patient enters queue

PHASE 3: EXAMINATION (Doctor treats patient)
├─ INSERT reg_periksa (status='Belum')
├─ Doctor performs examination
├─ UPDATE reg_periksa (status='Sudah')
└─ Patient completed
```

**Key Protection Mechanism:**

```php
// Race condition guard prevents overbooking
UPDATE jadwal
SET kuota = kuota - 1
WHERE kd_dokter = ?
  AND hari_kerja = ?
  AND kuota > 0  ← ⭐ CRITICAL: Check before decrement
  
// If rows affected = 0, rollback (quota exhausted)
```

---

#### Data Integrity Rules

**Booking Phase:**
- ✓ Duplicate prevention: PRIMARY KEY (no_rkm_medis, tanggal_periksa)
- ✓ Referential integrity: FK constraints enforced
- ✓ Quota atomicity: ACID transaction with race-condition guard
- ✓ Status validation: ENUM prevents invalid states

**Registration Phase:**
- ✓ Auto-generated ID: no_rawat format = YYYY/MM/DD/NNNNN
- ✓ Queue number uniqueness: Per clinic per date
- ✓ Booking linkage: Must have matching booking_registrasi record
- ✓ Patient verification: no_rkm_medis must exist in pasien table

---

### 🏥 Hospital-Specific Features

#### Multi-Clinic Support

The system supports multiple clinics (poliklinik) with separate:
- Doctor schedules per clinic
- Queue numbers per clinic (A=POLI UMUM, B=POLI GIGI, etc.)
- Quota management independent per clinic

#### Real-Time Queue Display

Recent registrations are displayed with:
- Queue number (noUrut)
- Patient name and medical record
- Registration time and booking time
- Doctor name and assigned clinic
- Treatment status (Belum/Sudah/Batal)
- Badge colors indicating status

#### Quota Management

- **Original quota** set by admin (e.g., 5 slots per doctor per day)
- **Real-time updates** calculated from active bookings
- **Atomic operations** ensure no overbooking
- **Flexible cancellation** can free up slots

---

### 📊 Data Models

#### Core Registration Tables

| Table | Purpose | Key Lookup |
|-------|---------|-----------|
| `pasien` | Patient master data | no_rkm_medis |
| `booking_registrasi` | Pre-registration bookings | (no_rkm_medis, tanggal_periksa) |
| `pcare_pendaftaran` | Actual registrations + queue | no_rawat |
| `reg_periksa` | Examination records | no_rawat |
| `jadwal` | Doctor schedules | (kd_dokter, hari_kerja, jam_mulai) |
| `dokter` | Doctor information | kd_dokter |
| `poliklinik` | Clinic/department info | kd_poli |

#### Relationships

```
pasien (1:M) ← booking_registrasi (M:1) → jadwal ← dokter
       ↓
pcare_pendaftaran (1:1) → reg_periksa (M:1) → dokter
```

---

### 🔧 Performance Optimization

#### Recommended Indices

```sql
-- booking_registrasi
CREATE INDEX idx_br_tanggal_status 
  ON booking_registrasi(tanggal_periksa, status);

-- pcare_pendaftaran  
CREATE INDEX idx_pp_tglDaftar_kdPoli 
  ON pcare_pendaftaran(tglDaftar, kdPoli);

-- jadwal
CREATE INDEX idx_jd_harikerja_kdpoli 
  ON jadwal(hari_kerja, kd_poli);
```

#### Query Optimization

- **Quota calculation:** Left join with aggregation (pre-computed in PHP)
- **Queue display:** Limit to current date (indexed on tglDaftar)
- **Schedule search:** Filter by hari_kerja + kd_poli (indexed)

---

### ⚠️ Known Issues & Recommendations

#### Issue #1: Queue Number Generation
- **Status:** Not implemented in PHP code
- **Location:** Database trigger/procedure (not visible in application)
- **Impact:** Cannot generate queue numbers programmatically
- **Recommendation:** Document the exact trigger logic or implement in PHP

#### Issue #2: No Authentication
- **Status:** Development mode (no auth required)
- **Impact:** Anyone can make bookings for any patient
- **Recommendation:** Implement JWT or session-based auth before production

#### Issue #3: No Rate Limiting
- **Status:** Disabled
- **Impact:** Vulnerable to DoS attacks
- **Recommendation:** Add rate limiter middleware (max 100 req/min per IP)

#### Issue #4: Race Condition Edge Case
- **Status:** Mitigated with WHERE kuota > 0
- **Impact:** Rare case where multiple simultaneous requests slip through
- **Recommendation:** Add application-level retry logic

---

### 📚 File Structure

```
docs/
├── README.md (this file)
├── DATABASE_SCHEMA.md
│   ├── Table definitions
│   ├── Foreign key relationships
│   ├── Query patterns
│   └── Performance tips
├── REGISTRATION_FLOW_ANALYSIS.md
│   ├── Workflow diagrams
│   ├── Queue system details
│   ├── Data integrity rules
│   ├── Integration points
│   └── Critical issues
└── API_DOCUMENTATION.md
    ├── Endpoint reference
    ├── Response formats
    ├── Error handling
    ├── Testing utilities
    └── Use cases
```

---

### 🚀 For Developers

#### Implementing New Features

1. **New Clinic Module?**
   - Refer to [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#poliklinik)
   - Check queue number format in [REGISTRATION_FLOW_ANALYSIS.md](REGISTRATION_FLOW_ANALYSIS.md#21-queue-number-generation-mechanism)

2. **Modifying Booking Logic?**
   - Review quota management in [REGISTRATION_FLOW_ANALYSIS.md](REGISTRATION_FLOW_ANALYSIS.md#4-quota-tracking--management)
   - Check atomic operation requirements in [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#atomic-booking-operation)

3. **Adding API Endpoints?**
   - See [API_DOCUMENTATION.md](API_DOCUMENTATION.md#endpoints) for pattern
   - Follow response format shown in endpoint examples

4. **Performance Tuning?**
   - Create indices from [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#recommended-indices)
   - Implement partitioning if dataset > 1M rows

---

### 🐛 Troubleshooting

#### "Kuota jadwal sudah habis"
→ See [REGISTRATION_FLOW_ANALYSIS.md](REGISTRATION_FLOW_ANALYSIS.md#42-quota-statuses)

#### Queue number not showing
→ Check [REGISTRATION_FLOW_ANALYSIS.md](REGISTRATION_FLOW_ANALYSIS.md#queue-display-logic)

#### Duplicate bookings occurring
→ Review race condition guards in [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#atomic-booking-operation)

#### Slow schedule queries
→ Create indices from [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#recommended-indices)

---

### 📞 Support

- **Documentation Issues:** Update this folder
- **Code Issues:** [GitHub Issues](https://github.com/suryadragn/ourkhanz/issues)
- **Database Schema Questions:** Refer to [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)
- **API Questions:** Refer to [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

---

### 📄 License

Part of SIMRS-Khanza ecosystem. See [LICENSE](../LICENSE) for details.

---

**Last Updated:** June 2026  
**Documentation Version:** 1.0.0
