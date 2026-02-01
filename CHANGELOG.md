# Changelog

All notable changes to the Com Orden Producción component will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Future features and improvements

### Changed
- N/A

### Fixed
- N/A

### Security
- N/A

## [3.54.0-STABLE] - 2025-02-01

### Added
- **Many-to-many payment documents and work orders**
  - Multiple payment documents can be associated with a single work order
  - Multiple work orders can be associated with a single payment document
  - Payment info popup: view all payment information (owner or Administracion group only)
  - Default amount in payment form set to order value (editable)
  - Support for partial/advance payments

### Changed
- **Payment registration**
  - Removed read-only restriction when order already has payments
  - "Add order" dropdown shows orders with remaining balance (same customer only)
  - Existing payments displayed as info table, form always editable
- **Access control**: Payment info popup uses same access as valor a facturar (owner + Administracion)

### Database Changes
- New junction table `#__ordenproduccion_payment_orders` (payment_proof_id, order_id, amount_applied)
- Migration: existing payment_proof_id/payment_value from ordenes migrated to junction table
- Removed payment_proof_id and payment_value columns from ordenes
- payment_proofs.order_id made nullable

## [3.4.0-STABLE] - 2025-10-29

### Added
- **Weekly Schedule for Employee Groups**
  - Day-specific work schedules (Monday-Sunday)
  - Each day can have different start times, end times, and expected hours
  - Enable/disable individual days (e.g., weekends off)
  - Optional notes per day
  - "Apply to All Days" button for quick setup
  - Perfect for scenarios like "Friday ends 1 hour earlier"
  
### Changed
- **Attendance Evaluation Enhancement**
  - System now checks day of week and uses appropriate schedule
  - Falls back to default group schedule if day-specific schedule not set
  - Supports non-working days (Saturday/Sunday can be disabled)

### Database Changes
- Added `weekly_schedule` JSON column to `#__ordenproduccion_employee_groups` table
- Migration automatically converts existing groups to weekly format with same schedule for all days

### New Features
- Interactive weekly schedule editor in employee group form
- Visual table showing all 7 days with individual controls
- Real-time JavaScript updates to schedule data
- Days can be enabled/disabled with checkboxes
- Form validation ensures data integrity

### User Interface
- Reorganized employee group form into sections (Basic Info, Default Schedule, Weekly Schedule)
- Color-coded headers for better visual organization
- Responsive table design for schedule editor
- Disabled state for non-working days with visual feedback

## [3.3.0-STABLE] - 2025-10-29

### Added
- **Employee Management System**
  - Employee Groups with customizable work schedules
  - Group-specific settings: start time, end time, expected hours, grace period
  - Color-coded groups for visual identification
  - Employee management with group assignment
  - Department and position tracking
  - Contact information management (email, phone)
  - Hire date tracking
  - Active/inactive employee status
  - Admin interfaces for managing groups and employees

### Changed
- **Attendance System Enhancement**
  - Attendance evaluation now uses employee group schedules instead of global settings
  - Each employee can be assigned to a group with specific work hours
  - Late/early exit detection based on group schedule settings
  - Expected daily hours now pulled from group configuration
  - Grace period now configurable per group

### Database Changes
- Added `#__ordenproduccion_employee_groups` table for employee group management
- Updated `#__ordenproduccion_employees` table with group assignment and additional fields
- Migration script for updating existing employee records

### New Components
- EmployeegroupsModel: List model for employee groups (admin)
- EmployeegroupModel: Form model for employee group management (admin)
- EmployeesModel: Enhanced list model with group information (admin)
- EmployeeModel: Enhanced form model with group assignment (admin)
- EmployeegroupController: Admin controller for group operations
- EmployeegroupsController: Admin controller for batch group operations
- EmployeeController: Enhanced admin controller for employee operations
- EmployeesController: Enhanced admin controller for batch employee operations
- Admin views and templates for groups and employees management
- Multi-language support for employee management (English and Spanish)

### Accessing Admin Features
- **Employee Groups**: Administration → Components → Ordenes Produccion → Employee Groups
  - Direct URL: `index.php?option=com_ordenproduccion&view=employeegroups`
- **Employees**: Administration → Components → Ordenes Produccion → Employees
  - Direct URL: `index.php?option=com_ordenproduccion&view=employees`

## [3.2.0-STABLE] - 2025-10-28

