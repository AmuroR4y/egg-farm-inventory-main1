<?php
require_once 'session_config.php';
// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<div id="page-profile" class="page">
    <div class="page-shell">
        <div class="page-subtitle">Profile & Security Center</div>
        <div class="profile-grid">
<style>
.field-hint {
    display: block;
    font-size: 11px;
    color: #888;
    margin-top: 4px;
    font-style: italic;
}
.btn-secondary {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.25s ease;
}
.btn-secondary:hover {
    background: #5a6268;
}
.password-requirements {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.password-requirements-title {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}
.password-requirements-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.requirement-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #6c757d;
}
.requirement-icon {
    font-size: 14px;
    font-weight: bold;
}
.requirement-item.met .requirement-icon {
    color: #28a745;
}
.requirement-item.met .requirement-text {
    color: #28a745;
}
.requirement-item:not(.met) .requirement-icon {
    color: #dc3545;
}
</style>
            <div class="panel">
                <div class="panel-title">Profile Information</div>
                <p style="font-size:11.5px;color:var(--text-soft);margin-bottom:16px;line-height:1.5;">Manage your account information and personal details.</p>
                <form id="profile-form">
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="profile-avatar-row">
                        <div class="profile-avatar-img" id="profile-avatar-preview">A</div>
                        <div class="profile-avatar-info">
                            <div class="name" id="profile-display-name">Loading...</div>
                            <div class="role" id="profile-display-role">Owner</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-full-name">Full Name</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <input class="form-input with-icon" id="profile-full-name" name="fullName" type="text" required />
                        </div>
                        <span class="field-error" id="profile-full-name-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-username">Username</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <input class="form-input with-icon" id="profile-username" name="username" type="text" readonly style="background-color: #f5f5f5; cursor: not-allowed;" />
                        </div>
                        <span class="field-hint">Username cannot be changed</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                            <span class="input-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                            <input class="form-input with-icon" id="profile-email" name="email" type="email" required />
                        </div>
                        <span class="field-error" id="profile-email-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-phone">Phone Number</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                            <span class="input-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                            <input class="form-input with-icon" id="profile-phone" name="phone" type="tel" placeholder="(Optional)" />
                        </div>
                        <span class="field-error" id="profile-phone-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-role">Role</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg></span>
                            <input class="form-input with-icon" id="profile-role" name="role" type="text" readonly style="background-color: #f5f5f5; cursor: not-allowed;" />
                        </div>
                        <span class="field-hint">Role is assigned by system administrator</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-status">Account Status</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                            <input class="form-input with-icon" id="profile-status" name="status" type="text" readonly style="background-color: #f5f5f5; cursor: not-allowed;" />
                        </div>
                        <span class="field-hint">Contact administrator to change account status</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profile-created">Account Created</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span>
                            <input class="form-input with-icon" id="profile-created" name="created" type="text" readonly style="background-color: #f5f5f5; cursor: not-allowed;" />
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn-primary" type="submit" id="profile-save-btn">Save Changes</button>
                        <button class="btn-secondary" type="button" id="profile-cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-title">Account Security</div>
                <p style="font-size:11.5px;color:var(--text-soft);margin-bottom:16px;line-height:1.5;">Change your password to keep your account secure.</p>
                <form id="password-form">
                    <input type="hidden" id="password_csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label class="form-label" for="current-password">Current Password</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            <input class="form-input with-icon" id="current-password" name="currentPassword" type="password" placeholder="Enter current password" required />
                            <button class="input-toggle" id="password-visibility" type="button" aria-label="Show password">
                                <svg aria-hidden="true" focusable="false" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="current-password-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new-password">New Password</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            <input class="form-input with-icon" id="new-password" name="newPassword" type="password" placeholder="Enter new password" required />
                            <button class="input-toggle" id="new-password-visibility" type="button" aria-label="Show password">
                                <svg aria-hidden="true" focusable="false" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="new-password-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm-password">Confirm New Password</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg aria-hidden="true" focusable="false" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            <input class="form-input with-icon" id="confirm-password" name="confirmPassword" type="password" placeholder="Confirm new password" required />
                            <button class="input-toggle" id="confirm-password-visibility" type="button" aria-label="Show password">
                                <svg aria-hidden="true" focusable="false" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="confirm-password-error"></span>
                    </div>
                    <div class="password-requirements">
                        <div class="password-requirements-title">Password Requirements:</div>
                        <div class="password-requirements-list">
                            <div class="requirement-item" id="req-length">
                                <span class="requirement-icon">○</span>
                                <span class="requirement-text">Minimum 8 characters</span>
                            </div>
                            <div class="requirement-item" id="req-special">
                                <span class="requirement-icon">○</span>
                                <span class="requirement-text">At least one special character (!@#$%^&* etc.)</span>
                            </div>
                            <div class="requirement-item" id="req-match">
                                <span class="requirement-icon">○</span>
                                <span class="requirement-text">Passwords match</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn-primary" type="submit" id="password-save-btn">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Load profile data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProfileData();
});

