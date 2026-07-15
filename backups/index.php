<?php
/**
 * Database Backups & System Maintenance Workspace View Page
 */

$page_title = "Database Backups & Maintenance";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

if (!has_role(['Administrator', 'Manager'])) {
    set_flash_message('danger', 'Unauthorized access to database backups administration.');
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

// Compute aggregate metrics from backups directory
$backup_dir = __DIR__ . '/files/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$files = glob($backup_dir . '*.sql');
$total_backups = count($files);
$total_size_bytes = 0;
$last_backup_date = 'N/A';

if ($total_backups > 0) {
    foreach ($files as $file) {
        $total_size_bytes += filesize($file);
    }
    // Sort by modification time
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $last_backup_date = date('Y-m-d H:i', filemtime($files[0]));
}

// Format size
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Create database backup files (SQL format), download them for safe keeping, or restore your business ledger back to previous states.</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 grid-cols-sm-3 gap-4 mb-6">
    <!-- Total Backup Files -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Backups</span>
            <div class="stats-card-value" id="stats-total"><?php echo $total_backups; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Saved locally on web server</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="database"></i>
        </div>
    </div>
    
    <!-- Total Disk Space Used -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Backup Storage Size</span>
            <div class="stats-card-value" id="stats-size"><?php echo format_bytes($total_size_bytes); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Physical disk storage space</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(99, 102, 241, 0.15); color: #6366f1;">
            <i data-lucide="hard-drive"></i>
        </div>
    </div>
    
    <!-- Last Backup Date -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Last Backup Date</span>
            <div class="stats-card-value text-md" id="stats-last" style="font-size: 1.25rem; font-weight: 800; line-height: 2.2rem;"><?php echo $last_backup_date; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Date of last backup generation</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(16, 185, 129, 0.15); color: var(--success);">
            <i data-lucide="calendar"></i>
        </div>
    </div>
</div>

<!-- Action toolbar card -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        
        <!-- Search bar input -->
        <div class="search-container" style="max-width: 280px; flex: 1;">
            <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
            <input type="text" id="backup-search" class="form-control search-input" placeholder="Search backup files...">
        </div>
        
        <div class="d-flex align-center gap-2 flex-wrap">
            <!-- Hidden file input for upload restore -->
            <input type="file" id="upload-file-input" style="display: none;" accept=".sql">
            
            <button type="button" class="btn btn-secondary d-flex align-center gap-2" id="btn-upload-backup">
                <i data-lucide="upload" style="width:16px; height:16px;"></i> Upload & Restore SQL
                <div class="spinner" id="upload-spinner" style="display: none; border-color: rgba(0,0,0,0.2); border-top-color: var(--text-color); width: 14px; height: 14px; border-width: 2px;"></div>
            </button>
            
            <form id="generate-form" style="display: inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="btn-generate-backup">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Generate Database Backup
                    <div class="spinner" id="generate-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Backups Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <input type="hidden" id="page-csrf" value="<?php echo generate_csrf_token(); ?>">
    <div class="table-responsive">
        <table class="table table-hover" id="backups-table">
            <thead>
                <tr>
                    <th>Backup File Name</th>
                    <th>Date & Time Generated</th>
                    <th>File Storage Size</th>
                    <th style="width: 130px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="backup-rows">
                <!-- Dynamic AJAX rows populated here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="backups-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="folder-search" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No backup files found</h4>
        <p class="text-muted text-sm">Click "Generate Database Backup" to create a new recovery point.</p>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Fetch backup files list
    fetchBackups();
    
    // Bind search keyup
    const searchInput = document.getElementById("backup-search");
    searchInput.addEventListener("keyup", () => {
        filterBackupTable();
    });
    
    // AJAX Fetch Backup files
    async function fetchBackups() {
        const rows = document.getElementById("backup-rows");
        const empty = document.getElementById("backups-empty-state");
        
        rows.innerHTML = "";
        for (let i = 0; i < 3; i++) {
            rows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:240px; height:16px;"></div></td>
                    <td><div class="skeleton" style="width:120px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:65px; height:14px;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:80px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/backups.php?action=list");
            
            rows.innerHTML = "";
            if (data && data.success && data.backups.length > 0) {
                empty.style.display = "none";
                document.getElementById("backups-table").style.display = "table";
                
                data.backups.forEach(b => {
                    const actions = `
                        <div class="d-flex justify-end gap-1">
                            <a href="/shop-system/ajax/backups.php?action=download&filename=\${b.filename}" class="btn btn-secondary" style="padding:0.4rem; font-size:0.75rem;" title="Download Backup">
                                <i data-lucide="download" style="width:14px; height:14px; color:var(--text-muted);"></i>
                            </a>
                            <button class="btn btn-secondary btn-restore-backup" data-filename="\${b.filename}" style="padding:0.4rem; font-size:0.75rem;" title="Restore Database">
                                <i data-lucide="rotate-ccw" style="width:14px; height:14px; color:var(--primary);"></i>
                            </button>
                            <button class="btn btn-secondary btn-delete-backup" data-filename="\${b.filename}" style="padding:0.4rem; font-size:0.75rem;" title="Delete File">
                                <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                            </button>
                        </div>
                    `;
                    
                    rows.innerHTML += `
                        <tr class="backup-row">
                            <td class="font-semibold text-xs file-name-td">\${e(b.filename)}</td>
                            <td class="text-xs text-muted">\${formatDateTime(b.created_at)}</td>
                            <td class="text-xs">\${formatBytes(b.size)}</td>
                            <td>\${actions}</td>
                        </tr>
                    `;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                bindActions();
            } else {
                document.getElementById("backups-table").style.display = "none";
                empty.style.display = "block";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve backup files.", "danger");
        }
    }
    
    // Filter rows client-side
    function filterBackupTable() {
        const query = searchInput.value.toLowerCase();
        document.querySelectorAll(".backup-row").forEach(row => {
            const name = row.querySelector(".file-name-td").innerText.toLowerCase();
            if (name.includes(query)) {
                row.style.display = "table-row";
            } else {
                row.style.display = "none";
            }
        });
    }
    
    // Bind click actions
    function bindActions() {
        // Restore Backup click
        document.querySelectorAll(".btn-restore-backup").forEach(btn => {
            btn.addEventListener("click", () => {
                const filename = btn.getAttribute("data-filename");
                const csrf = document.getElementById("page-csrf").value;
                
                showConfirmModal(
                    "Restore Database State",
                    `<span class="text-danger font-bold">Warning: Restoring will overwrite all current system data!</span><br><br>Are you sure you want to restore the database to recovery point "<strong>\${filename}</strong>"? All sales, purchases, and changes logged after this point will be lost.`,
                    async () => {
                        const fd = new FormData();
                        fd.append("filename", filename);
                        fd.append("csrf_token", csrf);
                        
                        try {
                            const res = await ajaxRequest("/shop-system/ajax/backups.php?action=restore", {
                                method: "POST",
                                body: fd
                            });
                            
                            if (res && res.success) {
                                showToast(res.message, "success");
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                showToast(res.message || "Failed to restore database.", "danger");
                            }
                        } catch(err) {
                            showToast("Restoration query timeout/failure.", "danger");
                        }
                    }
                );
            });
        });
        
        // Delete Backup click
        document.querySelectorAll(".btn-delete-backup").forEach(btn => {
            btn.addEventListener("click", () => {
                const filename = btn.getAttribute("data-filename");
                
                showConfirmModal(
                    "Delete Backup File",
                    `Are you sure you want to permanently delete the backup file "\${filename}"? This action cannot be undone.`,
                    async () => {
                        const fd = new FormData();
                        fd.append("filename", filename);
                        
                        try {
                            const res = await ajaxRequest("/shop-system/ajax/backups.php?action=delete", {
                                method: "POST",
                                body: fd
                            });
                            
                            if (res && res.success) {
                                showToast(res.message, "success");
                                fetchBackups();
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showToast(res.message || "Failed to delete file.", "danger");
                            }
                        } catch(err) {
                            showToast("Connection failed.", "danger");
                        }
                    }
                );
            });
        });
    }
    
    // Generate Backup Form submit
    const generateForm = document.getElementById("generate-form");
    const generateBtn = document.getElementById("btn-generate-backup");
    const generateSpinner = document.getElementById("generate-spinner");
    
    generateForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        generateBtn.disabled = true;
        generateSpinner.style.display = "inline-block";
        
        const fd = new FormData(generateForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/backups.php?action=generate", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                fetchBackups();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(res.message || "Backup failed.", "danger");
                generateBtn.disabled = false;
                generateSpinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
            generateBtn.disabled = false;
            generateSpinner.style.display = "none";
        }
    });
    
    // Upload & Restore trigger
    const uploadBtn = document.getElementById("btn-upload-backup");
    const uploadInput = document.getElementById("upload-file-input");
    const uploadSpinner = document.getElementById("upload-spinner");
    const csrf = document.getElementById("page-csrf").value;
    
    uploadBtn.addEventListener("click", () => {
        uploadInput.click();
    });
    
    uploadInput.addEventListener("change", () => {
        const file = uploadInput.files[0];
        if (!file) return;
        
        showConfirmModal(
            "Upload & Restore Database",
            `<span class="text-danger font-bold">Warning: Overwriting active database records!</span><br><br>Are you sure you want to upload and restore the SQL backup "<strong>\${file.name}</strong>"? All current data will be erased and replaced.`,
            async () => {
                uploadBtn.disabled = true;
                uploadSpinner.style.display = "inline-block";
                
                const fd = new FormData();
                fd.append("backup_file", file);
                fd.append("csrf_token", csrf);
                
                try {
                    const res = await ajaxRequest("/shop-system/ajax/backups.php?action=upload_restore", {
                        method: "POST",
                        body: fd
                    });
                    
                    if (res && res.success) {
                        showToast(res.message, "success");
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(res.message || "Restore failed.", "danger");
                        uploadBtn.disabled = false;
                        uploadSpinner.style.display = "none";
                    }
                } catch(err) {
                    showToast("Restoration parsed query errors.", "danger");
                    uploadBtn.disabled = false;
                    uploadSpinner.style.display = "none";
                } finally {
                    uploadInput.value = "";
                }
            },
            () => {
                uploadInput.value = "";
            }
        );
    });
    
    // Bytes size formatter
    function formatBytes(bytes) {
        if (bytes === 0) return "0 B";
        const k = 1024;
        const dm = 2;
        const sizes = ["B", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
    }
    
    // Format Date Time
    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return "";
        const parts = dateTimeStr.split(" ");
        if (parts.length !== 2) return dateTimeStr;
        
        const dateParts = parts[0].split("-");
        const timeParts = parts[1].split(":");
        if (dateParts.length !== 3 || timeParts.length !== 3) return dateTimeStr;
        
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const year = dateParts[0];
        const monthIndex = parseInt(dateParts[1]) - 1;
        const day = dateParts[2];
        
        const hour = timeParts[0];
        const minute = timeParts[1];
        
        return months[monthIndex] + " " + day + ", " + year + " " + hour + ":" + minute;
    }

    // Helper for HTML escaping
    function e(string) {
        return (string || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\x27/g, "&#039;");
    }
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
