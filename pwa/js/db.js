/**
 * IndexedDB Helper for Offline Storage
 */

class TreasureHuntDB {
    constructor() {
        this.dbName = 'TreasureHuntDB';
        this.version = 1;
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Store for offline actions (checkpoint completions, etc.)
                if (!db.objectStoreNames.contains('offlineActions')) {
                    const actionStore = db.createObjectStore('offlineActions', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    actionStore.createIndex('timestamp', 'timestamp', { unique: false });
                    actionStore.createIndex('type', 'type', { unique: false });
                }

                // Store for cached hunts
                if (!db.objectStoreNames.contains('hunts')) {
                    const huntStore = db.createObjectStore('hunts', { keyPath: 'hunt_id' });
                    huntStore.createIndex('cached_at', 'cached_at', { unique: false });
                }

                // Store for cached places
                if (!db.objectStoreNames.contains('places')) {
                    const placeStore = db.createObjectStore('places', { keyPath: 'place_id' });
                    placeStore.createIndex('cached_at', 'cached_at', { unique: false });
                }

                // Store for user progress
                if (!db.objectStoreNames.contains('progress')) {
                    const progressStore = db.createObjectStore('progress', { keyPath: 'progress_id' });
                    progressStore.createIndex('hunt_id', 'hunt_id', { unique: false });
                }
            };
        });
    }

    // Offline Actions
    async addOfflineAction(action) {
        const tx = this.db.transaction('offlineActions', 'readwrite');
        const store = tx.objectStore('offlineActions');
        
        const actionData = {
            ...action,
            timestamp: Date.now(),
            synced: false
        };
        
        return new Promise((resolve, reject) => {
            const request = store.add(actionData);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getOfflineActions() {
        const tx = this.db.transaction('offlineActions', 'readonly');
        const store = tx.objectStore('offlineActions');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async removeOfflineAction(id) {
        const tx = this.db.transaction('offlineActions', 'readwrite');
        const store = tx.objectStore('offlineActions');
        
        return new Promise((resolve, reject) => {
            const request = store.delete(id);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async clearOfflineActions() {
        const tx = this.db.transaction('offlineActions', 'readwrite');
        const store = tx.objectStore('offlineActions');
        
        return new Promise((resolve, reject) => {
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    // Cache Hunts
    async cacheHunt(hunt) {
        const tx = this.db.transaction('hunts', 'readwrite');
        const store = tx.objectStore('hunts');
        
        const huntData = {
            ...hunt,
            cached_at: Date.now()
        };
        
        return new Promise((resolve, reject) => {
            const request = store.put(huntData);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getCachedHunt(hunt_id) {
        const tx = this.db.transaction('hunts', 'readonly');
        const store = tx.objectStore('hunts');
        
        return new Promise((resolve, reject) => {
            const request = store.get(hunt_id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAllCachedHunts() {
        const tx = this.db.transaction('hunts', 'readonly');
        const store = tx.objectStore('hunts');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Cache Places
    async cachePlace(place) {
        const tx = this.db.transaction('places', 'readwrite');
        const store = tx.objectStore('places');
        
        const placeData = {
            ...place,
            cached_at: Date.now()
        };
        
        return new Promise((resolve, reject) => {
            const request = store.put(placeData);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getCachedPlace(place_id) {
        const tx = this.db.transaction('places', 'readonly');
        const store = tx.objectStore('places');
        
        return new Promise((resolve, reject) => {
            const request = store.get(place_id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // User Progress
    async saveProgress(progress) {
        const tx = this.db.transaction('progress', 'readwrite');
        const store = tx.objectStore('progress');
        
        return new Promise((resolve, reject) => {
            const request = store.put(progress);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getProgress(progress_id) {
        const tx = this.db.transaction('progress', 'readonly');
        const store = tx.objectStore('progress');
        
        return new Promise((resolve, reject) => {
            const request = store.get(progress_id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getProgressByHunt(hunt_id) {
        const tx = this.db.transaction('progress', 'readonly');
        const store = tx.objectStore('progress');
        const index = store.index('hunt_id');
        
        return new Promise((resolve, reject) => {
            const request = index.get(hunt_id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
}

// Export singleton instance
const treasureDB = new TreasureHuntDB();