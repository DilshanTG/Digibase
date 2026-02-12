import { DigibaseConfig } from './types';
import { DigibaseQuery } from './query';
import { DigibaseStorage } from './storage';

export class Digibase {
    public storage: DigibaseStorage;

    constructor(private config: DigibaseConfig) {
        this.storage = new DigibaseStorage(config);
    }

    // Access a specific table
    from<T = any>(table: string): DigibaseQuery<T> {
        return new DigibaseQuery<T>(this.config, table);
    }
}

// Factory function
export const createClient = (baseUrl: string, apiKey: string) => {
    return new Digibase({ baseUrl, apiKey });
};
