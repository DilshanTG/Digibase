/**
 * Digibase JS SDK
 * A fluent JavaScript client for the Digibase Dynamic Data API
 * 
 * Features:
 * - CRUD Operations (get, create, update, delete)
 * - Fluent Query Builder (from, include, search, where, orderBy)
 * - 📡 Real-Time Subscriptions (subscribe, on)
 */
class Digibase {
    constructor(baseUrl = '/api/data', token = null) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = token;
        this.queryTable = null;
        this.queryParams = new URLSearchParams();

        // 📡 LIVE WIRE: Real-time configuration
        this._reverbHost = null;
        this._reverbPort = null;
        this._echo = null;
        this._subscriptions = new Map();
    }

    /**
     * Set the API key for authentication.
     */
    setToken(token) {
        this.token = token;
        return this;
    }

    /**
     * Configure Reverb/WebSocket connection.
     * @param {Object} config - { host: 'localhost', port: 8080, key: 'your-app-key' }
     */
    configureRealtime(config = {}) {
        this._reverbHost = config.host || window.location.hostname;
        this._reverbPort = config.port || 8080;
        this._reverbScheme = config.scheme || (window.location.protocol === 'https:' ? 'wss' : 'ws');
        this._reverbKey = config.key || 'jvriyrkvunhxjn7nbxwa'; // Default from .env
        return this;
    }

    /**
     * Start the query chain.
     */
    from(table) {
        this.queryTable = table;
        this.queryParams = new URLSearchParams();
        return this;
    }

    include(relations) {
        const val = Array.isArray(relations) ? relations.join(',') : relations;
        this.queryParams.append('include', val);
        return this;
    }

    search(term) {
        if (term) this.queryParams.set('search', term);
        return this;
    }

    page(page) {
        this.queryParams.set('page', page);
        return this;
    }

    limit(limit) {
        this.queryParams.set('per_page', limit);
        return this;
    }

    orderBy(column, direction = 'asc') {
        this.queryParams.set('sort', column);
        this.queryParams.set('order', direction);
        return this;
    }

    where(column, value) {
        this.queryParams.set(`filter[${column}]`, value);
        return this;
    }

    async get() {
        if (!this.queryTable) throw new Error("You must specify a table using .from('tableName')");
        const url = `${this.baseUrl}/${this.queryTable}?${this.queryParams.toString()}`;

        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.token) headers['Authorization'] = `Bearer ${this.token}`;

        const response = await fetch(url, { headers });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `Digibase Error: ${response.status}`);
        }
        const json = await response.json();
        return json.data;
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD Methods
    // ═══════════════════════════════════════════════════════════

    async find(id) {
        return this._request('GET', `${this.queryTable}/${id}`);
    }

    async create(data) {
        return this._request('POST', this.queryTable, data);
    }

    async update(id, data) {
        return this._request('PUT', `${this.queryTable}/${id}`, data);
    }

    async delete(id) {
        return this._request('DELETE', `${this.queryTable}/${id}`);
    }

    async _request(method, endpoint, body = null) {
        const url = `${this.baseUrl}/${endpoint}`;
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.token) headers['Authorization'] = `Bearer ${this.token}`;

        const options = { method, headers };
        if (body) options.body = JSON.stringify(body);

        const response = await fetch(url, options);
        if (!response.ok) throw new Error(`Digibase Error: ${response.status}`);

        // Handle empty responses (like DELETE)
        if (response.status === 204) return true;
        const json = await response.json();
        return json.data || json;
    }

    // ═══════════════════════════════════════════════════════════
    // 📡 LIVE WIRE: Real-Time Subscriptions
    // ═══════════════════════════════════════════════════════════

    /**
     * Subscribe to real-time changes on a table.
     * Uses Laravel Echo + Reverb (via CDN).
     * 
     * @example
     * db.from('authors').subscribe((event) => {
     *   console.log(event.action, event.data);
     * });
     * 
     * // Or with specific events:
     * db.from('authors')
     *   .on('created', (data) => console.log('New author:', data))
     *   .on('updated', (data) => console.log('Updated:', data))
     *   .on('deleted', (data) => console.log('Deleted:', data.id));
     */
    subscribe(callback) {
        if (!this.queryTable) {
            throw new Error("You must specify a table using .from('tableName')");
        }

        const table = this.queryTable;
        this._ensureEchoLoaded().then(() => {
            const channel = this._echo.channel(`public-data.${table}`);

            channel.listen('.model.changed', (event) => {
                callback(event);
            });

            // Store subscription for cleanup
            this._subscriptions.set(table, channel);
        });

        return this;
    }

    /**
     * Listen for specific events on a table.
     * 
     * @param {string} action - 'created', 'updated', 'deleted', or '*' for all
     * @param {function} callback - Handler function
     */
    on(action, callback) {
        if (!this.queryTable) {
            throw new Error("You must specify a table using .from('tableName')");
        }

        const table = this.queryTable;
        this._ensureEchoLoaded().then(() => {
            let channel = this._subscriptions.get(table);

            if (!channel) {
                channel = this._echo.channel(`public-data.${table}`);
                this._subscriptions.set(table, channel);
            }

            channel.listen('.model.changed', (event) => {
                if (action === '*' || event.action === action) {
                    callback(event.data, event);
                }
            });
        });

        return this;
    }

    /**
     * Unsubscribe from a table's real-time updates.
     */
    unsubscribe(table = null) {
        const targetTable = table || this.queryTable;

        if (this._subscriptions.has(targetTable)) {
            this._echo.leave(`public-data.${targetTable}`);
            this._subscriptions.delete(targetTable);
        }

        return this;
    }

    /**
     * Ensure Laravel Echo is loaded (lazy-load from CDN).
     * @private
     */
    async _ensureEchoLoaded() {
        if (this._echo) return;

        // Load Pusher (required by Echo for WebSocket)
        if (typeof Pusher === 'undefined') {
            await this._loadScript('https://js.pusher.com/8.2.0/pusher.min.js');
        }

        // Load Laravel Echo
        if (typeof Echo === 'undefined') {
            await this._loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js');
        }

        // Initialize Echo with Reverb (Pusher-compatible)
        const host = this._reverbHost || window.location.hostname;
        const port = this._reverbPort || 8080;
        const scheme = this._reverbScheme || 'ws';

        this._echo = new Echo({
            broadcaster: 'pusher',
            key: this._reverbKey || 'jvriyrkvunhxjn7nbxwa',
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'wss',
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            cluster: 'mt1',  // Required but unused for Reverb
        });
    }

    /**
     * Dynamically load a script from URL.
     * @private
     */
    _loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load: ${src}`));
            document.head.appendChild(script);
        });
    }
}

// Export for ES Modules / Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Digibase;
}
