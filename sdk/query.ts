import { DigibaseConfig, ApiResponse, QueryParams } from './types';

export class DigibaseQuery<T = any> {
    private params: QueryParams = {};

    constructor(
        private config: DigibaseConfig,
        private table: string
    ) {}

    // 🔹 FILTERS
    select(columns: string): this {
        this.params.select = columns;
        return this;
    }

    eq(column: string, value: any): this {
        if (!this.params.filter) this.params.filter = {};
        this.params.filter[column] = value;
        return this;
    }

    sort(column: string, direction: 'asc' | 'desc' = 'asc'): this {
        this.params.sort = `${column}:${direction}`;
        return this;
    }

    page(page: number): this {
        this.params.page = page;
        return this;
    }

    limit(limit: number): this {
        this.params.limit = limit;
        return this;
    }

    // 🔹 CRUD EXECUTION
    private async request(method: string, endpoint: string, body?: any): Promise<ApiResponse<T>> {
        const url = new URL(`${this.config.baseUrl}/api/v1/data/${endpoint}`);

        // Append query params for GET
        if (method === 'GET') {
            if (this.params.select) url.searchParams.append('select', this.params.select);
            if (this.params.sort) url.searchParams.append('sort', this.params.sort);
            if (this.params.page) url.searchParams.append('page', this.params.page.toString());
            if (this.params.limit) url.searchParams.append('limit', this.params.limit.toString());

            if (this.params.filter) {
                Object.entries(this.params.filter).forEach(([key, val]) => {
                    url.searchParams.append(key, String(val));
                });
            }
        }

        const res = await fetch(url.toString(), {
            method,
            headers: {
                'x-api-key': this.config.apiKey,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: body ? JSON.stringify(body) : undefined
        });

        return res.json();
    }

    // Public Methods
    async get(): Promise<ApiResponse<T[]>> {
        return this.request('GET', this.table);
    }

    async find(id: string | number): Promise<ApiResponse<T>> {
        return this.request('GET', `${this.table}/${id}`);
    }

    async insert(data: Partial<T>): Promise<ApiResponse<T>> {
        return this.request('POST', this.table, data);
    }

    async bulkInsert(data: Partial<T>[]): Promise<ApiResponse<{ count: number }>> {
        return this.request('POST', `${this.table}/bulk`, { data });
    }

    async update(id: string | number, data: Partial<T>): Promise<ApiResponse<T>> {
        return this.request('PUT', `${this.table}/${id}`, data);
    }

    async delete(id: string | number): Promise<ApiResponse<null>> {
        return this.request('DELETE', `${this.table}/${id}`);
    }
}
