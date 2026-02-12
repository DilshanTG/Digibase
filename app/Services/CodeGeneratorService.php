<?php

namespace App\Services;

use App\Models\DynamicModel;
use Illuminate\Support\Str;

class CodeGeneratorService
{
    public function generate($modelId, $framework, $operation, $style = 'tailwind', $typescript = true)
    {
        $model = DynamicModel::with('fields')->findOrFail($modelId);
        
        switch ($framework) {
            case 'react':
                return $this->generateReact($model, $operation, $style, $typescript);
            case 'vue':
                return $this->generateVue($model, $operation, $style, $typescript);
            case 'nextjs':
                return $this->generateNextJs($model, $operation, $style, $typescript);
            case 'nuxt':
                return $this->generateNuxt($model, $operation, $style, $typescript);
            default:
                return [['name' => 'Error.txt', 'code' => 'Framework not supported yet.']];
        }
    }

    protected function generateReact(DynamicModel $model, $operation, $style, $typescript)
    {
        $files = [];
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $ext = $typescript ? 'tsx' : 'jsx';

        if ($operation === 'list' || $operation === 'all') {
            $files[] = [
                'name' => "{$modelName}List.{$ext}",
                'code' => $this->getReactListTemplate($model, $style, $typescript),
                'description' => "Table component to display {$displayName} records."
            ];
        }

        if ($operation === 'create' || $operation === 'all') {
            $files[] = [
                'name' => "{$modelName}Create.{$ext}",
                'code' => $this->getReactCreateTemplate($model, $style, $typescript),
                'description' => "Form component to create new {$displayName} records."
            ];
        }

        if ($operation === 'hook' || $operation === 'all') {
            $files[] = [
                'name' => "use{$modelName}.ts",
                'code' => $this->getReactHookTemplate($model),
                'description' => "Custom React hook for CRUD operations using Axios."
            ];
        }

        return $files;
    }

