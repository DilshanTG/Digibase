<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeGeneratorController extends Controller
{
    protected CodeGeneratorService $generator;

    public function __construct(CodeGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'model_id' => 'required|exists:dynamic_models,id',
            'framework' => 'required|string|in:react,vue,nextjs,nuxt',
            'operation' => 'required|string|in:all,list,create,hook',
            'style' => 'string|in:tailwind,bootstrap',
            'typescript' => 'boolean'
        ]);

        $files = $this->generator->generate(
            $request->model_id,
            $request->framework,
            $request->operation,
            $request->get('style', 'tailwind'),
            $request->boolean('typescript', true)
        );

        return response()->json([
            'files' => $files
        ]);
    }
}
