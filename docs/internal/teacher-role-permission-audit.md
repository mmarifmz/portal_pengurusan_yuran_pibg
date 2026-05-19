# Teacher Role Permission Audit

Updated: 2026-05-19

## Teacher-safe routes

- `GET /teacher/dashboard`
- `GET /teacher/class-progress`
- `GET /teacher/class-progress/{class}/details`
- `GET /teacher/records`
- `GET /teacher/records/family/{familyCode}`
- `GET /teacher/records/family/{familyCode}/payments/export`
- `GET /teacher/records/duplicates/{student}/review`
- `GET /students/families`
- `GET /school-calendar`

## Super Admin-only routes

- `POST /billing/setup/current-year`
- `POST|PATCH|DELETE /calendar-events*`
- `POST /teacher/records/parent-profile-sync`
- `PATCH /teacher/records/family/{familyCode}/parent-profile`
- `PATCH /teacher/records/family/{familyCode}/social-tags`
- `PATCH /teacher/records/students/{student}/tags`
- `DELETE /teacher/records/duplicates/{student}`
- `GET|POST /system/backups*`
- `GET|POST /students/import*`
- `GET|POST /admin/classes/*/whatsapp-*`
- `GET /admin/whatsapp-queue`
- `GET|POST /system/payment-*`
- `GET /system/visitor-logs*`
- `GET|POST /teacher/reconcile*`
- `GET|POST /teacher/social-tags*`
- `GET /teacher/finance-accounting*`
- `GET /teacher/family-login-monitor*`

## Admin-managed but not teacher routes

- `GET|POST|PATCH|DELETE /super-teacher/*`

## UI lock-down applied

- Teacher sidebar now keeps the read-only surface focused on `Dashboard` and `Class Progress`.
- `Setup/Sync RM100 Family Billing` is hidden from teacher-visible pages.
- Family profile edit forms, duplicate delete actions, and calendar-management controls are hidden unless the user can manage records/calendar as Super Admin.
- WhatsApp queue, blast, onboarding invite, backup, import, and billing setup actions remain backend-protected even if a URL is visited directly.

## TODO / ambiguous follow-up

- `GET /teacher/records*` remains available as read-only because it is used for payment detail lookups; if the product decision changes, this can be narrowed further to Class Progress only.
- `GET /teacher/contribution-leaderboard` remains route-accessible for teacher-compatible staff but is no longer shown in the teacher sidebar.