### Added
- **Time & Attendance System (Asistencia)**
  - Complete attendance tracking system with biometric device integration
  - Real-time attendance monitoring and reporting
  - Daily summary calculations with automatic work hour tracking
  - Employee registry management with custom schedules
  - Manual attendance entry capability for device failures
  - Advanced filtering and search capabilities
  - Statistical dashboard with key metrics (total employees, complete days, late arrivals, average hours)
  - CSV export functionality for reporting
  - Late arrival and early exit detection with grace period
  - Multi-language support (English and Spanish)
  - Responsive design for mobile and desktop

### Database Changes
- Added `#__ordenproduccion_asistencia` table for attendance records
- Added `#__ordenproduccion_asistencia_summary` table for daily summaries
- Added `#__ordenproduccion_employees` table for employee registry
- Migration script for existing attendance data from old structure
- New configuration settings for attendance tracking

### New Components
- AsistenciaHelper: Helper class for attendance calculations
- AsistenciaModel: List model for attendance records
- AsistenciaentryModel: Form model for manual entry
- AsistenciaController: Main controller for attendance operations
- AsistenciaentryController: Form controller for entry management
- HtmlView classes for both list and entry views
- Complete template system with default.php and edit.php
- Form XML definitions for manual entry
- Menu item type XML configuration
- Dedicated CSS and JavaScript for the interface

### Features
- Automatic calculation of daily work hours
- First entry and last exit tracking
- Expected vs. actual hours comparison
- Late arrival detection with configurable grace period
- Early exit detection
- Entry type tracking (biometric vs. manual)
- Recalculate summaries function for date ranges
- Export to CSV for external analysis
- Real-time statistics and metrics

## [3.1.2-STABLE] - 2025-10-12

### Fixed
- **CRITICAL: Data Import Date Preservation**
  - Fixed `import_cli.php` to use `marca_temporal` (timestamp field) for `request_date`
  - **Previous Issue**: All imported work orders had `request_date` set to October 8th, 2025 (import date)
  - **Root Cause**: Script used `fecha_de_solicitud` instead of `marca_temporal`, with fallback to current date
  - **Correct Mapping**: `marca_temporal` → `request_date` (preserves original timestamps)
  - **Enhanced Date Conversion Functions**:
    - `convertDate()`: Handles 7+ date formats (DD/MM/YYYY, YYYY-MM-DD, timestamps, etc.)
    - `convertDateTime()`: Handles Unix timestamps + multiple datetime formats
    - Removed fallback to current date (preserves data integrity)
    - Logs problematic dates for debugging
    - Allows NULL values (database supports it)
  - **Benefits**: Historical dates preserved, no data loss, better error reporting
  - **To Re-import**: Run `php import_cli.php` with corrected date mapping

### Changed
- Import script no longer uses current date as fallback for failed date conversions
- NULL values allowed for dates that cannot be converted

## [3.1.1-STABLE] - 2025-10-12

### Fixed
- **Bootstrap Loading Issue** in "Administracion" dashboard view
  - Error: "There is no 'bootstrap.bundle' asset of a 'script' type in the registry"
  - Fixed by replacing WebAssetManager calls with HTMLHelper::_('bootstrap.framework')
  - Proper Joomla 5.x asset loading for Bootstrap and jQuery

## [3.1.0-STABLE] - 2025-10-12

### Added
- **New "Administracion" Dashboard** (Menu Item Type)
  - Statistics dashboard for work order management
  - Displays count of work orders for current month
  - Month/Year filter for custom date ranges
  - Top 10 orders by "valor_factura" (invoice value)
  - Responsive Bootstrap-based layout
  - Real-time statistics with SQL aggregation
  - New view: `com_ordenproduccion/src/View/Administracion/HtmlView.php`
  - New model: `com_ordenproduccion/src/Model/AdministracionModel.php`
  - New template: `com_ordenproduccion/tmpl/administracion/default.php`
  - New menu item type: `com_ordenproduccion/tmpl/administracion/default.xml`
  - Language strings for dashboard (English and Spanish)

### Changed
- Component version updated to 3.1.0-STABLE for new major feature

## [2.6.0-STABLE] - 2025-10-11

### Added
- **Ventas Section** to Actions module (`mod_acciones_produccion`)
  - New "VENTAS" section with "Duplicar Solicitud" button (dummy for now)
  - Users in `ventas` group see Ventas section
  - Users in `produccion` group see Produccion section
  - Users in both groups see both sections
