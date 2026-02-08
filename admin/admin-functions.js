/**
 * Admin Dashboard JavaScript Functions
 */

const API_URL = 'https://10.73.197.212/treasure_hunt/api';
let adminToken = localStorage.getItem('admin_token');
let adminInfo = JSON.parse(localStorage.getItem('admin_info') || '{}');

// Check authentication
if (!adminToken) {
    window.location.href = 'login.php';
}

// Display admin name
document.getElementById('adminName').textContent = adminInfo.username || 'Admin';

// Navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const page = link.getAttribute('data-page');
        switchPage(page);
    });
});

function switchPage(page) {
    // Update active nav
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`[data-page="${page}"]`).classList.add('active');

    // Update active page
    document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
    document.getElementById(`${page}-page`).classList.add('active');

    // Update title
    const titles = {
        'dashboard': 'Dashboard',
        'users': 'Manage Users',
        'places-manage': 'Manage Places',
        'categories-manage': 'Manage Categories',
        'hunts-manage': 'Manage Hunts',
        'places': 'Pending Places',
        'photos': 'Photo Reviews',
        'hunts': 'Pending Hunts'
    };
    document.getElementById('page-title').textContent = titles[page] || 'Dashboard';

    // Load page data
    loadPageData(page);
}

function loadPageData(page) {
    switch (page) {
        case 'dashboard':
            loadDashboardStats();
            break;
        case 'users':
            loadAllUsers();
            break;
        case 'places-manage':
            loadAllPlaces();
            break;
        case 'categories-manage':
            loadAllCategories();
            break;
        case 'hunts-manage':
            loadAllHunts();
            break;
        case 'places':
            loadPendingPlaces();
            break;
        case 'photos':
            loadPendingPhotos();
            break;
        case 'hunts':
            loadPendingHunts();
            break;
    }
}

async function apiRequest(endpoint, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${adminToken}`,
            ...options.headers
        }
    });

    const data = await response.json();

    if (!data.success && response.status === 401) {
        logout();
        return;
    }

    return data;
}

// ========== DASHBOARD ==========
async function loadDashboardStats() {
    const data = await apiRequest('/admin/dashboard-stats');

    if (data && data.success) {
        document.getElementById('stat-users').textContent = data.data.total_users;
        document.getElementById('stat-places').textContent = data.data.total_places;
        document.getElementById('stat-hunts').textContent = data.data.total_hunts;
        document.getElementById('stat-pending-places').textContent = data.data.pending_places;
        document.getElementById('stat-pending-hunts').textContent = data.data.pending_hunts;
        document.getElementById('stat-active-progress').textContent = data.data.active_hunt_progress;
        document.getElementById('stat-pending-photos').textContent = data.data.pending_photos;
    }

    // Load pending photos count separately
    // const photosData = await apiRequest('/place-photos/pending');
    // if (photosData && photosData.success) {
    //     document.getElementById('stat-pending-photos').textContent = photosData.data.length;
    // } else {
    //     document.getElementById('stat-pending-photos').textContent = '0';
    // }
}

// ========== USER MANAGEMENT ==========
async function loadAllUsers() {
    const container = document.getElementById('users-content');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const data = await apiRequest('/admin/users?limit=100');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Points</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(user => `
                        <tr style="${user.account_status === 'deleted' ? 'opacity: 0.5; background: #f8d7da;' : ''}">
                            <td>${user.user_id}</td>
                            <td><strong>${user.username}</strong></td>
                            <td>${user.email}</td>
                            <td>${user.user_type}</td>
                            <td>${user.total_points}</td>
                            <td>${user.level}</td>
                            <td><span style="color: ${user.account_status === 'active' ? 'green' : user.account_status === 'deleted' ? 'red' : 'orange'};">${user.account_status}</span></td>
                            <td>
                                ${user.account_status !== 'deleted' ? `
                                    <button class="btn btn-info" onclick="editUser(${user.user_id})">Edit</button>
                                    <button class="btn btn-danger" onclick="deleteUser(${user.user_id}, '${user.username}')">Delete</button>
                                ` : '<em>Deleted</em>'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No users found</h3></div>';
    }
}
function showCreateUserModal() {
    document.getElementById('userModalTitle').textContent = 'Create New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;

    // Show "Make Admin" checkbox when creating
    document.getElementById('makeAdmin').parentElement.style.display = 'block';

    document.getElementById('userModal').classList.add('active');
}

