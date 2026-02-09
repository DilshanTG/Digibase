<?php

namespace App\Services;

use App\Models\DynamicModel;
use App\Models\DynamicField;
use Illuminate\Support\Str;

class ApiDocumentationService
{
    /**
     * Generate complete API documentation for a dynamic model.
     */
    public function generateDocumentation(DynamicModel $model): array
    {
        $model->load('fields');
        
        return [
            'model' => [
                'name' => $model->name,
                'display_name' => $model->display_name,
                'description' => $model->description,
                'table_name' => $model->table_name,
            ],
            'endpoints' => $this->generateEndpoints($model),
            'authentication' => $this->generateAuthenticationDocs(),
            'examples' => $this->generateExamples($model),
            'schema' => $this->generateSchema($model),
            'responses' => $this->generateResponseExamples($model),
            'webhooks' => $this->generateWebhookDocs($model),
        ];
    }

    /**
     * Generate OpenAPI 3.0 specification.
     */
    public function generateOpenApiSpec(DynamicModel $model): array
    {
        $model->load('fields');
        $tableName = $model->table_name;
        
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name') . ' API - ' . $model->display_name,
                'description' => $model->description ?? "API for {$model->display_name} table",
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'url' => config('app.url'),
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url') . '/api',
                    'description' => 'Production server',
                ],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'x-api-key',
                        'description' => 'API key for authentication (pk_ for read-only, sk_ for full access)',
                    ],
                ],
                'schemas' => [
                    $model->name => $this->generateOpenApiSchema($model),
                    $model->name . 'Input' => $this->generateOpenApiInputSchema($model),
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object'],
                        ],
                    ],
                    'PaginatedResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => "#/components/schemas/{$model->name}"],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'current_page' => ['type' => 'integer'],
                                    'last_page' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => $this->generateOpenApiPaths($model),
        ];
    }

    /**
     * Generate OpenAPI paths for all endpoints.
     */
    protected function generateOpenApiPaths(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        $modelName = $model->name;
        
        return [
            "/data/{$tableName}" => [
                'get' => [
                    'summary' => "List all {$model->display_name} records",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Page number'],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Items per page'],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Sort field'],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Search term'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PaginatedResponse'],
                                ],
                            ],
                        ],
                        '403' => ['description' => 'Access denied', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '404' => ['description' => 'Model not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'post' => [
                    'summary' => "Create a new {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$modelName}Input"],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Record created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validation failed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '403' => ['description' => 'Access denied', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            "/data/{$tableName}/{id}" => [
                'get' => [
                    'summary' => "Get a single {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'put' => [
                    'summary' => "Update a {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$modelName}Input"],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Record updated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validation failed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'delete' => [
                    'summary' => "Delete a {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Record deleted', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]]]],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate OpenAPI schema for the model.
     */
    protected function generateOpenApiSchema(DynamicModel $model): array
    {
        $properties = [
            'id' => ['type' => 'integer', 'readOnly' => true],
        ];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
        }
        
        if ($model->has_timestamps) {
            $properties['created_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
            $properties['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Generate OpenAPI input schema (for POST/PUT).
     */
    protected function generateOpenApiInputSchema(DynamicModel $model): array
    {
        $properties = [];
        $required = [];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
            
            if ($field->is_required) {
                $required[] = $field->name;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Generate response examples for different status codes.
     */
    protected function generateResponseExamples(DynamicModel $model): array
    {
        $sampleData = $this->generateSampleData($model);
        
        return [
            'success' => [
                '200' => [
                    'description' => 'Successful GET request',
                    'example' => [
                        'data' => array_merge(['id' => 1], $sampleData, [
                            'created_at' => '2026-02-09T10:30:00Z',
                            'updated_at' => '2026-02-09T10:30:00Z',
                        ]),
                    ],
                ],
                '201' => [
                    'description' => 'Resource created',
                    'example' => [
                        'data' => array_merge(['id' => 1], $sampleData, [
                            'created_at' => '2026-02-09T10:30:00Z',
                            'updated_at' => '2026-02-09T10:30:00Z',
                        ]),
                    ],
                ],
            ],
            'error' => [
                '400' => [
                    'description' => 'Bad Request',
                    'example' => [
                        'message' => 'Invalid request format',
                    ],
                ],
                '401' => [
                    'description' => 'Unauthorized',
                    'example' => [
                        'message' => 'Invalid or missing API key',
                    ],
                ],
                '403' => [
                    'description' => 'Forbidden',
                    'example' => [
                        'message' => 'Access denied by security rules',
                    ],
                ],
                '404' => [
                    'description' => 'Not Found',
                    'example' => [
                        'message' => 'Record not found',
                    ],
                ],
                '422' => [
                    'description' => 'Validation Error',
                    'example' => [
                        'message' => 'Validation failed',
                        'errors' => [
                            'email' => ['The email field is required.'],
                            'name' => ['The name must be at least 3 characters.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate webhook documentation.
     */
    protected function generateWebhookDocs(DynamicModel $model): array
    {
        $sampleData = $this->generateSampleData($model);
        
        return [
            'description' => 'Webhooks allow you to receive real-time notifications when data changes in your tables.',
            'events' => [
                'created' => [
                    'description' => 'Triggered when a new record is created',
                    'payload' => [
                        'event' => 'created',
                        'table' => $model->table_name,
                        'data' => array_merge(['id' => 1], $sampleData),
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
                'updated' => [
                    'description' => 'Triggered when a record is updated',
                    'payload' => [
                        'event' => 'updated',
                        'table' => $model->table_name,
                        'data' => array_merge(['id' => 1], $sampleData),
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
                'deleted' => [
                    'description' => 'Triggered when a record is deleted',
                    'payload' => [
                        'event' => 'deleted',
                        'table' => $model->table_name,
                        'data' => ['id' => 1],
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Digibase-Webhook/1.0',
                'X-Webhook-Event' => 'created|updated|deleted',
                'X-Webhook-Signature' => 'sha256=<signature>',
            ],
            'signature_verification' => [
                'description' => 'Verify webhook authenticity using HMAC SHA256',
                'algorithm' => 'sha256',
                'example_code' => [
                    'php' => <<<'PHP'
$signature = hash_hmac('sha256', $payload, $webhookSecret);
$isValid = hash_equals($signature, $receivedSignature);
PHP,
                    'javascript' => <<<'JS'
const crypto = require('crypto');
const signature = crypto
  .createHmac('sha256', webhookSecret)
  .update(payload)
  .digest('hex');
const isValid = signature === receivedSignature;
JS,
                    'python' => <<<'PYTHON'
import hmac
import hashlib
signature = hmac.new(
    webhook_secret.encode(),
    payload.encode(),
    hashlib.sha256
).hexdigest()
is_valid = signature == received_signature
PYTHON,
                ],
            ],
        ];
    }

    /**
     * Generate endpoint documentation.
     */
    protected function generateEndpoints(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        
        return [
            [
                'method' => 'GET',
                'path' => "/api/data/{$tableName}",
                'description' => "List all {$model->display_name} records",
                'parameters' => [
                    ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Page number for pagination'],
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Items per page (default: 15)'],
                    ['name' => 'sort', 'type' => 'string', 'required' => false, 'description' => 'Sort field (prefix with - for descending)'],
                    ['name' => 'filter', 'type' => 'object', 'required' => false, 'description' => 'Filter conditions'],
                ],
            ],
            [
                'method' => 'GET',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Get a single {$model->display_name} record by ID",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
            ],
            [
                'method' => 'POST',
                'path' => "/api/data/{$tableName}",
                'description' => "Create a new {$model->display_name} record",
                'parameters' => [],
                'body' => $this->generateRequestBody($model),
            ],
            [
                'method' => 'PUT',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Update an existing {$model->display_name} record",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
                'body' => $this->generateRequestBody($model, false),
            ],
            [
                'method' => 'DELETE',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Delete a {$model->display_name} record",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
            ],
        ];
    }

    /**
     * Generate request body schema.
     */
    protected function generateRequestBody(DynamicModel $model, bool $allRequired = true): array
    {
        $body = [];
        
        foreach ($model->fields as $field) {
            $body[] = [
                'name' => $field->name,
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'required' => $allRequired ? $field->is_required : false,
                'description' => $field->description ?? $field->display_name,
                'validation' => $this->getFieldValidation($field),
            ];
        }
        
        return $body;
    }

    /**
     * Generate authentication documentation.
     */
    protected function generateAuthenticationDocs(): array
    {
        return [
            'type' => 'API Key',
            'header' => 'x-api-key',
            'description' => 'All API requests require an API key in the x-api-key header',
            'key_types' => [
                'pk_' => 'Public key - Read-only access',
                'sk_' => 'Secret key - Full access (read, write, delete)',
            ],
            'example' => 'x-api-key: sk_your_secret_key_here',
        ];
    }

    /**
     * Generate code examples.
     */
    protected function generateExamples(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        $sampleData = $this->generateSampleData($model);
        
        return [
            'curl' => $this->generateCurlExamples($tableName, $sampleData),
            'javascript' => $this->generateJavaScriptExamples($tableName, $sampleData),
            'python' => $this->generatePythonExamples($tableName, $sampleData),
        ];
    }

    /**
     * Generate sample data for examples.
     */
    protected function generateSampleData(DynamicModel $model): array
    {
        $data = [];
        
        foreach ($model->fields as $field) {
            $data[$field->name] = $this->generateSampleValue($field);
        }
        
        return $data;
    }

    /**
     * Generate sample value based on field type.
     */
    protected function generateSampleValue(DynamicField $field): mixed
    {
        return match ($field->type) {
            'string', 'email', 'url', 'phone', 'slug' => "sample_{$field->name}",
            'text', 'richtext', 'markdown' => "This is sample {$field->name} content",
            'integer' => 42,
            'float', 'decimal' => 99.99,
            'boolean' => true,
            'date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'time' => date('H:i:s'),
            'json' => ['key' => 'value'],
            'enum', 'select' => $field->options[0] ?? 'option1',
            'uuid' => Str::uuid()->toString(),
            'password' => 'secure_password_123',
            'color' => '#3B82F6',
            default => "sample_value",
        };
    }

    /**
     * Generate cURL examples.
     */
    protected function generateCurlExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT);
        
        return [
            'list' => <<<CURL
curl -X GET "{$baseUrl}/api/data/{$tableName}" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
            'get' => <<<CURL
curl -X GET "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
            'create' => <<<CURL
curl -X POST "{$baseUrl}/api/data/{$tableName}" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{$jsonData}'
CURL,
            'update' => <<<CURL
curl -X PUT "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{$jsonData}'
CURL,
            'delete' => <<<CURL
curl -X DELETE "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
        ];
    }

    /**
     * Generate JavaScript examples.
     */
    protected function generateJavaScriptExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return [
            'list' => <<<JS
// Using digibase.js SDK
const digibase = new Digibase('{$baseUrl}', 'sk_your_secret_key_here');
const records = await digibase.from('{$tableName}').select();

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}', {
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
const data = await response.json();
JS,
            'get' => <<<JS
// Using digibase.js SDK
const record = await digibase.from('{$tableName}').select().eq('id', 1).single();

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
const data = await response.json();
JS,
            'create' => <<<JS
// Using digibase.js SDK
const newRecord = await digibase.from('{$tableName}').insert({$jsonData});

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}', {
  method: 'POST',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({$jsonData})
});
const data = await response.json();
JS,
            'update' => <<<JS
// Using digibase.js SDK
const updated = await digibase.from('{$tableName}').update({$jsonData}).eq('id', 1);

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  method: 'PUT',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({$jsonData})
});
const data = await response.json();
JS,
            'delete' => <<<JS
// Using digibase.js SDK
await digibase.from('{$tableName}').delete().eq('id', 1);

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  method: 'DELETE',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
JS,
        ];
    }

    /**
     * Generate Python examples.
     */
    protected function generatePythonExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return [
            'list' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.get('{$baseUrl}/api/data/{$tableName}', headers=headers)
data = response.json()
PYTHON,
            'get' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.get('{$baseUrl}/api/data/{$tableName}/1', headers=headers)
data = response.json()
PYTHON,
            'create' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
}

payload = {$jsonData}

response = requests.post('{$baseUrl}/api/data/{$tableName}', headers=headers, json=payload)
data = response.json()
PYTHON,
            'update' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
}

payload = {$jsonData}

response = requests.put('{$baseUrl}/api/data/{$tableName}/1', headers=headers, json=payload)
data = response.json()
PYTHON,
            'delete' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.delete('{$baseUrl}/api/data/{$tableName}/1', headers=headers)
PYTHON,
        ];
    }

    /**
     * Generate JSON schema for the model.
     */
    protected function generateSchema(DynamicModel $model): array
    {
        $properties = [];
        $required = [];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
            
            if ($field->is_required) {
                $required[] = $field->name;
            }
            
            // Add constraints
            if ($field->validation_rules) {
                $properties[$field->name]['constraints'] = $field->validation_rules;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Map field type to JSON schema type.
     */
    protected function mapFieldTypeToJsonType(string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'bigint' => 'integer',
            'float', 'decimal' => 'number',
            'boolean' => 'boolean',
            'json', 'array' => 'object',
            'date', 'datetime', 'time' => 'string',
            default => 'string',
        };
    }

    /**
     * Get field validation rules.
     */
    protected function getFieldValidation(DynamicField $field): array
    {
        $validation = [];
        
        if ($field->is_required) {
            $validation[] = 'required';
        }
        
        if ($field->is_unique) {
            $validation[] = 'unique';
        }
        
        if ($field->validation_rules) {
            $validation = array_merge($validation, $field->validation_rules);
        }
        
        return $validation;
    }
}