- **Settings Page Enhancement**
  - New "Configuración de Ventas" section
  - `duplicate_request_endpoint` field (HTTP URL for duplicate requests)
  - `duplicate_request_api_key` field (Optional Bearer token for authentication)
  - Information panel explaining Ventas actions
- **Database Schema**
  - SQL script: `helpers/add_ventas_settings_columns.sql`
  - Adds `duplicate_request_endpoint` VARCHAR(500) column
  - Adds `duplicate_request_api_key` VARCHAR(200) column
- **Language Strings** (English and Spanish)
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_ENDPOINT
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_ENDPOINT_DESC
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_API_KEY
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_API_KEY_DESC
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS_INFO
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS_INFO_DESC

### Changed
- **Module Restructure**: `mod_acciones_produccion` v2.1.0-STABLE
  - Renamed module title to "ACCIONES" (Actions)
  - Restructured into two sections: "PRODUCCION" and "VENTAS"
  - Improved visual hierarchy with section titles and icons
  - Consistent styling for both sections
- **Access Control Logic**
  - Module checks both `produccion` and `ventas` user groups
  - Module hidden if user is not in either group
  - Section visibility based on group membership
- **Settings Model Enhancement**
  - Updated `save()` method to handle new Ventas fields
  - Added fields to both INSERT and UPDATE queries

### Next Steps
1. Run SQL script: `helpers/add_ventas_settings_columns.sql` in phpMyAdmin
2. Configure endpoint URL in Component → Settings → Ventas Settings
3. Implement actual HTTP request logic for "Duplicar Solicitud" button (future task)

## [2.5.1-STABLE] - 2025-10-11

### Fixed
- **CRITICAL FIX**: Corrected menu item type XML location for Joomla 5.x
  - Moved from `views/[viewname]/metadata.xml` (Joomla 3.x) to `tmpl/[viewname]/default.xml` (Joomla 5.x)
  - Created `tmpl/cotizaciones/default.xml`
  - Created `tmpl/ordenes/default.xml`
  - Created `tmpl/orden/default.xml`
  - Removed obsolete `views/` directory

### Changed
- Updated troubleshooting.php with menu item type debugging
- Compared component structure with com_odoocontacts to identify correct XML location

## [2.0.2-STABLE] - 2025-01-27

### Fixed
- Updated component menu name to "Ordenes Produccion" for both English and Spanish
- Fixed deployment script filename references (fix_production_component.php)
- Added SQL scripts for updating Joomla database versions
- Synchronized version-control branch with latest changes

### Changed
- Component menu displays consistently as "Ordenes Produccion" regardless of language
- Deployment script now correctly references renamed files

## [2.0.1-STABLE] - 2025-01-27

### Fixed
- Fixed deployment script error: "fix_produccion_component.php not found in repository"
- Updated all filename references from fix_produccion_component.php to fix_production_component.php
- Deployment now completes successfully without filename mismatch errors

## [2.0.0-STABLE] - 2025-01-27

### Added
- Complete production-ready component with full functionality
- Working PDF generation for work orders and shipping slips
- AJAX status updates for production orders
- Proper language support (English/Spanish)
- Full admin interface with menu items
- Production actions module (mod_acciones_produccion)
- Database integration with EAV pattern
- Webhook system for external integrations
- Debug console and logging system

### Changed
- Major version bump to 2.0.0 indicating stable, production-ready release
- Component and module both updated to 2.0.0-STABLE
- Cleaned repository of debug and temporary files

## [1.0.0-ALPHA] - 2025-01-27

### Added
- Initial release
- Component description and documentation
- Git repository initialization
- Basic project structure

---

## Version History

- **1.0.0-ALPHA**: Initial alpha release with basic structure
- **Future versions**: Will be documented as development progresses

## Version Numbering

This component follows semantic versioning:
- **MAJOR**: Incompatible API changes
- **MINOR**: New functionality in a backwards compatible manner
- **PATCH**: Backwards compatible bug fixes
- **STAGE**: Pre-release stages (ALPHA, BETA, RC1, RC2, etc.)

## Release Stages

- **ALPHA**: Early development, features may be incomplete
- **BETA**: Feature complete, testing phase
- **RC**: Release candidate, final testing
- **STABLE**: Production ready release
