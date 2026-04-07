# Unavailable Dates — Design Spec

## Problem

There is no way to block bookings on specific dates (public holidays, coach vacations, studio closures). Availability is determined entirely by whether schedules exist and have remaining capacity. The only workaround is deactivating an entire recurring schedule, which removes it from all days.

## Solution

A single `unavailable_dates` table supporting both studio-wide closures and per-coach unavailability, with date range support.

## Data Model

### `unavailable_dates` table

| Column       | Type              | Notes                                         |
|--------------|-------------------|-----------------------------------------------|
| `id`         | bigint            | PK                                            |
| `coach_id`   | nullable FK → coaches | `null` = studio-wide, set = per-coach     |
| `start_date` | date              | Start of unavailable period                   |
| `end_date`   | date              | End of period (same as start for single days) |
| `created_at` | timestamp         | Standard                                      |
| `updated_at` | timestamp         | Standard                                      |

Index on `(start_date, end_date)` for date range queries. Index on `coach_id` for per-coach lookups. Foreign key `coach_id` uses `onDelete('cascade')` so deleting a coach removes their unavailable dates.

### `UnavailableDate` model

- `belongsTo(Coach::class)` — nullable relationship
- Scope `forDate(Carbon $date)` — filters to rows where `start_date <= $date AND end_date >= $date`
- Scope `studioWide()` — filters to `coach_id IS NULL`
- Scope `forCoach(int $coachId)` — filters to specific coach

## Availability Logic

Modify `ScheduleAvailabilityService` (`app/Services/ScheduleAvailabilityService.php`):

### `unavailableDates()` method

Before iterating schedules for a given date:

1. Check for studio-wide block → if exists, mark date unavailable immediately (skip schedule loop)
2. Load per-coach blocks for the date → collect blocked coach IDs
3. When iterating schedules, skip any schedule whose coach is in the blocked set
4. If no schedules remain after filtering, date is unavailable

### `availableTimeSlots()` method

Same logic applied per-slot:

1. If studio-wide block exists for the date → return empty array
2. Load blocked coach IDs for the date
3. Skip schedules belonging to blocked coaches

### Query optimization

Load all `UnavailableDate` records for the date range once at the start of `unavailableDates()`, rather than querying per-date. For `availableTimeSlots()` (single date), one query suffices.

## Admin UI

### Per-coach unavailability — Coach edit page

**Location:** Existing coach edit page (`/admin/coaches/{coach}/edit`)

Add a separate Livewire component (`coach.coach-unavailable-dates`) embedded below the existing coach form. This keeps the unavailable dates logic separate from the coach profile form.
- Table listing existing unavailable date ranges (start date, end date, delete button)
- "Add" button with inline start/end date pickers (Flux UI `flux:date-picker`)
- Delete button per row removes the entry
- Validation: end_date >= start_date, dates must not be in the past

### Studio-wide closures — New admin page

**Location:** New route `/admin/closures` with sidebar nav item "Slēgumi"

- Table listing all studio-wide closure date ranges
- "Add" button with start/end date pickers
- Delete button per row
- Same validation rules as per-coach

Both use Flux UI components consistent with existing admin pages.

## Testing

### Model tests
- `UnavailableDate` scopes: `forDate`, `studioWide`, `forCoach`
- Date range boundary conditions

### Availability service tests
- Studio-wide closure blocks all slots on that date
- Per-coach unavailability blocks only that coach's schedules
- Other coaches remain available when one coach is blocked
- Multi-day range blocks all dates in span
- Dates outside the range remain available
- Overlapping coach + studio-wide blocks handled correctly

### Admin UI tests
- Coach edit page: add and delete unavailable dates
- Studio closures page: add and delete closures
- Validation: end_date >= start_date, no past dates
- Deleting a coach cascades to their unavailable dates

## Key Files to Modify

- `app/Services/ScheduleAvailabilityService.php` — core availability logic
- `resources/views/components/coach/⚡coach-edit.blade.php` — embed unavailable dates component
- `resources/views/components/layouts/app/sidebar.blade.php` — new nav item
- `routes/web.php` — new closures route
- `app/Models/Coach.php` — add `unavailableDates()` relationship

## New Files

- Migration: `create_unavailable_dates_table`
- `app/Models/UnavailableDate.php`
- `database/factories/UnavailableDateFactory.php`
- `resources/views/components/closure/⚡closure-list.blade.php` — studio closures page
- `resources/views/components/coach/⚡coach-unavailable-dates.blade.php` — per-coach unavailability component
- `resources/views/dashboard/closure-list.blade.php` — dashboard view wrapper
- Test files for model, service, and admin UI
