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
        // Simple placeholder for Vue
        return [
            [
                'name' => "{$model->name}Component.vue",
                'code' => "<template>\n  <div>Vue component for {$model->display_name} coming soon!</div>\n</template>",
                'description' => "Vue 3 Component template."
            ]
        ];
    }
}
