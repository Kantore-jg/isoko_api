<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bank::query()
            ->select(['id', 'code', 'name', 'account_name', 'account_number', 'branch', 'description', 'status', 'created_at', 'updated_at'])
            ->withCount('payments')
            ->orderBy('name');

        if ($status = trim((string) $request->string('status'))) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreBankRequest $request): JsonResponse
    {
        $data = $request->validated();

        $bank = Bank::query()->create($data);

        return response()->json(['message' => 'Banque créée.', 'data' => $bank], 201);
    }

    public function show(Bank $bank): JsonResponse
    {
        return response()->json(['data' => $bank->loadCount('payments')]);
    }

    public function update(UpdateBankRequest $request, Bank $bank): JsonResponse
    {
        $data = $request->validated();

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
