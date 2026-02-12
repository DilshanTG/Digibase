import { DigibaseConfig, ApiResponse, FileUploadResponse } from './types';

export class DigibaseStorage {
    constructor(private config: DigibaseConfig) {}

    async upload(file: File, folder: string = 'uploads'): Promise<ApiResponse<FileUploadResponse>> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder', folder);

        const res = await fetch(`${this.config.baseUrl}/api/v1/upload`, {
            method: 'POST',
            headers: {
                'x-api-key': this.config.apiKey,
                // Content-Type is auto-set by browser for FormData
            },
            body: formData
        });

        return res.json();
    }

    async delete(path: string): Promise<ApiResponse<null>> {
        const res = await fetch(`${this.config.baseUrl}/api/v1/media`, {
            method: 'DELETE',
            headers: {
                'x-api-key': this.config.apiKey,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ path })
        });

        return res.json();
    }
}