async function editUser(userId) {
    // Fetch user data
    const data = await apiRequest(`/admin/users?limit=100`);
    if (data && data.success) {
        const user = data.data.find(u => u.user_id == userId);
        if (user) {
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = user.user_id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('userType').value = user.user_type;
            document.getElementById('accountStatus').value = user.account_status;
            document.getElementById('password').required = false;

            // Hide "Make Admin" checkbox when editing
            document.getElementById('makeAdmin').parentElement.style.display = 'none';

            document.getElementById('userModal').classList.add('active');
        }
    }
}

async function saveUser(event) {
    event.preventDefault();

    const userId = document.getElementById('userId').value;
    const userData = {
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        full_name: document.getElementById('fullName').value,
        user_type: document.getElementById('userType').value,
        account_status: document.getElementById('accountStatus').value
    };

    // Only include password if provided
    const password = document.getElementById('password').value;
    if (password) {
        userData.password = password;
    }

    const isAdmin = document.getElementById('makeAdmin').checked;

    if (userId) {
        // Update existing user
        const data = await apiRequest(`/admin/user/${userId}`, {
            method: 'PUT',
            body: JSON.stringify(userData)
        });

        if (data && data.success) {
            alert('User updated successfully!');
            closeUserModal();
            loadAllUsers();
        } else {
            alert('Error: ' + (data?.message || 'Failed to update user'));
        }
    } else {
        // Create new user
        if (!password) {
            alert('Password is required for new users');
            return;
        }

        const endpoint = isAdmin ? '/admin/create-admin' : '/auth/register';
        const data = await apiRequest(endpoint, {
            method: 'POST',
            body: JSON.stringify(userData)
        });

        if (data && data.success) {
            alert(isAdmin ? 'Admin user created successfully!' : 'User created successfully!');
            closeUserModal();
            loadAllUsers();
        } else {
            alert('Error: ' + (data?.message || 'Failed to create user'));
        }
    }
}

async function deleteUser(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"?`)) {
        const data = await apiRequest(`/admin/user/${userId}`, {
            method: 'DELETE'
        });

        if (data && data.success) {
            alert('User deleted successfully!');
            loadAllUsers();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to delete user'));
        }
    }
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
}

