
// Auto-detect API URL based on current host
const API_URL = 'https://10.73.197.212/treasure_hunt/api';

class TreasureHuntApp {
    constructor() {
        this.token = localStorage.getItem('user_token'); // JWT token for authentication
        this.userInfo = JSON.parse(localStorage.getItem('user_info') || '{}'); // User info object
        this.map = null; // Leaflet map instance
        this.currentLocation = null; // To store user's current location
        this.isOnline = navigator.onLine; // Track network status
        this.activeHuntProgress = null; // To track current hunt progress
        this.huntCheckpoints = []; // For hunt creation
        this.currentCheckpoint = null; // To track current checkpoint in an active hunt
        this.currentChallengeConfig = null; // To store current challenge configuration

        this.init(); // Initialize the app
    }

    async init() {
        // Initialize IndexedDB
        await treasureDB.init();

        // Check authentication
        if (!this.token) {
            this.showAuthModal();
            return;
        }

        // Setup navigation
        this.setupNavigation();

        // Setup network status
        this.setupNetworkStatus();

        // Load initial data
        this.loadHomePage();

        // Request location permission
        this.requestLocation();
    }

    setupNavigation() {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                this.switchPage(page);
            });
        });

        // Difficulty filter
        document.getElementById('difficulty-filter').addEventListener('change', (e) => {
            this.loadAllHunts(e.target.value);
        });
    }

    setupNetworkStatus() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            document.getElementById('offlineBanner').classList.remove('show');
            this.syncOfflineData();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            document.getElementById('offlineBanner').classList.add('show');
        });
    }

    switchPage(page) {
        // Update active nav
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        document.querySelector(`[data-page="${page}"]`).classList.add('active');

        // Update active page
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.getElementById(`${page}-page`).classList.add('active');

        // Load page data
        this.loadPageData(page);
    }

    loadPageData(page) {
        switch (page) {
            case 'home':
                this.loadHomePage();
                break;
            case 'explore':
                this.loadExplorePage();
                break;
            case 'hunts':
                this.loadMyHunts();
                break;
            case 'profile':
                this.loadProfile();
                break;
        }
    }

    async apiRequest(endpoint, options = {}) {
        try {
            const response = await fetch(`${API_URL}${endpoint}`, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`,
                    ...options.headers
                }
            });

            // Get the response text first
            const responseText = await response.text();

            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                // If it's not JSON, log the actual response
                console.error('API returned non-JSON response:', responseText.substring(0, 500));
                console.error('Endpoint:', endpoint);
                console.error('Status:', response.status);

                // Return a structured error
                return {
                    success: false,
                    message: 'Server returned an invalid response',
                    error: responseText.substring(0, 200)
                };
            }

            if (!data.success && response.status === 401) {
                this.logout();
                return null;
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            if (!this.isOnline) {
                return { success: false, offline: true };
            }
            return { success: false, message: error.message };
        }
    }

    // HOME PAGE
    async loadHomePage() {
        await this.loadFeaturedHunts();
        await this.loadAllHunts();
    }

    async loadFeaturedHunts() {
        const container = document.getElementById('featured-hunts');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/hunts/featured');

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(hunt => this.renderHuntCard(hunt, true)).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><p>No featured hunts available</p></div>';
        }
    }

    async loadAllHunts(difficulty = '') {
        const container = document.getElementById('all-hunts');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const params = difficulty ? `?difficulty=${difficulty}` : '';
        const data = await this.apiRequest(`/hunts${params}`);

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(hunt => this.renderHuntCard(hunt)).join('');

            // Cache hunts for offline
            data.data.forEach(hunt => treasureDB.cacheHunt(hunt));
        } else {
            container.innerHTML = '<div class="empty-state"><p>No hunts available</p></div>';
        }
    }

    renderHuntCard(hunt, featured = false) {
        return `
            <div class="hunt-card" onclick="app.showHuntDetail(${hunt.hunt_id})">
                <div class="hunt-card-header">
                    <div class="hunt-title">
                        ${hunt.title}
                        ${featured ? '<span class="badge badge-featured">⭐ Featured</span>' : ''}
                    </div>
                    <div class="hunt-meta">
                        <span class="badge badge-${hunt.difficulty_level}">${hunt.difficulty_level}</span>
                        <span>🏆 ${hunt.reward_points} pts</span>
                        <span>📍 ${hunt.checkpoint_count} checkpoints</span>
                    </div>
                </div>
                <div class="hunt-card-body">
                    <p class="hunt-description">${hunt.description || 'No description'}</p>
                    <div class="hunt-stats">
                        <span class="stat-item">⏱️ ${hunt.estimated_duration || 'N/A'} min</span>
                        <span class="stat-item">📏 ${hunt.distance_km || 'N/A'} km</span>
                        <span class="stat-item">👥 ${hunt.participant_count} participants</span>
                    </div>
                </div>
            </div>
        `;
    }

    async showHuntDetail(huntId) {
        const modal = document.getElementById('hunt-modal');
        const content = document.getElementById('hunt-detail-content');

        content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        modal.classList.add('active');

        const data = await this.apiRequest(`/hunts/${huntId}`);

        if (data && data.success) {
            const hunt = data.data;
            content.innerHTML = `
                <h2>${hunt.title}</h2>
                <div class="hunt-meta" style="margin: 15px 0;">
                    <span class="badge badge-${hunt.difficulty_level}">${hunt.difficulty_level}</span>
                    <span>🏆 ${hunt.reward_points} points</span>
                </div>
                
                <p style="margin: 15px 0;">${hunt.description || 'No description available'}</p>
                
                <div style="margin: 20px 0;">
                    <strong>Starting Location:</strong> ${hunt.starting_location_name}<br>
                    <strong>Checkpoints:</strong> ${hunt.checkpoints.length}<br>
                    <strong>Estimated Duration:</strong> ${hunt.estimated_duration || 'N/A'} minutes
                </div>
                
                <h3>Checkpoints:</h3>
                <div style="margin-bottom: 20px;">
                    ${hunt.checkpoints.map((cp, i) => `
                        <div style="padding: 10px; background: #f5f5f5; margin: 10px 0; border-radius: 8px;">
                            <strong>${i + 1}. ${cp.place_name}</strong><br>
                            <small>${cp.challenge_type} challenge</small>
                        </div>
                    `).join('')}
                </div>
                
                <button class="btn btn-primary btn-block" onclick="app.startHunt(${huntId})">
                    Start This Hunt
                </button>
            `;
        }
    }

    closeHuntModal() {
        document.getElementById('hunt-modal').classList.remove('active');
    }

    async startHunt(huntId) {
        const data = await this.apiRequest('/progress/start', {
            method: 'POST',
            body: JSON.stringify({ hunt_id: huntId })
        });

        if (data && data.success) {
            alert('Hunt started! Check "My Hunts" to continue.');
            this.closeHuntModal();
            this.switchPage('hunts');
        } else {
            alert(data?.message || 'Failed to start hunt');
        }
    }

    // EXPLORE PAGE
    async loadExplorePage() {
        if (!this.map) {
            this.initMap();
        }
        await this.loadNearbyPlaces();
    }

    showCreatePlaceModal() {
        document.getElementById('placeModalTitle').textContent = 'Create New Place';
        document.getElementById('placeForm').reset();
        document.getElementById('placeId').value = '';
        document.getElementById('placeModal').classList.add('active');
    }

    async savePlace(event) {
        event.preventDefault();

        const placeId = document.getElementById('placeId').value;

        // Use the current logged-in user as creator
        const creatorUserId = this.userInfo.user_id || 1;

        const placeData = {
            name: document.getElementById('placeName').value,
            description: document.getElementById('placeDescription').value,
            latitude: parseFloat(document.getElementById('latitude').value),
            longitude: parseFloat(document.getElementById('longitude').value),
            city: document.getElementById('city').value,
            place_type: document.getElementById('placeType').value,
            category_ids: [2], // Default to Nature category
        };

        if (placeId) {
            // Update existing place
            const data = await this.apiRequest(`/places/${placeId}`, {
                method: 'PUT',
                body: JSON.stringify(placeData)
            });

            if (data && data.success) {
                alert('Place updated successfully!');
                this.closePlaceModal();
                await this.loadNearbyPlaces(); // Reload the places list
            } else {
                alert('Error: ' + (data?.message || 'Failed to update place'));
            }
        } else {
            // Create new place using the standard places endpoint
            const data = await this.apiRequest('/places/create', {
                method: 'POST',
                body: JSON.stringify(placeData)
            });

            if (data && data.success) {
                alert('Place created successfully!');
                this.closePlaceModal();
                await this.loadNearbyPlaces(); // Reload the places list
            } else {
                alert('Error: ' + (data?.message || 'Failed to create place'));
            }
        }
    }

    closePlaceModal() {
        document.getElementById('placeModal').classList.remove('active');
    }

    showCreateHuntModal() {
        document.getElementById('huntModalTitle').textContent = 'Create New Treasure Hunt';
        document.getElementById('huntForm').reset();
        document.getElementById('huntId').value = '';

        // Load places for checkpoint selection
        this.loadPlacesForHunt();

        // Reset checkpoints
        this.huntCheckpoints = [];
        this.renderHuntCheckpoints();

        // Initialize challenge config (show geofence config by default)
        setTimeout(() => {
            this.onChallengeTypeChange();
        }, 100);

        document.getElementById('huntModal').classList.add('active');
    }

    async loadPlacesForHunt() {
        const data = await this.apiRequest('/places?limit=100');

        if (data && data.success) {
            // Populate starting location dropdown
            const startSelect = document.getElementById('huntStartLocation');
            startSelect.innerHTML = '<option value="">Select starting location...</option>';

            // Populate checkpoint place dropdown
            const checkpointSelect = document.getElementById('checkpointPlaceSelect');
            checkpointSelect.innerHTML = '<option value="">Select a place...</option>';

            data.data.forEach(place => {
                // Add to starting location
                const startOption = document.createElement('option');
                startOption.value = place.place_id;
                startOption.textContent = `${place.name} - ${place.city || 'N/A'}`;
                startSelect.appendChild(startOption);

                // Add to checkpoint selection
                const checkpointOption = document.createElement('option');
                checkpointOption.value = place.place_id;
                checkpointOption.textContent = `${place.name} - ${place.city || 'N/A'}`;
                checkpointOption.dataset.place = JSON.stringify(place);
                checkpointSelect.appendChild(checkpointOption);
            });
        }
    }

    addCheckpoint() {
        const placeSelect = document.getElementById('checkpointPlaceSelect');
        const selectedOption = placeSelect.options[placeSelect.selectedIndex];

        if (!selectedOption.value) {
            alert('Please select a place');
            return;
        }

        const place = JSON.parse(selectedOption.dataset.place);
        const clueText = document.getElementById('checkpointClue').value;
        const hintText = document.getElementById('checkpointHint').value;
        const challengeType = document.getElementById('checkpointChallenge').value;
        const points = parseInt(document.getElementById('checkpointPoints').value) || 10;
        const timeLimit = parseInt(document.getElementById('checkpointTimeLimit').value) || null;
        const isMandatory = document.getElementById('checkpointMandatory').checked;

        if (!clueText) {
            alert('Please enter a clue');
            return;
        }

        // Collect challenge-specific configuration
        const challengeConfig = this.collectChallengeConfig(challengeType);
        if (challengeConfig === null) {
            return; // Validation failed
        }

        // Add to checkpoints array
        this.huntCheckpoints.push({
            place_id: place.place_id,
            place_name: place.name,
            sequence_order: this.huntCheckpoints.length + 1,
            clue_text: clueText,
            hint_text: hintText || null,
            challenge_type: challengeType,
            challenge_data: challengeConfig,
            points_awarded: points,
            time_limit_minutes: timeLimit,
            is_mandatory: isMandatory ? 1 : 0
        });

        // Reset checkpoint form
        document.getElementById('checkpointClue').value = '';
        document.getElementById('checkpointHint').value = '';
        document.getElementById('checkpointPoints').value = '10';
        document.getElementById('checkpointTimeLimit').value = '';
        document.getElementById('checkpointMandatory').checked = true;
        document.getElementById('challengeConfig').innerHTML = '';
        placeSelect.selectedIndex = 0;

        // Re-render checkpoints
        this.renderHuntCheckpoints();
    }

    removeCheckpoint(index) {
        this.huntCheckpoints.splice(index, 1);

        // Update sequence orders
        this.huntCheckpoints.forEach((cp, i) => {
            cp.sequence_order = i + 1;
        });

        this.renderHuntCheckpoints();
    }

    renderHuntCheckpoints() {
        const container = document.getElementById('huntCheckpointsList');

        if (this.huntCheckpoints.length === 0) {
            container.innerHTML = '<p class="empty-checkpoint-state">No checkpoints added yet (minimum 3 required)</p>';
            return;
        }

        container.innerHTML = this.huntCheckpoints.map((cp, index) => {
            // Format challenge config for display
            let configSummary = '';

            if (cp.challenge_data) {
                switch (cp.challenge_type) {
                    case 'quiz':
                        if (cp.challenge_data.quiz_type === 'multiple_choice') {
                            configSummary = `
                            <div class="config-details">
                                <span class="config-label">Question:</span> ${cp.challenge_data.question}<br>
                                <span class="config-label">Options:</span> ${cp.challenge_data.options?.length || 0} choices<br>
                                <span class="config-label">Correct:</span> ${cp.challenge_data.options?.[cp.challenge_data.correct_answer] || 'N/A'}
                            </div>
                        `;
                        } else if (cp.challenge_data.quiz_type === 'true_false') {
                            configSummary = `
                            <div class="config-details">
                                <span class="config-label">Question:</span> ${cp.challenge_data.question}<br>
                                <span class="config-label">Answer:</span> ${cp.challenge_data.correct_answer === 0 ? 'True' : 'False'}
                            </div>
                        `;
                        } else if (cp.challenge_data.quiz_type === 'text') {
                            configSummary = `
                            <div class="config-details">
                                <span class="config-label">Question:</span> ${cp.challenge_data.question}<br>
                                <span class="config-label">Answer:</span> ${cp.challenge_data.correct_answer}
                            </div>
                        `;
                        }
                        break;

                    case 'geofence':
                        configSummary = `
                        <div class="config-details">
                            <span class="config-label">Radius:</span> ${cp.challenge_data.radius}m
                        </div>
                    `;
                        break;

                    case 'QR_scan':
                        configSummary = `
                        <div class="config-details">
                            <span class="config-label">QR Code:</span> ${cp.challenge_data.qr_code_value}
                        </div>
                    `;
                        break;

                    case 'photo':
                        configSummary = `
                        <div class="config-details">
                            <span class="config-label">Instructions:</span> ${cp.challenge_data.instructions || 'Take a photo'}<br>
                            <span class="config-label">Requires Approval:</span> ${cp.challenge_data.requires_approval ? 'Yes' : 'No'}
                        </div>
                    `;
                        break;
                }
            }

            return `
            <div class="checkpoint-item">
                <div class="checkpoint-item-content">
                    <strong>
                        ${cp.sequence_order}. ${cp.place_name}
                        <span class="checkpoint-challenge-badge">${this.getChallengeIcon(cp.challenge_type)} ${cp.challenge_type.toUpperCase()}</span>
                    </strong>
                    <small>Clue: ${cp.clue_text}</small>
                    ${cp.hint_text ? `<small>Hint: ${cp.hint_text}</small>` : ''}
                    <small>Points: ${cp.points_awarded} | ${cp.is_mandatory ? 'Mandatory' : 'Optional'}</small>
                    ${cp.time_limit_minutes ? `<small>Time Limit: ${cp.time_limit_minutes} minutes</small>` : ''}
                    ${configSummary}
                </div>
                <button type="button" class="checkpoint-remove-btn" onclick="app.removeCheckpoint(${index})">
                    Remove
                </button>
            </div>
        `;
        }).join('');
    }

    getChallengeIcon(type) {
        const icons = {
            'geofence': '📍',
            'QR': '📱',
            'photo': '📷',
            'quiz': '❓'
        };
        return icons[type] || '•';
    }

    async saveHunt(event) {
        event.preventDefault();

        if (this.huntCheckpoints.length < 3) {
            alert('A treasure hunt must have at least 3 checkpoints');
            return;
        }

        const startLocationId = document.getElementById('huntStartLocation').value;
        if (!startLocationId) {
            alert('Please select a starting location');
            return;
        }

        // Calculate total points from checkpoints
        const totalPoints = this.huntCheckpoints.reduce((sum, cp) => sum + cp.points_awarded, 0);

        const huntData = {
            title: document.getElementById('huntTitle').value,
            description: document.getElementById('huntDescription').value,
            difficulty_level: document.getElementById('huntDifficulty').value,
            estimated_duration: parseInt(document.getElementById('huntDuration').value) || null,
            distance_km: parseFloat(document.getElementById('huntDistance').value) || null,
            reward_points: totalPoints,
            starting_location_id: parseInt(startLocationId),
            ending_location_id: null,
            badge_id: null,
            checkpoints: this.huntCheckpoints.map(cp => ({
                place_id: cp.place_id,
                clue_text: cp.clue_text,
                hint_text: cp.hint_text,
                challenge_type: cp.challenge_type,
                challenge_data: cp.challenge_data,
                points_awarded: cp.points_awarded,
                time_limit_minutes: cp.time_limit_minutes,
                is_mandatory: cp.is_mandatory
            }))
        };

        // Debug: Log the data being sent
        console.log('=== HUNT CREATION DEBUG ===');
        console.log('Hunt Data:', JSON.stringify(huntData, null, 2));
        console.log('Checkpoints:', huntData.checkpoints);
        console.log('Challenge Data for each checkpoint:');
        huntData.checkpoints.forEach((cp, i) => {
            console.log(`Checkpoint ${i + 1}:`, cp.challenge_data);
        });

        try {
            const data = await this.apiRequest('/hunts/create', {
                method: 'POST',
                body: JSON.stringify(huntData)
            });

            console.log('API Response:', data);

            if (data && data.success) {
                alert('Treasure hunt created successfully! It will be reviewed by an admin before going live.');
                this.closeHuntCreationModal();
                this.loadAllHunts();
            } else {
                console.error('Hunt creation failed:', data);
                alert('Error: ' + (data?.message || data?.error || 'Failed to create hunt'));
            }
        } catch (error) {
            console.error('Error creating hunt:', error);
            alert('An error occurred while creating the hunt. Check console for details.');
        }
    }

    // Handle place selection change
    onCheckpointPlaceChange() {
        const placeSelect = document.getElementById('checkpointPlaceSelect');
        const selectedOption = placeSelect.options[placeSelect.selectedIndex];

        if (selectedOption.value) {
            const place = JSON.parse(selectedOption.dataset.place);
            // You can use this to auto-populate some fields if needed
            console.log('Selected place:', place);
        }
    }

    // Handle challenge type change
    onChallengeTypeChange() {
        const challengeType = document.getElementById('checkpointChallenge').value;
        const configContainer = document.getElementById('challengeConfig');

        // Reset challenge config
        this.currentChallengeConfig = null;

        switch (challengeType) {
            case 'geofence':
                configContainer.innerHTML = this.renderGeofenceConfig();
                break;
            case 'QR_scan':
                configContainer.innerHTML = this.renderQRConfig();
                break;
            case 'photo':
                configContainer.innerHTML = this.renderPhotoConfig();
                break;
            case 'quiz':
                configContainer.innerHTML = this.renderQuizConfig();
                break;
            default:
                configContainer.innerHTML = '';
        }
    }

    // Render Geofence Configuration
    renderGeofenceConfig() {
        return `
        <div class="challenge-config-section">
            <h4>📍 Geofence Configuration</h4>
            <div class="form-group">
                <label for="geofenceRadius">Verification Radius (meters)</label>
                <input type="number" id="geofenceRadius" class="form-control" 
                       value="50" min="10" max="500" 
                       placeholder="Distance from location to verify (default: 50m)">
                <small class="form-text">Users must be within this distance to verify the checkpoint</small>
            </div>
        </div>
    `;
    }

    // Render QR Code Configuration
    renderQRConfig() {
        return `
            <div class="challenge-config-section">
                <h4>📱 QR Code Configuration</h4>
                <div class="form-group">
                    <label for="qrCodeValue">QR Code Value *</label>
                    <input type="text" id="qrCodeValue" class="form-control" 
                        placeholder="Enter the QR code value that users must scan"
                        oninput="app.updateQRCodePreview()">
                    <small class="form-text">Users must scan a QR code with this exact value</small>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-secondary" onclick="app.generateQRCode()">
                        Generate Random QR Code
                    </button>
                </div>
                
                <!-- QR Code Preview and Download -->
                <div id="qrCodePreview" style="margin-top: 15px;"></div>
            </div>
        `;
    }

    // Render Photo Configuration
    renderPhotoConfig() {
        return `
        <div class="challenge-config-section">
            <h4>📷 Photo Challenge Configuration</h4>
            <div class="form-group">
                <label for="photoInstructions">Photo Instructions</label>
                <textarea id="photoInstructions" class="form-control" rows="2"
                          placeholder="What should users photograph? (e.g., 'Take a selfie with the statue')"></textarea>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="photoRequiresApproval" checked>
                    <span>Requires Admin Approval</span>
                </label>
                <small class="form-text">If checked, photos must be approved before points are awarded</small>
            </div>
        </div>
    `;
    }

    // Render Quiz Configuration
    renderQuizConfig() {
        return `
        <div class="challenge-config-section">
            <h4>❓ Quiz Configuration</h4>
            
            <div class="form-group">
                <label for="quizQuestion">Question *</label>
                <textarea id="quizQuestion" class="form-control" rows="2"
                          placeholder="Enter your question here..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="quizType">Question Type</label>
                <select id="quizType" class="form-control" onchange="app.onQuizTypeChange()">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                    <option value="text">Text Answer</option>
                </select>
            </div>
            
            <div id="quizOptionsContainer">
                ${this.renderMultipleChoiceOptions()}
            </div>
        </div>
    `;
    }

    // Handle quiz type change
    onQuizTypeChange() {
        const quizType = document.getElementById('quizType').value;
        const container = document.getElementById('quizOptionsContainer');

        switch (quizType) {
            case 'multiple_choice':
                container.innerHTML = this.renderMultipleChoiceOptions();
                break;
            case 'true_false':
                container.innerHTML = this.renderTrueFalseOptions();
                break;
            case 'text':
                container.innerHTML = this.renderTextAnswerOptions();
                break;
        }
    }

    // Render Multiple Choice Options
    renderMultipleChoiceOptions() {
        return `
        <div class="quiz-options-section">
            <label>Answer Options *</label>
            <div id="quizOptionsList">
                <div class="quiz-option-item">
                    <input type="radio" name="correctAnswer" value="0" checked>
                    <input type="text" class="form-control" id="quizOption0" placeholder="Option A (Correct)" required>
                </div>
                <div class="quiz-option-item">
                    <input type="radio" name="correctAnswer" value="1">
                    <input type="text" class="form-control" id="quizOption1" placeholder="Option B" required>
                </div>
                <div class="quiz-option-item">
                    <input type="radio" name="correctAnswer" value="2">
                    <input type="text" class="form-control" id="quizOption2" placeholder="Option C" required>
                </div>
                <div class="quiz-option-item">
                    <input type="radio" name="correctAnswer" value="3">
                    <input type="text" class="form-control" id="quizOption3" placeholder="Option D" required>
                </div>
            </div>
            <small class="form-text">Select the radio button next to the correct answer</small>
            <button type="button" class="btn btn-secondary btn-sm" onclick="app.addQuizOption()" style="margin-top: 10px;">
                + Add Another Option
            </button>
        </div>
    `;
    }

    // Render True/False Options
    renderTrueFalseOptions() {
        return `
        <div class="quiz-options-section">
            <label>Correct Answer *</label>
            <div style="display: flex; gap: 15px; margin: 10px 0;">
                <label class="quiz-tf-option">
                    <input type="radio" name="correctAnswer" value="true" checked>
                    <span>True</span>
                </label>
                <label class="quiz-tf-option">
                    <input type="radio" name="correctAnswer" value="false">
                    <span>False</span>
                </label>
            </div>
        </div>
    `;
    }

    // Render Text Answer Options
    renderTextAnswerOptions() {
        return `
        <div class="quiz-options-section">
            <div class="form-group">
                <label for="quizTextAnswer">Correct Answer *</label>
                <input type="text" id="quizTextAnswer" class="form-control" 
                       placeholder="Enter the correct answer (case-insensitive)">
                <small class="form-text">User's answer will be compared case-insensitively</small>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="quizExactMatch">
                    <span>Require Exact Match</span>
                </label>
                <small class="form-text">If unchecked, answers containing the correct text will be accepted</small>
            </div>
        </div>
    `;
    }

    // Add quiz option dynamically
    addQuizOption() {
        const optionsList = document.getElementById('quizOptionsList');
        const currentOptions = optionsList.querySelectorAll('.quiz-option-item').length;

        if (currentOptions >= 6) {
            alert('Maximum 6 options allowed');
            return;
        }

        const optionItem = document.createElement('div');
        optionItem.className = 'quiz-option-item';
        optionItem.innerHTML = `
        <input type="radio" name="correctAnswer" value="${currentOptions}">
        <input type="text" class="form-control" id="quizOption${currentOptions}" 
               placeholder="Option ${String.fromCharCode(65 + currentOptions)}" required>
        <button type="button" class="btn-remove-option" onclick="this.parentElement.remove()">×</button>
    `;

        optionsList.appendChild(optionItem);
    }

    // Generate random QR code value
    generateQRCode() {
        const randomValue = 'QR-' + Math.random().toString(36).substring(2, 15).toUpperCase();
        document.getElementById('qrCodeValue').value = randomValue;
        this.updateQRCodePreview();
    }

    // Update QR code preview when value changes
    updateQRCodePreview() {
        const qrValue = document.getElementById('qrCodeValue')?.value;
        const previewDiv = document.getElementById('qrCodePreview');

        if (!qrValue || !qrValue.trim()) {
            previewDiv.innerHTML = '';
            return;
        }

        previewDiv.innerHTML = `
            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center; border: 2px solid #667eea;">
                <strong style="color: #667eea;">QR Code Preview</strong>
                <div id="qrCodeContainer" style="margin: 15px auto; display: flex; justify-content: center;"></div>
                <div style="margin-top: 10px;">
                    <strong>Value:</strong> ${qrValue}<br>
                    <small style="color: #666;">Users will scan this QR code at the checkpoint</small>
                </div>
                <button type="button" class="btn btn-primary" onclick="app.downloadQRCodeLocal()">
                    📥 Download QR Code
                </button>
            </div>
        `;

        // Generate QR code
        setTimeout(() => {
            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.innerHTML = ''; // Clear previous QR code

            new QRCode(qrContainer, {
                text: qrValue.trim(),
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }, 100);
    }

    // Download QR code image (local generation)
    downloadQRCodeLocal() {
        const qrValue = document.getElementById('qrCodeValue')?.value;
        if (!qrValue) {
            alert('Please enter a QR code value first');
            return;
        }

        // Create a temporary container
        const tempContainer = document.createElement('div');
        tempContainer.style.display = 'none';
        document.body.appendChild(tempContainer);

        // Generate high-quality QR code
        new QRCode(tempContainer, {
            text: qrValue.trim(),
            width: 400,
            height: 400,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Wait for QR code to be generated
        setTimeout(() => {
            const canvas = tempContainer.querySelector('canvas');
            if (canvas) {
                // Convert to blob and download
                canvas.toBlob(function (blob) {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `qr-code-${qrValue}.png`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);

                    // Clean up
                    document.body.removeChild(tempContainer);

                    alert('QR Code downloaded successfully!');
                });
            }
        }, 100);
    }

    // Collect challenge configuration data
    collectChallengeConfig(challengeType) {
        let config = {};

        switch (challengeType) {
            case 'geofence':
                const radiusInput = document.getElementById('geofenceRadius');
                config = {
                    radius: radiusInput ? parseInt(radiusInput.value) || 50 : 50
                };
                break;
            case 'QR_scan':
                const qrValue = document.getElementById('qrCodeValue')?.value;
                if (!qrValue || !qrValue.trim()) {
                    alert('Please enter a QR code value');
                    return null;
                }
                config = {
                    qr_code_value: qrValue.trim()
                };
                break;

            case 'photo':
                const photoInstructions = document.getElementById('photoInstructions');
                const photoApproval = document.getElementById('photoRequiresApproval');
                config = {
                    instructions: photoInstructions ? photoInstructions.value : '',
                    requires_approval: photoApproval ? photoApproval.checked : true
                };
                break;

            case 'quiz':
                const quizType = document.getElementById('quizType')?.value;
                const question = document.getElementById('quizQuestion')?.value;

                if (!question || !question.trim()) {
                    alert('Please enter a quiz question');
                    return null;
                }

                config = {
                    question: question.trim(),
                    quiz_type: quizType || 'multiple_choice'
                };

                if (quizType === 'multiple_choice') {
                    const options = [];
                    const optionInputs = document.querySelectorAll('[id^="quizOption"]');

                    optionInputs.forEach(input => {
                        // Check if input exists and has a value
                        if (input && input.value && input.value.trim()) {
                            options.push(input.value.trim());
                        }
                    });

                    if (options.length < 2) {
                        alert('Please provide at least 2 options for the quiz');
                        return null;
                    }

                    const correctAnswerRadio = document.querySelector('input[name="correctAnswer"]:checked');
                    if (!correctAnswerRadio) {
                        alert('Please select the correct answer');
                        return null;
                    }

                    const correctAnswer = parseInt(correctAnswerRadio.value);

                    // Validate that the correct answer index is within the options array
                    if (correctAnswer >= options.length) {
                        alert('Invalid correct answer selection');
                        return null;
                    }

                    config.options = options;
                    config.correct_answer = correctAnswer;

                } else if (quizType === 'true_false') {
                    const correctAnswerRadio = document.querySelector('input[name="correctAnswer"]:checked');
                    if (!correctAnswerRadio) {
                        alert('Please select the correct answer (True or False)');
                        return null;
                    }

                    const correctAnswer = correctAnswerRadio.value === 'true';
                    config.options = ['True', 'False'];
                    config.correct_answer = correctAnswer ? 0 : 1;

                } else if (quizType === 'text') {
                    const textAnswer = document.getElementById('quizTextAnswer')?.value;
                    if (!textAnswer || !textAnswer.trim()) {
                        alert('Please enter the correct answer');
                        return null;
                    }

                    const exactMatch = document.getElementById('quizExactMatch');
                    config.correct_answer = textAnswer.trim();
                    config.exact_match = exactMatch ? exactMatch.checked : false;
                }
                break;

            default:
                // For unknown types, return empty config
                config = {};
        }

        return config;
    }

    closeHuntCreationModal() {
        document.getElementById('huntModal').classList.remove('active');
        this.huntCheckpoints = [];
    }

    initMap() {
        const mapElement = document.getElementById('map');

        // Default to Mauritius center
        const defaultLat = -20.1609;
        const defaultLon = 57.5012;

        this.map = L.map('map').setView([defaultLat, defaultLon], 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);

        // Add user location if available
        if (this.currentLocation) {
            L.marker([this.currentLocation.lat, this.currentLocation.lon])
                .addTo(this.map)
                .bindPopup('You are here')
                .openPopup();
        }
    }

    async loadNearbyPlaces() {
        const container = document.getElementById('nearby-places');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const params = this.currentLocation
            ? `?lat=${this.currentLocation.lat}&lon=${this.currentLocation.lon}&radius=50`
            : '';

        const data = await this.apiRequest(`/places/search${params}`);

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(place => `
            <div class="place-card" onclick="app.showPlaceDetail(${place.place_id})">
                <div class="place-card-image">
                    ${place.image_url
                    ? `<img src="${place.image_url}" alt="${place.name}">`
                    : `<div class="place-placeholder">${this.getPlaceIcon(place.place_type)}</div>`
                }
                    <div class="place-type-badge">${place.place_type}</div>
                </div>
                <div class="place-card-content">
                    <h3 class="place-name">${place.name}</h3>
                    <p class="place-description">${this.truncateText(place.description || 'No description available', 100)}</p>
                    <div class="place-meta">
                        <span class="place-location">📍 ${place.city || 'N/A'}</span>
                        ${place.distance ? `<span class="place-distance">🚶 ${place.distance.toFixed(1)} km away</span>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

            // Add markers to map
            if (this.map) {
                data.data.forEach(place => {
                    L.marker([place.latitude, place.longitude])
                        .addTo(this.map)
                        .bindPopup(`<strong>${place.name}</strong><br>${place.place_type}`)
                        .on('click', () => {
                            this.showPlaceDetail(place.place_id);
                        });
                });
            }
        } else {
            container.innerHTML = '<div class="empty-state"><p>No places found nearby</p></div>';
        }
    }

    // Get icon for place type
    getPlaceIcon(placeType) {
        const icons = {
            'landmark': '🏛️',
            'park': '🌳',
            'museum': '🏛️',
            'restaurant': '🍽️',
            'cafe': '☕',
            'beach': '🏖️',
            'mountain': '⛰️',
            'historical': '🏰',
            'temple': '⛩️',
            'church': '⛪',
            'market': '🏪',
            'viewpoint': '👁️',
            'nature': '🌿',
            'urban': '🏙️',
            'adventure': '🎒',
            'cultural': '🎭',
            'entertainment': '🎪',
            'shopping': '🛍️'
        };
        return icons[placeType.toLowerCase()] || '📍';
    }

    // Truncate text
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    // Show place detail modal
    async showPlaceDetail(placeId) {
        const modal = document.getElementById('place-detail-modal');
        const content = document.getElementById('place-detail-content');

        // Show modal with loading state
        content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        modal.classList.add('active');

        // Fetch place details
        const data = await this.apiRequest(`/places/${placeId}`);

        if (data && data.success) {
            const place = data.data;

            // Calculate distance if user location is available
            let distanceInfo = '';
            if (this.currentLocation) {
                const distance = this.calculateDistance(
                    this.currentLocation.lat,
                    this.currentLocation.lon,
                    place.latitude,
                    place.longitude
                );
                distanceInfo = `
                <div class="detail-distance">
                    <span class="distance-icon">🚶</span>
                    <span class="distance-text">${distance.toFixed(2)} km away</span>
                </div>
            `;
            }

            content.innerHTML = `
            <div class="place-detail-header">
                ${place.image_url
                    ? `<img src="${place.image_url}" alt="${place.name}" class="place-detail-image">`
                    : `<div class="place-detail-placeholder">${this.getPlaceIcon(place.place_type)}</div>`
                }
                <button class="modal-close-btn" onclick="app.closePlaceDetailModal()">×</button>
                
                <!-- Upload Photo Button -->
                ${!place.image_url ? `
                    <button class="upload-photo-btn" onclick="app.showPhotoUploadForm(${place.place_id})">
                        📷 Add Photo
                    </button>
                ` : ''}
            </div>
            
            <div class="place-detail-body">
                <h2 class="place-detail-title">${place.name}</h2>
                
                <div class="place-detail-meta">
                    <span class="place-type-tag">${place.place_type}</span>
                    ${place.city ? `<span class="place-location-tag">📍 ${place.city}</span>` : ''}
                </div>
                
                ${distanceInfo}
                
                <div class="place-detail-section">
                    <h3>📝 Description</h3>
                    <p>${place.description || 'No description available for this place.'}</p>
                </div>
                
                ${place.address ? `
                    <div class="place-detail-section">
                        <h3>📍 Address</h3>
                        <p>${place.address}</p>
                    </div>
                ` : ''}
                
                <div class="place-detail-section">
                    <h3>📊 Statistics</h3>
                    <div class="place-stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-info">
                                <div class="stat-value">${place.used_in_hunts || 0}</div>
                                <div class="stat-label">Used in Hunts</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">👥</div>
                            <div class="stat-info">
                                <div class="stat-value">${place.visit_count || 0}</div>
                                <div class="stat-label">Visits</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="place-detail-section">
                    <h3>🗺️ Location</h3>
                    <div id="place-detail-map" style="height: 250px; border-radius: 8px; margin-top: 10px;"></div>
                </div>
                
                <div class="place-detail-actions">
                    <button class="btn btn-primary" onclick="app.getDirections(${place.latitude}, ${place.longitude})">
                        🧭 Get Directions
                    </button>
                    ${this.userInfo.user_type === 'creator' || this.userInfo.user_type === 'admin' ? `
                        <button class="btn btn-secondary" onclick="app.editPlace(${place.place_id})">
                            ✏️ Edit Place
                        </button>
                    ` : ''}
                </div>
            </div>
        `;

            // Initialize map for this place
            setTimeout(() => {
                this.initPlaceDetailMap(place.latitude, place.longitude, place.name);
            }, 100);
        } else {
            content.innerHTML = `
            <div class="error-state">
                <p>Failed to load place details</p>
                <button class="btn btn-secondary" onclick="app.closePlaceDetailModal()">Close</button>
            </div>
        `;
        }
    }

    // Close place detail modal
    closePlaceDetailModal() {
        const modal = document.getElementById('place-detail-modal');
        modal.classList.remove('active');

        // Clean up map if it exists
        if (this.placeDetailMap) {
            this.placeDetailMap.remove();
            this.placeDetailMap = null;
        }
    }

    // Initialize map for place detail
    initPlaceDetailMap(lat, lon, placeName) {
        const mapElement = document.getElementById('place-detail-map');
        if (!mapElement) return;

        // Clean up existing map
        if (this.placeDetailMap) {
            this.placeDetailMap.remove();
        }

        // Create new map
        this.placeDetailMap = L.map('place-detail-map').setView([lat, lon], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.placeDetailMap);

        // Add place marker
        L.marker([lat, lon])
            .addTo(this.placeDetailMap)
            .bindPopup(`<strong>${placeName}</strong>`)
            .openPopup();

        // Add user location if available
        if (this.currentLocation) {
            L.marker([this.currentLocation.lat, this.currentLocation.lon], {
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                })
            })
                .addTo(this.placeDetailMap)
                .bindPopup('You are here');

            // Draw line between user and place
            L.polyline([
                [this.currentLocation.lat, this.currentLocation.lon],
                [lat, lon]
            ], {
                color: '#667eea',
                weight: 3,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(this.placeDetailMap);
        }
    }

    // Get directions to place
    getDirections(lat, lon) {
        if (this.currentLocation) {
            // Open Google Maps with directions
            const url = `https://www.google.com/maps/dir/${this.currentLocation.lat},${this.currentLocation.lon}/${lat},${lon}`;
            window.open(url, '_blank');
        } else {
            // Just open the location
            const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lon}`;
            window.open(url, '_blank');
        }
    }

    // Show photo upload form
    showPhotoUploadForm(placeId) {
        const modal = document.getElementById('photo-upload-modal');
        const form = document.getElementById('photo-upload-form');

        // Reset form
        form.reset();
        document.getElementById('upload-place-id').value = placeId;
        document.getElementById('photo-preview').innerHTML = '';

        modal.classList.add('active');
    }

    // Close photo upload modal
    closePhotoUploadModal() {
        document.getElementById('photo-upload-modal').classList.remove('active');
    }

    // Handle photo file selection
    handlePhotoSelect(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('photo-preview');

        if (!file) {
            preview.innerHTML = '';
            return;
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, or WebP)');
            event.target.value = '';
            preview.innerHTML = '';
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5242880) {
            alert('File size must be less than 5MB');
            event.target.value = '';
            preview.innerHTML = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML = `
            <div class="photo-preview-container">
                <img src="${e.target.result}" alt="Preview">
                <p class="photo-info">
                    <strong>${file.name}</strong><br>
                    Size: ${(file.size / 1024 / 1024).toFixed(2)} MB
                </p>
            </div>
        `;
        };
        reader.readAsDataURL(file);
    }

    // Upload place photo
    async uploadPlacePhoto(event) {
        event.preventDefault();

        const placeId = document.getElementById('upload-place-id').value;
        const fileInput = document.getElementById('place-photo-input');
        const file = fileInput.files[0];

        if (!file) {
            alert('Please select a photo');
            return;
        }

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        try {
            // Create FormData
            const formData = new FormData();
            formData.append('photo', file);
            formData.append('place_id', placeId);

            // Upload
            const response = await fetch(`${API_URL}/place-photos/upload`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('Photo uploaded successfully! It will be visible after admin approval.');
                this.closePhotoUploadModal();
                this.closePlaceDetailModal();
            } else {
                alert('Upload failed: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Failed to upload photo. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    // Edit place (for creators/admins)
    editPlace(placeId) {
        this.closePlaceDetailModal();
        // Implement edit functionality - can use your existing showCreatePlaceModal
        this.loadPlaceForEdit(placeId);
    }

    // Load place data for editing
    async loadPlaceForEdit(placeId) {
        const data = await this.apiRequest(`/places/${placeId}`);

        if (data && data.success) {
            const place = data.data;

            // Show place modal in edit mode
            document.getElementById('placeModalTitle').textContent = 'Edit Place';
            document.getElementById('placeId').value = place.place_id;
            document.getElementById('placeName').value = place.name;
            document.getElementById('placeDescription').value = place.description || '';
            document.getElementById('latitude').value = place.latitude;
            document.getElementById('longitude').value = place.longitude;
            document.getElementById('city').value = place.city || '';
            document.getElementById('placeType').value = place.place_type;

            document.getElementById('placeModal').classList.add('active');
        }
    }

    // MY HUNTS PAGE
    async loadMyHunts() {
        await this.loadActiveHunts();
        await this.loadCompletedHunts();
    }

    async loadActiveHunts() {
        const container = document.getElementById('active-hunts');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/progress/my-hunts?status=in_progress');

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(progress => `
                <div class="card" style="padding: 15px; margin: 10px 0;">
                    <h3>${progress.title}</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress.completion_percentage}%"></div>
                    </div>
                    <p style="margin: 10px 0; font-size: 14px;">
                        ${progress.completion_percentage}% Complete | 
                        ${progress.points_earned} points earned
                    </p>
                    <button class="btn btn-primary" onclick="app.continueHunt(${progress.progress_id})">
                        Continue Hunt
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><p>No active hunts. Start one from the Home page!</p></div>';
        }
    }

    async loadCompletedHunts() {
        const container = document.getElementById('completed-hunts');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/progress/my-hunts?status=completed');

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(progress => `
                <div class="card" style="padding: 15px; margin: 10px 0;">
                    <h3>✅ ${progress.title}</h3>
                    <p style="font-size: 14px; color: #777;">
                        Completed on ${new Date(progress.completed_at).toLocaleDateString()}<br>
                        Points earned: ${progress.points_earned}
                    </p>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><p>No completed hunts yet</p></div>';
        }
    }

    async continueHunt(progressId) {
        // Load detailed hunt progress
        const data = await this.apiRequest(`/progress/${progressId}`);

        if (data && data.success) {
            this.activeHuntProgress = data.data;
            this.showCheckpointUI();
        } else {
            alert('Failed to load hunt progress');
        }
    }

    showCheckpointUI() {
        // Switch to checkpoint view page
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.getElementById('checkpoint-page').classList.add('active');

        // Update nav
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));

        this.loadCheckpointDetails();
    }

    async loadCheckpointDetails() {
        if (!this.activeHuntProgress) return;

        const container = document.getElementById('checkpoint-container');
        const progress = this.activeHuntProgress;

        // Find current checkpoint (first incomplete one)
        this.currentCheckpoint = progress.checkpoints.find(cp => cp.status === 'pending' || cp.status === 'in_progress');

        if (!this.currentCheckpoint) {
            // All checkpoints completed
            this.showHuntCompletion();
            return;
        }

        // Render checkpoint UI
        container.innerHTML = `
        <div class="checkpoint-header">
            <button class="btn btn-secondary" onclick="app.exitCheckpointUI()">
                ← Back to My Hunts
            </button>
            <h2>${progress.hunt_title}</h2>
        </div>
        
        <!-- Progress Bar -->
        <div class="checkpoint-progress-section">
            <div class="progress-info">
                <span>Checkpoint ${this.currentCheckpoint.sequence_order} of ${progress.checkpoints.length}</span>
                <span>${progress.completion_percentage}% Complete</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${progress.completion_percentage}%"></div>
            </div>
            <div class="points-info">
                <span>🏆 ${progress.points_earned} / ${progress.total_points} points</span>
            </div>
        </div>
        
        <!-- Current Checkpoint -->
        <div class="checkpoint-card">
            <div class="checkpoint-badge">
                <div class="checkpoint-number">${this.currentCheckpoint.sequence_order}</div>
            </div>
            
            <h3>${this.currentCheckpoint.place_name}</h3>
            
            <div class="checkpoint-clue-section">
                <h4>🔍 Clue</h4>
                <p class="clue-text">${this.currentCheckpoint.clue_text}</p>
            </div>
            
            ${this.currentCheckpoint.hint_text ? `
                <div class="checkpoint-hint-section" id="hintSection">
                    <button class="btn btn-secondary btn-block" onclick="app.revealHint()">
                        💡 Show Hint (costs 2 points)
                    </button>
                    <div id="hintContent" style="display: none; margin-top: 10px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                        <p><strong>Hint:</strong> ${this.currentCheckpoint.hint_text}</p>
                    </div>
                </div>
            ` : ''}
            
            <div class="checkpoint-info">
                <div class="info-item">
                    <span class="info-label">Challenge Type:</span>
                    <span class="info-value">${this.formatChallengeType(this.currentCheckpoint.challenge_type)}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Points:</span>
                    <span class="info-value">🏆 ${this.currentCheckpoint.points_awarded}</span>
                </div>
                ${this.currentCheckpoint.time_limit_minutes ? `
                    <div class="info-item">
                        <span class="info-label">Time Limit:</span>
                        <span class="info-value">⏱️ ${this.currentCheckpoint.time_limit_minutes} minutes</span>
                    </div>
                ` : ''}
                ${this.currentCheckpoint.is_mandatory ? `
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="badge-mandatory">Mandatory</span>
                    </div>
                ` : ''}
            </div>
            
            <!-- Distance Indicator -->
            <div id="distanceIndicator" class="distance-indicator">
                <div class="spinner"></div>
                <span>Calculating distance...</span>
            </div>
            
            <!-- Map -->
            <div id="checkpoint-map" style="height: 300px; border-radius: 8px; margin: 20px 0;"></div>
            
            <!-- Verification Section -->
            <div class="verification-section">
                ${this.renderVerificationUI(this.currentCheckpoint.challenge_type)}
            </div>
        </div>
        
        <!-- Completed Checkpoints -->
        ${this.renderCompletedCheckpoints()}
    `;

        // Initialize map for this checkpoint
        this.initCheckpointMap();

        // Start distance tracking
        this.trackDistanceToCheckpoint();
    }

    formatChallengeType(type) {
        const types = {
            'geofence': '📍 Location Check',
            'QR': '📱 QR Code Scan',
            'photo': '📷 Photo Upload',
            'quiz': '❓ Quiz Question'
        };
        return types[type] || type;
    }

    renderVerificationUI(challengeType) {
        switch (challengeType) {
            case 'geofence':
                // Parse challenge_data for geofence
                let geofenceData = this.currentCheckpoint.challenge_data;
                if (typeof geofenceData === 'string') {
                    try {
                        geofenceData = JSON.parse(geofenceData);
                    } catch (e) {
                        console.error('Error parsing geofence challenge_data:', e);
                        geofenceData = {};
                    }
                }

                return `
                    <h4>Verify Your Location</h4>
                    <p>Get within ${geofenceData?.radius || 50} meters of the location to complete this checkpoint.</p>
                    <button class="btn btn-primary btn-block" onclick="app.verifyLocation()" id="verifyLocationBtn">
                        📍 Check My Location
                    </button>
                    <div id="locationStatus" style="margin-top: 10px;"></div>
                `;
            case 'QR_scan':
                // Parse challenge_data for QR
                let qrChallengeData = this.currentCheckpoint.challenge_data;

                console.log('QR Challenge - Raw challenge_data:', qrChallengeData);
                console.log('QR Challenge - Type:', typeof qrChallengeData);

                if (typeof qrChallengeData === 'string') {
                    try {
                        qrChallengeData = JSON.parse(qrChallengeData);
                        console.log('QR Challenge - Parsed challenge_data:', qrChallengeData);
                    } catch (e) {
                        console.error('Error parsing QR challenge_data:', e);
                        qrChallengeData = {};
                    }
                }

                if (!qrChallengeData) {
                    qrChallengeData = {};
                }

                console.log('QR Challenge - Expected QR code:', qrChallengeData?.qr_code_value);

                return `
                    <h4>Scan QR Code</h4>
                    <p>Find and scan the QR code at this location.</p>
                    
                    <!-- Camera Scanner -->
                    <div id="qrScannerSection" style="margin: 15px 0;">
                        <button class="btn btn-primary btn-block" onclick="app.startQRScanner()" id="startScanBtn">
                            📷 Open Camera Scanner
                        </button>
                        
                        <!-- Scanner container -->
                        <div id="qrVideoContainer" style="display: none; margin-top: 15px;">
                            <div id="qrReader" style="width: 100%; max-width: 500px; margin: 0 auto; border: 2px solid #667eea; border-radius: 8px;"></div>
                            <div style="margin-top: 10px; text-align: center;">
                                <button class="btn btn-secondary" onclick="app.stopQRScanner()">
                                    🛑 Stop Scanner
                                </button>
                            </div>
                            <div id="scanStatus" style="margin-top: 10px; padding: 10px; background: #f0f4ff; border-radius: 5px; text-align: center;">
                                <span>📸 Point camera at QR code...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Input Option -->
                    <div style="margin: 20px 0;">
                        <p style="text-align: center; color: #666; font-size: 14px;">— OR —</p>
                    </div>
                    
                    <input type="text" id="qrCodeInput" class="form-control" placeholder="Enter QR code manually" style="margin: 10px 0;">
                    <button class="btn btn-secondary btn-block" onclick="app.submitQRCode()">
                        ✓ Verify Manually Entered Code
                    </button>
                    
                    <div id="qrStatus" style="margin-top: 10px;"></div>
                `;
            case 'photo':
                // Parse challenge_data for photo
                let photoData = this.currentCheckpoint.challenge_data;
                if (typeof photoData === 'string') {
                    try {
                        photoData = JSON.parse(photoData);
                    } catch (e) {
                        console.error('Error parsing photo challenge_data:', e);
                        photoData = {};
                    }
                }

                const photoInstructions = photoData?.instructions || 'Take a photo at this location';

                return `
                    <h4>Upload Photo</h4>
                    <p>${photoInstructions}</p>
                    <input type="file" id="photoInput" accept="image/*" capture="environment" style="display: none;" onchange="app.handlePhotoUpload(event)">
                    <button class="btn btn-primary btn-block" onclick="document.getElementById('photoInput').click()">
                        📷 Take Photo
                    </button>
                    <div id="photoPreview" style="margin-top: 15px;"></div>
                `;

            case 'quiz':
                // Parse challenge_data if it's a string
                let challengeData = this.currentCheckpoint.challenge_data;

                console.log('Quiz Checkpoint - Raw challenge_data:', challengeData);
                console.log('Quiz Checkpoint - Type of challenge_data:', typeof challengeData);

                if (typeof challengeData === 'string') {
                    try {
                        challengeData = JSON.parse(challengeData);
                        console.log('Quiz Checkpoint - Parsed challenge_data:', challengeData);
                    } catch (e) {
                        console.error('Error parsing challenge_data:', e);
                        challengeData = {};
                    }
                }

                console.log('Quiz Checkpoint - Final challenge_data:', challengeData);
                console.log('Quiz Checkpoint - Quiz type:', challengeData?.quiz_type);

                return `
                    <h4>Answer the Question</h4>
                    <div id="quizQuestion">
                        <p style="font-size: 16px; font-weight: 500; margin-bottom: 15px;">
                            <strong>Question:</strong> ${challengeData?.question || 'What is special about this place?'}
                        </p>
                        ${this.renderQuizOptions(challengeData)}
                    </div>
                `;

            default:
                return '<p>Unknown challenge type</p>';
        }
    }

    // Start QR Code Scanner
    async startQRScanner() {
        const videoContainer = document.getElementById('qrVideoContainer');
        const startBtn = document.getElementById('startScanBtn');
        const scanStatus = document.getElementById('scanStatus');

        console.log('Starting QR scanner...');

        try {
            // Show video container, hide start button
            videoContainer.style.display = 'block';
            startBtn.style.display = 'none';

            scanStatus.innerHTML = '<span>🔄 Initializing camera...</span>';

            // Check if Html5Qrcode is available
            if (typeof Html5Qrcode === 'undefined') {
                throw new Error('QR Scanner library not loaded. Please refresh the page.');
            }

            // Initialize Html5Qrcode scanner
            const html5QrCode = new Html5Qrcode("qrReader");
            this.qrScanner = html5QrCode;

            console.log('QR Scanner initialized');

            // Configuration for scanning
            const config = {
                fps: 10,    // Scans per second
                qrbox: { width: 250, height: 250 },  // Scanning box size
                aspectRatio: 1.0
            };

            // Start scanning with back camera
            await html5QrCode.start(
                { facingMode: "environment" }, // Use back camera on mobile
                config,
                (decodedText, decodedResult) => {
                    // Success callback - QR code detected!
                    console.log('QR Code scanned successfully:', decodedText);

                    scanStatus.innerHTML = `
                        <span style="color: #27ae60; font-weight: 600;">✅ QR Code Detected!</span><br>
                        <small>${decodedText}</small>
                    `;

                    // Stop scanning
                    this.stopQRScanner();

                    // Verify the code
                    this.verifyScannedQRCode(decodedText);
                },
                (errorMessage) => {
                    // Error callback - scanning failed (this is normal, it tries continuously)
                    // Don't log every failed scan attempt as it clutters the console
                    // console.debug('Scan attempt:', errorMessage);
                }
            );

            console.log('Scanner started successfully');
            scanStatus.innerHTML = '<span style="color: #667eea; font-weight: 600;">📸 Point camera at QR code...</span>';

        } catch (error) {
            console.error('Scanner error:', error);

            let errorMessage = 'Unable to access camera. ';

            if (error.name === 'NotAllowedError') {
                errorMessage += 'Camera permission denied. Please allow camera access and try again.';
            } else if (error.name === 'NotFoundError') {
                errorMessage += 'No camera found on this device.';
            } else if (error.name === 'NotReadableError') {
                errorMessage += 'Camera is already in use by another application.';
            } else {
                errorMessage += error.message || 'Please try entering the code manually.';
            }

            alert(errorMessage);

            // Reset UI
            if (videoContainer) videoContainer.style.display = 'none';
            if (startBtn) startBtn.style.display = 'block';
            if (scanStatus) scanStatus.innerHTML = '<span>📸 Ready to scan</span>';
        }
    }

    // Stop QR Scanner
    async stopQRScanner() {
        console.log('Stopping QR scanner...');

        const videoContainer = document.getElementById('qrVideoContainer');
        const startBtn = document.getElementById('startScanBtn');

        if (this.qrScanner) {
            try {
                console.log('Stopping scanner instance...');
                await this.qrScanner.stop();
                console.log('Scanner stopped');

                // Clear the scanner
                this.qrScanner.clear();
                this.qrScanner = null;

                console.log('Scanner cleaned up');
            } catch (error) {
                console.error('Error stopping scanner:', error);
                // Even if there's an error, clear the reference
                this.qrScanner = null;
            }
        }

        // Hide video container, show start button
        if (videoContainer) {
            videoContainer.style.display = 'none';
        }
        if (startBtn) {
            startBtn.style.display = 'block';
        }
    }

    // Verify scanned QR code
    async verifyScannedQRCode(scannedValue) {
        console.log('Verifying scanned QR code:', scannedValue);

        const statusDiv = document.getElementById('qrStatus');
        statusDiv.innerHTML = '<div class="alert alert-info">Verifying QR code...</div>';

        // Parse challenge_data to get the expected QR code value
        let challengeData = this.currentCheckpoint.challenge_data;

        if (typeof challengeData === 'string') {
            try {
                challengeData = JSON.parse(challengeData);
            } catch (e) {
                console.error('Error parsing challenge_data:', e);
                statusDiv.innerHTML = '<div class="alert alert-error">❌ Error loading QR code data</div>';
                return;
            }
        }

        const expectedQRCode = challengeData?.qr_code_value;

        console.log('Expected QR code:', expectedQRCode);
        console.log('Scanned QR code:', scannedValue);

        // Verify the QR code matches (trim both to avoid whitespace issues)
        if (scannedValue.trim() === expectedQRCode.trim()) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ QR Code verified! Completing checkpoint...</div>';
            await this.completeCheckpoint('QR', {
                qr_code: scannedValue,
                verified: true,
                method: 'camera_scan'
            });
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-error">
                    ❌ Invalid QR code.<br>
                    <small style="font-size: 11px;">Expected: ${expectedQRCode}</small><br>
                    <small style="font-size: 11px;">Scanned: ${scannedValue}</small><br>
                    <small style="font-size: 11px; color: #999;">Please scan the correct QR code or try manual entry.</small>
                </div>
            `;
        }
    }

    // Verify scanned QR code
    async verifyScannedQRCode(scannedValue) {
        const statusDiv = document.getElementById('qrStatus');
        statusDiv.innerHTML = '<div class="alert alert-info">Verifying QR code...</div>';

        // Parse challenge_data to get the expected QR code value
        let challengeData = this.currentCheckpoint.challenge_data;

        if (typeof challengeData === 'string') {
            try {
                challengeData = JSON.parse(challengeData);
            } catch (e) {
                console.error('Error parsing challenge_data:', e);
                statusDiv.innerHTML = '<div class="alert alert-error">❌ Error loading QR code data</div>';
                return;
            }
        }

        const expectedQRCode = challengeData?.qr_code_value;

        console.log('Scanned QR code:', scannedValue);
        console.log('Expected QR code:', expectedQRCode);

        // Verify the QR code matches
        if (scannedValue === expectedQRCode) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ QR Code verified! Completing checkpoint...</div>';
            await this.completeCheckpoint('QR', {
                qr_code: scannedValue,
                verified: true,
                method: 'camera_scan'
            });
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-error">
                    ❌ Invalid QR code.<br>
                    <small>Expected: ${expectedQRCode}</small><br>
                    <small>Scanned: ${scannedValue}</small>
                </div>
            `;
        }
    }

    renderQuizOptions(challengeData) {
        if (!challengeData) {
            challengeData = {};
        }

        const quizType = challengeData.quiz_type || 'multiple_choice';

        if (quizType === 'multiple_choice') {
            const options = challengeData.options || ['Option A', 'Option B', 'Option C', 'Option D'];

            return `
            <div style="margin: 15px 0;">
                ${options.map((option, index) => `
                    <label class="quiz-option">
                        <input type="radio" name="quizAnswer" value="${index}">
                        <span>${option}</span>
                    </label>
                `).join('')}
            </div>
            <button class="btn btn-primary btn-block" onclick="app.submitQuizAnswer()">
                Submit Answer
            </button>
            <div id="quizResult" style="margin-top: 10px;"></div>
        `;
        } else if (quizType === 'true_false') {
            return `
            <div style="margin: 15px 0;">
                <label class="quiz-option">
                    <input type="radio" name="quizAnswer" value="0">
                    <span>True</span>
                </label>
                <label class="quiz-option">
                    <input type="radio" name="quizAnswer" value="1">
                    <span>False</span>
                </label>
            </div>
            <button class="btn btn-primary btn-block" onclick="app.submitQuizAnswer()">
                Submit Answer
            </button>
            <div id="quizResult" style="margin-top: 10px;"></div>
        `;
        } else if (quizType === 'text') {
            return `
            <div style="margin: 15px 0;">
                <input type="text" id="quizTextInput" class="form-control" placeholder="Enter your answer">
            </div>
            <button class="btn btn-primary btn-block" onclick="app.submitQuizTextAnswer()">
                Submit Answer
            </button>
            <div id="quizResult" style="margin-top: 10px;"></div>
        `;
        }

        return '<p>Invalid quiz type</p>';
    }

    renderCompletedCheckpoints() {
        const completed = this.activeHuntProgress.checkpoints.filter(cp => cp.status === 'completed');

        if (completed.length === 0) return '';

        return `
        <div class="completed-checkpoints-section">
            <h3>✅ Completed Checkpoints</h3>
            ${completed.map(cp => `
                <div class="completed-checkpoint-item">
                    <div class="checkpoint-check">✓</div>
                    <div>
                        <strong>${cp.sequence_order}. ${cp.place_name}</strong>
                        <div style="font-size: 13px; color: #666;">
                            ${cp.completed_at ? `Completed ${new Date(cp.completed_at).toLocaleString()}` : 'Completed'} | 
                            🏆 ${cp.points_awarded} points
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    }

    initCheckpointMap() {
        if (!this.currentCheckpoint) return;

        const mapElement = document.getElementById('checkpoint-map');
        if (!mapElement) return;

        // Clear existing map if any
        if (this.checkpointMap) {
            this.checkpointMap.remove();
        }

        this.checkpointMap = L.map('checkpoint-map').setView(
            [this.currentCheckpoint.latitude, this.currentCheckpoint.longitude],
            15
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.checkpointMap);

        // Add checkpoint marker
        L.marker([this.currentCheckpoint.latitude, this.currentCheckpoint.longitude])
            .addTo(this.checkpointMap)
            .bindPopup(`<strong>${this.currentCheckpoint.place_name}</strong>`)
            .openPopup();

        // Add user location if available
        if (this.currentLocation) {
            L.marker([this.currentLocation.lat, this.currentLocation.lon], {
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                })
            })
                .addTo(this.checkpointMap)
                .bindPopup('You are here');

            // Draw line between user and checkpoint
            L.polyline([
                [this.currentLocation.lat, this.currentLocation.lon],
                [this.currentCheckpoint.latitude, this.currentCheckpoint.longitude]
            ], {
                color: '#667eea',
                weight: 3,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(this.checkpointMap);
        }
    }

    trackDistanceToCheckpoint() {
        if (!this.currentCheckpoint || !navigator.geolocation) return;

        const updateDistance = (position) => {
            this.currentLocation = {
                lat: position.coords.latitude,
                lon: position.coords.longitude
            };

            const distance = this.calculateDistance(
                position.coords.latitude,
                position.coords.longitude,
                this.currentCheckpoint.latitude,
                this.currentCheckpoint.longitude
            );

            const distanceIndicator = document.getElementById('distanceIndicator');
            if (distanceIndicator) {
                let icon, message, color;

                if (distance < 0.05) { // Less than 50 meters
                    icon = '✅';
                    message = `You're here! (${Math.round(distance * 1000)}m away)`;
                    color = '#27ae60';
                } else if (distance < 0.5) {
                    icon = '🎯';
                    message = `Very close! ${Math.round(distance * 1000)}m away`;
                    color = '#f39c12';
                } else {
                    icon = '📍';
                    message = `${distance.toFixed(2)} km away`;
                    color = '#3498db';
                }

                distanceIndicator.innerHTML = `
                <span style="font-size: 24px;">${icon}</span>
                <span style="color: ${color}; font-weight: 600;">${message}</span>
            `;
            }
        };

        navigator.geolocation.getCurrentPosition(updateDistance);

        // Update every 5 seconds
        if (this.distanceWatcher) {
            navigator.geolocation.clearWatch(this.distanceWatcher);
        }
        this.distanceWatcher = navigator.geolocation.watchPosition(updateDistance);
    }

    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    // CHECKPOINT VERIFICATION METHODS
    async verifyLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser');
            return;
        }

        const btn = document.getElementById('verifyLocationBtn');
        const statusDiv = document.getElementById('locationStatus');

        btn.disabled = true;
        btn.textContent = 'Checking location...';

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const distance = this.calculateDistance(
                    position.coords.latitude,
                    position.coords.longitude,
                    this.currentCheckpoint.latitude,
                    this.currentCheckpoint.longitude
                );

                const distanceMeters = distance * 1000;

                if (distanceMeters <= 50) {
                    statusDiv.innerHTML = `<div class="alert alert-success">✅ Location verified! Completing checkpoint...</div>`;
                    await this.completeCheckpoint('geofence', {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        distance: distanceMeters
                    });
                } else {
                    statusDiv.innerHTML = `<div class="alert alert-warning">📍 You're ${Math.round(distanceMeters)}m away. Get closer to verify!</div>`;
                    btn.disabled = false;
                    btn.textContent = '📍 Check My Location';
                }
            },
            (error) => {
                statusDiv.innerHTML = `<div class="alert alert-error">❌ Could not get your location: ${error.message}</div>`;
                btn.disabled = false;
                btn.textContent = '📍 Check My Location';
            }
        );
    }

    async submitQRCode() {
        const qrInput = document.getElementById('qrCodeInput');
        const qrCode = qrInput.value.trim();

        if (!qrCode) {
            alert('Please enter the QR code');
            return;
        }

        const statusDiv = document.getElementById('qrStatus');
        statusDiv.innerHTML = '<div class="alert alert-info">Verifying QR code...</div>';

        // Parse challenge_data to get the expected QR code value
        let challengeData = this.currentCheckpoint.challenge_data;

        console.log('QR Submit - Raw challenge_data:', challengeData);
        console.log('QR Submit - Type:', typeof challengeData);

        if (typeof challengeData === 'string') {
            try {
                challengeData = JSON.parse(challengeData);
                console.log('QR Submit - Parsed challenge_data:', challengeData);
            } catch (e) {
                console.error('Error parsing challenge_data:', e);
                statusDiv.innerHTML = '<div class="alert alert-error">❌ Error loading QR code data</div>';
                return;
            }
        }

        const expectedQRCode = challengeData?.qr_code_value;

        console.log('QR Submit - User entered:', qrCode);
        console.log('QR Submit - Expected:', expectedQRCode);

        // Verify the QR code matches
        if (qrCode === expectedQRCode) {
            statusDiv.innerHTML = '<div class="alert alert-success">✅ QR Code verified! Completing checkpoint...</div>';
            await this.completeCheckpoint('QR', {
                qr_code: qrCode,
                verified: true,
                method: 'manual_entry'
            });
        } else {
            statusDiv.innerHTML = `<div class="alert alert-error">❌ Invalid QR code. Please try again.</div>`;
        }
    }

    async handlePhotoUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const preview = document.getElementById('photoPreview');
        preview.innerHTML = `
        <div class="photo-preview-container">
            <img src="${URL.createObjectURL(file)}" alt="Preview" style="max-width: 100%; border-radius: 8px; margin-bottom: 10px;">
            <button class="btn btn-primary btn-block" onclick="app.submitPhoto()">
                ✓ Confirm and Submit
            </button>
        </div>
    `;

        this.selectedPhoto = file;
    }

    async submitPhoto() {
        if (!this.selectedPhoto) return;

        const formData = new FormData();
        formData.append('photo', this.selectedPhoto);
        formData.append('progress_id', this.activeHuntProgress.progress_id);
        formData.append('checkpoint_id', this.currentCheckpoint.checkpoint_id);

        try {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = '<div class="alert alert-info">Uploading photo...</div>';

            // For now, just complete the checkpoint without uploading
            // You can implement the photo upload API later
            await this.completeCheckpoint('photo', {
                photo_uploaded: true
            });

        } catch (error) {
            console.error('Photo upload error:', error);
            alert('Failed to upload photo');
        }
    }

    async submitQuizAnswer() {
        const selected = document.querySelector('input[name="quizAnswer"]:checked');
        if (!selected) {
            alert('Please select an answer');
            return;
        }

        // Parse challenge_data
        let challengeData = this.currentCheckpoint.challenge_data;
        if (typeof challengeData === 'string') {
            try {
                challengeData = JSON.parse(challengeData);
            } catch (e) {
                console.error('Error parsing challenge_data:', e);
                alert('Error loading quiz data');
                return;
            }
        }

        const correctAnswer = challengeData?.correct_answer;
        const userAnswer = parseInt(selected.value);

        const resultDiv = document.getElementById('quizResult');

        console.log('User answer:', userAnswer, 'Correct answer:', correctAnswer);

        if (userAnswer === correctAnswer) {
            resultDiv.innerHTML = '<div class="alert alert-success">✅ Correct! Completing checkpoint...</div>';
            await this.completeCheckpoint('quiz', {
                answer: userAnswer,
                correct: true
            });
        } else {
            resultDiv.innerHTML = '<div class="alert alert-error">❌ Incorrect answer. Try again!</div>';
        }
    }

    async submitQuizTextAnswer() {
        const textInput = document.getElementById('quizTextInput');
        if (!textInput || !textInput.value.trim()) {
            alert('Please enter an answer');
            return;
        }

        // Parse challenge_data
        let challengeData = this.currentCheckpoint.challenge_data;
        if (typeof challengeData === 'string') {
            try {
                challengeData = JSON.parse(challengeData);
            } catch (e) {
                console.error('Error parsing challenge_data:', e);
                alert('Error loading quiz data');
                return;
            }
        }

        const correctAnswer = challengeData?.correct_answer?.toLowerCase() || '';
        const userAnswer = textInput.value.trim().toLowerCase();
        const exactMatch = challengeData?.exact_match || false;

        const resultDiv = document.getElementById('quizResult');

        let isCorrect = false;
        if (exactMatch) {
            isCorrect = userAnswer === correctAnswer;
        } else {
            isCorrect = userAnswer.includes(correctAnswer) || correctAnswer.includes(userAnswer);
        }

        if (isCorrect) {
            resultDiv.innerHTML = '<div class="alert alert-success">✅ Correct! Completing checkpoint...</div>';
            await this.completeCheckpoint('quiz', {
                answer: userAnswer,
                correct: true
            });
        } else {
            resultDiv.innerHTML = '<div class="alert alert-error">❌ Incorrect answer. Try again!</div>';
        }
    }

    async completeCheckpoint(verificationType, verificationData) {
        const data = await this.apiRequest('/progress/complete-checkpoint', {
            method: 'POST',
            body: JSON.stringify({
                progress_id: this.activeHuntProgress.progress_id,
                checkpoint_id: this.currentCheckpoint.checkpoint_id,
                verification_method: verificationType,
                verification_data: verificationData,
                hints_used: this.hintsUsed || 0
            })
        });

        if (data && data.success) {
            console.log('=== CHECKPOINT COMPLETION RESPONSE ===');
            console.log('Full response:', data);
            console.log('Has level_up key?', 'level_up' in (data.data || {}));
            console.log('Level up data:', data.data?.level_up);

            // Check if hunt is completed
            if (data.data && data.data.hunt_completed === true) {
                console.log('HUNT COMPLETED! Showing completion screen...');

                // Show level up modal first if user leveled up
                if (data.data.level_up && data.data.level_up.leveled_up === true) {
                    console.log('User leveled up! Showing modal...');
                    this.showLevelUpModal(data.data.level_up);
                } else {
                    console.log('No level up on hunt completion');
                }

                // Store completion data temporarily
                this.huntCompletionData = {
                    hunt_title: data.data.hunt_title || this.activeHuntProgress.hunt_title,
                    reward_points: data.data.reward_points || 0,
                    total_points_earned: this.activeHuntProgress.points_earned + (data.data.reward_points || 0),
                    badge_awarded: data.data.badge_awarded || false,
                    level_up: data.data.level_up
                };

                // Reload progress to get updated data, then show completion
                const progressData = await this.apiRequest(`/progress/${this.activeHuntProgress.progress_id}`);
                if (progressData && progressData.success) {
                    this.activeHuntProgress = progressData.data;
                    this.showHuntCompletion();
                } else {
                    // Fallback: show completion anyway
                    this.showHuntCompletion();
                }

                return; // Exit early since hunt is complete
            }

            // Normal checkpoint completion (not final checkpoint)
            console.log('Normal checkpoint completion');
            console.log('Checking for level up...');
            console.log('data.data exists?', !!data.data);
            console.log('data.data.level_up exists?', !!(data.data && data.data.level_up));
            console.log('leveled_up value:', data.data?.level_up?.leveled_up);

            // Check for level up - ONLY show modal if actually leveled up
            if (data.data && data.data.level_up && data.data.level_up.leveled_up === true) {
                console.log('✅ LEVEL UP CONFIRMED - Showing modal!');
                this.showLevelUpModal(data.data.level_up);
            } else {
                console.log('❌ No level up - Modal NOT shown');
            }

            // Show success message
            const pointsMessage = `🎉 Checkpoint completed! +${this.currentCheckpoint.points_awarded} points`;
            alert(pointsMessage);

            // Reload progress and continue to next checkpoint
            const progressData = await this.apiRequest(`/progress/my-hunts?status=in_progress`);
            if (progressData && progressData.success && progressData.data.length > 0) {
                // Find the current hunt in the response
                this.activeHuntProgress = progressData.data.find(h => h.progress_id === this.activeHuntProgress.progress_id);
                if (this.activeHuntProgress) {
                    // Get full details
                    const detailData = await this.apiRequest(`/progress/${this.activeHuntProgress.progress_id}`);
                    if (detailData && detailData.success) {
                        this.activeHuntProgress = detailData.data;
                        this.loadCheckpointDetails();
                    }
                }
            }
        } else {
            alert('Failed to complete checkpoint: ' + (data?.message || 'Unknown error'));
        }
    }

    revealHint() {
        document.getElementById('hintContent').style.display = 'block';
        document.querySelector('#hintSection button').style.display = 'none';
        this.hintsUsed = (this.hintsUsed || 0) + 1;
    }

    showHuntCompletion() {
        const container = document.getElementById('checkpoint-container');

        // Use stored completion data or fall back to activeHuntProgress
        const completionData = this.huntCompletionData || {};
        const progress = this.activeHuntProgress;

        const huntTitle = completionData.hunt_title || (progress ? progress.hunt_title : 'Treasure Hunt');
        const totalPoints = completionData.total_points_earned || (progress ? progress.points_earned : 0);
        const checkpointCount = progress ? progress.checkpoints.length : 0;
        const badgeAwarded = completionData.badge_awarded || false;

        container.innerHTML = `
            <div class="hunt-completion">
                <div class="completion-icon bounce">🏆</div>
                <h2>Congratulations!</h2>
                <p class="completion-message">You've completed the "${huntTitle}" treasure hunt!</p>
                
                ${badgeAwarded ? `
                    <div class="badge-awarded-notice" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #ffd700, #ffed4e); border-radius: 10px; color: #333;">
                        <div style="font-size: 36px; margin-bottom: 5px;">🎖️</div>
                        <strong>Special Badge Earned!</strong>
                    </div>
                ` : ''}
                
                <div class="completion-stats">
                    <div class="stat-box">
                        <div class="stat-value">${totalPoints}</div>
                        <div class="stat-label">Points Earned</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${checkpointCount}</div>
                        <div class="stat-label">Checkpoints</div>
                    </div>
                </div>
                
                <div id="huntCompletionLevelInfo" style="margin: 20px 0;"></div>
                
                <button class="btn btn-primary btn-block" onclick="app.finishHuntAndReturnToHunts()">
                    View My Hunts
                </button>
                <button class="btn btn-secondary btn-block" onclick="app.finishHuntAndStartNew()" style="margin-top: 10px;">
                    Start Another Hunt
                </button>
            </div>
        `;

        // Load and display current level info
        this.loadCompletionLevelInfo();

        // Clear completion data
        this.huntCompletionData = null;
    }

    // Finish hunt and return to hunts page
    finishHuntAndReturnToHunts() {
        this.exitCheckpointUI();
        this.switchPage('hunts');
    }

    // Finish hunt and go to home to start new hunt
    finishHuntAndStartNew() {
        this.exitCheckpointUI();
        this.switchPage('home');
    }

    async loadCompletionLevelInfo() {
        const container = document.getElementById('huntCompletionLevelInfo');
        if (!container) return;

        const data = await this.apiRequest('/levels/progress');

        if (data && data.success) {
            const progress = data.data;

            container.innerHTML = `
                <div class="completion-level-card">
                    <div class="level-badge-small">${progress.level}</div>
                    <div class="level-info-small">
                        <strong>${progress.level_name}</strong>
                        <div class="progress-bar-mini">
                            <div class="progress-fill-mini" style="width: ${progress.progress_percentage}%"></div>
                        </div>
                        <small>${progress.experience_points} / ${progress.next_level_xp || progress.experience_points} XP</small>
                    </div>
                </div>
            `;
        }
    }

    async loadLeaderboard() {
        const container = document.getElementById('leaderboard-container');

        if (!container) return;

        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/levels/leaderboard?limit=20');

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = `
                <div class="leaderboard-header">
                    <h3>🏆 Top Explorers</h3>
                </div>
                <div class="leaderboard-list">
                    ${data.data.map((user, index) => `
                        <div class="leaderboard-item ${user.user_id === this.userInfo.user_id ? 'current-user' : ''}">
                            <div class="rank-badge rank-${index + 1}">
                                ${index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `#${index + 1}`}
                            </div>
                            <div class="user-info">
                                <strong>${user.full_name || user.username}</strong>
                                <div class="user-stats-mini">
                                    <span>Level ${user.level} - ${user.level_name}</span>
                                    <span>•</span>
                                    <span>${user.total_points} points</span>
                                    <span>•</span>
                                    <span>${user.hunts_completed} hunts</span>
                                </div>
                            </div>
                            <div class="user-level-badge">
                                ${user.level}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            container.innerHTML = '<div class="empty-state"><p>No leaderboard data available</p></div>';
        }
    }

    exitCheckpointUI() {
        // Stop QR scanner if running
        this.stopQRScanner();

        if (this.distanceWatcher) {
            navigator.geolocation.clearWatch(this.distanceWatcher);
        }
        if (this.checkpointMap) {
            this.checkpointMap.remove();
            this.checkpointMap = null;
        }
        this.activeHuntProgress = null;
        this.currentCheckpoint = null;
        this.hintsUsed = 0;
        this.switchPage('hunts');
    }

    async loadLevelProgress() {
        const container = document.getElementById('level-progress-container');

        if (!container) {
            // Create container if it doesn't exist
            const userInfoSection = document.getElementById('user-info');
            if (userInfoSection) {
                const progressContainer = document.createElement('div');
                progressContainer.id = 'level-progress-container';
                progressContainer.style.margin = '20px 0';
                userInfoSection.parentNode.insertBefore(progressContainer, userInfoSection.nextSibling);
            }
        }

        const progressContainer = document.getElementById('level-progress-container');
        if (!progressContainer) return;

        progressContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/levels/progress');

        if (data && data.success) {
            const progress = data.data;

            progressContainer.innerHTML = `
                <div class="level-progress-card">
                    <div class="level-header">
                        <div class="level-badge-container">
                            <div class="level-badge">${progress.level}</div>
                            <div class="level-name">${progress.level_name}</div>
                        </div>
                        <div class="level-xp-info">
                            <span class="xp-current">${progress.experience_points} XP</span>
                            ${progress.next_level_xp ? `
                                <span class="xp-next">/ ${progress.next_level_xp} XP</span>
                            ` : '<span class="xp-max">MAX LEVEL</span>'}
                        </div>
                    </div>
                    
                    ${progress.next_level_xp ? `
                        <div class="level-progress-section">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: ${progress.progress_percentage}%">
                                    <span class="progress-text">${progress.progress_percentage}%</span>
                                </div>
                            </div>
                            <div class="next-level-info">
                                <span>Next: ${progress.next_level_name}</span>
                                <span>${progress.xp_to_next_level} XP to go</span>
                            </div>
                        </div>
                    ` : `
                        <div class="max-level-message">
                            <span style="font-size: 24px;">🏆</span>
                            <p>You've reached the maximum level!</p>
                        </div>
                    `}
                    
                    <div class="total-points-display">
                        <span class="points-icon">💎</span>
                        <span class="points-value">${progress.total_points}</span>
                        <span class="points-label">Total Points</span>
                    </div>
                </div>
            `;
        }
    }

    // Show level-up modal
    showLevelUpModal(levelUpData) {
        // Double-check that user actually leveled up
        if (!levelUpData || !levelUpData.leveled_up || levelUpData.leveled_up !== true) {
            console.log('No level up occurred, skipping modal');
            return;
        }

        // Also check that the level actually changed
        if (levelUpData.old_level === levelUpData.new_level) {
            console.log('Level did not change, skipping modal');
            return;
        }

        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'level-up-modal-overlay';
        modalOverlay.id = 'levelUpModal';

        const badgesHTML = levelUpData.badges_earned && levelUpData.badges_earned.length > 0
            ? levelUpData.badges_earned.map(badge => `
                <div class="earned-badge">
                    <div class="badge-icon">🏆</div>
                    <div class="badge-info">
                        <strong>${badge.name}</strong>
                        <p>${badge.description}</p>
                    </div>
                </div>
            `).join('')
            : '';

        modalOverlay.innerHTML = `
            <div class="level-up-modal">
                <div class="level-up-animation">
                    <div class="level-up-star">⭐</div>
                    <div class="level-up-star">⭐</div>
                    <div class="level-up-star">⭐</div>
                </div>
                
                <h2 class="level-up-title">LEVEL UP!</h2>
                
                <div class="level-up-content">
                    <div class="level-display">
                        <div class="old-level">
                            <span class="level-number">${levelUpData.old_level}</span>
                            <span class="level-arrow">→</span>
                        </div>
                        <div class="new-level">
                            <span class="level-number glow">${levelUpData.new_level}</span>
                        </div>
                    </div>
                    
                    <p class="level-up-message">
                        Congratulations! You've reached level ${levelUpData.new_level}!
                    </p>
                    
                    <div class="xp-info">
                        <span>Current XP: ${levelUpData.current_xp}</span>
                        ${levelUpData.next_level_xp ? `
                            <span>Next Level: ${levelUpData.next_level_xp} XP</span>
                        ` : `
                            <span class="max-level-badge">MAX LEVEL REACHED! 🏆</span>
                        `}
                    </div>
                    
                    ${badgesHTML ? `
                        <div class="badges-earned-section">
                            <h3>🎖️ Badges Earned</h3>
                            <div class="badges-list">
                                ${badgesHTML}
                            </div>
                        </div>
                    ` : ''}
                </div>
                
                <button class="btn btn-primary btn-block" onclick="app.closeLevelUpModal()">
                    Awesome! 🎉
                </button>
            </div>
        `;

        document.body.appendChild(modalOverlay);

        // Animate in
        setTimeout(() => {
            modalOverlay.classList.add('show');
        }, 100);
    }

    // Close level-up modal
    closeLevelUpModal() {
        const modal = document.getElementById('levelUpModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }

    // PROFILE PAGE
    async loadProfile() {
        await this.loadUserInfo();
        await this.loadUserStats();
        await this.loadLevelProgress();
        await this.loadUserBadges();
        await this.loadLeaderboard();
    }

    async loadUserInfo() {
        const container = document.getElementById('user-info');

        const data = await this.apiRequest('/users/profile');

        if (data && data.success) {
            const user = data.data;
            container.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 64px; margin-bottom: 10px;">👤</div>
                    <h2>${user.full_name}</h2>
                    <p style="color: #777;">@${user.username}</p>
                    <div style="margin: 20px 0;">
                        <span class="badge" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 8px 20px; font-size: 16px;">
                            ${user.level}
                        </span>
                    </div>
                </div>
            `;
        }
    }

    async loadUserStats() {
        const container = document.getElementById('user-stats');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/users/stats');

        if (data && data.success) {
            const stats = data.data;
            container.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">${stats.total_points}</div>
                        <div class="stat-label">Total Points</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${stats.hunts_completed}</div>
                        <div class="stat-label">Hunts Completed</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${stats.badges_earned}</div>
                        <div class="stat-label">Badges Earned</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${stats.places_visited}</div>
                        <div class="stat-label">Places Visited</div>
                    </div>
                </div>
            `;
        }
    }

    async loadUserBadges() {
        const container = document.getElementById('user-badges');
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        const data = await this.apiRequest('/badges/my-badges');

        if (data && data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(badge => `
                <div class="card" style="padding: 15px; margin: 10px 0;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="font-size: 48px;">🏆</div>
                        <div>
                            <h3>${badge.name}</h3>
                            <p style="font-size: 14px; color: #777;">${badge.description}</p>
                            <small>Earned on ${new Date(badge.earned_at).toLocaleDateString()}</small>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="empty-state"><p>No badges earned yet</p></div>';
        }
    }

    // AUTHENTICATION
    showAuthModal() {
        document.getElementById('auth-modal').classList.add('active');
    }

    showLoginForm() {
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('register-form').style.display = 'none';
    }

    showRegisterForm() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
    }

    async login() {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (data.success) {
            this.token = data.data.token;
            this.userInfo = data.data;
            localStorage.setItem('user_token', this.token);
            localStorage.setItem('user_info', JSON.stringify(this.userInfo));

            document.getElementById('auth-modal').classList.remove('active');
            location.reload();
        } else {
            alert(data.message || 'Login failed');
        }
    }

    async register() {
        const username = document.getElementById('reg-username').value;
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        const full_name = document.getElementById('reg-fullname').value;
        const user_type = document.getElementById('reg-usertype').value;

        const response = await fetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password, full_name, user_type })
        });

        const data = await response.json();

        if (data.success) {
            this.token = data.data.token;
            this.userInfo = data.data;
            localStorage.setItem('user_token', this.token);
            localStorage.setItem('user_info', JSON.stringify(this.userInfo));

            document.getElementById('auth-modal').classList.remove('active');
            location.reload();
        } else {
            alert(data.message || 'Registration failed');
        }
    }

    logout() {
        localStorage.removeItem('user_token');
        localStorage.removeItem('user_info');
        location.reload();
    }

    // GEOLOCATION
    requestLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentLocation = {
                        lat: position.coords.latitude,
                        lon: position.coords.longitude
                    };
                },
                (error) => {
                    console.log('Location permission denied', error);
                }
            );
        }
    }

    // OFFLINE SYNC
    async syncOfflineData() {
        const actions = await treasureDB.getOfflineActions();

        if (actions.length > 0) {
            console.log(`Syncing ${actions.length} offline actions...`);

            for (const action of actions) {
                try {
                    const response = await this.apiRequest('/sync/queue', {
                        method: 'POST',
                        body: JSON.stringify(action.data)
                    });

                    if (response && response.success) {
                        await treasureDB.removeOfflineAction(action.id);
                    }
                } catch (error) {
                    console.error('Sync failed:', error);
                }
            }
        }
    }
}

// Initialize app
const app = new TreasureHuntApp();