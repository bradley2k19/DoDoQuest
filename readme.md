# 🗺️ Treasure Hunt PWA

A Progressive Web Application (PWA) for creating and participating in location-based treasure hunts with gamification features including levels, badges, and leaderboards.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-yellow)
![PWA](https://img.shields.io/badge/PWA-Enabled-green)

## 📋 Table of Contents

- [Features](#features)
- [Demo](#demo)
- [Technologies](#technologies)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Project Structure](#project-structure)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

### Core Features
- 🗺️ **Interactive Map Integration** - Leaflet maps with real-time location tracking
- 📍 **Location-based Checkpoints** - Create hunts with multiple waypoints
- 🎯 **Multiple Challenge Types**:
  - Geofence verification (proximity-based)
  - QR code scanning
  - Photo upload challenges
  - Quiz questions (multiple choice, true/false, text answer)

### Gamification
- 🏆 **Level System** - Progress through 10 levels from Novice Explorer to Supreme Pathfinder
- 🎖️ **Badges & Achievements** - Earn badges for completing hunts and reaching milestones
- 📊 **Leaderboard** - Compete with other treasure hunters
- ⭐ **Points & Rewards** - Earn experience points and unlock new levels

### PWA Features
- 📱 **Installable** - Install as a native app on mobile and desktop
- 🔄 **Offline Support** - Service worker for offline functionality
- 🚀 **Fast Loading** - Optimized performance and caching
- 📲 **Responsive Design** - Works seamlessly on all devices

### User Features
- 👤 **User Authentication** - Secure JWT-based authentication
- 🎨 **Multiple User Roles** - Explorer, Creator, Admin
- 📈 **Progress Tracking** - Track your hunt completions and statistics
- 💡 **Hint System** - Optional hints with point penalties

## 🎮 Demo

[Add screenshots or GIF demonstrations here]

### Screenshots
```
[Home Page] [Hunt Details] [Active Hunt] [Level Up]
```

## 🛠️ Technologies

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 5.7+** - Database
- **JWT** - Authentication tokens
- **RESTful API** - Clean API architecture

### Frontend
- **Vanilla JavaScript (ES6+)** - No framework dependencies
- **Leaflet.js** - Interactive maps
- **QRCode.js** - QR code generation
- **Html5-qrcode** - QR code scanning
- **Service Workers** - PWA functionality

### Libraries & APIs
- **Leaflet** - Map visualization
- **OpenStreetMap** - Map tiles
- **QRCodeCat API** - QR code generation (alternative)

## 📦 Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for future dependencies)

### Step 1: Clone the Repository
```bash
git clone https://github.com/yourusername/treasure-hunt-pwa.git
cd treasure-hunt-pwa
```

### Step 2: Configure Web Server

#### Apache (.htaccess already included)

Make sure `mod_rewrite` is enabled:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx

Add to your site configuration:
```nginx
location /treasure_hunt/api/ {
    try_files $uri $uri/ /treasure_hunt/api/index.php?$query_string;
}
```

### Step 3: Database Configuration

1. Copy the database configuration template:
```bash
cp api/config/database.example.php api/config/database.php
```

2. Edit `api/config/database.php` with your credentials:
```php
private $host = "localhost";
private $db_name = "treasure_hunt_db";
private $username = "your_username";
private $password = "your_password";
```

### Step 4: Set Permissions
```bash
chmod 755 api/
chmod 644 api/config/database.php
```

## 🗄️ Database Setup

### Option 1: Import SQL File
```bash
mysql -u your_username -p treasure_hunt_db < database/schema.sql
```

### Option 2: Manual Setup

1. Create the database:
```sql
CREATE DATABASE treasure_hunt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema from `database/schema.sql`

3. Initialize level system:
```sql
-- Insert level definitions
INSERT INTO user_levels (level_number, points_required, level_name) VALUES
(1, 0, 'Novice Explorer'),
(2, 100, 'Junior Hunter'),
(3, 250, 'Skilled Tracker'),
(4, 500, 'Expert Navigator'),
(5, 1000, 'Master Explorer'),
(6, 2000, 'Legend Seeker'),
(7, 3500, 'Grand Master'),
(8, 5500, 'Elite Champion'),
(9, 8000, 'Mythic Wanderer'),
(10, 12000, 'Supreme Pathfinder');
```

4. Create level badges:
```sql
INSERT INTO badges (name, description, badge_type) VALUES
('Level 2 Achiever', 'Reached Level 2 - Junior Hunter', 'level'),
('Level 3 Achiever', 'Reached Level 3 - Skilled Tracker', 'level'),
('Level 4 Achiever', 'Reached Level 4 - Expert Navigator', 'level'),
('Level 5 Achiever', 'Reached Level 5 - Master Explorer', 'level'),
('Level 6 Achiever', 'Reached Level 6 - Legend Seeker', 'level'),
('Level 7 Achiever', 'Reached Level 7 - Grand Master', 'level'),
('Level 8 Achiever', 'Reached Level 8 - Elite Champion', 'level'),
('Level 9 Achiever', 'Reached Level 9 - Mythic Wanderer', 'level'),
('Level 10 Achiever', 'Reached Level 10 - Supreme Pathfinder', 'level');
```

## ⚙️ Configuration

### API URL Configuration

Update the API URL in `/pwa/js/app.js`:
```javascript
const API_URL = 'https://yourdomain.com/treasure_hunt/api';
```

### JWT Secret Key

Update the secret key in `/api/middleware/auth.php`:
```php
private static $secret_key = "your-secret-key-change-this-in-production";
```

**⚠️ Important:** Use a strong, random secret key in production!

### Service Worker Configuration

Update the cache name and version in `/pwa/service-worker.js`:
```javascript
const CACHE_NAME = 'treasure-hunt-v1.0.0';
```

## 🚀 Usage

### For Treasure Hunters (Explorers)

1. **Register/Login** - Create an account or sign in
2. **Browse Hunts** - Explore available treasure hunts on the home page
3. **Start a Hunt** - Select a hunt and begin your adventure
4. **Complete Checkpoints** - Navigate to each location and complete challenges
5. **Earn Rewards** - Gain points, level up, and earn badges

### For Hunt Creators

1. **Create Places** - Add interesting locations to the database
2. **Design Hunts** - Create treasure hunts with multiple checkpoints
3. **Configure Challenges** - Set up different challenge types for each checkpoint
4. **Publish** - Submit hunts for admin approval

### For Administrators

1. **Approve Content** - Review and approve user-created hunts
2. **Manage Users** - User administration and moderation
3. **Feature Hunts** - Mark exceptional hunts as featured

## 📚 API Documentation

### Authentication

#### Register
```http
POST /api/auth/register
Content-Type: application/json

{
  "username": "john_explorer",
  "email": "john@example.com",
  "password": "secure_password",
  "full_name": "John Doe",
  "user_type": "explorer"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "secure_password"
}
```

### Hunts

#### Get All Hunts
```http
GET /api/hunts
GET /api/hunts?difficulty=easy
```

#### Get Hunt Details
```http
GET /api/hunts/{hunt_id}
```

#### Create Hunt
```http
POST /api/hunts/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Historic City Tour",
  "description": "Explore the historic landmarks",
  "difficulty_level": "medium",
  "checkpoints": [...]
}
```

### Progress

#### Start Hunt
```http
POST /api/progress/start
Authorization: Bearer {token}
Content-Type: application/json

{
  "hunt_id": 1
}
```

#### Complete Checkpoint
```http
POST /api/progress/complete-checkpoint
Authorization: Bearer {token}
Content-Type: application/json

{
  "progress_id": 1,
  "checkpoint_id": 1,
  "verification_method": "geofence",
  "verification_data": {...}
}
```

### Levels & Leaderboard

#### Get Level Progress
```http
GET /api/levels/progress
Authorization: Bearer {token}
```

#### Get Leaderboard
```http
GET /api/levels/leaderboard?limit=20
```

[See full API documentation](docs/API.md)

## 📁 Project Structure
```
treasure-hunt-pwa/
├── api/                          # Backend API
│   ├── config/
│   │   ├── database.php         # Database configuration (gitignored)
│   │   └── database.example.php # Database config template
│   ├── controllers/             # API Controllers
│   │   ├── AuthController.php
│   │   ├── HuntController.php
│   │   ├── PlaceController.php
│   │   ├── ProgressController.php
│   │   └── LevelController.php
│   ├── middleware/
│   │   └── auth.php            # JWT authentication
│   ├── utils/
│   │   ├── Response.php        # API response handler
│   │   └── LevelSystem.php     # Gamification logic
│   ├── .htaccess
│   └── index.php               # API entry point
│
├── pwa/                         # Frontend PWA
│   ├── css/
│   │   └── style.css           # Main styles
│   ├── js/
│   │   ├── app.js              # Main application
│   │   └── db.js               # IndexedDB handling
│   ├── images/
│   ├── manifest.json           # PWA manifest
│   ├── service-worker.js       # Service worker
│   └── index.html              # Main HTML
│
├── database/
│   ├── schema.sql              # Database schema
│   └── seed.sql                # Sample data (optional)
│
├── docs/
│   └── API.md                  # API documentation
│
├── .gitignore
├── README.md
└── LICENSE
```

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards

- PHP: Follow PSR-12 coding standards
- JavaScript: Use ES6+ features, maintain consistent formatting
- Database: Use prepared statements, follow naming conventions
- Comments: Document complex logic and functions

## 🐛 Known Issues

- Photo upload feature pending full implementation
- QR scanner may require HTTPS in production
- Offline mode has limited functionality

## 🔮 Future Enhancements

- [ ] Real-time multiplayer hunts
- [ ] AR (Augmented Reality) integration
- [ ] Social features (friend system, teams)
- [ ] Hunt templates and categories
- [ ] Advanced analytics dashboard
- [ ] Mobile native apps (iOS/Android)
- [ ] Integration with fitness trackers

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Your Name**
- GitHub: [@yourusername](https://github.com/yourusername)
- Email: your.email@example.com

## 🙏 Acknowledgments

- OpenStreetMap for map tiles
- Leaflet.js for mapping library
- QRCode.js and Html5-qrcode for QR functionality
- All contributors and testers

## 📞 Support

For support, email your.email@example.com or open an issue on GitHub.

---

**Made with ❤️ for adventure seekers everywhere**
```