// ========== PLACE MANAGEMENT ==========
async function loadAllPlaces() {
    const container = document.getElementById('places-manage-content');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const data = await apiRequest('/places?limit=100');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(place => `
                        <tr>
                            <td>${place.place_id}</td>
                            <td><strong>${place.name}</strong></td>
                            <td>${place.city || 'N/A'}</td>
                            <td>${place.place_type}</td>
                            <td>${place.status}</td>
                            <td>${place.is_verified ? '✓' : '✗'}</td>
                            <td>
                                <button class="btn btn-info" onclick="editPlace(${place.place_id})">Edit</button>
                                <button class="btn btn-danger" onclick="deletePlace(${place.place_id}, '${place.name}')">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No places found</h3></div>';
    }
}

function showCreatePlaceModal() {
    document.getElementById('placeModalTitle').textContent = 'Create New Place';
    document.getElementById('placeForm').reset();
    document.getElementById('placeId').value = '';
    document.getElementById('placeModal').classList.add('active');
}

async function editPlace(placeId) {
    const data = await apiRequest(`/places/${placeId}`);
    if (data && data.success) {
        const place = data.data;
        document.getElementById('placeModalTitle').textContent = 'Edit Place';
        document.getElementById('placeId').value = place.place_id;
        document.getElementById('placeName').value = place.name;
        document.getElementById('placeDescription').value = place.description || '';
        document.getElementById('latitude').value = place.latitude;
        document.getElementById('longitude').value = place.longitude;
        document.getElementById('city').value = place.city || '';
        document.getElementById('placeType').value = place.place_type;
        document.getElementById('placeStatus').value = place.status;
        document.getElementById('placeModal').classList.add('active');
    }
}

async function savePlace(event) {
    event.preventDefault();

    const placeId = document.getElementById('placeId').value;

    // Get a user ID to assign as creator (use first active user)
    let creatorUserId = 1; // Default
    try {
        const usersData = await apiRequest('/admin/users?limit=1');
        if (usersData && usersData.success && usersData.data.length > 0) {
            creatorUserId = usersData.data[0].user_id;
        }
    } catch (e) {
        console.error('Could not fetch user for creator:', e);
    }

    const placeData = {
        name: document.getElementById('placeName').value,
        description: document.getElementById('placeDescription').value,
        latitude: parseFloat(document.getElementById('latitude').value),
        longitude: parseFloat(document.getElementById('longitude').value),
        city: document.getElementById('city').value,
        place_type: document.getElementById('placeType').value,
        status: document.getElementById('placeStatus').value,
        category_ids: [2], // Default to Nature category
        created_by_user_id: creatorUserId // Add creator
    };

    if (placeId) {
        // Update existing place
        const data = await apiRequest(`/admin/place/${placeId}`, {
            method: 'PUT',
            body: JSON.stringify(placeData)
        });

        if (data && data.success) {
            alert('Place updated successfully!');
            closePlaceModal();
            loadAllPlaces();
        } else {
            alert('Error: ' + (data?.message || 'Failed to update place'));
        }
    } else {
        // Create new place - needs to go through the regular API with auth
        // We'll create a workaround by using admin endpoint
        const data = await apiRequest('/admin/create-place', {
            method: 'POST',
            body: JSON.stringify(placeData)
        });

        if (data && data.success) {
            alert('Place created successfully!');
            closePlaceModal();
            loadAllPlaces();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to create place'));
        }
    }
}

async function deletePlace(placeId, placeName) {
    if (confirm(`Are you sure you want to delete place "${placeName}"?\n\nThis will also remove it from any treasure hunts!`)) {
        const data = await apiRequest(`/admin/place/${placeId}`, {
            method: 'DELETE'
        });

        if (data && data.success) {
            alert('Place deleted successfully!');
            loadAllPlaces();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to delete place'));
        }
    }
}

function closePlaceModal() {
    document.getElementById('placeModal').classList.remove('active');
}

// ========== CATEGORY MANAGEMENT ==========
async function loadAllCategories() {
    const container = document.getElementById('categories-manage-content');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const data = await apiRequest('/categories');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Place Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(category => `
                        <tr>
                            <td>${category.category_id}</td>
                            <td>${category.icon_url ? '🏷️' : '-'}</td>
                            <td><strong>${category.name}</strong></td>
                            <td>${category.description || 'N/A'}</td>
                            <td>${category.place_count || 0} places</td>
                            <td>
                                <button class="btn btn-info" onclick="editCategory(${category.category_id})">Edit</button>
                                <button class="btn btn-danger" onclick="deleteCategory(${category.category_id}, '${category.name}')">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No categories found</h3></div>';
    }
}

function showCreateCategoryModal() {
    document.getElementById('categoryModalTitle').textContent = 'Create New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryModal').classList.add('active');
}

async function editCategory(categoryId) {
    const data = await apiRequest(`/categories/${categoryId}`);
    if (data && data.success) {
        const category = data.data;
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryId').value = category.category_id;
        document.getElementById('categoryName').value = category.name;
        document.getElementById('categoryDescription').value = category.description || '';
        document.getElementById('categoryIcon').value = category.icon_url || '';
        document.getElementById('categoryModal').classList.add('active');
    }
}

