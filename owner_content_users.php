<div id="page-users" class="page">
    <div class="page-shell">
        <div class="page-subtitle">User Management</div>
        <div style="font-size:13px;font-weight:700;color:var(--sidebar);margin-bottom:12px;">Enterprise User Administration</div>
        <div class="users-container">
            <div class="panel">
                <div class="panel-title">User Accounts</div>
                <p style="font-size:11.5px;color:var(--text-soft);margin-bottom:16px;line-height:1.5;">Manage manager and customer accounts, permissions, and access levels.</p>
                <div style="margin-bottom: 20px;">
                    <button class="btn-primary" onclick="openUserModal()">Add New User</button>
                </div>
                <div id="users-list" class="users-list"></div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add New User</h3>
                <button class="modal-close" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="user-form">
                    <input type="hidden" id="user-id" name="user_id">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="user-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="user-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select id="user-role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="manager">Manager</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="user-status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="user-password" name="password" minlength="8" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save User</button>
                        <button type="button" class="btn-secondary" onclick="closeUserModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .users-list {
            margin-top: 20px;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th,
        .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .user-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .user-actions button {
            padding: 4px 8px;
            margin-right: 4px;
            font-size: 12px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal.open {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-primary {
            background: #214f2c;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: 600;
        }
    </style>

    <script>
        let currentUserId = 0;

        function loadUsers() {
            fetch('/egg-farm-inventory-main1/owner_api.php')
                .then(res => res.json())
                .then(data => {
                    const usersList = document.getElementById('users-list');
                    if (data.users && data.users.length > 0) {
                        usersList.innerHTML = `
                            <table class="user-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.users.map(user => `
                                        <tr>
                                            <td>${user.name}</td>
                                            <td>${user.email}</td>
                                            <td>${user.role}</td>
                                            <td><span class="status-${user.status}">${user.status}</span></td>
                                            <td class="user-actions">
                                                <button onclick="editUser(${user.id})">Edit</button>
                                                <button onclick="deleteUser(${user.id})">Delete</button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        usersList.innerHTML = '<p>No users found.</p>';
                    }
                });
        }

        function openUserModal(userId = 0) {
            currentUserId = userId;
            const modal = document.getElementById('user-modal');
            const title = document.getElementById('modal-title');
            const form = document.getElementById('user-form');
            
            form.reset();
            
            if (userId > 0) {
                title.textContent = 'Edit User';
                // Load user data for editing
                fetch('/egg-farm-inventory-main1/owner_api.php')
                    .then(res => res.json())
                    .then(data => {
                        const user = data.users.find(u => u.id === userId);
                        if (user) {
                            document.getElementById('user-id').value = user.id;
                            document.getElementById('user-name').value = user.name;
                            document.getElementById('user-email').value = user.email;
                            document.getElementById('user-role').value = user.role.toLowerCase();
                            document.getElementById('user-status').value = user.status;
                        }
                    });
            } else {
                title.textContent = 'Add New User';
                document.getElementById('user-id').value = '';
            }
            
            modal.classList.add('open');
        }

        function closeUserModal() {
            document.getElementById('user-modal').classList.remove('open');
        }

        function editUser(userId) {
            openUserModal(userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('user_id', userId);
                
                fetch('/egg-farm-inventory-main1/owner_users_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        loadUsers();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        document.getElementById('user-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const userId = document.getElementById('user-id').value;
            
            if (userId > 0) {
                formData.append('action', 'update');
            } else {
                formData.append('action', 'create');
            }
            
            fetch('/egg-farm-inventory-main1/owner_users_api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    closeUserModal();
                    loadUsers();
                } else {
                    alert(data.message);
                }
            });
        });

        // Load users when page loads
        loadUsers();
    </script>
</div>