function loadProfileData() {
    fetch('/egg-farm-inventory-main1/owner_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.profile) {
                const profile = data.profile;
                
                // Update display name
                document.getElementById('profile-display-name').textContent = profile.fullname || 'Owner';
                document.getElementById('profile-display-role').textContent = profile.role || 'Owner';
                
                // Populate form fields
                document.getElementById('profile-full-name').value = profile.fullname || '';
                document.getElementById('profile-username').value = profile.username || '';
                document.getElementById('profile-email').value = profile.email || '';
                document.getElementById('profile-phone').value = profile.phone || '';
                document.getElementById('profile-role').value = profile.role || '';
                document.getElementById('profile-status').value = profile.status || '';
                document.getElementById('profile-created').value = profile.created_at || '';
                
                // Update avatar
                const avatar = document.getElementById('profile-avatar-preview');
                if (avatar && profile.fullname) {
                    avatar.textContent = profile.fullname.charAt(0).toUpperCase();
                }
            }
        })
        .catch(error => {
            console.error('Failed to load profile data:', error);
        });
}

// Profile form submission
document.getElementById('profile-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'profile');
    
    fetch('/egg-farm-inventory-main1/owner_profile_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Profile updated successfully!');
            
            // Update header name
            if (data.fullname && window.parent.updateOwnerName) {
                window.parent.updateOwnerName(data.fullname);
            }
            
            // Reload profile data
            loadProfileData();
        } else {
            alert('Error: ' + (data.message || 'Failed to update profile'));
        }
    })
    .catch(error => {
        console.error('Profile update error:', error);
        alert('Error: Failed to update profile');
    });
});

// Password form submission
document.getElementById('password-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'password');
    
    fetch('/egg-farm-inventory-main1/owner_profile_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Password changed successfully!');
            this.reset();
            resetPasswordRequirements();
        } else {
            alert('Error: ' + (data.message || 'Failed to change password'));
        }
    })
    .catch(error => {
        console.error('Password change error:', error);
        alert('Error: Failed to change password');
    });
});

// Password validation in real-time
const newPasswordInput = document.getElementById('new-password');
const confirmPasswordInput = document.getElementById('confirm-password');

newPasswordInput.addEventListener('input', function() {
    validatePasswordRequirements(this.value);
    validatePasswordMatch();
});

confirmPasswordInput.addEventListener('input', function() {
    validatePasswordMatch();
});

function validatePasswordRequirements(password) {
    const reqLength = document.getElementById('req-length');
    const reqSpecial = document.getElementById('req-special');
    
    // Check length
    if (password.length >= 8) {
        reqLength.classList.add('met');
    } else {
        reqLength.classList.remove('met');
    }
    
    // Check special character
    const specialCharRegex = /[!@#$%^&*(),.?":{}|<>]/;
    if (specialCharRegex.test(password)) {
        reqSpecial.classList.add('met');
    } else {
        reqSpecial.classList.remove('met');
    }
}

function validatePasswordMatch() {
    const reqMatch = document.getElementById('req-match');
    const password = newPasswordInput.value;
    const confirm = confirmPasswordInput.value;
    
    if (password && confirm && password === confirm) {
        reqMatch.classList.add('met');
    } else {
        reqMatch.classList.remove('met');
    }
}

function resetPasswordRequirements() {
    document.getElementById('req-length').classList.remove('met');
    document.getElementById('req-special').classList.remove('met');
    document.getElementById('req-match').classList.remove('met');
}

// Cancel button
document.getElementById('profile-cancel-btn').addEventListener('click', function() {
    loadProfileData();
});

// Password visibility toggles
document.getElementById('password-visibility').addEventListener('click', function() {
    togglePasswordVisibility('current-password');
});

document.getElementById('new-password-visibility').addEventListener('click', function() {
    togglePasswordVisibility('new-password');
});

document.getElementById('confirm-password-visibility').addEventListener('click', function() {
    togglePasswordVisibility('confirm-password');
});

function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    const button = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>