async function saveCategory(event) {
    event.preventDefault();

    const categoryId = document.getElementById('categoryId').value;
    const categoryData = {
        name: document.getElementById('categoryName').value,
        description: document.getElementById('categoryDescription').value,
        icon_url: document.getElementById('categoryIcon').value
    };

    if (categoryId) {
        // Update existing category
        const data = await apiRequest(`/admin/category/${categoryId}`, {
            method: 'PUT',
            body: JSON.stringify(categoryData)
        });

        if (data && data.success) {
            alert('Category updated successfully!');
            closeCategoryModal();
            loadAllCategories();
        } else {
            alert('Error: ' + (data?.message || 'Failed to update category'));
        }
    } else {
        // Create new category
        const data = await apiRequest('/admin/create-category', {
            method: 'POST',
            body: JSON.stringify(categoryData)
        });

        if (data && data.success) {
            alert('Category created successfully!');
            closeCategoryModal();
            loadAllCategories();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to create category'));
        }
    }
}

async function deleteCategory(categoryId, categoryName) {
    if (confirm(`Are you sure you want to delete category "${categoryName}"?\n\nThis will remove it from all places!`)) {
        const data = await apiRequest(`/admin/category/${categoryId}`, {
            method: 'DELETE'
        });

        if (data && data.success) {
            alert('Category deleted successfully!');
            loadAllCategories();
        } else {
            alert('Error: ' + (data?.message || 'Failed to delete category'));
        }
    }
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

// ========== HUNT MANAGEMENT ==========
async function loadAllHunts() {
    const container = document.getElementById('hunts-manage-content');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const data = await apiRequest('/hunts?limit=100');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Difficulty</th>
                        <th>Points</th>
                        <th>Active</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(hunt => `
                        <tr>
                            <td>${hunt.hunt_id}</td>
                            <td><strong>${hunt.title}</strong></td>
                            <td>${hunt.difficulty_level}</td>
                            <td>${hunt.reward_points}</td>
                            <td>${hunt.is_active ? '✓' : '✗'}</td>
                            <td>${hunt.is_featured ? '⭐' : ''}</td>
                            <td>
                                <button class="btn btn-info" onclick="editHunt(${hunt.hunt_id})">Edit</button>
                                <button class="btn btn-${hunt.is_active ? 'warning' : 'success'}" onclick="toggleHuntStatus(${hunt.hunt_id}, ${hunt.is_active})">
                                    ${hunt.is_active ? 'Deactivate' : 'Activate'}
                                </button>
                                <button class="btn btn-danger" onclick="deleteHunt(${hunt.hunt_id}, '${hunt.title}')">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No hunts found</h3></div>';
    }
}

function showCreateHuntModal() {
    document.getElementById('huntModalTitle').textContent = 'Create New Hunt';
    document.getElementById('huntForm').reset();
    document.getElementById('huntId').value = '';
    document.getElementById('isActive').checked = true;
    document.getElementById('huntModal').classList.add('active');
}

async function editHunt(huntId) {
    const data = await apiRequest(`/hunts/${huntId}`);
    if (data && data.success) {
        const hunt = data.data;
        document.getElementById('huntModalTitle').textContent = 'Edit Hunt';
        document.getElementById('huntId').value = hunt.hunt_id;
        document.getElementById('huntTitle').value = hunt.title;
        document.getElementById('huntDescription').value = hunt.description || '';
        document.getElementById('difficulty').value = hunt.difficulty_level;
        document.getElementById('rewardPoints').value = hunt.reward_points;
        document.getElementById('isActive').checked = hunt.is_active == 1;
        document.getElementById('isFeatured').checked = hunt.is_featured == 1;
        document.getElementById('huntModal').classList.add('active');
    }
}

async function saveHunt(event) {
    event.preventDefault();

    const huntId = document.getElementById('huntId').value;
    const huntData = {
        title: document.getElementById('huntTitle').value,
        description: document.getElementById('huntDescription').value,
        difficulty_level: document.getElementById('difficulty').value,
        reward_points: parseInt(document.getElementById('rewardPoints').value),
        is_active: document.getElementById('isActive').checked ? 1 : 0,
        is_featured: document.getElementById('isFeatured').checked ? 1 : 0
    };

    if (huntId) {
        // Update existing hunt
        const data = await apiRequest(`/admin/hunt/${huntId}`, {
            method: 'PUT',
            body: JSON.stringify(huntData)
        });

        if (data && data.success) {
            alert('Hunt updated successfully!');
            closeHuntModal();
            loadAllHunts();
        } else {
            alert('Error: ' + (data?.message || 'Failed to update hunt'));
        }
    } else {
        alert('Creating new hunts requires the full hunt creation flow with checkpoints.\n\nPlease use the user PWA to create hunts, then approve them from the admin panel.');
        closeHuntModal();
    }
}

async function toggleHuntStatus(huntId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const action = newStatus ? 'activate' : 'deactivate';

    if (confirm(`Are you sure you want to ${action} this hunt?`)) {
        const data = await apiRequest(`/admin/hunt/${huntId}`, {
            method: 'PUT',
            body: JSON.stringify({ is_active: newStatus })
        });

        if (data && data.success) {
            alert(`Hunt ${action}d successfully!`);
            loadAllHunts();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to update hunt status'));
        }
    }
}

async function deleteHunt(huntId, huntTitle) {
    if (confirm(`Are you sure you want to delete hunt "${huntTitle}"?`)) {
        const data = await apiRequest(`/hunts/${huntId}`, {
            method: 'DELETE'
        });

        if (data && data.success) {
            alert('Hunt deleted!');
            loadAllHunts();
        } else {
            alert('Error: ' + (data?.message || 'Failed to delete hunt'));
        }
    }
}

function closeHuntModal() {
    document.getElementById('huntModal').classList.remove('active');
}

// ========== PENDING APPROVALS  ==========
async function loadPendingPlaces() {
    const container = document.getElementById('places-content');
    const data = await apiRequest('/admin/pending-places');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>Place Name</th>
                        <th>Type</th>
                        <th>City</th>
                        <th>Created By</th>
                        <th>Categories</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(place => `
                        <tr>
                            <td><strong>${place.name}</strong></td>
                            <td>${place.place_type}</td>
                            <td>${place.city || 'N/A'}</td>
                            <td>${place.created_by_username}</td>
                            <td>${place.categories || 'N/A'}</td>
                            <td>
                                <button class="btn btn-success" onclick="approvePlace(${place.place_id})">Approve</button>
                                <button class="btn btn-danger" onclick="rejectPlace(${place.place_id})">Reject</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No pending places</h3><p>All places have been reviewed!</p></div>';
    }
}

async function approvePlace(placeId) {
    if (confirm('Approve this place?')) {
        const data = await apiRequest('/admin/approve-place', {
            method: 'POST',
            body: JSON.stringify({ place_id: placeId, action: 'approve' })
        });

        if (data.success) {
            alert('Place approved!');
            loadPendingPlaces();
            loadDashboardStats();
        }
    }
}

async function rejectPlace(placeId) {
    if (confirm('Reject this place?')) {
        const data = await apiRequest('/admin/approve-place', {
            method: 'POST',
            body: JSON.stringify({ place_id: placeId, action: 'reject' })
        });

        if (data.success) {
            alert('Place rejected!');
            loadPendingPlaces();
        }
    }
}

async function loadPendingPhotos() {
    const container = document.getElementById('photos-content');
    container.innerHTML = '<div class="loading">Loading...</div>';

    const data = await apiRequest('/place-photos/pending');

    if (data && data.success && data.data.length > 0) {
        // Update dashboard count
        document.getElementById('stat-pending-photos').textContent = data.data.length;

        container.innerHTML = `
            <div style="display: grid; gap: 20px;">
                ${data.data.map(photo => `
                    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: grid; grid-template-columns: 300px 1fr auto; gap: 20px;">
                        <div style="height: 250px; overflow: hidden; background: #f0f0f0;">
                            <img src="${photo.photo_url}" alt="${photo.place_name}" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="padding: 20px; display: flex; flex-direction: column; justify-content: center;">
                            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 20px;">${photo.place_name}</h3>
                            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; font-size: 14px; color: #666;">
                                <span>📍 ${photo.city || 'N/A'}</span>
                                <span>👤 Uploaded by: ${photo.uploader_name} (@${photo.uploader_username})</span>
                                <span>📅 ${new Date(photo.uploaded_at).toLocaleString()}</span>
                            </div>
                            <div style="font-size: 13px; color: #888;">
                                <p style="margin: 5px 0;"><strong>File:</strong> ${photo.file_name}</p>
                                <p style="margin: 5px 0;"><strong>Size:</strong> ${(photo.file_size / 1024 / 1024).toFixed(2)} MB</p>
                            </div>
                        </div>
                        <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px; justify-content: center; border-left: 1px solid #eee;">
                            <button class="btn btn-success" onclick="approvePlacePhoto(${photo.photo_id}, '${photo.place_name.replace(/'/g, "\\'")}')">
                                ✓ Approve
                            </button>
                            <button class="btn btn-danger" onclick="rejectPlacePhoto(${photo.photo_id})">
                                ✗ Reject
                            </button>
                            <a href="${photo.photo_url}" target="_blank" class="btn btn-info" style="text-align: center; text-decoration: none;">
                                🔍 Full Size
                            </a>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>✅ No pending photos</h3><p>All photos have been reviewed!</p></div>';
        document.getElementById('stat-pending-photos').textContent = '0';
    }
}

