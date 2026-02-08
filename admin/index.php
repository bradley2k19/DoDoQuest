<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Treasure Hunt</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-menu a.active {
            border-left: 4px solid white;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            font-size: 28px;
            color: #333;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            padding: 8px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #d32f2f;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card.primary h3,
        .stat-card.primary .stat-value {
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .stat-card.success h3,
        .stat-card.success .stat-value {
            color: white;
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stat-card.warning h3,
        .stat-card.warning .stat-value {
            color: white;
        }
        
        .page-section {
            display: none;
        }
        
        .page-section.active {
            display: block;
        }
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .content-card h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-info {
            background: #2196f3;
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            margin: 0;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-block {
            width: 100%;
            display: block;
        }
        /* Photo Review Cards - Responsive */
        @media (max-width: 1024px) {
            #photos-content > div > div {
                grid-template-columns: 1fr !important;
            }
            
            #photos-content > div > div > div:last-child {
                border-left: none !important;
                border-top: 1px solid #eee;
                flex-direction: row !important;
            }
        }

        @media (max-width: 768px) {
            #photos-content > div > div > div:first-child {
                height: 200px;
            }
            
            #photos-content > div > div > div:last-child {
                flex-direction: column !important;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏆 Treasure Hunt</h2>
            <p>Admin Dashboard</p>
        </div>
        
        <ul class="nav-menu">
            <li><a href="#" class="nav-link active" data-page="dashboard">📊 Dashboard</a></li>
            <li><a href="#" class="nav-link" data-page="users">👥 Manage Users</a></li>
            <li><a href="#" class="nav-link" data-page="places-manage">📍 Manage Places</a></li>
            <li><a href="#" class="nav-link" data-page="categories-manage">🏷️ Manage Categories</a></li>
            <li><a href="#" class="nav-link" data-page="hunts-manage">🎯 Manage Hunts</a></li>
            <li><a href="#" class="nav-link" data-page="places">📝 Pending Places</a></li>
            <li><a href="#" class="nav-link" data-page="photos">📷 Photo Reviews</a></li>
            <li><a href="#" class="nav-link" data-page="hunts">🔍 Pending Hunts</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1 id="page-title">Dashboard</h1>
            <div class="admin-info">
                <span id="adminName">Admin</span>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
        
        <!-- Dashboard Page -->
        <div id="dashboard-page" class="page-section active">
            <div class="stats-grid">
                <div class="stat-card primary">
                    <h3>Total Users</h3>
                    <div class="stat-value" id="stat-users">-</div>
                </div>
                
                <div class="stat-card success">
                    <h3>Total Places</h3>
                    <div class="stat-value" id="stat-places">-</div>
                </div>
                
                <div class="stat-card warning">
                    <h3>Active Hunts</h3>
                    <div class="stat-value" id="stat-hunts">-</div>
                </div>
                
                <div class="stat-card">
                    <h3>Pending Places</h3>
                    <div class="stat-value" id="stat-pending-places">-</div>
                </div>
                
                <div class="stat-card">
                    <h3>Pending Photos</h3>
                    <div class="stat-value" id="stat-pending-photos">-</div>
                </div>

                <div class="stat-card">
                    <h3>Pending Hunts</h3>
                    <div class="stat-value" id="stat-pending-hunts">-</div>
                </div>
                
                <div class="stat-card">
                    <h3>Active Progress</h3>
                    <div class="stat-value" id="stat-active-progress">-</div>
                </div>
            </div>
        </div>
        
        <!-- Manage Users Page -->
        <div id="users-page" class="page-section">
            <div class="content-card">
                <div class="action-bar">
                    <h2>User Management</h2>
                    <button class="btn btn-primary" onclick="showCreateUserModal()">+ Create User</button>
                </div>
                <div id="users-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Manage Places Page -->
        <div id="places-manage-page" class="page-section">
            <div class="content-card">
                <div class="action-bar">
                    <h2>Place Management</h2>
                    <button class="btn btn-primary" onclick="showCreatePlaceModal()">+ Create Place</button>
                </div>
                <div id="places-manage-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Manage Categories Page -->
        <div id="categories-manage-page" class="page-section">
            <div class="content-card">
                <div class="action-bar">
                    <h2>Category Management</h2>
                    <button class="btn btn-primary" onclick="showCreateCategoryModal()">+ Create Category</button>
                </div>
                <div id="categories-manage-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Manage Hunts Page -->
        <div id="hunts-manage-page" class="page-section">
            <div class="content-card">
                <div class="action-bar">
                    <h2>Hunt Management</h2>
                    <button class="btn btn-primary" onclick="showCreateHuntModal()">+ Create Hunt</button>
                </div>
                <div id="hunts-manage-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Pending Places Page -->
        <div id="places-page" class="page-section">
            <div class="content-card">
                <h2>Pending Places for Approval</h2>
                <div id="places-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Photos Page -->
        <div id="photos-page" class="page-section">
            <div class="content-card">
                <h2>Photos Pending Review</h2>
                <div id="photos-content" class="loading">Loading...</div>
            </div>
        </div>
        
        <!-- Pending Hunts Page -->
        <div id="hunts-page" class="page-section">
            <div class="content-card">
                <h2>Pending Treasure Hunts</h2>
                <div id="hunts-content" class="loading">Loading...</div>
            </div>
        </div>
    </div>
    
    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="userModalTitle">Create User</h2>
                <span class="close" onclick="closeUserModal()">&times;</span>
            </div>
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="userId" value="">
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" id="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="fullName" class="form-control" required>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label>Password *</label>
                    <input type="password" id="password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>User Type *</label>
                    <select id="userType" class="form-control" required>
                        <option value="tourist">Tourist</option>
                        <option value="student">Student</option>
                        <option value="local">Local</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Account Status *</label>
                    <select id="accountStatus" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="makeAdmin"> Make Admin User
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Save User</button>
            </form>
        </div>
    </div>
    
    <!-- Place Modal -->
    <div id="placeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="placeModalTitle">Create Place</h2>
                <span class="close" onclick="closePlaceModal()">&times;</span>
            </div>
            <form id="placeForm" onsubmit="savePlace(event)">
                <input type="hidden" id="placeId" value="">
                
                <div class="form-group">
                    <label>Place Name *</label>
                    <input type="text" id="placeName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="placeDescription" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Latitude *</label>
                    <input type="number" step="0.00001" id="latitude" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Longitude *</label>
                    <input type="number" step="0.00001" id="longitude" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="city" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Place Type *</label>
                    <select id="placeType" class="form-control" required>
                        <option value="monument">Monument</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="museum">Museum</option>
                        <option value="park">Park</option>
                        <option value="beach">Beach</option>
                        <option value="viewpoint">Viewpoint</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status *</label>
                    <select id="placeStatus" class="form-control" required>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Save Place</button>
            </form>
        </div>
    </div>
    
    <!-- Hunt Modal -->
    <div id="huntModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="huntModalTitle">Create Hunt</h2>
                <span class="close" onclick="closeHuntModal()">&times;</span>
            </div>
            <form id="huntForm" onsubmit="saveHunt(event)">
                <input type="hidden" id="huntId" value="">
                
                <div class="form-group">
                    <label>Hunt Title *</label>
                    <input type="text" id="huntTitle" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="huntDescription" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Difficulty *</label>
                    <select id="difficulty" class="form-control" required>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reward Points *</label>
                    <input type="number" id="rewardPoints" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isActive"> Active
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isFeatured"> Featured
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Save Hunt</button>
            </form>
        </div>
    </div>
    
    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="categoryModalTitle">Create Category</h2>
                <span class="close" onclick="closeCategoryModal()">&times;</span>
            </div>
            <form id="categoryForm" onsubmit="saveCategory(event)">
                <input type="hidden" id="categoryId" value="">
                
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" id="categoryName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="categoryDescription" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Icon URL</label>
                    <input type="text" id="categoryIcon" class="form-control" placeholder="icons/category.png">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Save Category</button>
            </form>
        </div>
    </div>
    
    <script src="admin-functions.js"></script>
</body>
</html>