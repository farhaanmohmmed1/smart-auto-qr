# Smart Auto QR - Flexible Import System

## Overview

The bulk import system now supports **flexible column mapping** which allows you to:
- ✅ Upload files with columns in **any order**
- ✅ Omit optional fields (they'll be stored as blank/NULL)
- ✅ Use alternative column names (flexible header matching)
- ✅ Keep only **required fields** (Auto Number, Driver Name) mandatory

---

## Required Fields (MUST HAVE)

These fields are **mandatory** and must be present in your file:

| Field | Description | Format | Example |
|-------|-------------|--------|---------|
| **Auto Number** | Vehicle registration number | Indian format: XX NN XX NNNN | `AP 40 CB 6407` or `AP40CB6407` |
| **Driver Name** | Driver's full name | 3-100 characters | `Ramesh Kumar` |

---

## Optional Fields (CAN OMIT)

These fields will be stored as **empty/blank** in the database if missing from your file:

| Field | Description | Format | Example |
|-------|-------------|--------|---------|
| Reg Number | Vehicle registration number | Alphanumeric | `TS09EA1234` |
| Phone | Driver contact number | 10-12 digits | `9876543210` |
| License Number | Driver's license ID | Alphanumeric | `TS14DL20190001` |
| Permit Number | Operating permit ID | Alphanumeric | `HYD/PERMIT/2024/001` |
| Area | Operating zone/area | Any text | `Ameerpet` |
| Stand | Auto stand/depot name | Any text | `Ameerpet Stand` |

---

## Column Header Aliases

The system **automatically detects** columns by their header names. These alternatives are supported:

### Auto Number
- `Auto Number`, `Auto#`, `Auto No`, `auto_number`

### Driver Name
- `Driver Name`, `Driver`, `Driver's Name`, `driver_name`

### Registration Number
- `Registration Number`, `Reg Number`, `Reg#`, `Vehicle Reg`, `reg_number`

### Phone Number
- `Phone Number`, `Phone`, `Mobile`, `Contact`, `phone`

### License Number
- `License Number`, `License#`, `DL`, `License`, `license_number`

### Permit Number
- `Permit Number`, `Permit#`, `Permit`, `permit_number`

### Area
- `Area`, `Zone`, `Operating Area`, `area`

### Stand
- `Stand`, `Stand Name`, `Depot`, `Stand Depot`, `stand`

---

## Example 1: Minimal File (Only Required Fields)

```csv
Auto Number,Driver Name
AUTO-001,Ramesh Kumar
AUTO-002,Suresh Reddy
AUTO-003,Mahesh Yadav
```

**Result in Database:**
| auto_number | driver_name | reg_number | phone | license_number | permit_number | area | stand |
|-------------|-------------|-----------|-------|----------------|---------------|------|-------|
| AUTO-001 | Ramesh Kumar | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) |
| AUTO-002 | Suresh Reddy | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) |
| AUTO-003 | Mahesh Yadav | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) | (NULL) |

---

## Example 2: Reordered Columns

```csv
Driver Name,Auto Number,Phone,Area
Ramesh Kumar,AUTO-001,9876543210,Ameerpet
Suresh Reddy,AUTO-002,9845612345,Kukatpally
Mahesh Yadav,AUTO-003,9912378456,Secunderabad
```

**Result:** Works perfectly! Columns are detected by header names, not position.

---

## Example 3: Mixed Required + Some Optional Fields

```csv
Auto Number,Driver Name,Reg Number,License Number
AUTO-001,Ramesh Kumar,TS09EA1234,TS14DL20190001
AUTO-002,Suresh Reddy,TS09EB5678,TS14DL20200045
AUTO-003,Mahesh Yadav,TS09EC9101,TS14DL20180023
```

**Result:** Phone, Permit, Area, Stand will be blank. Others filled.

---

## Example 4: Alternative Header Names

```csv
Auto#,Driver,Phone Number,Reg#
AUTO-001,Ramesh Kumar,9876543210,TS09EA1234
AUTO-002,Suresh Reddy,9845612345,TS09EB5678
```

**Works!** The system recognizes alternative header names.

---

## Error Handling

### ❌ Will Fail - Missing Required Field
```csv
Driver Name,Phone
Ramesh Kumar,9876543210
```
**Error:** "Missing required columns: auto_number"

### ❌ Will Fail - Invalid Auto Number Format
```csv
Auto Number,Driver Name
123,Ramesh Kumar
```
**Error:** "Invalid auto number format"

### ❌ Will Fail - Short Driver Name
```csv
Auto Number,Driver Name
AUTO-001,Ra
```
**Error:** "Driver name must be 3-100 characters"

### ✅ Will Succeed - Blank Optional Fields
```csv
Auto Number,Driver Name,Phone
AUTO-001,Ramesh Kumar,(blank)
AUTO-002,Suresh Reddy,(blank)
```
**Result:** Records created with blank phone numbers.

---

## Validation Rules

| Field | Validation | Action if Invalid |
|-------|-----------|-------------------|
| Auto Number | 3-20 alphanumeric chars, no duplicates | ❌ Row rejected |
| Driver Name | 3-100 characters | ❌ Row rejected |
| Phone | 10-12 digits only | ❌ Row rejected (if provided) |
| License Number | No validation | ⚠️ Stored as-is |
| Permit Number | No validation | ⚠️ Stored as-is |
| All Optional | Can be blank/empty | ✅ Stored as NULL |

---

## Tips for Best Results

1. **Use the Sample Template**
   - Download the template from the admin panel
   - It includes all field names in correct format
   - Copy/paste your data into it

2. **Minimal File Approach**
   - If you only have Auto Number and Driver Name, that's enough!
   - Other fields can be added later via the admin panel

3. **Google Sheets Workflow**
   - Create spreadsheet with just: Auto Number, Driver Name
   - Share with team for data entry
   - Download as CSV and upload here
   - Easy and collaborative!

4. **Excel Tips**
   - Format phone numbers as text (not numbers)
   - Use CSV export, not XLSX for better compatibility
   - First row MUST be headers

5. **Data Cleanup**
   - Remove duplicate auto numbers before upload
   - Check driver names for consistency
   - Validate phone numbers are 10-12 digits

---

## Import Results

After successful import, you'll see:
- ✅ **Successful**: Records inserted into database
- ⚠️ **Skipped**: Duplicate auto numbers (ignored to prevent duplicates)
- ❌ **Errors**: Validation failed (see error message for reason)

Each result shows:
- Row number (from your file)
- Auto number
- Status badge
- Details/error message

---

## File Size Limits

- **Maximum file size**: 50MB
- **Records per file**: 1,000 to 100,000 rows
- **For larger imports**: Split into multiple files

---

## Support

For questions about import format or issues:
1. Download the sample template
2. Check column aliases above
3. Verify required fields are present
4. Review error messages in import results

Common issues:
- **"Missing required field"** → Auto Number or Driver Name is missing/blank
- **"Duplicate entry"** → Auto Number already exists in database
- **"Invalid format"** → Check Auto Number format or Phone digits
