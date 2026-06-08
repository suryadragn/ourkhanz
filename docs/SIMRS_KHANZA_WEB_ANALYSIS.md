# SIMRS-Khanza to Yii2 Advanced Analysis

## Scope Summary
This repository now uses Yii2 Advanced as the web foundation with:
- Frontend entrypoint at `public/index.php`
- Backend entrypoint at `public/admin/index.php`
- Shared database `sik` on `127.0.0.1:3307`

## Source System Observations
From folder `C:/laragon/www/SIMRS-Khanza`, the source system appears to be a desktop-heavy/hybrid implementation with:
- Core source under `src/`
- Multiple satellite services (`KhanzaHMSService*`, `api-*`)
- Large report/document artifacts (`*.html`, `*.csv`, `*.pdf`)
- Domain breadth spanning registration, outpatient, inpatient, pharmacy, laboratory, radiology, billing, and bridging integrations.

## Current Web Architecture (Implemented)
### Backend Modules
- master
- pendaftaran
- rawatjalan
- rawatinap
- igd
- farmasi
- lab
- radiologi
- keuangan
- laporan
- bridging
- setting

### Frontend Modules
- pasien
- pendaftaran
- antrean
- jadwal
- billing
- lab
- radiologi
- farmasi

All modules are scaffolded with:
- Module class
- Default controller
- Initial index view

## Feature Parity Status
- Foundation & routing: Ready
- Database connectivity to `sik`: Ready
- Module skeletons for major SIMRS domains: Ready
- Full behavioral parity with original SIMRS-Khanza: Not yet complete

## Why Full Parity Needs Phased Delivery
SIMRS-Khanza has broad and deep workflows (clinical, admin, finance, reporting, and external bridging).
A safe migration to web requires staged implementation and validation to prevent clinical/business regression.

## Recommended Implementation Phases
1. Master data + authentication/authorization + audit logging
2. Registration and outpatient flow
3. Inpatient and emergency flow
4. Pharmacy dispensing + stock control
5. Laboratory and radiology ordering/results
6. Billing, claims, and financial reports
7. BPJS/bridging integrations and interoperability hardening
8. UAT, performance testing, and go-live hardening

## Notes
- Existing imported DB `sik` is used as the primary datasource.
- This foundation is ready for iterative CRUD, workflow orchestration, and report migration per module.
