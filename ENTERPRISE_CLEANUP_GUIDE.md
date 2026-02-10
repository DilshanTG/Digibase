# 🧹 ENTERPRISE UPGRADE - CLEANUP GUIDE

## Files to Remove After Successful Upgrade

Once you've verified that the new Spatie Media Library integration works correctly, you can safely remove these custom implementations:

---

## 🗑️ PHASE 1: Storage Controller (Can be Removed)

### File to Delete:
```
app/Http/Controllers/Api/StorageController.php
```

### Why it's safe to remove:
- Spatie Media Library handles all file operations now
- File uploads in DataExplorer use `SpatieMediaLibraryFileUpload`
- Media files are managed through the `media` table
- All file operations (upload, download, delete) are handled by Spatie

### Before removing:
1. ✅ Verify file uploads work in DataExplorer
2. ✅ Check that existing files are accessible
3. ✅ Test file downloads
4. ✅ Confirm image thumbnails display correctly

### API Routes to Remove:
**File:** `routes/api.php`

Remove these routes (if you're not using the Storage API externally):

```php
// File Storage (OLD - Can be removed)
Route::get('/storage/stats', [StorageController::class, 'stats']);
Route::get('/storage/buckets', [StorageController::class, 'buckets']);
Route::get('/storage', [StorageController::class, 'index']);
Route::post('/storage', [StorageController::class, 'store']);
Route::get('/storage/{file}', [StorageController::class, 'show']);
Route::put('/storage/{file}', [StorageController::class, 'update']);
Route::delete('/storage/{file}', [StorageController::class, 'destroy']);
```

**⚠️ IMPORTANT:** If you have external clients using the Storage API, you'll need to:
1. Create a migration path for them to use Media Library API
2. Or keep the StorageController and adapt it to use Media Library internally

---

## 🗑️ PHASE 2: Storage Model (Optional - Keep for Migration)

### File to Consider:
```
app/Models/StorageFile.php
```

### Why you might want to KEEP it temporarily:
- You may have existing files in the `storage_files` table
- You'll need to migrate these to the `media` table
- Keep it until migration is complete

### Migration Strategy:

**Option 1: Migrate Existing Files**

Create a migration command to move existing files to Media Library:

```php
<?php

namespace App\Console\Commands;

use App\Models\StorageFile;
use App\Models\DynamicRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToMediaLibrary extends Command
{
    protected $signature = 'storage:migrate-to-media';
    protected $description = 'Migrate existing StorageFile records to Spatie Media Library';

    public function handle()
    {
        $this->info('Starting migration...');
        
        $files = StorageFile::all();
        $this->info("Found {$files->count()} files to migrate");

        foreach ($files as $file) {
            try {
                // Find the related record (if applicable)
                // This depends on your data structure
                
                // For now, we'll just log what needs to be migrated
                $this->line("File: {$file->original_name} ({$file->path})");
                
                // TODO: Implement actual migration logic based on your needs
                
            } catch (\Exception $e) {
                $this->error("Failed to migrate file {$file->id}: {$e->getMessage()}");
            }
        }

        $this->info('Migration complete!');
    }
}
```

**Option 2: Fresh Start**

If you don't have critical files in production:
1. Delete the `storage_files` table
2. Remove the `StorageFile` model
3. Start fresh with Media Library

---

## 🗑️ PHASE 3: Database Tables (After Migration)

### Tables to Drop (After Migration):

```sql
-- Only run this after migrating all files to media library
DROP TABLE IF EXISTS storage_files;
```

Or create a migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old storage_files table
        Schema::dropIfExists('storage_files');
    }

    public function down(): void
    {
        // Recreate if needed (copy from original migration)
    }
};
```

---

## 🗑️ PHASE 4: Filament Resources (If Exists)

### Check for these files:

```
app/Filament/Resources/StorageFileResource.php
```

If you have a Filament resource for managing storage files, you can remove it since:
- Media files are now managed through the model they're attached to
- You can use Filament's built-in media management
- Or create a new resource for the `media` table if needed

---

## 📋 Cleanup Checklist

### Before Cleanup:
- [ ] Verify all file uploads work in DataExplorer
- [ ] Test image thumbnails display correctly
- [ ] Confirm file downloads work
- [ ] Check that existing files are accessible
- [ ] Backup your database
- [ ] Backup your storage directory

### Safe to Remove Immediately:
- [ ] `app/Http/Controllers/Api/StorageController.php` (if not used by external clients)
- [ ] Storage API routes in `routes/api.php` (if not used externally)

### Remove After Migration:
- [ ] `app/Models/StorageFile.php` (after migrating data)
- [ ] `storage_files` database table (after migrating data)
- [ ] `app/Filament/Resources/StorageFileResource.php` (if exists)

### Keep (Don't Remove):
- [ ] `storage/app/public/` directory (contains actual files)
- [ ] `config/filesystems.php` (still needed for disk configuration)
- [ ] `digibase_storage` disk configuration (used by Media Library)

---

## 🔄 Migration Script Example

If you need to migrate existing files, here's a complete example:

```php
<?php

