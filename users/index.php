<?php
/**
 * User Administration Directory View Page
 */

$page_title = "User Administration";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

if (!has_role(['Administrator', 'Manager'])) {
    set_flash_message('danger', 'Unauthorized access to user administration.');
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Register new employees, assign system roles, edit details, and toggle accounts suspension states.</p>
</div>

<!-- Search and Add Actions Row -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        <div class="search-container" style="max-width: 300px; flex: 1;">
            <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
            <input type="text" id="user-search" class="form-control search-input" placeholder="Search by name, email, role...">
        </div>
        <div>
            <button class="btn btn-primary d-flex align-center gap-2" id="btn-add-user">
                <i data-lucide="user-plus" style="width:16px; height:16px;"></i> Register New User
            </button>
        </div>
    </div>
</div>

<!-- Users List Datatable -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="users-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Username</th>
                    <th>System Role</th>
                    <th>Account Status</th>
                    <th>Created Date</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="user-rows">
                <!-- Dynamic AJAX rows populated here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="users-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="users" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No staff profiles found</h4>
        <p class="text-muted text-sm">Add active employee accounts to list them here.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<!-- USER REGISTER/EDIT MODAL -->
<div class="modal-overlay" id="user-modal">
    <div class="modal-window" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modal-title">Register Employee Account</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="user-form" autocomplete="off">
            <input type="hidden" name="id" id="user-id" value="">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="modal-body">
                <div class="form-group" id="username-group">
                    <label class="form-label" for="username">Username *</label>
                    <input type="text" name="username" id="user-username" class="form-control" placeholder="e.g. jdoe" required>
                    <span class="text-xs text-muted" style="margin-top: 2px; display:block;">Only lowercase letters and numbers. Cannot be modified later.</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="user-fullname" class="form-control" placeholder="e.g. John Doe" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="user-email" class="form-control" placeholder="e.g. john@example.com">
                </div>
                
                <div class="grid grid-cols-2 gap-2">
                    <div class="form-group">
                        <label class="form-label" for="role">System Access Role *</label>
                        <select name="role" id="user-role" class="form-control" required>
                            <option value="Cashier">Cashier</option>
                            <option value="Store Keeper">Store Keeper</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Manager">Manager</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Account Status</label>
                        <select name="status" id="user-status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive / Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password" id="password-label">Password *</label>
                    <input type="password" name="password" id="user-password" class="form-control" placeholder="Enter secure password">
                    <span class="text-xs text-muted" id="password-help" style="margin-top:2px; display:none;">Leave blank to preserve current password.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="modal-submit-btn">
                    <span id="submit-btn-text">Create Account</span>
                    <div class="spinner" id="modal-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let pageLimit = 10;
    let editMode = false;
    const currentLoggedUserId = ' . $current_user_id . ';
    
    // Fetch initial list
    fetchUsers();
    
    // Bind search keyup debounce
    const searchInput = document.getElementById("user-search");
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchUsers();
        }, 300);
    });
    
    // AJAX Fetch Users list
    async function fetchUsers() {
        const userRows = document.getElementById("user-rows");
        const emptyState = document.getElementById("users-empty-state");
        
        userRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            userRows.innerHTML += `
                <tr>
                    <td>
                        <div class="d-flex align-center gap-3">
                            <div class="skeleton" style="width:32px; height:32px; border-radius:50px;"></div>
                            <div>
                                <div class="skeleton" style="width:140px; height:16px; margin-bottom:4px;"></div>
                                <div class="skeleton" style="width:90px; height:12px;"></div>
                            </div>
                        </div>
                    </td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:85px; height:20px; border-radius:50px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:14px;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:80px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: searchInput.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/users.php?" + params.toString());
            
            userRows.innerHTML = "";
            if (data && data.success && data.users.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("users-table").style.display = "table";
                
                data.users.forEach(u => {
                    const initials = u.full_name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
                    
                    // Role badges
                    let roleBadge = "badge-secondary";
                    if (u.role === "Administrator") roleBadge = "badge-danger";
                    if (u.role === "Manager") roleBadge = "badge-warning";
                    if (u.role === "Accountant") roleBadge = "badge-primary";
                    if (u.role === "Store Keeper") roleBadge = "badge-success";
                    if (u.role === "Cashier") roleBadge = "badge-info";
                    
                    // Status toggles/badge
                    const statusClass = u.status === "Active" ? "text-success font-semibold" : "text-muted";
                    const isSelf = parseInt(u.id) === currentLoggedUserId;
                    
                    // Check toggle perm
                    const toggleToggle = isSelf 
                        ? `<span class="\${statusClass} text-xs">Active (You)</span>`
                        : `<button class="btn-toggle-status font-semibold cursor-pointer" data-id="\${u.id}" style="background:none; border:none; padding:0; text-decoration:underline; font-size:0.8rem; text-align:left;" class="\${statusClass}">\${u.status}</button>`;
                    
                    // Action permissions
                    const actions = isSelf 
                        ? `<button class="btn btn-secondary btn-edit-user" data-id="\${u.id}" data-username="\${e(u.username)}" data-fullname="\${e(u.full_name)}" data-email="\${e(u.email)}" data-role="\${u.role}" data-status="\${u.status}" style="padding:0.4rem; font-size:0.75rem;"><i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i></button>`
                        : `<div class="d-flex justify-end gap-1">
                            <button class="btn btn-secondary btn-edit-user" data-id="\${u.id}" data-username="\${e(u.username)}" data-fullname="\${e(u.full_name)}" data-email="\${e(u.email)}" data-role="\${u.role}" data-status="\${u.status}" style="padding:0.4rem; font-size:0.75rem;"><i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i></button>
                            <button class="btn btn-secondary btn-delete-user" data-id="\${u.id}" data-username="\${e(u.username)}" style="padding:0.4rem; font-size:0.75rem;"><i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i></button>
                           </div>`;
                    
                    userRows.innerHTML += \`
                        <tr>
                            <td>
                                <div class="d-flex align-center gap-3">
                                    <div style="width:32px; height:32px; background-color:var(--bg-app); border-radius:50px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem; color:var(--primary);">
                                        \${initials}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-sm">\${e(u.full_name)}</div>
                                        <div class="text-muted text-xs">\${e(u.email || "No email address")}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm font-semibold">@\${e(u.username)}</td>
                            <td><span class="badge \${roleBadge}">\${u.role}</span></td>
                            <td>\${toggleToggle}</td>
                            <td class="text-xs text-muted">\${formatDate(u.created_at)}</td>
                            <td>\${actions}</td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
                bindRowActions();
            } else {
                document.getElementById("users-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve user accounts listing.", "danger");
        }
    }
    
    // Pagination renderer
    function renderPagination(total, page, limit, totalPages) {
        const start = (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);
        document.getElementById("pagination-summary").innerText = \`Showing \${start} to \${end} of \${total} entries\`;
        
        const controls = document.getElementById("pagination-controls");
        controls.innerHTML = "";
        
        // Prev
        const prevBtn = document.createElement("button");
        prevBtn.className = "page-btn";
        prevBtn.disabled = page === 1;
        prevBtn.innerHTML = "<i data-lucide=\'chevron-left\' style=\'width:16px; height:16px;\'></i>";
        prevBtn.addEventListener("click", () => {
            currentPage = page - 1;
            fetchUsers();
        });
        controls.appendChild(prevBtn);
        
        // Pages
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
                const btn = document.createElement("button");
                btn.className = \`page-btn \${i === page ? "active" : ""}\`;
                btn.innerText = i;
                btn.addEventListener("click", () => {
                    currentPage = i;
                    fetchUsers();
                });
                controls.appendChild(btn);
            }
        }
        
        // Next
        const nextBtn = document.createElement("button");
        nextBtn.className = "page-btn";
        nextBtn.disabled = page === totalPages;
        nextBtn.innerHTML = "<i data-lucide=\'chevron-right\' style=\'width:16px; height:16px;\'></i>";
        nextBtn.addEventListener("click", () => {
            currentPage = page + 1;
            fetchUsers();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Actions: Edit, Delete, Toggle Status
    const modal = document.getElementById("user-modal");
    const userForm = document.getElementById("user-form");
    const submitBtn = document.getElementById("modal-submit-btn");
    const spinner = document.getElementById("modal-spinner");
    
    const closeModal = () => {
        modal.classList.remove("active");
    };
    
    document.getElementById("modal-close").addEventListener("click", closeModal);
    document.getElementById("modal-cancel-btn").addEventListener("click", closeModal);
    
    document.getElementById("btn-add-user").addEventListener("click", () => {
        editMode = false;
        userForm.reset();
        document.getElementById("user-id").value = "";
        
        // Username visible
        document.getElementById("username-group").style.display = "block";
        document.getElementById("user-username").required = true;
        
        // Password required
        document.getElementById("password-label").innerText = "Password *";
        document.getElementById("user-password").required = true;
        document.getElementById("password-help").style.display = "none";
        
        document.getElementById("modal-title").innerText = "Register Employee Account";
        document.getElementById("submit-btn-text").innerText = "Create Account";
        
        modal.classList.add("active");
    });
    
    function bindRowActions() {
        // Toggle Status clicks
        document.querySelectorAll(".btn-toggle-status").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.getAttribute("data-id");
                const fd = new FormData();
                fd.append("id", id);
                
                try {
                    const res = await ajaxRequest("/shop-system/ajax/users.php?action=toggle_status", {
                        method: "POST",
                        body: fd
                    });
                    
                    if (res && res.success) {
                        showToast(res.message, "success");
                        fetchUsers();
                    } else {
                        showToast(res.message || "Failed to toggle status.", "danger");
                    }
                } catch(err) {
                    showToast("Communication error.", "danger");
                }
            });
        });
        
        // Edit clicks
        document.querySelectorAll(".btn-edit-user").forEach(btn => {
            btn.addEventListener("click", () => {
                editMode = true;
                userForm.reset();
                
                const id = btn.getAttribute("data-id");
                const username = btn.getAttribute("data-username");
                const fullname = btn.getAttribute("data-fullname");
                const email = btn.getAttribute("data-email");
                const role = btn.getAttribute("data-role");
                const status = btn.getAttribute("data-status");
                
                document.getElementById("user-id").value = id;
                document.getElementById("user-fullname").value = fullname;
                document.getElementById("user-email").value = email;
                document.getElementById("user-role").value = role;
                document.getElementById("user-status").value = status;
                
                // Hide username group
                document.getElementById("username-group").style.display = "none";
                document.getElementById("user-username").required = false;
                
                // Make password optional
                document.getElementById("password-label").innerText = "New Password";
                document.getElementById("user-password").required = false;
                document.getElementById("password-help").style.display = "block";
                
                document.getElementById("modal-title").innerText = "Edit Employee Details";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                
                modal.classList.add("active");
            });
        });
        
        // Delete clicks
        document.querySelectorAll(".btn-delete-user").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const username = btn.getAttribute("data-username");
                
                showConfirmModal(
                    "Delete User Account",
                    \`Are you sure you want to permanently delete the account "@\${username}"? This operation will be rejected if they have recorded ledger data.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        try {
                            const res = await ajaxRequest("/shop-system/ajax/users.php?action=delete", {
                                method: "POST",
                                body: fd
                            });
                            
                            if (res && res.success) {
                                showToast(res.message, "success");
                                fetchUsers();
                            } else {
                                showToast(res.message || "Failed to delete user.", "danger");
                            }
                        } catch(err) {
                            showToast("Connection error.", "danger");
                        }
                    }
                );
            });
        });
    }
    
    // Handle form submit
    userForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(userForm);
        const queryAction = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/users.php?action=" + queryAction, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closeModal();
                fetchUsers();
            } else {
                showToast(res.message || "Operation failed.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = "none";
        }
    });
    
    // Format Date helper
    function formatDate(dateStr) {
        if (!dateStr) return "";
        const parts = dateStr.split(" ")[0].split("-");
        if (parts.length !== 3) return dateStr;
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const year = parts[0];
        const monthIndex = parseInt(parts[1]) - 1;
        const day = parts[2];
        return months[monthIndex] + " " + day + ", " + year;
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
