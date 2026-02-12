export interface DigibaseConfig {
    baseUrl: string;
    apiKey: string;
}

export interface ApiResponse<T = any> {
    data: T;
    meta?: {
        current_page?: number;
        last_page?: number;
        total?: number;
    };
    message?: string;
    error?: string;
}

export interface QueryParams {
    select?: string;
    filter?: Record<string, any>;
    sort?: string;
    page?: number;
    limit?: number;
}

export interface FileUploadResponse {
    url: string;
    path: string;
    type: string;
    size: number;
}
