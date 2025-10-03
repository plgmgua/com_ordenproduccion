# com_ordenproduccion Project Rules

## Database Table Naming Convention

### **CRITICAL RULE: Table Prefix**
- **Component Name**: `com_ordenproduccion`
- **Table Prefix**: `#__ordenproduccion_` (NOT `#__produccion_`)

### Database Tables Structure
All database tables MUST use the prefix `#__ordenproduccion_`:

```sql
-- Main tables
#__ordenproduccion_ordenes          -- Main work orders table
#__ordenproduccion_config           -- Configuration settings
#__ordenproduccion_info             -- EAV data structure for flexible order data
#__ordenproduccion_technicians      -- Technician assignments
#__ordenproduccion_attendance       -- Daily attendance integration
#__ordenproduccion_webhook_logs     -- Webhook processing logs
#__ordenproduccion_shipping         -- Shipping and delivery tracking
```

### Component Naming Standards
- **Component Name**: `com_ordenproduccion`
- **Namespace**: `Grimpsa\Component\Ordenproduccion`
- **Class Prefix**: `Ordenproduccion`
- **File Names**: `ordenproduccion` (lowercase)

### Version Management
- **Current Version**: 1.0.0-STABLE
- **Version File**: Must be maintained in root `VERSION` file
- **Changelog**: Must be maintained in `CHANGELOG.md`

### Language Support
- **Primary Language**: Spanish (es-ES)
- **Secondary Language**: English (en-GB)
- **Language Files**: Must be created for both admin and site

### Security Requirements
- **CSRF Protection**: Required for all forms
- **Input Validation**: All user inputs must be validated
- **HMAC Webhook Security**: Required for external integrations
- **Access Control**: Joomla ACL integration required

### Database Compatibility
- **Existing Tables**: Must work with existing Grimpsa database structure
- **Migration**: Safe installation without data loss
- **EAV Pattern**: Use Entity-Attribute-Value for flexible order data

### Integration Requirements
- **Attendance System**: Integration with existing `asistencia` table
- **Google Sheets**: Import functionality from existing scripts
- **PDF Generation**: Shipping documents with QR codes
- **Webhook System**: External order import capabilities

## Development Standards

### Code Standards
- Follow Joomla 5.x coding standards
- Use PSR-4 autoloading
- Implement proper error handling
- Include comprehensive logging

### File Structure
- Follow Joomla component directory structure
- Separate admin and site functionality
- Use proper MVC pattern implementation
- Include proper service providers

### Testing Requirements
- Test all CRUD operations
- Test security measures
- Test webhook functionality
- Test PDF generation
- Test multilingual support

## Important Notes

1. **Table Prefix**: Always use `#__ordenproduccion_` - this is critical for database consistency
2. **Existing Data**: Component must work with existing Grimpsa database
3. **Backward Compatibility**: Maintain compatibility with existing scripts during transition
4. **Performance**: Optimize for production environment with large datasets
5. **Documentation**: All code must be properly documented

## Migration Strategy

When migrating from existing scripts:
1. Preserve existing data structure
2. Map existing table names to new component tables
3. Maintain existing functionality
4. Add new Joomla 5.x features
5. Ensure seamless transition

---

**Last Updated**: 2025-01-27
**Component Version**: 1.0.0-STABLE
**Joomla Compatibility**: 5.x (minimum 4.0)
