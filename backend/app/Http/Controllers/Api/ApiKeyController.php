<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /**
     * List all API keys (Personal Access Tokens) for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderBy('created_at', 'desc')->get();
        return response()->json($tokens);
    }

    /**
     * Generate a new API key.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $abilities = $request->input('abilities', ['*']);
        $expiresAt = $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null;

        $token = $request->user()->createToken(
            $request->name, 
            $abilities,
            $expiresAt
        );

        return response()->json([
            'message' => 'API Key generated successfully',
            'token' => $token->plainTextToken,
            'name' => $request->name
        ], 201);
    }

    /**
     * Revoke (Delete) an API key.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (!$token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'API Key revoked successfully']);
    }
}
