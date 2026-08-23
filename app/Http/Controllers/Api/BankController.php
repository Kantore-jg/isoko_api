<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bank::query()->withCount('payments')->orderBy('name');

        if ($status = $request->string('status')->trim()) {
            $query->where('status', $status->toString());
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:banks,code'],
            'name' => ['required', 'string', 'max:150'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $bank = Bank::query()->create($data);

        return response()->json(['message' => 'Banque créée.', 'data' => $bank], 201);
    }

    public function show(Bank $bank): JsonResponse
    {
        return response()->json(['data' => $bank->loadCount('payments')]);
    }

    public function update(Request $request, Bank $bank): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:banks,code,' . $bank->id],
            'name' => ['sometimes', 'string', 'max:150'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $bank->update($data);

        return response()->json(['message' => 'Banque mise à jour.', 'data' => $bank->fresh()->loadCount('payments')]);
    }

    public function destroy(Bank $bank): JsonResponse
    {
        if ($bank->payments()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une banque déjà utilisée.',
            ], 422);
        }

        $bank->delete();

        return response()->json(['message' => 'Banque supprimée.']);
    }
}
