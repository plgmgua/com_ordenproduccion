# Project Rules for com_ordenproduccion

## 📋 **Development Workflow Rules**

### **1. Code Changes & Version Control**
- ✅ **MANDATORY**: Every code change must be committed and pushed to GitHub
- ✅ **MANDATORY**: Version number must be incremented with every improvement
- ✅ **MANDATORY**: Use semantic versioning (MAJOR.MINOR.PATCH)
- ✅ **MANDATORY**: Include descriptive commit messages

### **2. Deployment Process**
- ✅ **MANDATORY**: Use `update_build.sh` script for all deployments
- ✅ **MANDATORY**: Script must always use the same filename: `update_build.sh`
- ✅ **MANDATORY**: Script must delete existing files or overwrite them completely
- ✅ **MANDATORY**: Script must show commit version at the end (success or failure)
- ✅ **MANDATORY**: No separate deployment scripts - only `update_build.sh`

### **3. Database Changes**
- ✅ **MANDATORY**: All database changes must update `phpmyadmin_fix.sql`
- ✅ **MANDATORY**: Do NOT create separate SQL files for database changes
- ✅ **MANDATORY**: `phpmyadmin_fix.sql` is the single source of truth for database schema
- ✅ **MANDATORY**: Include both table creation and data insertion in the same file

### **4. File Management**
- ✅ **MANDATORY**: Always overwrite existing files with new versions
- ✅ **MANDATORY**: No file versioning or backup of old files during deployment
- ✅ **MANDATORY**: Complete replacement of component files on each deployment

### **5. Version Display**
- ✅ **MANDATORY**: `update_build.sh` must display the current commit version at the end
- ✅ **MANDATORY**: Version display must work for both successful and failed deployments
- ✅ **MANDATORY**: Version must be clearly visible in the output

### **6. Validation File Updates**
- ✅ **MANDATORY**: Always update `validate_deployment.php` with every code change
- ✅ **MANDATORY**: Validation file must reflect current version number
- ✅ **MANDATORY**: Validation file must check all new features and files
- ✅ **MANDATORY**: Update validation script version to match deployment script version

## 🚫 **What NOT to Do**

- ❌ **NEVER** create multiple deployment scripts
- ❌ **NEVER** create separate SQL files for database changes
- ❌ **NEVER** skip committing and pushing changes
- ❌ **NEVER** deploy without version increment
- ❌ **NEVER** keep old files during deployment (always overwrite)
- ❌ **NEVER** skip updating validation file with code changes

## 📝 **Standard Process**

1. **Make code changes**
2. **Update version number**
3. **Commit and push to GitHub**
4. **Run `update_build.sh` on server**
5. **Verify deployment with version display**

---

**Last Updated**: 2025-01-27  
**Version**: 1.0.0