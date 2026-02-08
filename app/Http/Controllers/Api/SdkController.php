<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;

class SdkController extends Controller
{
    public function generate()
    {
        // Auto-detect the API base URL
        $baseUrl = config('app.url');

        $js = <<<JS
/**
 * Digibase JavaScript SDK
 * auto-generated
 */
class Digibase {
    constructor(baseUrl = '$baseUrl') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = localStorage.getItem('digibase_token');
    }

    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('digibase_token', token);
        } else {
            localStorage.removeItem('digibase_token');
        }
    }

    async request(method, endpoint, data = null, headers = {}) {
        const url = `\${this.baseUrl}/api\${endpoint}`;
        
        const config = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...headers
            }
        };

        if (this.token) {
            config.headers['Authorization'] = `Bearer \${this.token}`;
        }

        if (data) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(url, config);
        const json = await response.json();

        if (!response.ok) {
            throw { status: response.status, ...json };
        }

        return json;
    }

    // --- Auth ---
    get auth() {
        return {
            login: async (email, password) => {
                const res = await this.request('POST', '/login', { email, password });
                this.setToken(res.token);
                return res.user;
            },
            register: async (name, email, password, password_confirmation) => {
                const res = await this.request('POST', '/register', { name, email, password, password_confirmation });
                this.setToken(res.token);
                return res.user;
            },
            logout: async () => {
                try {
                    await this.request('POST', '/logout');
                } finally {
                    this.setToken(null);
                }
            },
            user: async () => {
                return await this.request('GET', '/user');
            }
        };
    }

    // --- Data Collection ---
    collection(name) {
        return new CollectionQuery(this, name);
    }
    
    // --- Storage ---
    get storage() {
        return {
            getFileUrl: (path) => {
                if (!path) return '';
                if (path.startsWith('http')) return path;
                return `\${this.baseUrl}/storage/\${path}`;
            },
            download: async (id) => {
                 // For private files download via API
                 const res = await this.request('GET', `/storage/\${id}/download`);
                 return res;
            }
        }
    }
}

class CollectionQuery {
    constructor(client, collection) {
        this.client = client;
        this.collection = collection;
        this.params = new URLSearchParams();
    }

    where(field, value) {
        this.params.append(field, value);
        return this;
    }

    sort(field, direction = 'asc') {
        this.params.append('sort', field);
        this.params.append('direction', direction);
        return this;
    }

    page(page) {
        this.params.append('page', page);
        return this;
    }
    
    perPage(count) {
        this.params.append('per_page', count);
        return this;
    }
    
    include(relations) {
        this.params.append('include', relations);
        return this;
    }

    async getAll() {
        const queryString = this.params.toString();
        const endpoint = `/data/\${this.collection}\${queryString ? '?' + queryString : ''}`;
        return await this.client.request('GET', endpoint);
    }

    async getOne(id) {
        return await this.client.request('GET', `/data/\${this.collection}/\${id}`);
    }

    async create(data) {
        return await this.client.request('POST', `/data/\${this.collection}`, data);
    }

    async update(id, data) {
        return await this.client.request('PUT', `/data/\${this.collection}/\${id}`, data);
    }

    async delete(id) {
        return await this.client.request('DELETE', `/data/\${this.collection}/\${id}`);
    }
}

export default Digibase;
JS;

        return Response::make($js, 200, [
            'Content-Type' => 'application/javascript',
            'Content-Disposition' => 'attachment; filename="digibase.js"',
        ]);
    }
}