namespace App\Console\Commands;

use App\Models\StorageFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateToMediaLibrary extends Command
{
    protected $signature = 'storage:migrate';
    protected $description = 'Migrate StorageFile to Spatie Media Library';

    public function handle()
    {
        $this->info('🚀 Starting migration to Media Library...');
        
        $files = StorageFile::all();
        $total = $files->count();
        $this->info("Found {$total} files to migrate");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $migrated = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                // Check if file exists on disk
                if (!Storage::disk($file->disk)->exists($file->path)) {
                    $this->newLine();
                    $this->warn("File not found: {$file->path}");
                    $failed++;
                    $bar->advance();
                    continue;
                }

                // Insert into media table
                DB::table('media')->insert([
                    'model_type' => 'App\\Models\\DynamicRecord',
                    'model_id' => 0, // Orphaned files - you may need to link these
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'collection_name' => 'files',
                    'name' => $file->name,
                    'file_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'disk' => $file->disk,
                    'conversions_disk' => $file->disk,
                    'size' => $file->size,
                    'manipulations' => '[]',
                    'custom_properties' => json_encode([
                        'bucket' => $file->bucket,
                        'folder' => $file->folder,
                        'is_public' => $file->is_public,
                        'migrated_from_storage_file' => true,
                        'original_id' => $file->id,
                    ]),
                    'generated_conversions' => '[]',
                    'responsive_images' => '[]',
                    'order_column' => 1,
                    'created_at' => $file->created_at,
                    'updated_at' => $file->updated_at,
                ]);

                $migrated++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to migrate file {$file->id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Migration complete!");
        $this->info("Migrated: {$migrated}");
        $this->error("Failed: {$failed}");
        
        if ($failed === 0) {
            $this->info("🎉 All files migrated successfully!");
            $this->info("You can now safely remove the StorageFile model and table.");
        } else {
            $this->warn("⚠️  Some files failed to migrate. Please review the errors above.");
        }
    }
}
```

Register this command in `app/Console/Kernel.php`:

```php
protected $commands = [
    \App\Console\Commands\MigrateToMediaLibrary::class,
];
```

Then run:

```bash
php artisan storage:migrate
```

---

## 🎯 Final Cleanup Commands

After successful migration and testing:

```bash
# Remove the controller
rm app/Http/Controllers/Api/StorageController.php

# Remove the model (after migration)
rm app/Models/StorageFile.php

# Remove the resource (if exists)
rm app/Filament/Resources/StorageFileResource.php

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Optimize
php artisan optimize
```

---

## ⚠️ Important Notes

### DO NOT Remove:
- ❌ `storage/app/public/` directory (contains actual files)
- ❌ `config/filesystems.php` (needed for disk configuration)
- ❌ `digibase_storage` disk configuration (used by Media Library)
- ❌ Any files currently in use by production

### Safe to Remove:
- ✅ `StorageController.php` (after verifying Media Library works)
- ✅ `StorageFile.php` model (after migrating data)
- ✅ `storage_files` table (after migrating data)
- ✅ Storage API routes (if not used externally)

### Migration Timeline:
1. **Week 1:** Install and test new Media Library integration
2. **Week 2:** Run migration script to move existing files
3. **Week 3:** Verify all files accessible and working
4. **Week 4:** Remove old code and tables

---

## 📊 Verification Checklist

Before removing any code, verify:

- [ ] All file uploads work in DataExplorer
- [ ] Image thumbnails generate correctly
- [ ] File downloads work
- [ ] Existing files are accessible
- [ ] No errors in logs
- [ ] External API clients migrated (if applicable)
- [ ] Database backup created
- [ ] Storage backup created
- [ ] Migration script tested on staging
- [ ] All team members notified

---

**Cleanup Status: Ready After Migration** ✅