    protected function getReactListTemplate(DynamicModel $model, $style, $typescript)
    {
        $modelName = $model->name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $tableHeaders = "";
        $tableCells = "";
        foreach ($fields->take(5) as $field) {
            $tableHeaders .= "                <th className=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">{$field->display_name}</th>\n";
            $tableCells .= "                <td className=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\">{item.{$field->name}}</td>\n";
        }

        return <<<React
import React, { useState, useEffect } from 'react';
import axios from 'axios';

export const {$modelName}List = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await axios.get(`/api/data/{$tableName}`);
                setData(response.data.data || response.data);
            } catch (err) {
                console.error("Failed to fetch {$tableName}", err);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    if (loading) return <div className="p-8 text-center text-gray-500">Loading...</div>;

    return (
        <div className="overflow-x-auto bg-white rounded-lg shadow">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
{$tableHeaders}                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {data.map((item: any) => (
                        <tr key={item.id} className="hover:bg-gray-50 transition-colors">
{$tableCells}                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button className="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <button className="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
React;
    }

    protected function getReactCreateTemplate(DynamicModel $model, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $initialState = "";
        $formFields = "";
        foreach ($fields as $field) {
            $initialState .= "        {$field->name}: '',\n";
            $type = $field->type === 'email' ? 'email' : ($field->type === 'integer' ? 'number' : 'text');
            
            $formFields .= <<<HTML
            <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {$field->display_name}
                </label>
                <input
                    type="{$type}"
                    value={formData.{$field->name}}
                    onChange={(e) => setFormData({ ...formData, {$field->name}: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Enter {$field->display_name}"
                />
            </div>\n
HTML;
        }

        return <<<React
import React, { useState } from 'react';
import axios from 'axios';

export const {$modelName}Create = ({ onSuccess }) => {
    const [formData, setFormData] = useState({
{$initialState}    });
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            await axios.post(`/api/data/{$tableName}`, formData);
            if (onSuccess) onSuccess();
        } catch (err) {
            alert("Failed to create {$displayName}");
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="max-w-lg bg-white p-6 rounded-xl shadow-lg">
            <h2 className="text-xl font-bold mb-6 text-gray-800">Create {$displayName}</h2>
            
{$formFields}
            <button
                type="submit"
                disabled={submitting}
                className="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
                {submitting ? 'Creating...' : 'Create {$displayName}'}
            </button>
        </form>
    );
};
React;
    }

    protected function getReactHookTemplate(DynamicModel $model)
    {
        $modelName = $model->name;
        $tableName = $model->table_name;

        return <<<React
import { useState, useCallback } from 'react';
import axios from 'axios';

export const use{$modelName} = () => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchAll = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get(`/api/data/{$tableName}`);
            return res.data.data;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const create = async (data) => {
        setLoading(true);
        try {
            const res = await axios.post(`/api/data/{$tableName}`, data);
            return res.data;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const remove = async (id) => {
        setLoading(true);
        try {
            await axios.delete(`/api/data/{$tableName}/\${id}`);
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    return { fetchAll, create, remove, loading, error };
};
React;
    }

    protected function generateVue(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $formFields = "";
        foreach ($fields as $field) {
            $formFields .= "          <div class=\"mb-4\">\n";
            $formFields .= "            <label class=\"block text-sm font-medium mb-1\">{$field->display_name}</label>\n";
            $formFields .= "            <input v-model=\"formData.{$field->name}\" type=\"text\" class=\"w-full px-3 py-2 border rounded-md\" />\n";
            $formFields .= "          </div>\n";
        }

        $code = <<<VUE
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const data = ref([])
const formData = ref({})

onMounted(async () => {
  const res = await axios.get('/api/data/{$tableName}')
  data.value = res.data.data || res.data
})

const create = async () => {
  await axios.post('/api/data/{$tableName}', formData.value)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">{$displayName} Manager</h1>
    
    <form @submit.prevent="create" class="mb-8 p-4 border rounded-lg">
{$formFields}      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create</button>
    </form>
    
    <ul class="divide-y">
      <li v-for="item in data" :key="item.id" class="py-2">{{ JSON.stringify(item) }}</li>
    </ul>
  </div>
</template>
VUE;

        return [
            [
                'name' => "{$modelName}Manager.vue",
                'code' => $code,
                'description' => "Vue 3 Component with Composition API for {$displayName}."
            ]
        ];
    }

    protected function generateNextJs(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        
        $code = <<<NEXT
'use client'

import { useEffect, useState } from 'react'

export default function {$modelName}Page() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/api/data/{$tableName}')
      .then(res => res.json())
      .then(json => {
        setData(json.data || json)
        setLoading(false)
      })
  }, [])

  if (loading) return <div className="p-8">Loading...</div>

  return (
    <div className="p-8">
      <h1 className="text-3xl font-bold mb-6">{$displayName}</h1>
      <div className="grid gap-4">
        {data.map((item: any) => (
          <div key={item.id} className="p-4 border rounded-lg">
            {JSON.stringify(item)}
          </div>
        ))}
      </div>
    </div>
  )
}
NEXT;

        return [
            [
                'name' => "page.tsx",
                'code' => $code,
                'description' => "Next.js 14 App Router page component for {$displayName}."
            ]
        ];
    }

    protected function generateNuxt(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;

        $code = <<<NUXT
<script setup lang="ts">
const { data } = await useFetch('/api/data/{$tableName}')
</script>

<template>
  <div class="p-8">
    <h1 class="text-3xl font-bold mb-6">{$displayName}</h1>
    <div class="grid gap-4">
      <div v-for="item in data?.data || data" :key="item.id" class="p-4 border rounded-lg">
        {{ JSON.stringify(item) }}
      </div>
    </div>
  </div>
</template>
NUXT;

        return [
            [
                'name' => "{$modelName}.vue",
                'code' => $code,
                'description' => "Nuxt 3 page component with useFetch for {$displayName}."
            ]
        ];
    }

    /**
     * 🎨 VIBE CODING: Generate Next.js component using Digibase SDK
     *
     * Generates a production-ready Next.js component that uses the
     * official Digibase TypeScript SDK for type-safe data fetching.
     */
    public function generateNextJsComponent(DynamicModel $model): string
    {
        $className = Str::studly(Str::singular($model->table_name));
        $tableName = $model->table_name;
        $displayName = $model->display_name;

        // Generate TypeScript interface fields
        $interfaceFields = "    id: number;\n";
        foreach ($model->fields as $field) {
            $tsType = $this->mapTypeToTypeScript($field->type);
            $nullable = !$field->is_required ? '?' : '';
            $interfaceFields .= "    '{$field->name}'{$nullable}: {$tsType};\n";
        }
        $interfaceFields .= "    created_at: string;\n";
        $interfaceFields .= "    updated_at: string;";

        return <<<TSX
'use client';
import { useState, useEffect } from 'react';
import { digibase } from '@/lib/digibase/client';

// 🎯 Type Definition
interface {$className} {
{$interfaceFields}
}

export default function {$className}List() {
    const [data, setData] = useState<{$className}[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        // ✨ Vibe Coding: Auto-generated Digibase SDK Call
        digibase
            .from<{$className}>('{$tableName}')
            .select('*')
            .sort('created_at', 'desc')
            .limit(20)
            .get()
            .then(({ data: items, error: apiError }) => {
                if (apiError) {
                    setError(apiError);
                } else {
                    setData(items);
                }
            })
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p className="mt-4 text-gray-600">Loading vibe... ⏳</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-10 text-center">
                <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md mx-auto">
                    <h3 className="text-red-800 font-semibold mb-2">Error Loading Data</h3>
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <div className="p-10 text-center">
                <p className="text-gray-500">No {$displayName} found. Create your first one!</p>
            </div>
        );
    }

    return (
        <div className="container mx-auto p-6">
            <div className="mb-6">
                <h1 className="text-3xl font-bold text-gray-900">{$displayName}</h1>
                <p className="text-gray-600 mt-2">Powered by Digibase SDK</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {data.map((item) => (
                    <div
                        key={item.id}
                        className="p-6 bg-white border border-gray-200 rounded-lg shadow hover:shadow-lg transition-shadow duration-200"
                    >
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold text-lg text-gray-900">#{item.id}</h3>
                            <span className="text-xs text-gray-500">
                                {new Date(item.created_at).toLocaleDateString()}
                            </span>
                        </div>

                        <pre className="text-xs text-gray-600 bg-gray-50 p-3 rounded overflow-x-auto">
{JSON.stringify(item, null, 2)}
                        </pre>

                        <div className="mt-4 flex gap-2">
                            <button className="flex-1 px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                Edit
                            </button>
                            <button className="flex-1 px-3 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition">
                                Delete
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
TSX;
    }

    /**
     * Map Digibase field types to TypeScript types
     */
    protected function mapTypeToTypeScript(string $fieldType): string
    {
        return match($fieldType) {
            'string', 'text', 'email', 'url', 'date', 'datetime', 'file', 'image' => 'string',
            'integer', 'decimal', 'float', 'number' => 'number',
            'boolean' => 'boolean',
            'json' => 'any',
            default => 'string'
        };
    }
}
