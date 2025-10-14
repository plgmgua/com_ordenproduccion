# Google Drive PDF Import Script

This script downloads Google Drive PDF files and updates the database with local file paths, organizing files by year/month folders.

## Features

- **Automatic Download**: Downloads PDF files from Google Drive URLs
- **Organized Storage**: Creates year/month folder structure (e.g., `2025/01/`)
- **File Renaming**: Renames files with COT prefix (e.g., `ORD-005483` → `COT-005483.pdf`)
- **Database Update**: Updates the same `quotation_files` field with local paths
- **Comprehensive Logging**: Creates detailed logs with success/error information
- **Progress Tracking**: Shows progress during processing
- **Error Handling**: Continues processing even if individual files fail

## Prerequisites

1. **Google Service Account**: The file `leernuevacotizacion-7b11714cae3f.json` must be in the `helpers/` folder
2. **Google API Library**: Install using the setup script
3. **Database Access**: Uses the same credentials as `import_cli.php`

## Installation

### Step 1: Install Google API Library

```bash
./setup_google_api.sh
```

This will install the required Google API client library using Composer.

### Step 2: Verify Credentials File

Ensure the Google service account credentials file is in the correct location:

```bash
ls -la helpers/leernuevacotizacion-7b11714cae3f.json
```

## Usage

### Run the Import Script

```bash
php google_drive_import.php
```

### What the Script Does

1. **Connects to Database**: Uses credentials from `import_cli.php`
2. **Finds Records**: Searches for records with Google Drive URLs in `quotation_files` field
3. **Downloads Files**: Downloads each PDF file from Google Drive
4. **Organizes Files**: Creates year/month folders based on the `created` date
5. **Renames Files**: Uses COT prefix instead of ORD prefix
6. **Updates Database**: Replaces Google Drive URLs with local file paths
7. **Creates Logs**: Logs all operations to `google_drive_import.log`

## File Organization

### Download Directory Structure

```
/var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/
├── 2024/
│   ├── 01/
│   │   ├── COT-005001.pdf
│   │   └── COT-005002.pdf
│   ├── 02/
│   │   └── COT-005003.pdf
│   └── ...
├── 2025/
│   ├── 01/
│   │   └── COT-005483.pdf
│   └── ...
```

### File Naming Convention

- **Original**: `ORD-005483` (from `orden_de_trabajo` field)
- **New**: `COT-005483.pdf` (with COT prefix and PDF extension)
- **Path**: `media/com_ordenproduccion/cotizaciones/2025/01/COT-005483.pdf`

## Database Changes

### Before Import
```sql
quotation_files = "https://drive.google.com/open?id=1trmwEDic18ke7Z_zKJXOqOZ5oa0qf64f"
```

### After Import
```sql
quotation_files = "media/com_ordenproduccion/cotizaciones/2025/01/COT-005483.pdf"
```

## Logging

The script creates detailed logs in `google_drive_import.log` with:

- **Timestamp**: When each operation occurred
- **Record Information**: Order number and database ID
- **File Details**: Google Drive file name and size
- **Download Status**: Success or failure for each file
- **Database Updates**: Confirmation of database changes
- **Error Details**: Specific error messages for failed operations

### Log Example

```
[2025-01-27 14:30:15] [info] Processing record 1/150: ORD-005483 (ID: 5124)
[2025-01-27 14:30:16] [info] File: Cotizacion_ORD_005483.pdf (Size: 245760 bytes)
[2025-01-27 14:30:17] [success] File saved successfully: /var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/2025/01/COT-005483.pdf (Size: 245760 bytes)
[2025-01-27 14:30:18] [success] Database updated with local path: media/com_ordenproduccion/cotizaciones/2025/01/COT-005483.pdf
```

## Error Handling

The script handles various error scenarios:

- **Invalid URLs**: Skips records with malformed Google Drive URLs
- **Access Denied**: Logs errors when Google Drive files are not accessible
- **Download Failures**: Continues processing other files if one fails
- **Database Errors**: Logs database update failures
- **File System Errors**: Handles directory creation and file writing issues

## Performance

- **Memory Limit**: Set to 512MB for large file processing
- **Time Limit**: No execution time limit for long operations
- **Progress Updates**: Shows progress every 10 records
- **Batch Processing**: Processes all records in a single run

## Troubleshooting

### Common Issues

1. **"Google API library not found"**
   - Run `./setup_google_api.sh` to install the library

2. **"Credentials file not found"**
   - Ensure `leernuevacotizacion-7b11714cae3f.json` is in the `helpers/` folder

3. **"Database connection failed"**
   - Verify database credentials in the script match your environment

4. **"Permission denied" creating directories**
   - Ensure the script has write permissions to the download directory

### Verification

After running the script, verify the results:

```bash
# Check log file
tail -f google_drive_import.log

# Check downloaded files
ls -la /var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/

# Check database updates
mysql -u joomla -p grimpsa_prod -e "SELECT orden_de_trabajo, quotation_files FROM joomla_ordenproduccion_ordenes WHERE quotation_files LIKE 'media/%' LIMIT 5;"
```

## Security Notes

- **Service Account**: The Google service account should have read-only access
- **File Permissions**: Downloaded files are set to 644 permissions
- **Database Updates**: Only updates the `quotation_files` field
- **Log Files**: Contains sensitive information, secure appropriately

## Support

For issues or questions:

1. Check the log file for detailed error information
2. Verify all prerequisites are met
3. Ensure proper file permissions
4. Check database connectivity

The script is designed to be robust and continue processing even if individual files fail, providing comprehensive logging for troubleshooting.