async function approvePlacePhoto(photoId, placeName) {
    if (confirm(`Approve this photo for "${placeName}"?\n\nThis will set it as the place's primary photo.`)) {
        const data = await apiRequest('/place-photos/approve', {
            method: 'POST',
            body: JSON.stringify({
                photo_id: photoId,
                set_as_primary: true
            })
        });

        if (data && data.success) {
            alert('Photo approved successfully!');
            loadPendingPhotos();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to approve photo'));
        }
    }
}

async function rejectPlacePhoto(photoId) {
    const reason = prompt('Please provide a reason for rejection:');

    if (reason && reason.trim()) {
        const data = await apiRequest('/place-photos/reject', {
            method: 'POST',
            body: JSON.stringify({
                photo_id: photoId,
                reason: reason.trim()
            })
        });

        if (data && data.success) {
            alert('Photo rejected');
            loadPendingPhotos();
            loadDashboardStats();
        } else {
            alert('Error: ' + (data?.message || 'Failed to reject photo'));
        }
    } else if (reason !== null) {
        alert('Please provide a rejection reason');
    }
}

async function loadPendingHunts() {
    const container = document.getElementById('hunts-content');
    const data = await apiRequest('/admin/pending-hunts');

    if (data && data.success && data.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>Hunt Title</th>
                        <th>Difficulty</th>
                        <th>Checkpoints</th>
                        <th>Reward Points</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(hunt => `
                        <tr>
                            <td><strong>${hunt.title}</strong></td>
                            <td>${hunt.difficulty_level}</td>
                            <td>${hunt.checkpoint_count}</td>
                            <td>${hunt.reward_points}</td>
                            <td>${hunt.created_by}</td>
                            <td>
                                <button class="btn btn-success" onclick="approveHunt(${hunt.hunt_id})">Approve</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = '<div class="empty-state"><h3>No pending hunts</h3><p>All hunts have been reviewed!</p></div>';
    }
}

async function approveHunt(huntId) {
    const featured = confirm('Mark this hunt as featured?');
    const data = await apiRequest('/admin/approve-hunt', {
        method: 'POST',
        body: JSON.stringify({ hunt_id: huntId, is_featured: featured ? 1 : 0 })
    });

    if (data.success) {
        alert('Hunt approved!');
        loadPendingHunts();
        loadDashboardStats();
    }
}

function logout() {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_info');
    window.location.href = 'login.php';
}

// Initial load
loadDashboardStats();