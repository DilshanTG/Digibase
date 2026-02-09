/**
 * Digibase JS SDK
 * A fluent JavaScript client for the Digibase Dynamic Data API
 */
class Digibase {
    constructor(baseUrl = '/api/data', token = null) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = token;
        this.queryTable = null;
        this.queryParams = new URLSearchParams();
    }

    setToken(token) {
        this.token = token;
        return this;
    }

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

    // CRUD Methods
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
}

// Export for ES Modules / Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Digibase;
}
